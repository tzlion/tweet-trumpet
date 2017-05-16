<?php
namespace TzLion\TweetTrumpet;

abstract class TwitterAuthenticated
{
    /** @var \TwitterApiExchange */
    protected $tw;

    private $settings;

    const TWITTER_API_BASE_URL = "https://api.twitter.com/1.1";
    const TWITTER_UPLOAD_BASE_URL = "https://upload.twitter.com/1.1";

    private static $defaultConfig;

    public static function setGlobalOauthConfig($config)
    {
        self::$defaultConfig = $config;
    }

    public function __construct( $config = null )
    {
        if ( $config ) $this->settings = $config;
        else $this->settings = self::$defaultConfig;
        if ( !$this->settings )
            throw new \Exception( "Missing oauth settings! Must call TwitterAuthenticated::setGlobalOauthConfig or pass in settings in the constructor");
        $this->tw = new \TwitterAPIExchange( $this->settings );
    }

    protected function get( $url, $queryData )
    {
        $get = http_build_query( $queryData );
        $result = $this->tw
            ->setGetfield( "?$get" )
            ->buildOauth($url,"GET")
            ->performRequest();
        return json_decode( $result, true );
    }

    protected function post( $url, $postData )
    {
        $result = $this->tw->buildOauth($url, "POST")
            ->setPostfields($postData)
            ->performRequest();
        return json_decode( $result, true );
    }


} 