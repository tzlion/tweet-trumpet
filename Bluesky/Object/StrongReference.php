<?php

namespace TzLion\TweetTrumpet\Bluesky\Object;

class StrongReference
{
    private string $uri;
    private string $cid;

    public function __construct(string $uri, string $cid)
    {
        $this->uri = $uri;
        $this->cid = $cid;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getCid(): string
    {
        return $this->cid;
    }
}
