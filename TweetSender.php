<?php
namespace TzLion\TweetTrumpet;

class TweetSender extends TwitterAuthenticated
{
    /**
     * @param string $message Text of the tweet to tweet
     * @param string|null $filename Filename of a media file to attach (optional)
     * @return array Response from Twitter API
     *
     * @throws \Exception
     */
    public function tweet($message, $filename = null)
    {
        if ($filename) {
            $fileResult = $this->uploadFile($filename);
            return $this->doTweet($message, $fileResult["media_id_string"]);
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
     * @param string|null $mediaId
     * @return array
     *
     * @throws \Exception
     */
    private function doTweet($message = null, $mediaId = null)
    {
        $url = self::TWITTER_API_BASE_URL_V2 . '/tweets';
        $post = [];
        if ($message) {
            $post["text"] = $message;
        }
        if ($mediaId) {
            $post["media"]["media_ids"] = [$mediaId];
        }
        return $this->post( $url, $post, true );
    }
}
