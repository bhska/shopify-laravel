<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ShopifyService;
use Illuminate\Http\Request;

class ProductImageManagementController extends Controller
{
    public function __construct(protected ShopifyService $shopifyService) {}

    public function store(Request $request, Product $product)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:10240'],
        ]);

        if (! $product->shopify_product_id) {
            return back()->with('error', 'Product must be synced to Shopify first before uploading images.');
        }

        $file = $request->file('image');

        try {
            $shopifyImage = $this->shopifyService->uploadProductImage($product, $file);
            $imageId = $this->extractIdFromGid($shopifyImage['id']);

            $product->images()->create([
                'shopify_image_id' => $imageId,
                'path' => $shopifyImage['url'],
            ]);

            return back()->with('success', 'Image uploaded to Shopify successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to upload image to Shopify: '.$e->getMessage());
        }
    }

    public function destroy(Product $product, ProductImage $image)
    {
        try {
            // Delete from Shopify if it has a Shopify ID
            if ($image->shopify_image_id) {
                try {
                    $this->shopifyService->deleteProductImage($product, $image->shopify_image_id);
                } catch (\Exception $e) {
                    // Log error but continue with local deletion
                    logger()->error('Failed to delete image from Shopify: '.$e->getMessage());
                }
            }

            // Delete from local database
            $image->delete();

            return back()->with('success', 'Image deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete image: '.$e->getMessage());
        }
    }

    /**
     * Extract numeric ID from Shopify GID.
     * Example: "gid://shopify/ProductImage/123456" -> 123456
     */
    protected function extractIdFromGid(string $gid): int
    {
        $parts = explode('/', $gid);

        return (int) end($parts);
    }
}
