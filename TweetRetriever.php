<?php
namespace TzLion\TweetTrumpet;

class TweetRetriever extends TwitterAuthenticated
{
    private $lastIds = [];

    const FILTER_TWEETS_WITH_URLS = true;
    const FILTER_TWEETS_WITH_MENTIONS = true;

    public function getTweets( $username, $continueFromLast = false, $incRts = false, $incReplies = false )
    {
        $queryIdsKey = "u_$username";
        $maxId = $this->getMaxId( $queryIdsKey, $continueFromLast );

        $params = [
            "include_rts" => $incRts,
            "screen_name" => $username,
            "exclude_replies" => !$incReplies,
            "count" => 200
        ];
        if ( $maxId ) $params["max_id"] = $maxId;
        $tweets = $this->get( self::TWITTER_API_BASE_URL . "/statuses/user_timeline.json", $params );

        $this->updateLastIds($tweets, $queryIdsKey);

        return $tweets;
    }

    public function searchTweets( $query, $lang = "en", $continueFromLast = false )
    {
        $queryIdsKey = "s_$query";
        $maxId = $this->getMaxId( $queryIdsKey, $continueFromLast );

        $params = [
            "q" => $query,
            "result_type" => "recent",
            "lang" => $lang,
            "count" => 100
        ];
        if ( $maxId ) $params["max_id"] = $maxId;
        $tweets = $this->get( self::TWITTER_API_BASE_URL . "/search/tweets.json", $params )['statuses'] ?: [];

        $this->updateLastIds($tweets, $queryIdsKey);

        return $tweets;
    }

    public function getMentions($count=10,$fromId=null)
    {
        $params = [
            "count" => $count,
        ];
        if ( $fromId ) {
            $params['since_id'] = $fromId;
        }
        $tweets = $this->get( self::TWITTER_API_BASE_URL . "/statuses/mentions_timeline.json", $params );
        return $tweets;
    }

    private function getMaxId($queryIdsKey,$fromLastId)
    {
        if ( !$fromLastId || !isset( $this->lastIds[$queryIdsKey] ) ) {
            return null;
        }
        return bcsub($this->lastIds[$queryIdsKey],"1");
    }

    private function updateLastIds( $tweets, $queryIdsKey )
    {
        $lastTweet = end( $tweets );
        $this->lastIds[$queryIdsKey] = $lastTweet["id_str"];
    }

    public function getFilteredTweets($username, $continueFromLast = false, $incMediaTweets = false )
    {
        $tweets = $this->getTweets($username, $continueFromLast);
        return $this->filterTweets($tweets, $incMediaTweets);
    }

    public function searchFilteredTweets($query, $lang = "en", $continueFromLast = false, $incMediaTweets = false )
    {
        $tweets = $this->searchTweets($query, $lang, $continueFromLast);
        return $this->filterTweets($tweets, $incMediaTweets);
    }

    private function filterTweets( $tweets, $incMediaTweets )
    {
        $textOnlyTweets = [];
        foreach( $tweets as $tweet ) {
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

    public function getConsecutiveFilteredTweets($username, $passes = 1, $incMediaTweets = false )
    {
        $tweets = [];
        for( $x=0;$x<$passes;$x++)
            $tweets = array_merge($tweets,($this->getFilteredTweets($username,true,$incMediaTweets)));
        return $tweets;
    }

    public function searchConsecutiveFilteredTweets($query, $lang = "en", $passes = 1, $incMediaTweets = false )
    {
        $tweets = [];
        for( $x=0;$x<$passes;$x++)
            $tweets = array_merge($tweets,$this->searchFilteredTweets($query,$lang,true,$incMediaTweets));
        return $tweets;
    }
}