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
     * Perform a REST API request to Shopify.
     *
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE)
     * @param  string  $endpoint  REST endpoint path
     * @param  array  $data  Optional data for POST/PUT
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    protected function restRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $apiVersion = config('services.shopify.api_version', env('SHOPIFY_API_VERSION', '2025-10'));
        $url = rtrim($this->storeUrl, '/').'/admin/api/'.$apiVersion.'/'.$endpoint;

        $options = [
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ];

        if ($method === 'GET') {
            $response = Http::withHeaders($options)->get($url);
        } elseif ($method === 'POST') {
            $response = Http::withHeaders($options)->post($url, $data ?? []);
        } elseif ($method === 'PUT') {
            $response = Http::withHeaders($options)->put($url, $data ?? []);
        } elseif ($method === 'DELETE') {
            $response = Http::withHeaders($options)->delete($url);
        } else {
            throw new \Exception("Unsupported HTTP method: {$method}");
        }

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    /**
     * Create a variant in Shopify using REST API.
     */
    public function createVariantViaRest(int $productId, array $variantData): array
    {
        $endpoint = "products/{$productId}/variants.json";

        $data = [
            'variant' => [
                'option1' => $variantData['option1'] ?? null,
                'option2' => $variantData['option2'] ?? null,
                'option3' => $variantData['option3'] ?? null,
                'price' => (string) $variantData['price'],
                'sku' => $variantData['sku'] ?? null,
                'inventory_quantity' => $variantData['inventory_quantity'] ?? 0,
                'taxable' => $variantData['taxable'] ?? true,
                'barcode' => $variantData['barcode'] ?? null,
                'weight' => $variantData['weight'] ?? 0,
                'weight_unit' => $variantData['weight_unit'] ?? 'kg',
            ],
        ];

        return $this->restRequest('POST', $endpoint, $data);
    }

    /**
     * Update a variant in Shopify using REST API.
     */
    public function updateVariantViaRest(int $variantId, array $variantData): array
    {
        $endpoint = "variants/{$variantId}.json";

        $data = [
            'variant' => [
                'id' => $variantId,
                'option1' => $variantData['option1'] ?? null,
                'option2' => $variantData['option2'] ?? null,
                'option3' => $variantData['option3'] ?? null,
                'price' => (string) $variantData['price'],
                'sku' => $variantData['sku'] ?? null,
                'inventory_quantity' => $variantData['inventory_quantity'] ?? 0,
                'taxable' => $variantData['taxable'] ?? true,
                'barcode' => $variantData['barcode'] ?? null,
                'weight' => $variantData['weight'] ?? 0,
                'weight_unit' => $variantData['weight_unit'] ?? 'kg',
            ],
        ];

        return $this->restRequest('PUT', $endpoint, $data);
    }

    /**
     * Delete a variant in Shopify using REST API.
     */
    public function deleteVariantViaRest(int $variantId): array
    {
        return $this->restRequest('DELETE', "variants/{$variantId}.json");
    }

    /**
     * Get a variant from Shopify using REST API.
     */
    public function getVariantViaRest(int $variantId): array
    {
        return $this->restRequest('GET', "variants/{$variantId}.json");
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
                        variants(first: 1) {
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

        $response = $this->query($query, ['input' => $input]);

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

        $shopifyProduct = $response['data']['productCreate']['product'];

        // Handle variants - update default variant price
        if ($product->variants->isNotEmpty()) {
            $shopifyProduct = $this->handleProductVariants($product, $shopifyProduct);
        }

        return $shopifyProduct;
    }

    /**
     * Create product options in Shopify.
     *
     * @param  int  $productId  Shopify product ID
     * @param  array  $options  Array of options with name and values
     * @param  string  $variantStrategy  Strategy for variant creation: "CREATE" or "LEAVE_AS_IS"
     * @return array Created options with their IDs
     */
    public function createProductOptions(int $productId, array $options, string $variantStrategy = 'LEAVE_AS_IS'): array
    {
        $query = '
            mutation productOptionsCreate($productId: ID!, $options: [OptionCreateInput!]!, $variantStrategy: ProductOptionCreateVariantStrategy) {
                productOptionsCreate(productId: $productId, options: $options, variantStrategy: $variantStrategy) {
                    product {
                        id
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    price
                                    inventoryQuantity
                                    selectedOptions {
                                        name
                                        value
                                    }
                                }
                            }
                        }
                        options {
                            id
                            name
                            values
                            position
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = [];
        foreach ($options as $index => $option) {
            // Values should be objects with name property, not plain strings
            $values = [];
            foreach ($option['values'] as $value) {
                $values[] = ['name' => $value];
            }
            $input[] = [
                'name' => $option['name'],
                'values' => $values,
                'position' => $index + 1,
            ];
        }

        $response = $this->query($query, [
            'productId' => $this->createGid('Product', $productId),
            'options' => $input,
            'variantStrategy' => $variantStrategy,
        ]);

        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errors = $response['errors'];
            throw new \Exception('GraphQL Error: '.implode(', ', array_column($errors, 'message')));
        }

        if (isset($response['data']['productOptionsCreate']['userErrors']) && ! empty($response['data']['productOptionsCreate']['userErrors'])) {
            $errors = $response['data']['productOptionsCreate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        $product = $response['data']['productOptionsCreate']['product'];

        return [
            'options' => $product['options'] ?? [],
            'variants' => collect($product['variants']['edges'])->pluck('node')->values()->all(),
        ];
    }

    /**
     * Get option values for a product from Shopify.
     *
     * @param  int  $productId  Shopify product ID
     * @return array Options with their IDs and values
     */
    public function getProductOptions(int $productId): array
    {
        $query = '
            query getProductOptions($id: ID!) {
                product(id: $id) {
                    id
                    options {
                        id
                        name
                        values
                        position
                    }
                }
            }
        ';

        $response = $this->query($query, ['id' => $this->createGid('Product', $productId)]);

        return $response['data']['product']['options'] ?? [];
    }

    /**
     * Create multiple variants with proper option values.
     *
     * @param  int  $productId  Shopify product ID
     * @param  array  $variants  Array of variant data with option values
     * @return array Created variants
     */
    public function createVariantsBulk(int $productId, array $variants): array
    {
        $query = '
            mutation productVariantsBulkCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkCreate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        title
                        sku
                        price
                        inventoryQuantity
                        selectedOptions {
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

        $variantsInput = [];
        foreach ($variants as $variant) {
            $variantInput = [
                'price' => (string) $variant['price'],
                'sku' => $variant['sku'] ?? null,
                'inventoryQuantity' => $variant['inventory_quantity'] ?? 0,
                'taxable' => $variant['taxable'] ?? true,
            ];

            // Add option values
            if (isset($variant['option1'])) {
                $variantInput['option1'] = $variant['option1'];
            }
            if (isset($variant['option2'])) {
                $variantInput['option2'] = $variant['option2'];
            }
            if (isset($variant['option3'])) {
                $variantInput['option3'] = $variant['option3'];
            }

            $variantsInput[] = $variantInput;
        }

        $response = $this->query($query, [
            'productId' => $this->createGid('Product', $productId),
            'variants' => $variantsInput,
        ]);

        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errors = $response['errors'];
            throw new \Exception('GraphQL Error: '.implode(', ', array_column($errors, 'message')));
        }

        if (isset($response['data']['productVariantsBulkCreate']['userErrors']) && ! empty($response['data']['productVariantsBulkCreate']['userErrors'])) {
            $errors = $response['data']['productVariantsBulkCreate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        return $response['data']['productVariantsBulkCreate']['productVariants'] ?? [];
    }

    /**
     * Create multiple variants with optionValues format (for new product model).
     *
     * @param  int  $productId  Shopify product ID
     * @param  array  $variants  Array of variant data with optionValues
     * @return array Created variants
     */
    public function createVariantsBulkWithOptionValues(int $productId, array $variants): array
    {
        $query = '
            mutation productVariantsBulkCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!, $strategy: ProductVariantsBulkCreateStrategy) {
                productVariantsBulkCreate(productId: $productId, variants: $variants, strategy: $strategy) {
                    productVariants {
                        id
                        title
                        sku
                        price
                        inventoryQuantity
                        selectedOptions {
                            name
                            value
                        }
                    }
                    userErrors {
                        field
                        message
                        code
                    }
                }
            }
        ';

        $response = $this->query($query, [
            'productId' => $this->createGid('Product', $productId),
            'variants' => $variants,
            'strategy' => 'REMOVE_STANDALONE_VARIANT',
        ]);

        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errors = $response['errors'];
            throw new \Exception('GraphQL Error: '.implode(', ', array_column($errors, 'message')));
        }

        if (isset($response['data']['productVariantsBulkCreate']['userErrors']) && ! empty($response['data']['productVariantsBulkCreate']['userErrors'])) {
            $errors = $response['data']['productVariantsBulkCreate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        return $response['data']['productVariantsBulkCreate']['productVariants'] ?? [];
    }

    /**
     * Handle product variants with full support for multiple variants.
     * This method creates options first with CREATE strategy, which auto-generates all variants.
     * Then updates the created variants with price and SKU.
     */
    protected function handleProductVariants(Product $product, array $shopifyProduct): array
    {
        $shopifyProductId = $this->extractIdFromGid($shopifyProduct['id']);
        $defaultVariantGid = $shopifyProduct['variants']['edges'][0]['node']['id'] ?? null;
        $defaultVariantId = $defaultVariantGid ? $this->extractIdFromGid($defaultVariantGid) : null;

        if ($product->variants->isEmpty() || ! $defaultVariantId) {
            return $shopifyProduct;
        }

        $variants = $product->variants;
        $variantCount = $variants->count();

        \Illuminate\Support\Facades\Log::debug('handleProductVariants: Start', [
            'product_id' => $product->id,
            'shopify_product_id' => $shopifyProductId,
            'default_variant_id' => $defaultVariantId,
            'variant_count' => $variantCount,
        ]);

        if ($variantCount === 1) {
            \Illuminate\Support\Facades\Log::debug('handleProductVariants: Single variant case');
            $firstVariant = $variants->first();
            $this->updateVariantViaRest($defaultVariantId, [
                'price' => $firstVariant->price,
                'sku' => $firstVariant->sku,
                'inventory_quantity' => $firstVariant->inventory_quantity,
            ]);

            return $this->fetchProductWithVariants($shopifyProduct['id']);
        }

        $options = $this->extractOptionsFromVariants($variants);

        \Illuminate\Support\Facades\Log::debug('handleProductVariants: Extracted options', ['options' => $options]);

        $optionsInput = [];
        foreach ($options as $index => $option) {
            $optionsInput[] = [
                'name' => $option['name'],
                'position' => $index + 1,
                'values' => $option['values'],
            ];
        }

        try {
            $result = $this->createProductOptions($shopifyProductId, $optionsInput, 'CREATE');
            $createdVariants = $result['variants'];

            \Illuminate\Support\Facades\Log::debug('handleProductVariants: Options and variants created', [
                'options_count' => count($result['options']),
                'variants_count' => count($createdVariants),
            ]);

            if (empty($createdVariants)) {
                throw new \Exception('No variants were created by the CREATE strategy');
            }

            $variantMap = $this->mapLocalVariantsToShopifyVariants($variants, $createdVariants);

            foreach ($variantMap as $shopifyVariantGid => $localVariant) {
                $variantId = $this->extractIdFromGid($shopifyVariantGid);
                $this->updateVariantViaRest($variantId, [
                    'price' => $localVariant->price,
                    'sku' => $localVariant->sku,
                    'inventory_quantity' => $localVariant->inventory_quantity ?? 0,
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('handleProductVariants: Option/variant creation failed', ['error' => $e->getMessage()]);

            $firstVariant = $variants->first();
            $this->updateVariantViaRest($defaultVariantId, [
                'price' => $firstVariant->price,
                'sku' => $firstVariant->sku,
                'inventory_quantity' => $firstVariant->inventory_quantity,
            ]);
        }

        return $this->fetchProductWithVariants($shopifyProduct['id']);
    }

    /**
     * Extract options from variants collection.
     * Groups unique option values by option name (Option1, Option2, Option3).
     *
     * @return array Array of options with name and values
     */
    protected function extractOptionsFromVariants($variants): array
    {
        $options = [];

        foreach (['option1', 'option2', 'option3'] as $optionKey) {
            $values = $variants->pluck($optionKey)->filter()->unique()->values()->all();

            if (! empty($values)) {
                // Try to generate a meaningful option name
                $optionName = $this->generateOptionName($optionKey, $values);

                $options[] = [
                    'name' => $optionName,
                    'values' => $values,
                ];
            }
        }

        return $options;
    }

    /**
     * Generate a meaningful option name based on the values.
     */
    protected function generateOptionName(string $optionKey, array $values): string
    {
        // Check if values look like sizes
        $sizeValues = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Small', 'Medium', 'Large', 'x-small', 'small', 'medium', 'large', 'x-large'];
        $intersectSizes = array_intersect(array_map('ucfirst', $values), $sizeValues);
        if (! empty($intersectSizes)) {
            return 'Size';
        }

        // Check if values look like colors
        $colorValues = ['red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'black', 'white', 'gray', 'grey', 'brown', 'navy'];
        $intersectColors = array_intersect(array_map('strtolower', $values), $colorValues);
        if (! empty($intersectColors)) {
            return 'Color';
        }

        // Check if values look like materials
        $materialValues = ['cotton', 'polyester', 'silk', 'wool', 'leather', 'denim', 'linen', 'velvet'];
        $intersectMaterials = array_intersect(array_map('strtolower', $values), $materialValues);
        if (! empty($intersectMaterials)) {
            return 'Material';
        }

        // Default naming based on option key
        return match ($optionKey) {
            'option1' => 'Option 1',
            'option2' => 'Option 2',
            'option3' => 'Option 3',
            default => 'Option',
        };
    }

    /**
     * Map local variants to created Shopify variants based on option values.
     * Returns an array mapping Shopify variant GID to local variant.
     */
    protected function mapLocalVariantsToShopifyVariants($localVariants, array $shopifyVariants): array
    {
        $map = [];

        foreach ($shopifyVariants as $shopifyVariant) {
            $selectedOptions = collect($shopifyVariant['selectedOptions'] ?? []);

            foreach ($localVariants as $localVariant) {
                $localOption1 = $localVariant->option1;
                $localOption2 = $localVariant->option2;
                $localOption3 = $localVariant->option3;

                $matches = true;

                if ($localOption1) {
                    $hasOption1 = $selectedOptions->contains(function ($opt) use ($localOption1) {
                        return $opt['name'] !== 'Title' && strtolower($opt['value']) === strtolower($localOption1);
                    });
                    if (! $hasOption1) {
                        $matches = false;
                    }
                }

                if ($matches && $localOption2) {
                    $hasOption2 = $selectedOptions->contains(function ($opt) use ($localOption2) {
                        return $opt['name'] !== 'Title' && strtolower($opt['value']) === strtolower($localOption2);
                    });
                    if (! $hasOption2) {
                        $matches = false;
                    }
                }

                if ($matches && $localOption3) {
                    $hasOption3 = $selectedOptions->contains(function ($opt) use ($localOption3) {
                        return $opt['name'] !== 'Title' && strtolower($opt['value']) === strtolower($localOption3);
                    });
                    if (! $hasOption3) {
                        $matches = false;
                    }
                }

                if ($matches) {
                    $map[$shopifyVariant['id']] = $localVariant;
                    break;
                }
            }
        }

        if (empty($map)) {
            \Illuminate\Support\Facades\Log::warning('mapLocalVariantsToShopifyVariants: No matches found, falling back to index matching');
            foreach ($shopifyVariants as $index => $shopifyVariant) {
                $localVariant = $localVariants->get($index);
                if ($localVariant) {
                    $map[$shopifyVariant['id']] = $localVariant;
                }
            }
        }

        return $map;
    }

    /**
     * Update variant price using bulk update mutation.
     */
    protected function updateVariantPrice(string $variantGid, string $price, string $shopifyProductGid): array
    {
        $query = '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
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

        $variants = [
            [
                'id' => $variantGid,
                'price' => $price,
            ],
        ];

        $response = $this->query($query, [
            'productId' => $shopifyProductGid,
            'variants' => $variants,
        ]);

        if (isset($response['errors']) && ! empty($response['errors'])) {
            $errors = $response['errors'];
            throw new \Exception('GraphQL Error: '.implode(', ', array_column($errors, 'message')));
        }

        if (isset($response['data']['productVariantsBulkUpdate']['userErrors']) && ! empty($response['data']['productVariantsBulkUpdate']['userErrors'])) {
            $errors = $response['data']['productVariantsBulkUpdate']['userErrors'];
            throw new \Exception('Shopify API Error: '.implode(', ', array_column($errors, 'message')));
        }

        return $response['data']['productVariantsBulkUpdate']['productVariants'][0] ?? [];
    }

    /**
     * Fetch product from Shopify with variants.
     */
    protected function fetchProductWithVariants(string $shopifyProductGid): array
    {
        $query = '
            query getProduct($id: ID!) {
                product(id: $id) {
                    id
                    title
                    descriptionHtml
                    vendor
                    productType
                    status
                    variants(first: 50) {
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
            }
        ';

        $response = $this->query($query, ['id' => $shopifyProductGid]);

        return $response['data']['product'] ?? [];
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
