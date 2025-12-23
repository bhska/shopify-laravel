<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function __construct(protected ShopifyService $shopifyService) {}

    /**
     * Upload product image to Shopify.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        if (! $product->shopify_product_id) {
            return response()->json([
                'success' => false,
                'message' => 'Product must be synced to Shopify first before uploading images.',
            ], 422);
        }

        $file = $request->file('image');

        try {
            $shopifyImage = $this->shopifyService->uploadProductImage($product, $file);
            $imageId = $this->extractIdFromGid($shopifyImage['id']);

            $productImage = $product->images()->create([
                'shopify_image_id' => $imageId,
                'path' => $shopifyImage['url'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded to Shopify successfully!',
                'data' => $productImage,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image to Shopify: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract numeric ID from Shopify GID.
     */
    protected function extractIdFromGid(string $gid): int
    {
        $parts = explode('/', $gid);

        return (int) end($parts);
    }
}
