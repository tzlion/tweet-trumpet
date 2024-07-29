<?php

namespace TzLion\TweetTrumpet\Bluesky;

class BlueskySender extends BlueskyAuthenticated
{
    public function post(string $message, ?string $filename = null)
    {
        $record = [
            'text' => $message,
            'langs' => ['en'],
            'createdAt' => date('c'),
            '$type' => 'app.bsky.feed.post',
        ];

        if ($filename) {
            if (preg_match("/\\.png$/", $filename)) {
                $mimetype = "image/png";
            } else if (preg_match("/\\.jpe?g$/", $filename)) {
                $mimetype = "image/jpeg";
            } else if (preg_match("/\\.gif$/", $filename)) {
                $mimetype = "image/gif";
            } else {
                throw new \Exception("Couldn't detect image type");
            }
            $body = file_get_contents($filename);
            $response = $this->blueskyApi->request('POST', 'com.atproto.repo.uploadBlob', [], $body, $mimetype);
            $image = $response->blob;
            $record['embed'] = [
                '$type' => 'app.bsky.embed.images',
                'images' => [
                    [
                        'alt' => '',
                        'image' => $image,
                    ],
                ],
            ];
        }

        $args = [
            'collection' => 'app.bsky.feed.post',
            'repo' => $this->blueskyApi->getAccountDid(),
            'record' => $record,
        ];
        return $this->blueskyApi->request('POST', 'com.atproto.repo.createRecord', $args);
    }
}
