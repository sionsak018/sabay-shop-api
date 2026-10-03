<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Public XML sitemap for the storefront, served from the API domain and
     * referenced by the frontend robots.txt.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap_xml', now()->addHour(), function () {
            $base = rtrim((string) config('app.frontend_url'), '/');
            $now = now()->toAtomString();

            $urls = [
                ['loc' => $base.'/', 'lastmod' => $now, 'priority' => '1.0'],
                ['loc' => $base.'/products', 'lastmod' => $now, 'priority' => '0.8'],
            ];

            Category::query()
                ->orderBy('id')
                ->get(['id', 'updated_at'])
                ->each(function (Category $category) use (&$urls, $base, $now) {
                    $urls[] = [
                        'loc' => $base.'/products?category_id='.$category->id,
                        'lastmod' => optional($category->updated_at)->toAtomString() ?? $now,
                        'priority' => '0.6',
                    ];
                });

            Product::query()
                ->where('status', 'active')
                ->orderByDesc('updated_at')
                ->limit(5000)
                ->get(['id', 'updated_at'])
                ->each(function (Product $product) use (&$urls, $base, $now) {
                    $urls[] = [
                        'loc' => $base.'/products/'.$product->id,
                        'lastmod' => optional($product->updated_at)->toAtomString() ?? $now,
                        'priority' => '0.7',
                    ];
                });

            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as $url) {
                $xml .= '  <url>'."\n";
                $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'."\n";
                $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
                $xml .= '    <priority>'.$url['priority'].'</priority>'."\n";
                $xml .= '  </url>'."\n";
            }
            $xml .= '</urlset>';

            return $xml;
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
