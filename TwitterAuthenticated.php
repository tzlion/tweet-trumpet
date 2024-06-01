<?php
namespace TzLion\TweetTrumpet;

abstract class TwitterAuthenticated
{
    /** @var \TwitterApiExchange */
    protected $tw;

    private $settings;

    const TWITTER_API_BASE_URL = "https://api.twitter.com/1.1";
    const TWITTER_API_BASE_URL_V2 = "https://api.twitter.com/2";
    const TWITTER_UPLOAD_BASE_URL = "https://upload.twitter.com/1.1";

    private static $defaultConfig;

    /**
     * Set authentication settings to be used in any instance of TwitterAuthenticated
     * if they are not passed into the constructor
     *
     * @param array $config Array of authentication settings to pass to TwitterAPIExchange.
     *                      Should contain: oauth_access_token, oauth_access_token_secret, consumer_key, consumer_secret
     */
    public static function setGlobalOauthConfig($config)
    {
        self::$defaultConfig = $config;
    }

    /**
     * TwitterAuthenticated constructor.
     *
     * @param array $config Array of authentication settings to pass to TwitterAPIExchange.
     *                      Should contain: oauth_access_token, oauth_access_token_secret, consumer_key, consumer_secret
     *
     * @throws \Exception
     */
    public function __construct($config = null)
    {
        if ($config) $this->settings = $config;
        else $this->settings = self::$defaultConfig;
        if (!$this->settings)
            throw new \Exception("Missing oauth settings! Must call TwitterAuthenticated::setGlobalOauthConfig or pass in settings in the constructor");
        $this->tw = new \TwitterAPIExchange($this->settings);
    }

    /**
     * @param string $url
     * @param array $queryData
     * @return array
     *
     * @throws \Exception
     */
    protected function get($url, $queryData)
    {
        $get = http_build_query($queryData);
        $result = $this->tw
            ->setGetfield("?$get")
            ->buildOauth($url, "GET")
            ->performRequest();
        return json_decode($result, true);
    }

    /**
     * @param string $url
     * @param array $postData
     * @return array
     *
     * @throws \Exception
     */
    protected function post($url, $postData, $v2 = false)
    {
        $result = $this->tw->buildOauth($url, "POST", $v2)
            ->setPostfields($postData, $v2)
            ->performRequest(true, [], $v2);
        return json_decode($result, true);
    }
}
