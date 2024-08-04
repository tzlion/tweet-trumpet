<?php

namespace TzLion\TweetTrumpet\Mastodon;

use Exception;
use TzLion\TweetTrumpet\Common\FileHelper;
use TzLion\TweetTrumpet\Common\Object\Attachment;

class MastodonSender extends MastodonAuthenticated
{
    /**
     * @param string $message
     * @param Attachment[] $filenames
     * @param bool $sensitive
     * @return object
     */
    public function post(string $message, array $attachments = [], bool $sensitive = false): object
    {
        $status = ['status' => $message, 'sensitive' => $sensitive];

        if ($attachments) {
            $status['media_ids'] = [];
            foreach ($attachments as $attachment) {
                $status['media_ids'][] = $this->uploadFile($attachment);
            }
        }

        return $this->mastodonApi->request('POST', 'v1/statuses', $status);
    }

    private function uploadFile(Attachment $attachment): string
    {
        // todo: alt text
        $mimetype = FileHelper::determineMimeType($attachment->getFilename());
        $ext = array_reverse(explode(".", $attachment->getFilename()))[0];
        $curl_file = curl_file_create($attachment->getFilename(), $mimetype, "image.$ext");
        $uploadRes = $this->mastodonApi->request('POST', 'v1/media', [
            'file' => $curl_file
        ], null, true);
        if (!$uploadRes || !($uploadRes->id ?? null)) {
            throw new Exception('Error uploading file: ' . $uploadRes->error ?? "Unknown");
        }
        if ($uploadRes->type !== "image") {
            throw new Exception('File not recognised as image');
        }
        return $uploadRes->id;
    }
}
