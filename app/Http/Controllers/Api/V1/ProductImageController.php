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
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'], // 10MB limit
        ]);

        $file = $request->file('image');
        $path = $file->store('product_images', 'public');

        $productImage = $product->images()->create([
            'path' => $path,
        ]);

        // Sync to Shopify
        try {
            $shopifyImage = $this->shopifyService->uploadProductImage($product, $file);

            $productImage->update([
                'shopify_image_id' => $shopifyImage->id,
            ]);
        } catch (\Exception $e) {
            // Log error but maybe don't fail the request completely if local storage worked?
            // PRD: "Sync: Send image to Shopify... Store shopify_image_id"
            // If sync fails, we likely want to inform user.
            throw $e;
        }

        return response()->json($productImage, 201);
    }
}
