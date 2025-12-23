<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Laravel Shopify API",
 *     description="RESTful API for managing Shopify products and variants with GraphQL integration",
 *
 *     @OA\Contact(
 *         email="dev@example.com",
 *         name="API Support"
 *     ),
 *
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Development API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Laravel Sanctum Bearer Token authentication. Use the token returned from /api/v1/auth/login endpoint in the format: Bearer {your_token}"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and token management endpoints"
 * )
 * @OA\Tag(
 *     name="Products",
 *     description="Product management endpoints with Shopify integration"
 * )
 * @OA\Tag(
 *     name="Shopify",
 *     description="Shopify-specific operations and synchronization"
 * )
 * @OA\Tag(
 *     name="Health",
 *     description="Health check and system status endpoints"
 * )
 * @OA\Tag(
 *     name="Variants",
 *     description="Product variant management endpoints"
 * )
 * @OA\Tag(
 *     name="Search",
 *     description="Advanced search and filtering endpoints"
 * )
 *
 * @OA\Schema(
 *     schema="Product",
 *     title="Product",
 *     description="Product model with basic information",
 *
 *     @OA\Property(property="id", type="integer", example=1, description="Local product ID"),
 *     @OA\Property(property="shopify_product_id", type="integer", nullable=true, example=987654321, description="Shopify product ID"),
 *     @OA\Property(property="title", type="string", example="Premium T-Shirt", description="Product title"),
 *     @OA\Property(property="body_html", type="string", nullable=true, example="<p>High quality cotton t-shirt</p>", description="Product description in HTML"),
 *     @OA\Property(property="vendor", type="string", nullable=true, example="BrandName", description="Product vendor"),
 *     @OA\Property(property="product_type", type="string", nullable=true, example="Clothing", description="Product type/category"),
 *     @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, example="active", description="Product status"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Last update timestamp"),
 * )
 *
 * @OA\Schema(
 *     schema="Variant",
 *     title="Product Variant",
 *     description="Product variant with pricing and inventory",
 *
 *     @OA\Property(property="id", type="integer", example=1, description="Local variant ID"),
 *     @OA\Property(property="product_id", type="integer", example=1, description="Parent product ID"),
 *     @OA\Property(property="shopify_variant_id", type="integer", nullable=true, example=987654322, description="Shopify variant ID"),
 *     @OA\Property(property="option1", type="string", nullable=true, example="Medium", description="First variant option"),
 *     @OA\Property(property="option2", type="string", nullable=true, example="Red", description="Second variant option"),
 *     @OA\Property(property="price", type="number", format="decimal", example=29.99, description="Variant price"),
 *     @OA\Property(property="sku", type="string", nullable=true, example="TSHIRT-M-RED", description="Stock keeping unit"),
 *     @OA\Property(property="inventory_quantity", type="integer", example=50, description="Available inventory quantity"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Last update timestamp"),
 * )
 *
 * @OA\Schema(
 *     schema="ProductImage",
 *     title="Product Image",
 *     description="Product image with Shopify integration",
 *
 *     @OA\Property(property="id", type="integer", example=1, description="Local image ID"),
 *     @OA\Property(property="product_id", type="integer", example=1, description="Parent product ID"),
 *     @OA\Property(property="shopify_image_id", type="integer", nullable=true, example=987654323, description="Shopify image ID"),
 *     @OA\Property(property="path", type="string", example="products/tshirt-medium-red.jpg", description="Local file path"),
 *     @OA\Property(property="url", type="string", format="uri", example="https://example.com/storage/products/tshirt-medium-red.jpg", description="Full image URL"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Last update timestamp"),
 * )
 *
 * @OA\Schema(
 *     schema="StoreProductRequest",
 *     title="Store Product Request",
 *     description="Request schema for creating a new product",
 *
 *     @OA\Property(property="title", type="string", maxLength=255, example="Premium T-Shirt", description="Product title (required)"),
 *     @OA\Property(property="body_html", type="string", nullable=true, example="<p>High quality cotton t-shirt with premium print</p>", description="Product description in HTML"),
 *     @OA\Property(property="vendor", type="string", maxLength=255, nullable=true, example="BrandName", description="Product vendor"),
 *     @OA\Property(property="product_type", type="string", maxLength=255, nullable=true, example="Clothing", description="Product type/category"),
 *     @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, example="active", description="Product status (required)"),
 *     @OA\Property(
 *         property="variants",
 *         type="array",
 *         nullable=true,
 *
 *         @OA\Items(
 *
 *             @OA\Property(property="option1", type="string", nullable=true, example="Medium"),
 *             @OA\Property(property="option2", type="string", nullable=true, example="Red"),
 *             @OA\Property(property="price", type="number", format="decimal", minimum=0, example=29.99),
 *             @OA\Property(property="sku", type="string", nullable=true, example="TSHIRT-M-RED"),
 *             @OA\Property(property="inventory_quantity", type="integer", minimum=0, example=50)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UpdateProductRequest",
 *     title="Update Product Request",
 *     description="Request schema for updating an existing product",
 *
 *     @OA\Property(property="title", type="string", maxLength=255, nullable=true, example="Premium T-Shirt v2"),
 *     @OA\Property(property="body_html", type="string", nullable=true, example="<p>Updated product description</p>"),
 *     @OA\Property(property="vendor", type="string", maxLength=255, nullable=true, example="UpdatedBrandName"),
 *     @OA\Property(property="product_type", type="string", maxLength=255, nullable=true, example="UpdatedClothing"),
 *     @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, nullable=true, example="draft")
 * )
 *
 * @OA\Schema(
 *     schema="BulkOperationResponse",
 *     title="Bulk Operation Response",
 *     description="Response schema for bulk operations",
 *
 *     @OA\Property(property="operation_id", type="string", example="bulk_op_123456", description="Unique operation identifier"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="processing", description="Operation status"),
 *     @OA\Property(property="total_items", type="integer", example=100, description="Total items to process"),
 *     @OA\Property(property="processed_items", type="integer", example=45, description="Items processed so far"),
 *     @OA\Property(property="failed_items", type="integer", example=2, description="Items that failed processing"),
 *     @OA\Property(property="started_at", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Operation start time"),
 *     @OA\Property(property="estimated_completion", type="string", format="date-time", example="2024-01-20T16:00:00Z", description="Estimated completion time"),
 *     @OA\Property(property="progress_percentage", type="number", format="decimal", example=45.0, description="Progress percentage"),
 *     @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={"Product 456 validation failed"}, description="Error messages")
 * )
 *
 * @OA\Schema(
 *     schema="SyncStatusResponse",
 *     title="Sync Status Response",
 *     description="Response schema for Shopify sync status",
 *
 *     @OA\Property(property="shopify_connected", type="boolean", example=true, description="Whether Shopify API is connected"),
 *     @OA\Property(property="total_products", type="integer", example=100, description="Total products in database"),
 *     @OA\Property(property="synced_products", type="integer", example=95, description="Products synced with Shopify"),
 *     @OA\Property(property="total_variants", type="integer", example=250, description="Total variants in database"),
 *     @OA\Property(property="synced_variants", type="integer", example=240, description="Variants synced with Shopify"),
 *     @OA\Property(property="last_sync", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Last successful sync timestamp"),
 *     @OA\Property(property="pending_syncs", type="integer", example=5, description="Products pending sync"),
 *     @OA\Property(property="sync_conflicts", type="array", @OA\Items(type="string"), example={"Product 123 has conflicting data"}, description="Sync conflict messages")
 * )
 *
 * @OA\Schema(
 *     schema="HealthCheckResponse",
 *     title="Health Check Response",
 *     description="Response schema for health check endpoints",
 *
 *     @OA\Property(property="status", type="string", enum={"healthy", "unhealthy", "degraded"}, example="healthy", description="Overall system status"),
 *     @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-20T15:45:00Z", description="Health check timestamp"),
 *     @OA\Property(property="version", type="string", example="1.0.0", description="API version"),
 *     @OA\Property(property="uptime", type="string", example="5 days, 12:34:56", description="System uptime"),
 *     @OA\Property(property="database", type="object",
 *         @OA\Property(property="status", type="string", example="connected"),
 *         @OA\Property(property="connections", type="integer", example=5),
 *         @OA\Property(property="latency_ms", type="number", example=2.5)
 *     ),
 *     @OA\Property(property="shopify", type="object",
 *         @OA\Property(property="status", type="string", example="connected"),
 *         @OA\Property(property="api_calls_remaining", type="integer", example=1800),
 *         @OA\Property(property="api_calls_limit", type="integer", example=2000),
 *         @OA\Property(property="latency_ms", type="number", example=150)
 *     ),
 *     @OA\Property(property="storage", type="object",
 *         @OA\Property(property="status", type="string", example="available"),
 *         @OA\Property(property="free_space_mb", type="integer", example=10240)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ProductWithRelations",
 *     title="Product with Relations",
 *     description="Product model with variants and images loaded",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/Product"),
 *         @OA\Schema(
 *
 *             @OA\Property(
 *                 property="variants",
 *                 type="array",
 *
 *                 @OA\Items(ref="#/components/schemas/Variant")
 *             ),
 *
 *             @OA\Property(
 *                 property="images",
 *                 type="array",
 *
 *                 @OA\Items(ref="#/components/schemas/ProductImage")
 *             )
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="SearchSuggestion",
 *     title="Search Suggestion",
 *     description="Search suggestion result",
 *
 *     @OA\Property(property="id", type="integer", example=1, description="Product ID"),
 *     @OA\Property(property="title", type="string", example="Premium T-Shirt", description="Product title"),
 *     @OA\Property(property="sku", type="string", nullable=true, example="TSHIRT-M-RED", description="First matching SKU"),
 *     @OA\Property(property="vendor", type="string", nullable=true, example="BrandName", description="Product vendor"),
 *     @OA\Property(property="relevance_score", type="number", format="decimal", example=0.95, description="Search relevance score")
 * )
 *
 * @OA\Schema(
 *     schema="FilterOption",
 *     title="Filter Option",
 *     description="Available filter option with count",
 *
 *     @OA\Property(property="value", type="string", example="active", description="Filter value"),
 *     @OA\Property(property="label", type="string", example="Active", description="Human-readable label"),
 *     @OA\Property(property="count", type="integer", example=45, description="Number of items matching this filter")
 * )
 *
 * @OA\Schema(
 *     schema="SearchFiltersResponse",
 *     title="Search Filters Response",
 *     description="Available search filters with counts",
 *
 *     @OA\Property(property="status", type="array", @OA\Items(ref="#/components/schemas/FilterOption"), description="Status filters"),
 *     @OA\Property(property="vendor", type="array", @OA\Items(ref="#/components/schemas/FilterOption"), description="Vendor filters"),
 *     @OA\Property(property="product_type", type="array", @OA\Items(ref="#/components/schemas/FilterOption"), description="Product type filters"),
 *     @OA\Property(property="price_ranges", type="array", @OA\Items(ref="#/components/schemas/FilterOption"), description="Price range filters")
 * )
 */
class SwaggerController
{
    // This controller is used only for Swagger annotations
}
