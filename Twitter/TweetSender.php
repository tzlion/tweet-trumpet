<?php
namespace TzLion\TweetTrumpet\Twitter;

class TweetSender extends TwitterAuthenticated
{
    /**
     * @param string $message Text of the tweet to tweet
     * @param array $filenames Filenames of media files to attach
     * @return array Response from Twitter API
     *
     * @throws \Exception
     */
    public function tweet($message, array $filenames = [])
    {
        if ($filenames) {
            $mediaids = [];
            foreach ($filenames as $filename) {
                $fileResult = $this->uploadFile($filename);
                $mediaids[] = $fileResult['media_id_string'];
            }
            return $this->doTweet($message, $mediaids);
        } else {
            return $this->doTweet($message);
        }
    }

    /**
     * @param string $filename
     * @return array
     *
     * @throws \Exception
     */
    private function uploadFile($filename)
    {
        $file = base64_encode(file_get_contents($filename));
        $url = self::TWITTER_UPLOAD_BASE_URL . "/media/upload.json";
        $post["media_data"] = $file;
        return $this->post($url, $post);
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
