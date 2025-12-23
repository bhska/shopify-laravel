<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth Routes
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login'])->name('login.post');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::resource('products', ProductController::class);
    Route::post('products/{product}/upload-image', [ProductController::class, 'uploadImage'])->name('web.products.upload-image');
    Route::post('products/sync-from-shopify', [ProductController::class, 'syncFromShopify'])->name('products.sync-from-shopify');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    // Product Image Management Routes
    Route::post('products/{product}/images', [ProductImageManagementController::class, 'store'])->name('products.images.store');
    Route::delete('products/{product}/images/{image}', [ProductImageManagementController::class, 'destroy'])->name('products.images.destroy');
});
