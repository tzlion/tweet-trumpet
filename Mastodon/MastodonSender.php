<?php

namespace TzLion\TweetTrumpet\Mastodon;

use Exception;
use TzLion\TweetTrumpet\Common\FileHelper;

class MastodonSender extends MastodonAuthenticated
{
    public function post(string $message, ?string $filename = null, bool $sensitive = false): object
    {
        $status = ['status' => $message, 'sensitive' => $sensitive];

        if ($filename) {
            $mimetype = FileHelper::determineMimeType($filename);
            $ext = array_reverse(explode(".", $filename))[0];
            $curl_file = curl_file_create($filename, $mimetype, "image.$ext");
            $uploadRes = $this->mastodonApi->request('POST', 'v1/media', [
                'file' => $curl_file
            ], null, true);
            if (!$uploadRes || !($uploadRes->id ?? null)) {
                throw new Exception('Error uploading file: ' . $uploadRes->error ?? "Unknown");
            }
            $status['media_ids'] = [$uploadRes->id];
        }

        return $this->mastodonApi->request('POST', 'v1/statuses', $status);
    }
}
