<?php
namespace TzLion\TweetTrumpet\Twitter;

use TzLion\TweetTrumpet\Common\Object\Attachment;

class TweetSender extends TwitterAuthenticated
{
    /**
     * @param string $message Text of the tweet to tweet
     * @param Attachment[] $attachments Media files to attach
     * @return array Response from Twitter API
     *
     * @throws \Exception
     */
    public function tweet($message, array $attachments = [])
    {
        if ($attachments) {
            $mediaids = [];
            foreach ($attachments as $attachment) {
                $fileResult = $this->uploadFile($attachment);
                $mediaids[] = $fileResult['media_id_string'];
            }
            return $this->doTweet($message, $mediaids);
        } else {
            return $this->doTweet($message);
        }
    }

    /**
     * @param Attachment $attachment
     * @return array
     *
     * @throws \Exception
     */
    private function uploadFile(Attachment $attachment)
    {
        $file = base64_encode(file_get_contents($attachment->getFilename()));
        $url = self::TWITTER_UPLOAD_BASE_URL . "/media/upload.json";
        $post["media_data"] = $file;
        $res = $this->post($url, $post);
        if ($attachment->getAltText()) {
            $this->post(
                self::TWITTER_UPLOAD_BASE_URL . "/media/metadata/create.json",
                [
                    'media_id' => $res['media_id_string'],
                    'alt_text' => [
                        'text' => $attachment->getAltText(),
                    ]
                ]
            );
        }
        return $res;
    }

    /**
     * @param string|null $message
     * @param $mediaIds $mediaId
     * @return array
     *
     * @throws \Exception
     */
    private function doTweet($message = null, array $mediaIds = [])
    {
        $url = self::TWITTER_API_BASE_URL_V2 . '/tweets';
        $post = [];
        if ($message) {
            $post["text"] = $message;
        }
        if ($mediaIds) {
            $post["media"]["media_ids"] = $mediaIds;
        }
        return $this->post( $url, $post, true );
    }
}
