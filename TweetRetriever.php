<?php
namespace TzLion\TweetTrumpet;

class TweetRetriever extends TwitterAuthenticated
{
    private $lastIds = [];

    // todo: make these configurable
    const FILTER_TWEETS_WITH_URLS = true;
    const FILTER_TWEETS_WITH_MENTIONS = true;

    /**
     * Get a user's tweets
     *
     * @param string $username
     * @param bool $continueFromLast
     * @param bool $incRts
     * @param bool $incReplies
     * @return array Tweets retrieved from Twitter API
     *
     * @throws \Exception
     */
    public function getTweets($username, $continueFromLast = false, $incRts = false, $incReplies = false)
    {
        $queryIdsKey = "u_$username";
        $maxId = $this->getMaxId( $queryIdsKey, $continueFromLast );

        $params = [
            "include_rts" => $incRts,
            "screen_name" => $username,
            "exclude_replies" => !$incReplies,
            "count" => 200
        ];
        if ($maxId) $params["max_id"] = $maxId;
        $tweets = $this->get(self::TWITTER_API_BASE_URL . "/statuses/user_timeline.json", $params);

        $this->updateLastIds($tweets, $queryIdsKey);

        return $tweets;
    }

    /**
     * Get tweets matching a search term
     *
     * @param $query
     * @param string $lang
     * @param bool $continueFromLast
     * @return array Tweets retrieved from Twitter API
     *
     * @throws \Exception
     */
    public function searchTweets($query, $lang = "en", $continueFromLast = false)
    {
        $queryIdsKey = "s_$query";
        $maxId = $this->getMaxId($queryIdsKey, $continueFromLast);

        $params = [
            "q" => $query,
            "result_type" => "recent",
            "lang" => $lang,
            "count" => 100
        ];
        if ( $maxId ) $params["max_id"] = $maxId;
        $tweets = $this->get(self::TWITTER_API_BASE_URL . "/search/tweets.json", $params)['statuses'] ?: [];

        $this->updateLastIds($tweets, $queryIdsKey);

        return $tweets;
    }

    /**
     * Get tweets mentioning the authenticated account
     *
     * @param int $count
     * @param string|null $fromId
     * @return array Tweets retrieved from Twitter API
     *
     * @throws \Exception
     */
    public function getMentions($count = 10, $fromId = null)
    {
        $params = [
            "count" => $count,
        ];
        if ( $fromId ) {
            $params['since_id'] = $fromId;
        }
        $tweets = $this->get(self::TWITTER_API_BASE_URL . "/statuses/mentions_timeline.json", $params);
        return $tweets;
    }

    /**
     * Get a user's tweets, filtering out tweets with URLs, mentions, and (optionally) media
     *
     * @param $username
     * @param bool $continueFromLast
     * @param bool $incMediaTweets
     * @return array Filtered tweets
     *
     * @throws \Exception
     */
    public function getFilteredTweets($username, $continueFromLast = false, $incMediaTweets = false)
    {
        $tweets = $this->getTweets($username, $continueFromLast);
        return $this->filterTweets($tweets, $incMediaTweets);
    }

    /**
     * Get tweets matching a search term, filtering out tweets with URLs, mentions, and (optionally) media
     *
     * @param $query
     * @param string $lang
     * @param bool $continueFromLast
     * @param bool $incMediaTweets
     * @return array Filtered tweets
     *
     * @throws \Exception
     */
    public function searchFilteredTweets($query, $lang = "en", $continueFromLast = false, $incMediaTweets = false)
    {
        $tweets = $this->searchTweets($query, $lang, $continueFromLast);
        return $this->filterTweets($tweets, $incMediaTweets);
    }

    /**
     * Get a user's tweets, filtered, and making multiple passes to try to retrieve more tweets
     *
     * @param $username
     * @param int $passes
     * @param bool $incMediaTweets
     * @return array Filtered tweets
     *
     * @throws \Exception
     */
    public function getConsecutiveFilteredTweets($username, $passes = 1, $incMediaTweets = false)
    {
        $tweets = [];
        for($x=0;$x<$passes;$x++)
            $tweets = array_merge($tweets,($this->getFilteredTweets($username, true, $incMediaTweets)));
        return $tweets;
    }

    /**
     * Get tweets matching a search term, filtered, and making multiple passes to try to retrieve more tweets
     *
     * @param $query
     * @param string $lang
     * @param int $passes
     * @param bool $incMediaTweets
     * @return array Filtered tweets
     *
     * @throws \Exception
     */
    public function searchConsecutiveFilteredTweets($query, $lang = "en", $passes = 1, $incMediaTweets = false)
    {
        $tweets = [];
        for($x=0; $x<$passes; $x++)
            $tweets = array_merge($tweets,$this->searchFilteredTweets($query, $lang, true, $incMediaTweets));
        return $tweets;
    }

    /**
     * @param string $queryIdsKey
     * @param string $fromLastId
     * @return string|null
     */
    private function getMaxId($queryIdsKey, $fromLastId)
    {
        if (!$fromLastId || !isset($this->lastIds[$queryIdsKey])) {
            return null;
        }
        return bcsub($this->lastIds[$queryIdsKey],"1");
    }

    /**
     * @param array $tweets
     * @param string $queryIdsKey
     */
    private function updateLastIds($tweets, $queryIdsKey)
    {
        $lastTweet = end($tweets);
        $this->lastIds[$queryIdsKey] = $lastTweet["id_str"];
    }

    /**
     * @param array $tweets
     * @param bool $incMediaTweets
     * @return array
     */
    private function filterTweets($tweets, $incMediaTweets)
    {
        $textOnlyTweets = [];
        foreach($tweets as $tweet) {
            $tweetText = $tweet["text"];
            $tweetUrls = $tweet["entities"]["urls"];
            $tweetMedia = isset($tweet["entities"]["media"]) ? $tweet["entities"]["media"] : [];

            if ($tweetUrls && self::FILTER_TWEETS_WITH_URLS) continue;
            if ($tweet["entities"]["user_mentions"] && self::FILTER_TWEETS_WITH_MENTIONS) continue;
            if ($tweetMedia && !$incMediaTweets) continue;

            $tweetText = preg_replace("~https?://[^ ]+~","",$tweetText);
            $tweetText = preg_replace("~@[a-zA-Z0-9_]+~","",$tweetText);

            $textOnlyTweets[$tweet["id_str"]] = trim($tweetText);
        }
        return $textOnlyTweets;
    }
}
