<?php

namespace App\Sitemaps;

use WP_Sitemaps_Provider;

/**
 * Sitemap provider with a single URL: the public start page.
 */
class HomeProvider extends WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name = 'home';
        $this->object_type = 'home';
    }

    /**
     * @param  int  $page_num
     * @param  string  $object_subtype
     * @return list<array{loc: string}>
     */
    public function get_url_list($page_num, $object_subtype = '')
    {
        return (int) $page_num === 1 ? [['loc' => home_url('/')]] : [];
    }

    /**
     * @param  string  $object_subtype
     * @return int
     */
    public function get_max_num_pages($object_subtype = '')
    {
        return 1;
    }
}
