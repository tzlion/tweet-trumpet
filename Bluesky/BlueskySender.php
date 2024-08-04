<?php

namespace TzLion\TweetTrumpet\Bluesky;

use TzLion\TweetTrumpet\Bluesky\Object\StrongReference;
use TzLion\TweetTrumpet\Common\FileHelper;
use TzLion\TweetTrumpet\Common\Object\Attachment;

class BlueskySender extends BlueskyAuthenticated
{
    /**
     * @param string $message
     * @param Attachment[] $attachments
     * @param string $lang
     * @return object
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
        // todo return strong ref
        return $this->blueskyApi->request('POST', 'com.atproto.repo.createRecord', $args);
    }

    private function findRootOfPost(StrongReference $ref): StrongReference
    {
        // todo
    }
}
