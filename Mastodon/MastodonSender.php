<?php

namespace TzLion\TweetTrumpet\Mastodon;

class MastodonSender extends MastodonAuthenticated
{
    public function post(string $message, ?string $filename = null, bool $sensitive = false)
    {
        $status = ['status' => $message, 'sensitive' => $sensitive];

        if ($filename) {
            if (preg_match("/\\.png$/", $filename)) {
                $mimetype = "image/png";
                $ext = "png";
            } else if (preg_match("/\\.jpe?g$/", $filename)) {
                $mimetype = "image/jpeg";
                $ext = "jpg";
            } else if (preg_match("/\\.gif$/", $filename)) {
                $mimetype = "image/gif";
                $ext = "gif";
            } else {
                throw new \Exception("Couldn't detect image type");
            }

            $curl_file = curl_file_create($filename, $mimetype, "image.$ext");
            $uploadRes = $this->mastodonApi->request('POST', 'v1/media', [
                'file' => $curl_file
            ], null, true);
            if (!$uploadRes || !($uploadRes->id ?? null)) {
                throw new \Exception('Error: ' . $uploadRes->error ?? "Unknown");
            }
            $status['media_ids'] = [$uploadRes->id];
        }

        return $this->mastodonApi->request('POST', 'v1/statuses', $status);
    }
}
