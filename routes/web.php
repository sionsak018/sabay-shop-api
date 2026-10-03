<?php

use App\Http\Controllers\Api\SitemapController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
