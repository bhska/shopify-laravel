<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShopifySyncController;
use App\Http\Controllers\Api\V1\VariantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication endpoints (no authentication required)
Route::middleware('api')->prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });
});

// Public health check endpoints (no authentication required)
Route::middleware('api')->prefix('v1')->group(function () {
    Route::prefix('health')->group(function () {
        Route::get('/', [HealthCheckController::class, 'index']);
        Route::get('/detailed', [HealthCheckController::class, 'detailed']);
        Route::get('/database', [HealthCheckController::class, 'database']);
        Route::get('/shopify', [HealthCheckController::class, 'shopify']);
    });

    // Search endpoints (public for demo purposes)
    Route::prefix('search')->group(function () {
        Route::get('/products', [SearchController::class, 'products']);
        Route::get('/variants', [SearchController::class, 'variants']);
        Route::get('/suggestions', [SearchController::class, 'suggestions']);
        Route::get('/filters', [SearchController::class, 'filters']);
    });
});

// Protected API routes (require authentication)
Route::middleware(['api', 'auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
    // Authentication endpoints (require token)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
    // Product endpoints
    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');

    // Bulk operations for products
    Route::prefix('products')->group(function () {
        Route::post('/bulk', [ProductController::class, 'bulkStore']);
        Route::patch('/bulk', [ProductController::class, 'bulkUpdate']);
        Route::delete('/bulk', [ProductController::class, 'bulkDestroy']);
        Route::get('/bulk/status/{operationId}', [ProductController::class, 'bulkStatus']);
    });

    // Shopify sync endpoints
    Route::prefix('shopify')->group(function () {
        Route::post('/import', [ShopifySyncController::class, 'import']);
        Route::post('/export/{product}', [ShopifySyncController::class, 'exportProduct']);
        Route::post('/export/bulk', [ShopifySyncController::class, 'bulkExport']);
        Route::get('/sync/status', [ShopifySyncController::class, 'syncStatus']);
        Route::post('/sync/validate', [ShopifySyncController::class, 'validateCredentials']);
        Route::get('/sync/conflicts', [ShopifySyncController::class, 'conflicts']);
    });

    // Variant endpoints
    Route::prefix('variants')->group(function () {
        Route::get('/', [VariantController::class, 'index']);
        Route::get('/{variant}', [VariantController::class, 'show']);
        Route::post('/products/{product}/variants', [VariantController::class, 'store']);
        Route::put('/{variant}', [VariantController::class, 'update']);
        Route::delete('/{variant}', [VariantController::class, 'destroy']);
        Route::patch('/{variant}/inventory', [VariantController::class, 'updateInventory']);
    });

    // Variant endpoints for specific products
    Route::get('/products/{product}/variants', [VariantController::class, 'forProduct']);
});

// User endpoint (for testing authentication)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
