<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Search",
 *     description="Advanced search and filtering endpoints"
 * )
 */
class SearchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/search/products",
     *     summary="Advanced product search",
     *     description="Search products with multiple filters and sorting options",
     *     tags={"Search"},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query for title, description, vendor, SKU",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by product status",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"active", "draft", "archived"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="vendor",
     *         in="query",
     *         description="Filter by vendor",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="product_type",
     *         in="query",
     *         description="Filter by product type",
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
     *     @OA\Parameter(
     *         name="has_shopify_id",
     *         in="query",
     *         description="Filter by Shopify sync status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"title", "price", "created_at", "updated_at"}, default="created_at")
     *     ),
     *
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
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
     *         name="include",
     *         in="query",
     *         description="Include related resources",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"variants", "images", "variants,images"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/ProductWithRelations")
     *             ),
     *
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="count", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="total_pages", type="integer"),
     *                 @OA\Property(property="filters_applied", type="object"),
     *                 @OA\Property(property="search_time_ms", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function products(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $query = Product::query();

        // Text search
        if ($request->has('q') && ! empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', '%'.$searchTerm.'%')
                    ->orWhere('body_html', 'like', '%'.$searchTerm.'%')
                    ->orWhere('vendor', 'like', '%'.$searchTerm.'%')
                    ->orWhere('product_type', 'like', '%'.$searchTerm.'%')
                    ->orWhereHas('variants', function ($variantQuery) use ($searchTerm) {
                        $variantQuery->where('sku', 'like', '%'.$searchTerm.'%');
                    });
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Vendor filter
        if ($request->has('vendor')) {
            $query->where('vendor', 'like', '%'.$request->vendor.'%');
        }

        // Product type filter
        if ($request->has('product_type')) {
            $query->where('product_type', 'like', '%'.$request->product_type.'%');
        }

        // Price filtering through variants
        if ($request->has('min_price') || $request->has('max_price')) {
            $query->whereHas('variants', function ($variantQuery) use ($request) {
                if ($request->has('min_price')) {
                    $variantQuery->where('price', '>=', $request->min_price);
                }
                if ($request->has('max_price')) {
                    $variantQuery->where('price', '<=', $request->max_price);
                }
            });
        }

        // Shopify sync filter
        if ($request->has('has_shopify_id')) {
            if ($request->boolean('has_shopify_id')) {
                $query->whereNotNull('shopify_product_id');
            } else {
                $query->whereNull('shopify_product_id');
            }
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        // Validate sort field
        $allowedSorts = ['title', 'created_at', 'updated_at', 'vendor', 'product_type'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder);
        } elseif ($sortField === 'price') {
            // Price sorting requires joining with variants
            $query->withMin('variants', 'price')
                ->orderBy('variants_min_price', $sortOrder);
        }

        // Include relations
        $includes = $request->get('include');
        if ($includes) {
            $includeArray = explode(',', $includes);
            $validIncludes = ['variants', 'images'];
            $relations = array_intersect($includeArray, $validIncludes);

            if (! empty($relations)) {
                $query->with($relations);
            }
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $page = $request->get('page', 1);

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $searchTime = round((microtime(true) - $startTime) * 1000);

        // Prepare filters applied for response
        $filtersApplied = [
            'search' => $request->get('q'),
            'status' => $request->get('status'),
            'vendor' => $request->get('vendor'),
            'product_type' => $request->get('product_type'),
            'price_range' => [
                'min' => $request->get('min_price'),
                'max' => $request->get('max_price'),
            ],
            'has_shopify_id' => $request->get('has_shopify_id'),
            'sort' => $sortField.' '.$sortOrder,
        ];

        // Remove null values from filters
        $filtersApplied = array_filter($filtersApplied, function ($value) {
            return $value !== null && $value !== '' && (! is_array($value) || ! empty(array_filter($value)));
        });

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'total' => $results->total(),
                'count' => $results->count(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'total_pages' => $results->lastPage(),
                'filters_applied' => $filtersApplied,
                'search_time_ms' => $searchTime,
            ],
            'links' => [
                'first' => $results->url(1),
                'last' => $results->url($results->lastPage()),
                'prev' => $results->previousPageUrl(),
                'next' => $results->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/suggestions",
     *     summary="Get search suggestions",
     *     description="Get auto-complete suggestions for product search",
     *     tags={"Search"},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Partial search query",
     *         required=true,
     *
     *         @OA\Schema(type="string", minLength=2)
     *     ),
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type of suggestions",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"titles", "vendors", "product_types", "all"}, default="all")
     *     ),
     *
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of suggestions",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=10, maximum=50)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Search suggestions",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="query", type="string", example="t-shir"),
     *             @OA\Property(property="suggestions", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="type", type="string", example="title"),
     *                 @OA\Property(property="value", type="string", example="T-Shirt"),
     *                 @OA\Property(property="count", type="integer", example=25)
     *             ))
     *         )
     *     )
     * )
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'type' => 'nullable|in:titles,vendors,product_types,all',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->q;
        $type = $request->get('type', 'all');
        $limit = min($request->get('limit', 10), 50);
        $suggestions = [];

        // Title suggestions
        if ($type === 'all' || $type === 'titles') {
            $titleSuggestions = Product::where('title', 'like', '%'.$query.'%')
                ->select('title')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('title')
                ->orderBy('count', 'desc')
                ->limit($limit)
                ->get();

            foreach ($titleSuggestions as $suggestion) {
                $suggestions[] = [
                    'type' => 'title',
                    'value' => $suggestion->title,
                    'count' => $suggestion->count,
                ];
            }
        }

        // Vendor suggestions
        if ($type === 'all' || $type === 'vendors') {
            $vendorSuggestions = Product::where('vendor', 'like', '%'.$query.'%')
                ->whereNotNull('vendor')
                ->select('vendor')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('vendor')
                ->orderBy('count', 'desc')
                ->limit($limit)
                ->get();

            foreach ($vendorSuggestions as $suggestion) {
                $suggestions[] = [
                    'type' => 'vendor',
                    'value' => $suggestion->vendor,
                    'count' => $suggestion->count,
                ];
            }
        }

        // Product type suggestions
        if ($type === 'all' || $type === 'product_types') {
            $typeSuggestions = Product::where('product_type', 'like', '%'.$query.'%')
                ->whereNotNull('product_type')
                ->select('product_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('product_type')
                ->orderBy('count', 'desc')
                ->limit($limit)
                ->get();

            foreach ($typeSuggestions as $suggestion) {
                $suggestions[] = [
                    'type' => 'product_type',
                    'value' => $suggestion->product_type,
                    'count' => $suggestion->count,
                ];
            }
        }

        // Sort suggestions by count and limit total results
        usort($suggestions, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        $suggestions = array_slice($suggestions, 0, $limit);

        return response()->json([
            'query' => $query,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/filters",
     *     summary="Get available filters",
     *     description="Get available filter options and their counts",
     *     tags={"Search"},
     *
     *     @OA\Parameter(
     *         name="filter_type",
     *         in="query",
     *         description="Type of filters to return",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"status", "vendor", "product_type", "all"}, default="all")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Available filters",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="filters", type="object", example={
     *                 "status": {"active": 150, "draft": 25, "archived": 10},
     *                 "vendors": {"BrandA": 50, "BrandB": 30, "BrandC": 20},
     *                 "product_types": {"Clothing": 80, "Accessories": 40, "Electronics": 25}
     *             })
     *         )
     *     )
     * )
     */
    public function filters(Request $request): JsonResponse
    {
        $filterType = $request->get('filter_type', 'all');
        $filters = [];

        // Status filters
        if ($filterType === 'all' || $filterType === 'status') {
            $statusCounts = Product::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $filters['status'] = $statusCounts;
        }

        // Vendor filters
        if ($filterType === 'all' || $filterType === 'vendor') {
            $vendorCounts = Product::whereNotNull('vendor')
                ->selectRaw('vendor, COUNT(*) as count')
                ->groupBy('vendor')
                ->orderBy('count', 'desc')
                ->pluck('count', 'vendor')
                ->take(20) // Limit to top 20 vendors
                ->toArray();

            $filters['vendors'] = $vendorCounts;
        }

        // Product type filters
        if ($filterType === 'all' || $filterType === 'product_type') {
            $typeCounts = Product::whereNotNull('product_type')
                ->selectRaw('product_type, COUNT(*) as count')
                ->groupBy('product_type')
                ->orderBy('count', 'desc')
                ->pluck('count', 'product_type')
                ->take(20) // Limit to top 20 types
                ->toArray();

            $filters['product_types'] = $typeCounts;
        }

        return response()->json([
            'filters' => $filters,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/variants",
     *     summary="Advanced variant search",
     *     description="Search variants with multiple filters",
     *     tags={"Search"},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query for SKU, options",
     *         required=false,
     *
     *         @OA\Schema(type="string")
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
     *     @OA\Parameter(
     *         name="has_inventory",
     *         in="query",
     *         description="Filter by inventory availability",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
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
     *     @OA\Response(
     *         response=200,
     *         description="Variant search results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Variant")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="count", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function variants(Request $request): JsonResponse
    {
        $query = Variant::with('product');

        // Text search
        if ($request->has('q') && ! empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('sku', 'like', '%'.$searchTerm.'%')
                    ->orWhere('option1', 'like', '%'.$searchTerm.'%')
                    ->orWhere('option2', 'like', '%'.$searchTerm.'%');
            });
        }

        // Product filter
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Price filtering
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Inventory filtering
        if ($request->has('has_inventory')) {
            if ($request->boolean('has_inventory')) {
                $query->where('inventory_quantity', '>', 0);
            } else {
                $query->where('inventory_quantity', '<=', 0);
            }
        }

        // Sorting
        $sortField = $request->get('sort', 'price');
        $sortOrder = $request->get('order', 'asc');

        $allowedSorts = ['price', 'sku', 'inventory_quantity', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $page = $request->get('page', 1);

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'total' => $results->total(),
                'count' => $results->count(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
            ],
            'links' => [
                'first' => $results->url(1),
                'last' => $results->url($results->lastPage()),
                'prev' => $results->previousPageUrl(),
                'next' => $results->nextPageUrl(),
            ],
        ]);
    }
}
