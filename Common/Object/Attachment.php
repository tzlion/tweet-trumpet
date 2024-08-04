<?php

namespace TzLion\TweetTrumpet\Common\Object;

class Attachment
{
    private string $filename;
    private ?string $altText;

    public function __construct(string $filename, ?string $altText = null)
    {
        $this->filename = $filename;
        $this->altText = $altText;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }
}
