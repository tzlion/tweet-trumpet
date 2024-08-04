<?php

namespace TzLion\TweetTrumpet\Bluesky;

use TzLion\TweetTrumpet\Common\FileHelper;

class BlueskySender extends BlueskyAuthenticated
{
    public function post(string $message, array $filenames = [], string $lang = 'en'): object
    {
        $record = [
            'text' => $message,
            'langs' => [$lang],
            'createdAt' => date('c'),
            '$type' => 'app.bsky.feed.post',
        ];

        if ($filenames) {
            $record['embed'] = [
                '$type' => 'app.bsky.embed.images',
                'images' => [],
            ];
            foreach ($filenames as $filename) {
                $mimetype = FileHelper::determineMimeType($filename);
                $body = file_get_contents($filename);
                $response = $this->blueskyApi->request('POST', 'com.atproto.repo.uploadBlob', [], $body, $mimetype);
                $image = $response->blob;
                $record['embed']['images'][] =
                    [
                        'alt' => '',
                        'image' => $image,
                    ];
            }
        }

        $args = [
            'collection' => 'app.bsky.feed.post',
            'repo' => $this->blueskyApi->getAccountDid(),
            'record' => $record,
        ];
        return $this->blueskyApi->request('POST', 'com.atproto.repo.createRecord', $args);
    }
}
