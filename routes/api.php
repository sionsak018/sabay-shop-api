<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\CategoryController;

// Public routes
Route::get('/home', [\App\Http\Controllers\Api\HomeController::class, 'index']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public product browsing
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/sliders', [\App\Http\Controllers\Api\Admin\SliderController::class, 'getActive']);
Route::get('/profile/{id}', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
Route::get('/category-attributes/{categoryId}', [\App\Http\Controllers\Api\Admin\CategoryAttributeController::class, 'index']);
Route::get('/brands', [\App\Http\Controllers\Api\Admin\BrandController::class, 'index']);
Route::get('/brand-models', [\App\Http\Controllers\Api\Admin\BrandModelController::class, 'index']);
Route::get('/body-types', [\App\Http\Controllers\Api\Admin\BodyTypeController::class, 'index']);

// Public Location Routes
Route::get('/provinces', [\App\Http\Controllers\Api\Admin\ProvinceController::class, 'index']);
Route::get('/districts', [\App\Http\Controllers\Api\Admin\DistrictController::class, 'index']);
Route::get('/communes', [\App\Http\Controllers\Api\Admin\CommuneController::class, 'index']);
Route::get('/villages', [\App\Http\Controllers\Api\Admin\VillageController::class, 'index']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update']); // Use POST for multipart support
    Route::get('/user-stats/{id}', [\App\Http\Controllers\Api\ProfileController::class, 'stats']);

    // Social & Likes
    Route::get('/favorites', [\App\Http\Controllers\Api\FavoriteController::class, 'index']);
    Route::post('/favorites/{productId}', [\App\Http\Controllers\Api\FavoriteController::class, 'toggle']);
    Route::post('/follow/{userId}', [\App\Http\Controllers\Api\FollowController::class, 'toggle']);
    Route::get('/followers/{userId}', [\App\Http\Controllers\Api\FollowController::class, 'followers']);
    Route::get('/following/{userId}', [\App\Http\Controllers\Api\FollowController::class, 'following']);

    // Create, update, delete require auth
    Route::get('/my-products/check-limit', [ProductController::class, 'checkLimit']);
    Route::get('/my-products', [ProductController::class, 'userProducts']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);      // Get user's orders
    Route::post('/checkout', [OrderController::class, 'store']);   // Create

    Route::get('/cart', [CartController::class, 'index']);               // Get user's cart
    Route::post('/cart/add', [CartController::class, 'addItem']);       // Add product
    Route::put('/cart/item/{id}', [CartController::class, 'updateItem']); // Update quantity
    Route::delete('/cart/item/{id}', [CartController::class, 'removeItem']); // Remove item
    Route::delete('/cart', [CartController::class, 'clear']);

    Route::apiResource('messages', MessageController::class)->only(['index', 'store', 'destroy']);
    Route::post('/messages/{id}/react', [MessageController::class, 'react']);
    Route::put('/messages/{id}/read', [MessageController::class, 'markAsRead']);
});

// Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Api\Admin\AdminDashboardController::class, 'index']);

    // User & Role Management
    Route::apiResource('users', \App\Http\Controllers\Api\Admin\UserController::class)->middleware('permission:view_users');
    Route::apiResource('roles', \App\Http\Controllers\Api\Admin\RoleController::class)->middleware('permission:manage_roles');
    Route::apiResource('permissions', \App\Http\Controllers\Api\Admin\PermissionController::class)->middleware('permission:manage_roles');

    // Product Management
    Route::apiResource('products', \App\Http\Controllers\Api\Admin\AdminProductController::class)->only(['index', 'update', 'destroy'])->middleware('permission:view_products');
    Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class)->except(['index'])->middleware('permission:manage_categories');

    // Settings & Master Data
    Route::apiResource('brands', \App\Http\Controllers\Api\Admin\BrandController::class);
    Route::apiResource('brand-models', \App\Http\Controllers\Api\Admin\BrandModelController::class);
    Route::apiResource('body-types', \App\Http\Controllers\Api\Admin\BodyTypeController::class);
    Route::apiResource('attributes', \App\Http\Controllers\Api\Admin\AttributeController::class);

    Route::apiResource('provinces', \App\Http\Controllers\Api\Admin\ProvinceController::class);
    Route::apiResource('districts', \App\Http\Controllers\Api\Admin\DistrictController::class);
    Route::apiResource('communes', \App\Http\Controllers\Api\Admin\CommuneController::class);
    Route::apiResource('villages', \App\Http\Controllers\Api\Admin\VillageController::class);
    Route::apiResource('sliders', \App\Http\Controllers\Api\Admin\SliderController::class);

    Route::get('/category-attributes/{categoryId}', [\App\Http\Controllers\Api\Admin\CategoryAttributeController::class, 'index']);
    Route::post('/category-attributes/{categoryId}', [\App\Http\Controllers\Api\Admin\CategoryAttributeController::class, 'sync']);

    // Placeholder for configuration
    Route::get('/config', function() {
        return response()->json([
            'site_name' => 'Sabay Shop',
            'contact_email' => 'support@sabayshop.com',
            'maintenance_mode' => false
        ]);
    });
});
