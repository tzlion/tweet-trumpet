<?php
namespace TzLion\TweetTrumpet;

class TweetSender extends TwitterAuthenticated
{
    public function tweet( $message, $filename = null )
    {
        if ( $filename ) {
            $fileResult = $this->uploadFile( $filename );
            return $this->doTweet( $message, $fileResult[ "media_id_string" ] );
        } else {
            return $this->doTweet( $message );
        }
    }

    private function uploadFile( $filename )
    {
        $file = base64_encode(file_get_contents($filename));
        $url = self::TWITTER_UPLOAD_BASE_URL . "/media/upload.json";
        $post["media_data"] = $file;
        return $this->post( $url, $post );
    }

    private function doTweet( $message = null, $mediaId = null )
    {
        $url = self::TWITTER_API_BASE_URL . '/statuses/update.json';
        $post = [];
        if ( $message ) {
            $post["status"] = $message;
        }
        if ( $mediaId ) {
            $post["media_ids"] = $mediaId;
        }
        return $this->post( $url, $post );
    }

}
