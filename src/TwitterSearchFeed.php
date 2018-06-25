<?php
/**
 * Copyright © 2015, Ambroise Maupate
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @file TwitterSearchFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Abraham\TwitterOAuth\TwitterOAuthException;
use Doctrine\Common\Cache\CacheProvider;
use RZ\MixedFeed\AbstractFeedProvider\AbstractTwitterFeed;

/**
 * Get a Twitter search tweets feed.
 */
class TwitterSearchFeed extends AbstractTwitterFeed
{
    protected $cacheKey;
    protected $queryParams;

    /**
     * @var bool
     */
    protected $includeRetweets = true;

    /**
     * @var bool
     */
    protected $extended = false;

    /**
     * @var string
     */
    protected $resultType = 'mixed';

    /**
     * @var string
     */
    protected static $timeKey = 'created_at';

    /**
     *
     * @param array $queryParams
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $accessToken
     * @param string $accessTokenSecret
     * @param CacheProvider|null $cacheProvider
     * @param bool $extended
     */
    public function __construct(
        array $queryParams,
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
        CacheProvider $cacheProvider = null,
        $extended = false
    ) {
        parent::__construct(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret,
            $cacheProvider
        );

        $this->queryParams = array_filter($queryParams);
        $this->cacheKey = $this->getFeedPlatform() . md5(serialize($queryParams));
        $this->extended = $extended;
    }

    /**
     * @return string
     */
    protected function formatQueryParams()
    {
        $inlineParams = [];
        foreach ($this->queryParams as $key => $value) {
            if (is_numeric($key)) {
                $inlineParams[] = $value;
            } else {
                $inlineParams[] = $key . ':' . $value;
            }
        }

        return implode(' ', $inlineParams);
    }

    protected function getFeed($count = 5)
    {
        $countKey = $this->cacheKey . $count;

        try {
            if (null !== $this->cacheProvider &&
                $this->cacheProvider->contains($countKey)) {
                return $this->cacheProvider->fetch($countKey);
            }

            if ($this->includeRetweets === false) {
                $this->queryParams['-filter'] = 'retweets';
            }

            $params = [
                "q" => $this->formatQueryParams(),
                "count" => $count,
                "result_type" => $this->resultType,
            ];
            if ($this->extended) {
                $params['tweet_mode'] = 'extended';
            }

            $body = $this->twitterConnection->get("search/tweets", $params);

            if (null !== $this->cacheProvider) {
                $this->cacheProvider->save(
                    $countKey,
                    $body->statuses,
                    $this->ttl
                );
            }

            return $body->statuses;
        } catch (TwitterOAuthException $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return bool
     */
    public function isIncludeRetweets()
    {
        return $this->includeRetweets;
    }

    /**
     * @param bool $includeRetweets
     * @return TwitterSearchFeed
     */
    public function setIncludeRetweets($includeRetweets)
    {
        $this->includeRetweets = $includeRetweets;
        return $this;
    }

    /**
     * @return string
     */
    public function getResultType()
    {
        return $this->resultType;
    }

    /**
     * Optional. Specifies what type of search results you would prefer to receive. The current default is “mixed.” Valid values include:
     * mixed : Include both popular and real time results in the response.
     * recent : return only the most recent results in the response
     * popular : return only the most popular results in the response.
     *
     * @param string $resultType
     * @return TwitterSearchFeed
     */
    public function setResultType($resultType)
    {
        $this->resultType = $resultType;
        return $this;
    }
}
