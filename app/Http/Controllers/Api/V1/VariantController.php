<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Variant;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Variants",
 *     description="Product variant management endpoints"
 * )
 */
class VariantController extends Controller
{
    public function __construct(private ShopifyService $shopifyService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/variants",
     *     summary="Get paginated list of variants",
     *     description="Retrieve variants with optional filtering and search",
     *     tags={"Variants"},
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
     *         name="product_id",
     *         in="query",
     *         description="Filter by product ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search variants by SKU or title",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *
     *         @OA\Schema(type="number", format="decimal")
     *     ),
     *
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *
     *         @OA\Schema(type="number", format="decimal")
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
     *                 @OA\Items(ref="#/components/schemas/Variant")
     *             ),
     *
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Variant::with('product');

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Search by SKU or title
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('option1', 'like', '%'.$search.'%')
                    ->orWhere('option2', 'like', '%'.$search.'%');
            });
        }

        // Price filtering
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/variants/{variant}",
     *     summary="Get a specific variant",
     *     description="Retrieve a single variant by ID",
     *     tags={"Variants"},
     *
     *     @OA\Parameter(
     *         name="variant",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             allOf={
     *
     *                 @OA\Schema(ref="#/components/schemas/Variant"),
     *                 @OA\Schema(
     *
     *                     @OA\Property(property="product", ref="#/components/schemas/Product")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Variant not found")
     * )
     */
    public function show(Variant $variant): JsonResponse
    {
        return response()->json($variant->load('product'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products/{product}/variants",
     *     summary="Create a new variant",
     *     description="Create a new variant for a product and sync to Shopify",
     *     tags={"Variants"},
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
     *             @OA\Property(property="option1", type="string", nullable=true, example="Medium", description="First variant option"),
     *             @OA\Property(property="option2", type="string", nullable=true, example="Red", description="Second variant option"),
     *             @OA\Property(property="price", type="number", format="decimal", minimum=0, example=29.99, description="Variant price (required)"),
     *             @OA\Property(property="sku", type="string", nullable=true, example="TSHIRT-M-RED", description="Stock keeping unit"),
     *             @OA\Property(property="inventory_quantity", type="integer", minimum=0, example=50, description="Available inventory quantity")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Variant created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Variant")
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal Server Error - Shopify sync failed")
     * )
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'option1' => 'nullable|string|max:255',
            'option2' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|max:255',
            'inventory_quantity' => 'nullable|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $product) {
            // Ensure product is synced to Shopify first
            if (! $product->shopify_product_id) {
                return response()->json([
                    'message' => 'Product must be synced to Shopify before creating variants',
                ], 422);
            }

            $variantData = $request->all();
            $variantData['product_id'] = $product->id;

            $variant = Variant::create($variantData);

            // Sync to Shopify
            try {
                $shopifyVariant = $this->shopifyService->syncVariant($variant);

                // Update local variant with Shopify ID
                $shopifyId = (int) substr(strrchr($shopifyVariant['id'], '/'), 1);
                $variant->update(['shopify_variant_id' => $shopifyId]);
            } catch (\Exception $e) {
                // If sync fails, transaction rolls back local changes
                throw $e;
            }

            return response()->json($variant->fresh(), 201);
        });
    }

    /**
     * @OA\Put(
     *     path="/api/v1/variants/{variant}",
     *     summary="Update a variant",
     *     description="Update an existing variant and sync changes to Shopify",
     *     tags={"Variants"},
     *
     *     @OA\Parameter(
     *         name="variant",
     *         in="path",
     *         description="Variant ID",
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
     *             @OA\Property(property="option1", type="string", nullable=true, example="Large", description="First variant option"),
     *             @OA\Property(property="option2", type="string", nullable=true, example="Blue", description="Second variant option"),
     *             @OA\Property(property="price", type="number", format="decimal", minimum=0, example=35.99, description="Variant price"),
     *             @OA\Property(property="sku", type="string", nullable=true, example="TSHIRT-L-BLU", description="Stock keeping unit"),
     *             @OA\Property(property="inventory_quantity", type="integer", minimum=0, example=75, description="Available inventory quantity")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Variant updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Variant")
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Variant not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal Server Error - Shopify sync failed")
     * )
     */
    public function update(Request $request, Variant $variant): JsonResponse
    {
        $request->validate([
            'option1' => 'sometimes|nullable|string|max:255',
            'option2' => 'sometimes|nullable|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'sku' => 'sometimes|nullable|string|max:255',
            'inventory_quantity' => 'sometimes|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $variant) {
            $variant->update($request->validated());

            // Sync to Shopify
            $this->shopifyService->syncVariant($variant);

            return response()->json($variant->fresh());
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/variants/{variant}",
     *     summary="Delete a variant",
     *     description="Delete a variant locally and from Shopify if it exists",
     *     tags={"Variants"},
     *
     *     @OA\Parameter(
     *         name="variant",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Variant deleted successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Variant not found"),
     *     @OA\Response(response=500, description="Internal Server Error - Shopify deletion failed")
     * )
     */
    public function destroy(Variant $variant): JsonResponse
    {
        return DB::transaction(function () use ($variant) {
            // In a real implementation, you would delete from Shopify first
            // ShopifyService::deleteVariant($variant->shopify_variant_id);

            $variant->delete();

            return response()->json(null, 204);
        });
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/variants/{variant}/inventory",
     *     summary="Update variant inventory",
     *     description="Quick inventory update for a variant",
     *     tags={"Variants"},
     *
     *     @OA\Parameter(
     *         name="variant",
     *         in="path",
     *         description="Variant ID",
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
     *             @OA\Property(property="inventory_quantity", type="integer", minimum=0, example=100, description="New inventory quantity"),
     *             @OA\Property(property="operation", type="string", enum={"set", "add", "subtract"}, example="set", description="Inventory operation type"),
     *             @OA\Property(property="sync_to_shopify", type="boolean", example=true, description="Sync inventory to Shopify")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Inventory updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Inventory updated successfully"),
     *             @OA\Property(property="old_quantity", type="integer", example=50),
     *             @OA\Property(property="new_quantity", type="integer", example=100),
     *             @OA\Property(property="change", type="integer", example=50)
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Variant not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateInventory(Request $request, Variant $variant): JsonResponse
    {
        $request->validate([
            'inventory_quantity' => 'required|integer|min:0',
            'operation' => 'required|in:set,add,subtract',
            'sync_to_shopify' => 'nullable|boolean',
        ]);

        $oldQuantity = $variant->inventory_quantity;
        $newQuantity = $oldQuantity;
        $change = 0;

        switch ($request->operation) {
            case 'set':
                $newQuantity = $request->inventory_quantity;
                $change = $newQuantity - $oldQuantity;
                break;
            case 'add':
                $newQuantity = $oldQuantity + $request->inventory_quantity;
                $change = $request->inventory_quantity;
                break;
            case 'subtract':
                $newQuantity = max(0, $oldQuantity - $request->inventory_quantity);
                $change = -$request->inventory_quantity;
                break;
        }

        $variant->update(['inventory_quantity' => $newQuantity]);

        // Sync to Shopify if requested
        if ($request->boolean('sync_to_shopify', true)) {
            try {
                $this->shopifyService->syncVariant($variant);
            } catch (\Exception $e) {
                // Log error but don't fail the request
                \Log::warning('Failed to sync inventory to Shopify', [
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Inventory updated successfully',
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'change' => $change,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{product}/variants",
     *     summary="Get variants for a product",
     *     description="Retrieve all variants for a specific product",
     *     tags={"Variants"},
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
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/Variant")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function forProduct(Product $product): JsonResponse
    {
        return response()->json($product->variants);
    }
}
