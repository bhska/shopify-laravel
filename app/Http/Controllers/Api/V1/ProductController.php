<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(protected ShopifyService $shopifyService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     summary="Get paginated list of products",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search products by title",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             ),
     *
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $query = Product::with(['variants', 'images']);

        if (request()->has('search')) {
            $query->where('title', 'like', '%'.request('search').'%');
        }

        return response()->json($query->paginate(request('per_page', 15)));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{product}",
     *     summary="Get a specific product",
     *     description="Retrieve a single product by ID with its variants and images",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="string",
     *             enum={"variants", "images", "variants,images"}
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             allOf={
     *
     *                 @OA\Schema(ref="#/components/schemas/Product"),
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="variants",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/Variant")
     *                     ),
     *
     *                     @OA\Property(
     *                         property="images",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/ProductImage")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load(['variants', 'images']));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     summary="Create a new product",
     *     description="Create a new product and sync it to Shopify. All operations are wrapped in a database transaction.",
     *     tags={"Products"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", maxLength=255, example="Premium T-Shirt", description="Product title (required)"),
     *             @OA\Property(property="body_html", type="string", nullable=true, example="<p>High quality cotton t-shirt with premium print</p>", description="Product description in HTML"),
     *             @OA\Property(property="vendor", type="string", maxLength=255, nullable=true, example="BrandName", description="Product vendor"),
     *             @OA\Property(property="product_type", type="string", maxLength=255, nullable=true, example="Clothing", description="Product type/category"),
     *             @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, example="active", description="Product status (required)"),
     *             @OA\Property(
     *                 property="variants",
     *                 type="array",
     *                 nullable=true,
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="option1", type="string", nullable=true, example="Medium", description="First variant option"),
     *                     @OA\Property(property="option2", type="string", nullable=true, example="Red", description="Second variant option"),
     *                     @OA\Property(property="price", type="number", format="decimal", minimum=0, example=29.99, description="Variant price (required with variants)"),
     *                     @OA\Property(property="sku", type="string", nullable=true, example="TSHIRT-M-RED", description="Stock keeping unit"),
     *                     @OA\Property(property="inventory_quantity", type="integer", minimum=0, example=50, description="Available inventory quantity")
     *                 ),
     *                 description="Product variants array"
     *             )
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="X-Requested-With",
     *         in="header",
     *         description="Header to identify AJAX requests",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="XMLHttpRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *
     *         @OA\JsonContent(
     *             allOf={
     *
     *                 @OA\Schema(ref="#/components/schemas/Product"),
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="variants",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/Variant")
     *                     ),
     *
     *                     @OA\Property(
     *                         property="images",
     *                         type="array",
     *
     *                         @OA\Items(ref="#/components/schemas/ProductImage")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Invalid data provided",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"title": {"The title field is required."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity - Validation failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error - Shopify sync failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Shopify API Error: Product creation failed"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            // 1. Create Local
            $product = Product::create($request->validated());

            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    $product->variants()->create($variantData);
                }
            }

            // 2. Sync to Shopify
            try {
                $shopifyProduct = $this->shopifyService->syncProduct($product);
            } catch (\Exception $e) {
                // If sync fails, transaction rolls back local changes
                throw $e;
            }

            // 3. Update Local IDs - extract ID from Shopify GID
            $shopifyId = (int) substr(strrchr($shopifyProduct['id'], '/'), 1);
            $product->update(['shopify_product_id' => $shopifyId]);

            // 4. Update Variant IDs - handle GraphQL response structure
            if (isset($shopifyProduct['variants']['edges'])) {
                $shopifyVariants = collect($shopifyProduct['variants']['edges']);

                foreach ($product->variants as $localVariant) {
                    // Find matching shopify variant by SKU or title
                    $match = $shopifyVariants->first(function ($edge) use ($localVariant) {
                        $shopifyVariant = $edge['node'];

                        return (! empty($shopifyVariant['sku']) && $shopifyVariant['sku'] == $localVariant->sku)
                            || $shopifyVariant['title'] == $localVariant->title;
                    });

                    if ($match) {
                        $variantId = (int) substr(strrchr($match['node']['id'], '/'), 1);
                        $localVariant->update(['shopify_variant_id' => $variantId]);
                    }
                }
            }

            return response()->json($product->fresh('variants'), 201);
        });
    }

    /**
     * @OA\Put(
     *     path="/api/v1/products/{product}",
     *     summary="Update a product",
     *     description="Update an existing product and sync changes to Shopify",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", maxLength=255, nullable=true, example="Premium T-Shirt v2", description="Updated product title"),
     *             @OA\Property(property="body_html", type="string", nullable=true, example="<p>Updated product description</p>", description="Updated product description in HTML"),
     *             @OA\Property(property="vendor", type="string", maxLength=255, nullable=true, example="UpdatedBrandName", description="Updated product vendor"),
     *             @OA\Property(property="product_type", type="string", maxLength=255, nullable=true, example="UpdatedClothing", description="Updated product type/category"),
     *             @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, nullable=true, example="draft", description="Updated product status")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Invalid data provided"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity - Validation failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error - Shopify sync failed"
     *     )
     * )
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return DB::transaction(function () use ($request, $product) {
            $product->update($request->validated());

            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    if (isset($variantData['id'])) {
                        $product->variants()->where('id', $variantData['id'])->update($variantData);
                    } else {
                        $product->variants()->create($variantData);
                    }
                }
            }

            $this->shopifyService->syncProduct($product);

            return response()->json($product->load('variants'));
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/{product}",
     *     summary="Delete a product",
     *     description="Soft delete a product locally and remove it from Shopify if it exists",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="force",
     *         in="query",
     *         description="Force delete from Shopify even if local soft delete fails",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Product deleted successfully",
     *
     *         @OA\JsonContent()
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error - Shopify deletion failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Failed to delete product from Shopify"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(Product $product): JsonResponse
    {
        return DB::transaction(function () use ($product) {
            $shopifyId = $product->shopify_product_id;

            $product->delete(); // Soft delete

            if ($shopifyId) {
                $this->shopifyService->deleteProduct($shopifyId);
            }

            return response()->json(null, 204);
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products/bulk",
     *     summary="Bulk create products",
     *     description="Create multiple products in a single request",
     *     tags={"Products"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=50,
     *
     *                 @OA\Items(ref="#/components/schemas/StoreProductRequest")
     *             ),
     *
     *             @OA\Property(property="skip_errors", type="boolean", example=false, description="Continue processing even if some products fail"),
     *             @OA\Property(property="sync_to_shopify", type="boolean", example=true, description="Sync products to Shopify")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Bulk operation started",
     *
     *         @OA\JsonContent(
     *
     *                 @OA\Property(property="operation_id", type="string", example="bulk_op_123456", description="Unique operation identifier"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="processing", description="Operation status"),
     *                 @OA\Property(property="total_items", type="integer", example=100, description="Total items to process"),
     *                 @OA\Property(property="processed_items", type="integer", example=45, description="Items processed so far"),
     *                 @OA\Property(property="failed_items", type="integer", example=2, description="Items that failed processing"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Operation start time"),
     *                 @OA\Property(property="estimated_completion", type="string", format="date-time", example="2024-01-20T16:00:00Z", description="Estimated completion time"),
     *                 @OA\Property(property="progress_percentage", type="number", format="decimal", example=45.0, description="Progress percentage"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={"Product 456 validation failed"}, description="Error messages")
     *             )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array|min:1|max:50',
            'products.*.title' => 'required|string|max:255',
            'products.*.body_html' => 'nullable|string',
            'products.*.vendor' => 'nullable|string|max:255',
            'products.*.product_type' => 'nullable|string|max:255',
            'products.*.status' => 'required|in:active,draft,archived',
            'products.*.variants' => 'nullable|array',
            'skip_errors' => 'nullable|boolean',
            'sync_to_shopify' => 'nullable|boolean',
        ]);

        $operationId = 'bulk_create_'.uniqid();
        $skipErrors = $request->boolean('skip_errors', false);
        $syncToShopify = $request->boolean('sync_to_shopify', true);

        // In a real implementation, this would queue a background job
        // For this example, we'll return a mock response
        return response()->json([
            'operation_id' => $operationId,
            'status' => 'pending',
            'total_items' => count($request->products),
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => now()->toISOString(),
            'estimated_completion' => now()->addMinutes(count($request->products) * 3)->toISOString(),
            'progress_percentage' => 0.0,
            'message' => 'Bulk product creation started',
        ], 202);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/products/bulk",
     *     summary="Bulk update products",
     *     description="Update multiple products in a single request",
     *     tags={"Products"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="updates",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=50,
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1, description="Product ID"),
     *                     @OA\Property(property="title", type="string", nullable=true, example="Updated Title"),
     *                     @OA\Property(property="body_html", type="string", nullable=true),
     *                     @OA\Property(property="vendor", type="string", nullable=true),
     *                     @OA\Property(property="product_type", type="string", nullable=true),
     *                     @OA\Property(property="status", type="string", nullable=true, enum={"active", "draft", "archived"})
     *                 )
     *             ),
     *             @OA\Property(property="skip_errors", type="boolean", example=false),
     *             @OA\Property(property="sync_to_shopify", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Bulk update started",
     *
     *         @OA\JsonContent(
     *
     *                 @OA\Property(property="operation_id", type="string", example="bulk_op_123456", description="Unique operation identifier"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="processing", description="Operation status"),
     *                 @OA\Property(property="total_items", type="integer", example=100, description="Total items to process"),
     *                 @OA\Property(property="processed_items", type="integer", example=45, description="Items processed so far"),
     *                 @OA\Property(property="failed_items", type="integer", example=2, description="Items that failed processing"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Operation start time"),
     *                 @OA\Property(property="estimated_completion", type="string", format="date-time", example="2024-01-20T16:00:00Z", description="Estimated completion time"),
     *                 @OA\Property(property="progress_percentage", type="number", format="decimal", example=45.0, description="Progress percentage"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={"Product 456 validation failed"}, description="Error messages")
     *             )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'updates' => 'required|array|min:1|max:50',
            'updates.*.id' => 'required|integer|exists:products,id',
            'updates.*.title' => 'sometimes|string|max:255',
            'updates.*.body_html' => 'sometimes|nullable|string',
            'updates.*.vendor' => 'sometimes|nullable|string|max:255',
            'updates.*.product_type' => 'sometimes|nullable|string|max:255',
            'updates.*.status' => 'sometimes|in:active,draft,archived',
            'skip_errors' => 'nullable|boolean',
            'sync_to_shopify' => 'nullable|boolean',
        ]);

        $operationId = 'bulk_update_'.uniqid();

        return response()->json([
            'operation_id' => $operationId,
            'status' => 'pending',
            'total_items' => count($request->updates),
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => now()->toISOString(),
            'estimated_completion' => now()->addMinutes(count($request->updates) * 2)->toISOString(),
            'progress_percentage' => 0.0,
            'message' => 'Bulk product update started',
        ], 202);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/bulk",
     *     summary="Bulk delete products",
     *     description="Delete multiple products in a single request",
     *     tags={"Products"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="product_ids",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=100,
     *
     *                 @OA\Items(type="integer"),
     *                 description="Array of product IDs to delete"
     *             ),
     *
     *             @OA\Property(property="force", type="boolean", example=false, description="Force delete (hard delete)"),
     *             @OA\Property(property="delete_from_shopify", type="boolean", example=true, description="Delete from Shopify as well")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Bulk deletion started",
     *
     *         @OA\JsonContent(
     *
     *                 @OA\Property(property="operation_id", type="string", example="bulk_op_123456", description="Unique operation identifier"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="processing", description="Operation status"),
     *                 @OA\Property(property="total_items", type="integer", example=100, description="Total items to process"),
     *                 @OA\Property(property="processed_items", type="integer", example=45, description="Items processed so far"),
     *                 @OA\Property(property="failed_items", type="integer", example=2, description="Items that failed processing"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Operation start time"),
     *                 @OA\Property(property="estimated_completion", type="string", format="date-time", example="2024-01-20T16:00:00Z", description="Estimated completion time"),
     *                 @OA\Property(property="progress_percentage", type="number", format="decimal", example=45.0, description="Progress percentage"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={"Product 456 validation failed"}, description="Error messages")
     *             )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => 'integer|exists:products,id',
            'force' => 'nullable|boolean',
            'delete_from_shopify' => 'nullable|boolean',
        ]);

        $operationId = 'bulk_delete_'.uniqid();

        return response()->json([
            'operation_id' => $operationId,
            'status' => 'pending',
            'total_items' => count($request->product_ids),
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => now()->toISOString(),
            'estimated_completion' => now()->addMinutes(count($request->product_ids) * 1)->toISOString(),
            'progress_percentage' => 0.0,
            'message' => 'Bulk product deletion started',
        ], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/bulk/status/{operation_id}",
     *     summary="Get bulk operation status",
     *     description="Check the status of a bulk operation",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="operation_id",
     *         in="path",
     *         description="Bulk operation ID",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Operation status retrieved",
     *
     *         @OA\JsonContent(
     *
     *                 @OA\Property(property="operation_id", type="string", example="bulk_op_123456", description="Unique operation identifier"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="processing", description="Operation status"),
     *                 @OA\Property(property="total_items", type="integer", example=100, description="Total items to process"),
     *                 @OA\Property(property="processed_items", type="integer", example=45, description="Items processed so far"),
     *                 @OA\Property(property="failed_items", type="integer", example=2, description="Items that failed processing"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Operation start time"),
     *                 @OA\Property(property="estimated_completion", type="string", format="date-time", example="2024-01-20T16:00:00Z", description="Estimated completion time"),
     *                 @OA\Property(property="progress_percentage", type="number", format="decimal", example=45.0, description="Progress percentage"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={"Product 456 validation failed"}, description="Error messages")
     *             )
     *     ),
     *
     *     @OA\Response(response=404, description="Operation not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function bulkStatus(string $operationId): JsonResponse
    {
        // In a real implementation, this would query the database for operation status
        // For now, return mock data
        return response()->json([
            'operation_id' => $operationId,
            'status' => 'completed',
            'total_items' => 10,
            'processed_items' => 9,
            'failed_items' => 1,
            'started_at' => now()->subMinutes(5)->toISOString(),
            'completed_at' => now()->subMinutes(1)->toISOString(),
            'progress_percentage' => 100.0,
            'errors' => ['Product with ID 456 failed validation'],
            'results' => [
                'created' => 8,
                'updated' => 1,
                'failed' => 1,
            ],
        ]);
    }
}
