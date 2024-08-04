<?php

namespace TzLion\TweetTrumpet\Bluesky;

use cjrasmussen\BlueskyApi\BlueskyApi;
use Exception;

abstract class BlueskyAuthenticated
{
    private static ?string $defaultHandle = null;
    private static ?string $defaultAppPassword = null;

    protected BlueskyApi $blueskyApi;

    public function __construct(?string $handle = null, ?string $appPassword = null)
    {
        $handle = $handle ?? self::$defaultHandle;
        $appPassword = $appPassword ?? self::$defaultAppPassword;

        if (!$handle || !$appPassword) {
            throw new Exception("Missing auth settings! Must call BlueskyAuthenticated::setGlobalOauthConfig or pass in settings in the constructor");
        }

        $this->blueskyApi = new BlueskyApi();
        $this->blueskyApi->auth($handle, $appPassword);
    }

    public static function setGlobalAuthConfig(string $handle, string $appPassword): void
    {
        self::$defaultHandle = $handle;
        self::$defaultAppPassword = $appPassword;
    }
}
