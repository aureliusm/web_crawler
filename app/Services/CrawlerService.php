<?php

namespace App\Services;

use App\Resource;
use App\Search;
use DOMDocument;

class CrawlerService
{

    // Visited urls
    private $_urls = [];

    // Current search
    private $_search = null;

    // Current resource id
    private $_current_resource_id = null;

    // DOM document
    private $_dom = null;

    // Current meta resource
    private $_cur_meta_resource = null;

    /**
     * @return null
     */
    public function getCurrentResourceId()
    {
        return $this->_current_resource_id;
    }

    /**
     * @param null $current_resource_id
     */
    public function setCurrentResourceId($current_resource_id)
    {
        $this->_current_resource_id = $current_resource_id;
    }

    /**
     * Returns current scrape
     */
    public function getSearch()
    {
        return $this->_search;
    }

    /**
     * @param null $search
     */
    public function setSearch($search)
    {
        $this->_search = $search;
    }

    /**
     * Returns HTTP response code for url
     *
     * @param $url
     * @return string
     */
    private function get_http_response_code(string $url)
    {
        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
    }

    /**
     * Saves image resources to DB
     *
     * @param $image_url
     */
    private function saveImageResource($image_url)
    {
        $src = $image_url->getAttribute('src');

        if ($src = $this->convertUrl($src)) {

            // Check if image is accessible
            if($this->get_http_response_code($src) == '200') {

                // Get image headers
                $headers = get_headers($src);

                // Load image for dimensions
                list($width, $height) = getimagesize($src);

                // Persist to DB
                $img_resource = new Resource();
                $img_resource->resource_id = $this->_current_resource_id;
                $img_resource->type = 'image';
                $img_resource->url = $src;
                $img_resource->file_type = str_replace('Content-Type: ', '', (isset($headers[11]) ? $headers[11] : ''));
                $img_resource->file_size = str_replace('Content-Length: ', '', (isset($headers[6]) ? $headers[6] : ''));
                $img_resource->file_dimensions = $width . 'x' . $height;
                $this->_search->resources()->save($img_resource);

            }

        }

    }

    /**
     * Returns HTTP redirect location for url
     *
     * @param $url
     * @return string
     */
    private function get_redirect_location(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Must be set to true so that PHP follows any "Location:" header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $a = curl_exec($ch); // $a will contain all headers
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // This is what you need, it will return you the last effective URL
        return $url;
    }

    /**
     * Returns root domain of URL
     *
     * @param $url
     * @return string
     */
    private function getRootDomain($url)
    {
        $result = parse_url($url);
        return $result['scheme'] . "://" . $result['host'];
    }

    /**
     *
     * Checks if scrape can proceed for url
     *
     * @param $url
     * @return bool
     */
    private function checkScrapeLimitations($url)
    {

        // Exclude existing links
        if (in_array($url, $this->_urls)) {
            return false;
        }

        // Exclude mailto links
        if (strpos($url, 'mailto:') !== false) {
            return false;
        }

        return true;

    }

    /**
     * Saves scrape error
     *
     * @param $url
     * @param $response_code
     */
    private function saveError($url, $response_code)
    {
        $meta_resource = new Resource;
        $meta_resource->resource_id = $this->_current_resource_id;
        $meta_resource->url = $url;
        $meta_resource->type = 'error';
        $meta_resource->title = $response_code;
        $this->_search->resources()->save($meta_resource);
    }

    /**
     * Parses URL content
     *
     * @param $url
     */
    private function parseContent($url)
    {

        // Get html content
        $html_content = file_get_contents($url);

        // Create DOM Document instance
        $this->_dom = new DOMDocument;

        // Surpress errors
        libxml_use_internal_errors(true);

        // Load HTML
        $this->_dom->loadHTML($html_content);

        // Enable errors
        libxml_use_internal_errors(false);

        // Save meta data for this URL
        $this->saveMetaData($url);

        // Parse images
        $images = $this->_dom->getElementsByTagName('img');
        foreach ($images as $image) {
            $this->saveImageResource($image);
        }

        // Follow links
        $links = $this->_dom->getElementsByTagName('a');
        foreach ($links AS $link) {
            $this->scrape($link->getAttribute('href'), $this->_cur_meta_resource->id);
        }

    }

    /**
     * Checks url if not absolute or different domain
     *
     * @param $url
     * @return bool|string
     */
    private function convertUrl($url)
    {

        // Remove trailing slash
//        if (substr($url, -1) == '/') {
//            $url = substr($url, 0, -1);
//        }

        // Set base domain if missing
        if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
            $url = $this->getRootDomain($this->_search->url) . '/' . $url;
        }

        // If it's a different domain, dont parse!
        if ($this->getRootDomain($this->_search->url) != $this->getRootDomain($url)) {
            return false;
        }

        return $url;

    }

    private function saveMetaData($url)
    {
        $meta_resource = new Resource;
        $meta_resource->resource_id = $this->_current_resource_id;
        $meta_resource->url = $url;
        $meta_resource->type = 'meta';
        $title = $this->_dom->getElementsByTagName("title");
        if ($title->length > 0) {
            $meta_resource->title = $title->item(0)->textContent;
        }
        $metas = $this->_dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) == 'description') {
                $meta_resource->description = $meta->getAttribute('content');
            } else if (strtolower($meta->getAttribute('property')) == 'og:title') {
                $meta_resource->og_title = $meta->getAttribute('content');
            } else if (strtolower($meta->getAttribute('property')) == 'og:description') {
                $meta_resource->og_description = $meta->getAttribute('content');
            }
        }
        $htmls = $this->_dom->getElementsByTagName('html');
        foreach ($htmls as $html) {
            $meta_resource->language = $html->getAttribute('lang');
        }
        $this->_search->resources()->save($meta_resource);
        $this->_cur_meta_resource = $meta_resource;
    }

    /**
     * Recursively scrapes links and meta data from URL
     *
     * @param $url
     * @param $resource_id
     * @return array
     */
    private function scrape(string $url, $resource_id = null)
    {

        // Set current resource ID
        $this->setCurrentResourceId($resource_id);

        // Convert URL to absolute
        $url = $this->convertUrl($url);

        if ($url) {

            // Check limitations
            if ($this->checkScrapeLimitations($url)) {

                // Save url to "already scraped" urls
                $this->_urls[] = $url;

                // Get the response code
                $response_code = $this->get_http_response_code($url);

                // If redirect 301, follow redirect
                if ($response_code == '301') {

                    $url = $this->get_redirect_location($url);
                    $this->scrape($url, $this->_current_resource_id);

                } else if ($response_code != '200') {

                    $this->saveError($url, $response_code);

                } else {

                    $this->parseContent($url);

                }

            }

        }

    }

    /**
     * Scrapes URL for information
     *
     * @param $url
     * @return array
     */
    public function scrapeUrl(string $url)
    {

        // Scrape newer than 1 day exists?
        $search = Search::findByUrl($url);
        if ($search) {

            $this->setSearch($search);

        } else {

            // Create a new search
            $search = new Search();
            $search->url = $url;
            $search->save();

            // Sets the scrape
            $this->setSearch($search);

            // Parses links and images
            $this->scrape($url);

        }
    }


}
