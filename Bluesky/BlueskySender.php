<?php

namespace TzLion\TweetTrumpet\Bluesky;

use Exception;
use TzLion\TweetTrumpet\Bluesky\Object\StrongReference;
use TzLion\TweetTrumpet\Common\FileHelper;
use TzLion\TweetTrumpet\Common\Object\Attachment;

class BlueskySender extends BlueskyAuthenticated
{
    /**
     * @param string $message
     * @param Attachment[] $attachments
     * @param string $lang
     * @return object As returned from API. Contains properties uri and cid which make up a strong reference
     */
    public function post(string $message, array $attachments = [], string $lang = 'en', ?StrongReference $inReplyTo = null): object
    {
        $record = [
            'text' => $message,
            'langs' => [$lang],
            'createdAt' => date('c'),
            '$type' => 'app.bsky.feed.post',
        ];

        $facets = $this->determineFacets($message);
        if ($facets) {
            $record['facets'] = $facets;
        }
        $embed = $this->tryCardEmbed($facets);
        if ($embed) {
            $record['embed'] = $embed;
        }

        if ($attachments) {
            $record['embed'] = [
                '$type' => 'app.bsky.embed.images',
                'images' => [],
            ];
            foreach ($attachments as $attachment) {
                $mimetype = FileHelper::determineMimeType($attachment->getFilename());
                $body = file_get_contents($attachment->getFilename());
                $response = $this->blueskyApi->request('POST', 'com.atproto.repo.uploadBlob', [], $body, $mimetype);
                $image = $response->blob;
                $record['embed']['images'][] =
                    [
                        'alt' => $attachment->getAltText() ?? '',
                        'image' => $image,
                    ];
            }
        }

        if ($inReplyTo) {
            $root = $this->findRootOfPost($inReplyTo);
            $record['reply'] = [
                'root' => [
                    'uri' => $root->getUri(),
                    'cid' => $root->getCid()
                ],
                'parent' => [
                    'uri' => $inReplyTo->getUri(),
                    'cid' => $inReplyTo->getCid()
                ]
            ];
        }

        $args = [
            'collection' => 'app.bsky.feed.post',
            'repo' => $this->blueskyApi->getAccountDid(),
            'record' => $record,
        ];
        return $this->blueskyApi->request('POST', 'com.atproto.repo.createRecord', $args);
    }

