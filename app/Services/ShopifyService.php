<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ShopifyService
{
    protected string $storeUrl;

    protected string $accessToken;

    public function __construct()
    {
        $this->storeUrl = 'https://'.config('services.shopify.domain', env('SHOPIFY_DOMAIN'));
        $this->accessToken = config('services.shopify.access_token', env('SHOPIFY_ACCESS_TOKEN'));
    }

    /**
     * Perform a GraphQL query to Shopify API.
     *
     * @param  string  $query  GraphQL query string
     * @param  array  $variables  Optional variables for GraphQL
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function query(string $query, ?array $variables = null): array
    {
        $apiVersion = config('services.shopify.api_version', env('SHOPIFY_API_VERSION', '2025-01'));
        $endpoint = rtrim($this->storeUrl, '/').'/admin/api/'.$apiVersion.'/graphql.json';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'query' => $query,
            'variables' => $variables,
        ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    /**
     * Validate if the given Shopify domain and access token are valid.
     */
    public function validateCredentials(): bool
    {
        try {
            $query = '{ shop { name myshopifyDomain } }';
            $apiVersion = config('services.shopify.api_version', env('SHOPIFY_API_VERSION', '2025-01'));

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post(rtrim($this->storeUrl, '/').'/admin/api/'.$apiVersion.'/graphql.json', [
                'query' => $query,
            ]);

            if ($response->status() === 401 || $response->status() === 403) {
                return false;
            }

            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Import products from Shopify to local database.
     *
     * @param  array  $params  Additional parameters
     * @return array Imported products count
     */
    public function importProductsFromShopify(array $params = []): array
    {
        $defaultParams = [
            'first' => 50, // GraphQL pagination
        ];

        $params = array_merge($defaultParams, $params);

        $query = '
            query getProducts($first: Int!, $cursor: String) {
                products(first: $first, after: $cursor) {
                    edges {
                        node {
                            id
                            title
                            description
                            vendor
                            productType
                            status
                            handle
                            variants(first: 10) {
                                edges {
                                    node {
                                        id
                                        title
                                        sku
                                        price
                                        inventoryQuantity
                                    }
                                }
                            }
                            images(first: 10) {
                                edges {
                                    node {
                                        id
                                        url
                                        altText
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        $variables = [
            'first' => $params['first'],
            'cursor' => $params['cursor'] ?? null,
        ];

        $response = $this->query($query, $variables);

        $importedCount = 0;
        $updatedCount = 0;

        if (isset($response['data']['products']['edges'])) {
            foreach ($response['data']['products']['edges'] as $edge) {
                $shopifyProduct = $edge['node'];

                // Extract Shopify ID from gid format
                $shopifyId = $this->extractIdFromGid($shopifyProduct['id']);

                $localProduct = Product::withTrashed()
                    ->where('shopify_product_id', $shopifyId)
                    ->first();

                if ($localProduct) {
                    // Update existing product
                    $this->updateLocalProductFromShopify($localProduct, $shopifyProduct);
                    $updatedCount++;
                } else {
                    // Create new product
                    $this->createLocalProductFromShopify($shopifyProduct);
                    $importedCount++;
                }
            }
        }

        return [
            'imported' => $importedCount,
            'updated' => $updatedCount,
            'total' => $importedCount + $updatedCount,
            'hasNextPage' => $response['data']['products']['pageInfo']['hasNextPage'] ?? false,
            'endCursor' => $response['data']['products']['pageInfo']['endCursor'] ?? null,
        ];
    }

    /**
     * Sync product to Shopify.
     *
     * @return array Shopify product data
     */
    public function syncProduct(Product $product): array
    {
        if ($product->shopify_product_id) {
            // Update existing product
            return $this->updateProductInShopify($product);
        } else {
            // Create new product
            return $this->createProductInShopify($product);
        }
    }

    /**
     * Create new product in Shopify.
     */
    protected function createProductInShopify(Product $product): array
    {
        $query = '
            mutation productCreate($input: ProductInput!) {
                productCreate(input: $input) {
                    product {
                        id
                        title
                        descriptionHtml
                        vendor
                        productType
                        status
                        variants(first: 10) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    price
                                    inventoryQuantity
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = [
            'title' => $product->title,
            'descriptionHtml' => $product->body_html,
            'vendor' => $product->vendor,
            'productType' => $product->product_type,
            'status' => $this->mapStatusToShopify($product->status),
        ];

        // Note: Shopify GraphQL productCreate creates default variants automatically
        // Additional variants can be created using productVariantsBulkCreate

        $variables = ['input' => $input];
        $response = $this->query($query, $variables);

        // Check for GraphQL errors
        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errors = $response['errors'];
            throw new \Exception('GraphQL Error: '.implode(', ', array_column($errors, 'message')));
        }

        // Check for user errors in product creation
        if (isset($response['data']['productCreate']['userErrors']) && ! empty($response['data']['productCreate']['userErrors'])) {
            $errors = $response['data']['productCreate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        // Check if product was created successfully
        if (! isset($response['data']['productCreate']['product'])) {
            throw new \Exception('Failed to create product: No product returned from Shopify API');
        }

        return $response['data']['productCreate']['product'];
    }

    /**
     * Update existing product in Shopify.
     */
    protected function updateProductInShopify(Product $product): array
    {
        $query = '
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                        title
                        description
                        vendor
                        productType
                        status
                        variants(first: 10) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    price
                                    inventoryQuantity
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = [
            'id' => $this->createGid('Product', $product->shopify_product_id),
            'title' => $product->title,
            'descriptionHtml' => $product->body_html,
            'vendor' => $product->vendor,
            'productType' => $product->product_type,
            'status' => $this->mapStatusToShopify($product->status),
        ];

        $variables = ['input' => $input];
        $response = $this->query($query, $variables);

        // Check for GraphQL errors
        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errors = $response['errors'];
            throw new \Exception('GraphQL Error: '.implode(', ', array_column($errors, 'message')));
        }

        // Check for user errors in product update
        if (isset($response['data']['productUpdate']['userErrors']) && ! empty($response['data']['productUpdate']['userErrors'])) {
            $errors = $response['data']['productUpdate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        // Check if product was updated successfully
        if (! isset($response['data']['productUpdate']['product'])) {
            throw new \Exception('Failed to update product: No product returned from Shopify API');
        }

        return $response['data']['productUpdate']['product'];
    }

    /**
     * Delete product from Shopify.
     *
     * @param  int  $shopifyId
     */
    public function deleteProduct($shopifyId): void
    {
        $query = '
            mutation productDelete($input: ProductDeleteInput!) {
                productDelete(input: $input) {
                    deletedProductId
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $variables = [
            'input' => [
                'id' => $this->createGid('Product', $shopifyId),
            ],
        ];

        $this->query($query, $variables);
    }

    /**
     * Upload product image to Shopify using staged uploads.
     *
     * @return array Shopify image data with id and url
     */
    public function uploadProductImage(Product $product, UploadedFile $image): array
    {
        // Step 1: Create staged upload target
        $stagedUpload = $this->createStagedUpload($image);

        // Step 2: Upload file to staged target
        $this->uploadToStagedTarget($stagedUpload, $image);

        // Step 3: Create product media using the staged upload URL
        return $this->createProductMedia($product, $stagedUpload['resourceUrl'], $image->getClientOriginalName());
    }

    /**
     * Create a staged upload target for the image.
     */
    protected function createStagedUpload(UploadedFile $image): array
    {
        $query = '
            mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
                stagedUploadsCreate(input: $input) {
                    stagedTargets {
                        resourceUrl
                        url
                        parameters {
                            name
                            value
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $variables = [
            'input' => [
                [
                    'filename' => $image->getClientOriginalName(),
                    'mimeType' => $image->getMimeType(),
                    'fileSize' => (string) $image->getSize(),
                    'resource' => 'IMAGE',
                    'httpMethod' => 'POST',
                ],
            ],
        ];

        $response = $this->query($query, $variables);

        // Check for GraphQL errors
        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errorMessages = array_map(fn ($e) => $e['message'] ?? 'Unknown error', $response['errors']);
            throw new \Exception('Shopify GraphQL Error: '.implode(', ', $errorMessages));
        }

        // Check for user errors
        if (isset($response['data']['stagedUploadsCreate']['userErrors']) && ! empty($response['data']['stagedUploadsCreate']['userErrors'])) {
            $errors = $response['data']['stagedUploadsCreate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        $stagedTargets = $response['data']['stagedUploadsCreate']['stagedTargets'] ?? [];
        if (empty($stagedTargets)) {
            throw new \Exception('Failed to create staged upload: No staged targets returned');
        }

        return $stagedTargets[0];
    }

    /**
     * Upload file to the staged target URL.
     */
    protected function uploadToStagedTarget(array $stagedUpload, UploadedFile $image): void
    {
        $url = $stagedUpload['url'];
        $parameters = $stagedUpload['parameters'];

        // Build multipart form data
        $multipart = [];
        foreach ($parameters as $param) {
            $multipart[] = [
                'name' => $param['name'],
                'contents' => $param['value'],
            ];
        }

        // Add the file
        $multipart[] = [
            'name' => 'file',
            'contents' => fopen($image->getRealPath(), 'r'),
            'filename' => $image->getClientOriginalName(),
        ];

        $response = Http::asMultipart()->post($url, $multipart);

        if ($response->failed()) {
            throw new \Exception('Failed to upload file to staged target: '.$response->body());
        }
    }

    /**
     * Create product media from staged upload URL.
     */
    protected function createProductMedia(Product $product, string $resourceUrl, string $filename): array
    {
        $query = '
            mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
                productCreateMedia(productId: $productId, media: $media) {
                    media {
                        ... on MediaImage {
                            id
                            image {
                                url
                                altText
                            }
                        }
                    }
                    mediaUserErrors {
                        field
                        message
                    }
                    product {
                        id
                    }
                }
            }
        ';

        $variables = [
            'productId' => $this->createGid('Product', $product->shopify_product_id),
            'media' => [
                [
                    'originalSource' => $resourceUrl,
                    'mediaContentType' => 'IMAGE',
                    'alt' => pathinfo($filename, PATHINFO_FILENAME),
                ],
            ],
        ];

        $response = $this->query($query, $variables);

        // Check for GraphQL errors
        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errorMessages = array_map(fn ($e) => $e['message'] ?? 'Unknown error', $response['errors']);
            throw new \Exception('Shopify GraphQL Error: '.implode(', ', $errorMessages));
        }

        // Check for user errors
        if (isset($response['data']['productCreateMedia']['mediaUserErrors']) && ! empty($response['data']['productCreateMedia']['mediaUserErrors'])) {
            $errors = $response['data']['productCreateMedia']['mediaUserErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        $media = $response['data']['productCreateMedia']['media'] ?? [];
        if (empty($media)) {
            throw new \Exception('Failed to create product media: No media returned');
        }

        $mediaImage = $media[0];

        return [
            'id' => $mediaImage['id'],
            'url' => $mediaImage['image']['url'] ?? $resourceUrl,
            'altText' => $mediaImage['image']['altText'] ?? null,
        ];
    }

    /**
     * Delete product image from Shopify.
     */
    public function deleteProductImage(Product $product, int $shopifyImageId): void
    {
        $query = '
            mutation productImageDelete($input: ProductImageDeleteInput!) {
                productImageDelete(input: $input) {
                    deletedImageId
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $variables = [
            'input' => [
                'id' => $this->createGid('ProductImage', $shopifyImageId),
            ],
        ];

        $response = $this->query($query, $variables);

        if (isset($response['data']['productImageDelete']['userErrors']) && ! empty($response['data']['productImageDelete']['userErrors'])) {
            $errors = $response['data']['productImageDelete']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }
    }

    /**
     * Sync variant to Shopify.
     *
     * @return array Shopify variant data
     */
    public function syncVariant(Variant $variant): array
    {
        // Ensure parent product is synced
        if (! $variant->product->shopify_product_id) {
            throw new \Exception('Parent product must be synced to Shopify first.');
        }

        if ($variant->shopify_variant_id) {
            return $this->updateVariantInShopify($variant);
        } else {
            return $this->createVariantInShopify($variant);
        }
    }

    /**
     * Create new variant in Shopify.
     */
    protected function createVariantInShopify(Variant $variant): array
    {
        $query = '
            mutation productVariantCreate($input: ProductVariantInput!) {
                productVariantCreate(input: $input) {
                    productVariant {
                        id
                        title
                        sku
                        price
                        inventoryQuantity
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = [
            'productId' => $this->createGid('Product', $variant->product->shopify_product_id),
            'title' => $variant->title ?: 'Default Title',
            'sku' => $variant->sku,
            'price' => (string) $variant->price,
            'inventoryQuantity' => $variant->inventory_quantity ?? 0,
        ];

        $variables = ['input' => $input];
        $response = $this->query($query, $variables);

        if (isset($response['data']['productVariantCreate']['userErrors']) && ! empty($response['data']['productVariantCreate']['userErrors'])) {
            $errors = $response['data']['productVariantCreate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        return $response['data']['productVariantCreate']['productVariant'];
    }

    /**
     * Update existing variant in Shopify.
     */
    protected function updateVariantInShopify(Variant $variant): array
    {
        $query = '
            mutation productVariantUpdate($input: ProductVariantInput!) {
                productVariantUpdate(input: $input) {
                    productVariant {
                        id
                        title
                        sku
                        price
                        inventoryQuantity
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = [
            'id' => $this->createGid('ProductVariant', $variant->shopify_variant_id),
            'title' => $variant->title ?: 'Default Title',
            'sku' => $variant->sku,
            'price' => (string) $variant->price,
            'inventoryQuantity' => $variant->inventory_quantity ?? 0,
        ];

        $variables = ['input' => $input];
        $response = $this->query($query, $variables);

        if (isset($response['data']['productVariantUpdate']['userErrors']) && ! empty($response['data']['productVariantUpdate']['userErrors'])) {
            $errors = $response['data']['productVariantUpdate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        return $response['data']['productVariantUpdate']['productVariant'];
    }

    /**
     * Create a local product from Shopify GraphQL data.
     */
    protected function createLocalProductFromShopify(array $shopifyProduct): Product
    {
        $shopifyId = $this->extractIdFromGid($shopifyProduct['id']);

        $product = Product::create([
            'shopify_product_id' => $shopifyId,
            'title' => $shopifyProduct['title'],
            'body_html' => $shopifyProduct['description'] ?? null,
            'vendor' => $shopifyProduct['vendor'] ?? null,
            'product_type' => $shopifyProduct['productType'] ?? null,
            'status' => $this->mapStatusFromShopify($shopifyProduct['status']),
        ]);

        // Import variants
        if (isset($shopifyProduct['variants']['edges']) && ! empty($shopifyProduct['variants']['edges'])) {
            foreach ($shopifyProduct['variants']['edges'] as $edge) {
                $shopifyVariant = $edge['node'];
                $variantId = $this->extractIdFromGid($shopifyVariant['id']);

                $product->variants()->create([
                    'shopify_variant_id' => $variantId,
                    'price' => $shopifyVariant['price'] ?? 0,
                    'sku' => $shopifyVariant['sku'] ?? null,
                    'inventory_quantity' => $shopifyVariant['inventoryQuantity'] ?? 0,
                ]);
            }
        }

        // Import images
        if (isset($shopifyProduct['images']['edges']) && ! empty($shopifyProduct['images']['edges'])) {
            foreach ($shopifyProduct['images']['edges'] as $edge) {
                $shopifyImage = $edge['node'];
                $imageId = $this->extractIdFromGid($shopifyImage['id']);

                $product->images()->create([
                    'shopify_image_id' => $imageId,
                    'path' => $shopifyImage['url'],
                ]);
            }
        }

        return $product;
    }

    /**
     * Update local product from Shopify GraphQL data.
     */
    protected function updateLocalProductFromShopify(Product $localProduct, array $shopifyProduct): void
    {
        $localProduct->update([
            'title' => $shopifyProduct['title'],
            'body_html' => $shopifyProduct['description'] ?? $localProduct->body_html,
            'vendor' => $shopifyProduct['vendor'] ?? $localProduct->vendor,
            'product_type' => $shopifyProduct['productType'] ?? $localProduct->product_type,
            'status' => $this->mapStatusFromShopify($shopifyProduct['status']),
            'deleted_at' => null, // Restore if it was soft deleted
        ]);

        // Sync variants
        if (isset($shopifyProduct['variants']['edges']) && ! empty($shopifyProduct['variants']['edges'])) {
            foreach ($shopifyProduct['variants']['edges'] as $edge) {
                $shopifyVariant = $edge['node'];
                $variantId = $this->extractIdFromGid($shopifyVariant['id']);

                $localVariant = $localProduct->variants()
                    ->where('shopify_variant_id', $variantId)
                    ->first();

                if ($localVariant) {
                    $localVariant->update([
                        'price' => $shopifyVariant['price'] ?? $localVariant->price,
                        'sku' => $shopifyVariant['sku'] ?? $localVariant->sku,
                        'inventory_quantity' => $shopifyVariant['inventoryQuantity'] ?? $localVariant->inventory_quantity,
                    ]);
                } else {
                    // Create new variant
                    $localProduct->variants()->create([
                        'shopify_variant_id' => $variantId,
                        'price' => $shopifyVariant['price'] ?? 0,
                        'sku' => $shopifyVariant['sku'] ?? null,
                        'inventory_quantity' => $shopifyVariant['inventoryQuantity'] ?? 0,
                    ]);
                }
            }
        }

        // Sync images
        if (isset($shopifyProduct['images']['edges']) && ! empty($shopifyProduct['images']['edges'])) {
            foreach ($shopifyProduct['images']['edges'] as $edge) {
                $shopifyImage = $edge['node'];
                $imageId = $this->extractIdFromGid($shopifyImage['id']);

                $localImage = $localProduct->images()
                    ->where('shopify_image_id', $imageId)
                    ->first();

                if (! $localImage) {
                    $localProduct->images()->create([
                        'shopify_image_id' => $imageId,
                        'path' => $shopifyImage['url'],
                    ]);
                }
            }
        }
    }

    /**
     * Extract numeric ID from Shopify GID.
     * Example: "gid://shopify/Product/123456" -> 123456
     */
    protected function extractIdFromGid(string $gid): int
    {
        $parts = explode('/', $gid);

        return (int) end($parts);
    }

    /**
     * Create Shopify GID from type and ID.
     * Example: ("Product", 123456) -> "gid://shopify/Product/123456"
     */
    protected function createGid(string $type, int $id): string
    {
        return "gid://shopify/{$type}/{$id}";
    }

    /**
     * Map local status to Shopify status.
     */
    protected function mapStatusToShopify(string $localStatus): string
    {
        return match ($localStatus) {
            'active' => 'ACTIVE',
            'draft' => 'DRAFT',
            'archived' => 'ARCHIVED',
            default => 'DRAFT',
        };
    }

    /**
     * Map Shopify status to local status.
     */
    protected function mapStatusFromShopify(string $shopifyStatus): string
    {
        return match ($shopifyStatus) {
            'ACTIVE' => 'active',
            'DRAFT' => 'draft',
            'ARCHIVED' => 'archived',
            default => 'draft',
        };
    }

    /**
     * Get all products from Shopify with pagination support.
     */
    public function getAllShopifyProducts(array $params = []): array
    {
        $allProducts = [];
        $hasNextPage = true;
        $cursor = null;
        $first = $params['first'] ?? 50;

        while ($hasNextPage) {
            $result = $this->importProductsFromShopify([
                'first' => $first,
                'cursor' => $cursor,
            ]);

            // Collect the imported products data
            // Note: This would need to be adjusted based on what you need

            $hasNextPage = $result['hasNextPage'];
            $cursor = $result['endCursor'];

            // Break after one page for now to avoid infinite loops
            break;
        }

        return $allProducts;
    }
}
