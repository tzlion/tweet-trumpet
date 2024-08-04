<?php

namespace TzLion\TweetTrumpet\Common;

class FileHelper
{
    public static function determineMimeType(string $filename): string
    {
        if (preg_match("/\\.png$/i", $filename)) {
            return "image/png";
        } else if (preg_match("/\\.jpe?g$/i", $filename)) {
            return "image/jpeg";
        } else if (preg_match("/\\.gif$/i", $filename)) {
            return "image/gif";
        } else {
            throw new \Exception("Couldn't detect image type");
        }
    }
}
