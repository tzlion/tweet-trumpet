<?php

namespace TzLion\TweetTrumpet\Mastodon;

use cjrasmussen\MastodonApi\MastodonApi;
use Exception;

class MastodonAuthenticated
{
    private static ?string $defaultInstance = null;
    private static ?string $defaultAccessToken = null;

    protected MastodonApi $mastodonApi;

    public function __construct(?string $instance = null, ?string $accessToken = null)
    {
        $instance = $instance ?? self::$defaultInstance;
        $accessToken = $accessToken ?? self::$defaultAccessToken;

        if (!$instance || !$accessToken) {
            throw new Exception("Missing auth settings! Must call MastodonAuthenticated::setGlobalOauthConfig or pass in settings in the constructor");
        }

        $this->mastodonApi = new MastodonApi($instance);
        $this->mastodonApi->setBearerToken($accessToken);
    }

    public static function setGlobalAuthConfig(string $instance, string $accessToken): void
    {
        self::$defaultInstance = $instance;
        self::$defaultAccessToken = $accessToken;
    }
}
