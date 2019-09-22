<?php

namespace sabri\tiktok\scraper;

use GuzzleHttp\Client;
use sabri\tiktok\scraper\exceptions\LoginRequiredException;
use sabri\tiktok\scraper\exceptions\InvalidResponseException;
use sabri\tiktok\scraper\exceptions\EmptyResponseException;

class TikTokApi {

    /** @var string Base API URL of Tiktok */
    private $_baseUrl = 'https://api2.musical.ly';

    /** @var ApiParams */
    private $_apiParams;

    /**
     * @param $sessionQueryParameters device related extra query parameters
     */
    public function __construct(array $sessionQueryParameters)
    {
        $this->_apiParams = new ApiParams($sessionQueryParameters);
    }

    /**
     * Get user profile info
     * 
     * @param string @uid Unique user id on Tiktok
     * @return array
     * @throws Exception
     */
    public function getUser(string $uid): array
    {
        $extraParams = ['user_id' => $uid];

        $content = $this->request(
            'aweme/v1/user/', 
            $extraParams
        );

        return $content;
    }

    /**
     * Get user videos
     * 
     * @param string @uid Unique user id on Tiktok
     * @return array
     * @throws Exception
     */
    public function getUserVideos(string $uid): array
    {
        // TODO Implement pagination

        $extraParams = [
            'user_id' => $uid,
            'max_cursor' => 0,
            'type' => 0,
            'count' => 20,
        ];

        $content = $this->request(
            'aweme/v1/aweme/post/', 
            $extraParams
        );

        $moreContent = [];

        // Pagination for user's videos
        while (isset($content['max_cursor']) && $content['has_more'] == 1) {
            $extraParams['max_cursor'] = $content['max_cursor'];

            $moreContent = $this->request(
                'aweme/v1/aweme/post/', 
                $extraParams
            );

            if ($moreContent) {
                $content['has_more'] = $moreContent['has_more'];
                $content['max_cursor'] = $moreContent['max_cursor'];
                $content['min_cursor'] = $moreContent['min_cursor'];
                $content['aweme_list'] = array_merge($content['aweme_list'], $moreContent['aweme_list']);
            } else {
                break;
            }
        }

        return $content;
    }

    /**
     * Search users on Tiktok
     * 
     * @param string @keyword a search term
     * @return array
     * @throws Exception
     */
    public function searchUser(string $keyword): array
    {
        $extraParams = [
            'cursor' => 0,
            'count' => 10,
            'hot_search' => 0,
            'keyword' => $keyword,
            'type' => 1
        ];

        $content = $this->request(
            'aweme/v1/discover/search', 
            $extraParams
        );

        return $content;
    }

    /**
     * Get a video details
     * 
     * @param string @uid Unique video id on Tiktok
     * @return array
     * @throws Exception
     */
    public function getPost(string $uid)
    {
        $extraParams = ['aweme_id' => $uid];
        
        $content = $this->request(
            'aweme/v1/aweme/detail', 
            $extraParams
        );

        return $content;
    }

    /**
     * Makes request to Tiktok
     * 
     * @param $url relative path to the API endpoint
     * @param $extraQueryPrameters Extra query parameters to be appended.
     * @param $extraHeaders Extra headers to be appended.
     * 
     * @return array
     * 
     * @throws LoginRequiredException if user should be logged in for the current request
     * @throws InvalidResponseException if there are errors in response
     * @throws InvalidResponseException if reponse body is empty 
     */
    protected function request(
        string $url,
        array $extraQueryPrameters = [],
        array $extraHeaders = []
    ): array {

        $client = new Client([
            'base_uri' => $this->_baseUrl,
            //'debug' => true
        ]);
        
        $response = $client->request('GET', $url, [
            'query' => $this->_apiParams->getQueryParams($extraQueryPrameters),
            'headers' => $this->_apiParams->getHeaders($extraHeaders),
        ]);
        
        if ($response->getStatusCode() != 200) {
            throw new InvalidResponseException('Problems fetching content');
        }

        $arrayContent = json_decode($response->getBody()->getContents(), true);

        // If empty response
        if (!$arrayContent) {
            throw new InvalidResponseException('Invalid response');            
        }

        if (isset($arrayContent['status_code']) && $arrayContent['status_code'] > 0) {
            if ($arrayContent['status_code'] == 2483) {
                throw new LoginRequiredException($arrayContent['status_msg']);
            }
            throw new InvalidResponseException($arrayContent['status_msg'] ?? 'Invalid response');            
        }

        return $arrayContent;
    }
}
