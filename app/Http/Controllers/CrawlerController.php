<?php

namespace App\Http\Controllers;

use App\Resource;
use App\Search;
use Illuminate\Http\Request;

use App\Http\Requests;

class CrawlerController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crawler.index');
    }

    /**
     * Performs scraping for url or shows existing data if newer than 1 day
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function scrape(Request $request)
    {

        // Get URL
        $url = $request->get('url');

        // Check if URL is valid
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            return view('crawler.index', [
                'error' => 'URL is incorrect'
            ]);
        }

        // Get crawler service
        $crawler_service = resolve('CrawlerService');

        // Crawl the URL and save information to Database
        $crawler_service->scrapeUrl($url);

        // Display information about URL from the database
        return view('crawler.index', [
            'search' => $crawler_service->getSearch()
        ]);

    }

    /**
     * Shows search for url if existing data
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function search(Request $request)
    {
        $keywords = $request->get('keywords');
        $url = $request->get('url');
        $search = Search::findByUrl($url);
        $resources = Resource::findAllByKeywordsAndSearchId($keywords, $search->id);
        if($search) {
            return view('crawler.search', [
                'resources' => $resources,
                'url' => $url,
                'keywords' => $keywords
            ]);
        }
    }

}