    public function tryCardEmbed(array $facets): ?array
    {
        $foundLink = null;
        foreach ($facets as $facet) {
            if ($facet['features'][0]['$type'] === 'app.bsky.richtext.facet#link') {
                $foundLink = $facet['features'][0]['uri'];
                break;
            }
        }
        if (!$foundLink) {
            return null;
        }
        // yes if we want a card embed we need to do all the legwork ourselves because it doesn't do dick all on the server
        $ch = curl_init($foundLink);
        if (preg_match("~(^|\.)youtube\.com$~", parse_url($foundLink,PHP_URL_HOST))) {
            // apparently for youtube to send the opengraph tags consistently we need to pretend to be facebook
            curl_setopt($ch, CURLOPT_USERAGENT, "facebookexternalhit/1.1");
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        if (!$content) {
            return null;
        }
        $embed = [
            '$type' => 'app.bsky.embed.external',
            'external' => [
                'uri' => $foundLink,
                'title' => '',
                'description' => ''
            ]
        ];
        preg_match_all("/<meta .+?>/i", $content, $metas);
        foreach ($metas[0] as $meta) {
            preg_match("/property\s*=\s*(['\"])(.+?)\\1/i", $meta, $propmatches);
            preg_match("/content\s*=\s*(['\"])(.+?)\\1/i", $meta, $contmatches);
            if (empty($propmatches[2]) || empty($contmatches[2])) {
                continue;
            }
            switch ($propmatches[2]) {
                case 'og:title':
                    $embed['external']['title'] = html_entity_decode($contmatches[2], ENT_QUOTES | ENT_SUBSTITUTE);
                    break;
                case 'og:description':
                    $embed['external']['description'] = html_entity_decode($contmatches[2], ENT_QUOTES | ENT_SUBSTITUTE);
                case 'og:image':
                    $blobresponse = $this->transferRemoteImageToBlob(html_entity_decode($contmatches[2], ENT_QUOTES | ENT_SUBSTITUTE));
                    if ($blobresponse) {
                        $embed['external']['thumb'] = $blobresponse;
                    }
            }
        }
        if (!$embed['external']['title']) {
            return null;
        }
        return $embed;
    }

    public function transferRemoteImageToBlob(string $url): ?object
    {
        // not fannying around with relative urls or anything for now
        if (!preg_match("~^https?://~i", $url)) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $imgContents = curl_exec($ch);
        if (!$imgContents) {
            return null;
        }
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (!$contentType) {
            return null;
        }
        $mimeType = explode(";", $contentType)[0];
        if (!preg_match("~^image/.+$~i", $mimeType)) {
            return null;
        }
        $response = $this->blueskyApi->request('POST', 'com.atproto.repo.uploadBlob', [], $imgContents, $mimeType);
        return $response && !empty($response->blob) ? $response->blob : null;
    }

    // fucking hell
    // todo: mentions????
    private function determineFacets(string $message): array
    {
        $facets = [];

        $linkPositions = [];
        $hashtagPositions = [];
        $currentLinkStart = null;
        $currentHashtagStart = null;
        for ($char = 0; $char < strlen($message); $char++) {
            $thisChar = substr($message, $char, 1);
            if ($currentLinkStart === null && $currentHashtagStart === null &&
                (substr($message, $char, 7) === "http://" || substr($message, $char, 8) === "https://")) {
                $currentLinkStart = $char;
            }
            if ($currentLinkStart !== null) {
                if (preg_match("/\s/", $thisChar)) {
                    $linkPositions[] = [$currentLinkStart, $char];
                    $currentLinkStart = null;
                }
            }
            if ($currentLinkStart === null && $currentHashtagStart === null
                && $thisChar === "#" && ($char === 0 || preg_match("/\s/", substr($message, $char-1, 1)))) {
                $currentHashtagStart = $char;
            }
            if ($currentHashtagStart !== null) {
                if (preg_match("/\s/", $thisChar)) {
                    $hashtagPositions[] = [$currentHashtagStart, $char];
                    $currentHashtagStart = null;
                }
            }
        }
        if ($currentLinkStart !== null) {
            $linkPositions[] = [$currentLinkStart, $char];
        }
        if ($currentHashtagStart !== null) {
            $hashtagPositions[] = [$currentHashtagStart, $char];
        }
        foreach ($linkPositions as $linkPosition) {
            $facets[] = [
                'index' => ['byteStart' => $linkPosition[0], 'byteEnd' => $linkPosition[1]],
                'features' => [
                    ['$type' => 'app.bsky.richtext.facet#link', 'uri' => substr($message, $linkPosition[0], $linkPosition[1] - $linkPosition[0])]
                ]
            ];
        }
        foreach ($hashtagPositions as $hashtagPosition) {
            $tag = substr($message, $hashtagPosition[0] + 1, $hashtagPosition[1] - ($hashtagPosition[0] + 1));
            if (!$tag) {
                continue;
            }
            $facets[] = [
                'index' => ['byteStart' => $hashtagPosition[0], 'byteEnd' => $hashtagPosition[1]],
                'features' => [
                    ['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tag]
                ]
            ];
        }

        return $facets;
    }

    private function findRootOfPost(StrongReference $ref): StrongReference
    {
        $refPost = $this->getPost($ref);
        $rpValue = $refPost->value ?? null;
        if (!$rpValue) {
            throw new Exception("referenced post doesn't seem to exist");
        }
        $rpReply = $rpValue->reply ?? null;
        if (!$rpReply || !$rpReply->root ?? null) {
            return $ref;
        }
        return new StrongReference(
            $rpReply->root->uri,
            $rpReply->root->cid
        );
    }

    private function getPost(StrongReference $ref): object
    {
        [$prot, , $repo, $collection, $key] = explode("/", $ref->getUri());
        if ($prot !== "at:" || !$repo || !$collection || !$key) {
            throw new Exception("couldn't parse URI");
        }
        $args = [
            'repo' => $repo,
            'collection' => $collection,
            'rkey' => $key,
            'cid' => $ref->getCid()
        ];
        return $this->blueskyApi->request('GET', 'com.atproto.repo.getRecord', $args);
    }
}
