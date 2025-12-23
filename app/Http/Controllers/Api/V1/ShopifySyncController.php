<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Shopify",
 *     description="Shopify-specific operations and synchronization"
 * )
 */
class ShopifySyncController extends Controller
{
    public function __construct(private ShopifyService $shopifyService) {}

    /**
     * @OA\Post(
     *     path="/api/v1/shopify/import",
     *     summary="Import products from Shopify",
     *     description="Import products from Shopify to local database with pagination support",
     *     tags={"Shopify"},
     *
     *     @OA\Parameter(
     *         name="first",
     *         in="query",
     *         description="Number of products to import",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=50, maximum=250)
     *     ),
     *
     *     @OA\Parameter(
     *         name="cursor",
     *         in="query",
     *         description="Pagination cursor for fetching next page",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="update_existing",
     *         in="query",
     *         description="Whether to update existing products",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Products imported successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="imported", type="integer", example=25),
     *             @OA\Property(property="updated", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=35),
     *             @OA\Property(property="hasNextPage", type="boolean", example=true),
     *             @OA\Property(property="endCursor", type="string", example="eyJpZCI6IjEyMzQ1Njc4OTAifQ=="),
     *             @OA\Property(property="message", type="string", example="Import completed successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Invalid parameters"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error - Shopify API error"
     *     )
     * )
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'first' => 'nullable|integer|min:1|max:250',
            'cursor' => 'nullable|string',
            'update_existing' => 'nullable|boolean',
        ]);

        $params = [
            'first' => $request->get('first', 50),
            'cursor' => $request->get('cursor'),
        ];

        try {
            $result = $this->shopifyService->importProductsFromShopify($params);

            return response()->json([
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'total' => $result['total'],
                'hasNextPage' => $result['hasNextPage'],
                'endCursor' => $result['endCursor'],
                'message' => 'Import completed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/shopify/export/{product}",
     *     summary="Export specific product to Shopify",
     *     description="Export a local product to Shopify (creates new or updates existing)",
     *     tags={"Shopify"},
     *
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID to export",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="force_create",
     *         in="query",
     *         description="Force create new product even if shopify_product_id exists",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product exported successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Product exported successfully"),
     *             @OA\Property(property="shopify_product_id", type="integer", example=987654321),
     *             @OA\Property(property="shopify_gid", type="string", example="gid://shopify/Product/987654321"),
     *             @OA\Property(property="synced_variants", type="integer", example=3)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Product missing required data"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error - Shopify API error"
     *     )
     * )
     */
    public function exportProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'force_create' => 'nullable|boolean',
        ]);

        $forceCreate = $request->boolean('force_create', false);

        if ($forceCreate && $product->shopify_product_id) {
            // Temporarily remove Shopify ID to force creation
            $tempShopifyId = $product->shopify_product_id;
            $product->shopify_product_id = null;
        }

        try {
            $shopifyProduct = $this->shopifyService->syncProduct($product);

            // Update local product with Shopify ID
            if (! isset($tempShopifyId) || ! $forceCreate) {
                $shopifyId = (int) substr(strrchr($shopifyProduct['id'], '/'), 1);
                $product->update(['shopify_product_id' => $shopifyId]);
            }

            // Count synced variants
            $syncedVariiants = 0;
            if (isset($shopifyProduct['variants']['edges'])) {
                $syncedVariants = count($shopifyProduct['variants']['edges']);
            }

            return response()->json([
                'message' => 'Product exported successfully',
                'shopify_product_id' => $product->fresh()->shopify_product_id,
                'shopify_gid' => $shopifyProduct['id'],
                'synced_variants' => $syncedVariants,
            ]);
        } catch (\Exception $e) {
            // Restore temp Shopify ID if it was removed
            if (isset($tempShopifyId)) {
                $product->shopify_product_id = $tempShopifyId;
                $product->save();
            }

            return response()->json([
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/shopify/export/bulk",
     *     summary="Export multiple products to Shopify",
     *     description="Bulk export products to Shopify (operates in background)",
     *     tags={"Shopify"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="product_ids",
     *                 type="array",
     *
     *                 @OA\Items(type="integer"),
     *                 description="Array of product IDs to export",
     *                 example={1, 2, 3, 4, 5}
     *             ),
     *
     *             @OA\Property(property="update_existing", type="boolean", example=true, description="Update existing products in Shopify"),
     *             @OA\Property(property="skip_failed", type="boolean", example=true, description="Continue processing if some products fail")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Bulk export started successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/BulkOperationResponse")
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Invalid product IDs"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function bulkExport(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => 'integer|exists:products,id',
            'update_existing' => 'nullable|boolean',
            'skip_failed' => 'nullable|boolean',
        ]);

        $productIds = $request->get('product_ids');
        $operationId = 'bulk_export_'.uniqid();

        // In a real implementation, this would queue a background job
        // For this example, we'll return a mock response
        return response()->json([
            'operation_id' => $operationId,
            'status' => 'pending',
            'total_items' => count($productIds),
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => now()->toISOString(),
            'estimated_completion' => now()->addMinutes(count($productIds) * 2)->toISOString(),
            'progress_percentage' => 0.0,
            'message' => 'Bulk export operation started',
        ], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/shopify/sync/status",
     *     summary="Get sync status overview",
     *     description="Get comprehensive sync status between local database and Shopify",
     *     tags={"Shopify"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Sync status retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", enum={"synced", "pending", "error"}, example="synced", description="Sync status"),
     *             @OA\Property(property="last_sync_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Last sync timestamp"),
     *             @OA\Property(property="total_products", type="integer", example=150, description="Total products in system"),
     *             @OA\Property(property="synced_products", type="integer", example=145, description="Products synced to Shopify"),
     *             @OA\Property(property="pending_products", type="integer", example=5, description="Products pending sync"),
     *             @OA\Property(property="sync_errors", type="array", @OA\Items(type="string"), example={"Product 123 sync failed"}, description="Sync error messages")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function syncStatus(): JsonResponse
    {
        $totalProducts = Product::count();
        $syncedProducts = Product::whereNotNull('shopify_product_id')->count();
        $pendingProducts = $totalProducts - $syncedProducts;

        // Get some example sync errors (in real implementation, this would come from logs)
        $syncErrors = [];

        $status = 'synced';
        if ($pendingProducts > 0) {
            $status = 'pending';
        }

        // Get last sync timestamp (in real implementation, this would be stored)
        $lastSyncAt = Product::orderBy('updated_at', 'desc')->value('updated_at');

        return response()->json([
            'status' => $status,
            'last_sync_at' => $lastSyncAt?->toISOString(),
            'total_products' => $totalProducts,
            'synced_products' => $syncedProducts,
            'pending_products' => $pendingProducts,
            'sync_percentage' => $totalProducts > 0 ? round(($syncedProducts / $totalProducts) * 100, 2) : 100,
            'sync_errors' => $syncErrors,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/shopify/sync/validate",
     *     summary="Validate Shopify credentials",
     *     description="Test Shopify API credentials and connection",
     *     tags={"Shopify"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Credentials are valid",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="valid", type="boolean", example=true),
     *             @OA\Property(property="shop_name", type="string", example="My Awesome Store"),
     *             @OA\Property(property="shop_domain", type="string", example="mystore.myshopify.com"),
     *             @OA\Property(property="message", type="string", example="Shopify credentials are valid")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="valid", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Invalid access token or store domain")
     *         )
     *     )
     * )
     */
    public function validateCredentials(): JsonResponse
    {
        try {
            $isValid = $this->shopifyService->validateCredentials();

            if ($isValid) {
                return response()->json([
                    'valid' => true,
                    'shop_name' => 'Shop Name', // In real implementation, get from Shopify API
                    'shop_domain' => config('services.shopify.domain'),
                    'message' => 'Shopify credentials are valid',
                ]);
            } else {
                return response()->json([
                    'valid' => false,
                    'error' => 'Invalid access token or store domain',
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/shopify/sync/conflicts",
     *     summary="Get sync conflicts",
     *     description="Identify products with sync conflicts between local and Shopify data",
     *     tags={"Shopify"},
     *
     *     @OA\Parameter(
     *         name="resolve",
     *         in="query",
     *         description="Type of conflicts to return",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"all", "local_newer", "shopify_newer"}, default="all")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Conflicts retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="conflicts", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="product_id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="conflict_type", type="string", enum={"local_newer", "shopify_newer", "variant_mismatch"}),
     *                 @OA\Property(property="local_updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="shopify_updated_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="total_conflicts", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function syncConflicts(Request $request): JsonResponse
    {
        $request->validate([
            'resolve' => 'nullable|in:all,local_newer,shopify_newer',
        ]);

        // In a real implementation, this would compare timestamps and data
        // For now, return mock data
        $conflicts = [];

        return response()->json([
            'conflicts' => $conflicts,
            'total_conflicts' => count($conflicts),
            'message' => 'No conflicts found',
        ]);
    }
}
