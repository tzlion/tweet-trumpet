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
