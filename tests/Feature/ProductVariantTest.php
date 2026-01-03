<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_variants_are_saved_with_correct_option_fields(): void
    {
        $user = User::factory()->create();

        $data = [
            'title' => 'Test T-Shirt',
            'status' => 'active',
            'body_html' => '<p>Test description</p>',
            'vendor' => 'TestBrand',
            'product_type' => 'Clothing',
            'variants' => [
                [
                    'option1' => 'Small',
                    'option2' => 'Red',
                    'price' => 10.00,
                    'sku' => 'TSHIRT-S-RED',
                    'inventory_quantity' => 10,
                ],
                [
                    'option1' => 'Medium',
                    'option2' => 'Red',
                    'price' => 12.00,
                    'sku' => 'TSHIRT-M-RED',
                    'inventory_quantity' => 15,
                ],
                [
                    'option1' => 'Large',
                    'option2' => 'Blue',
                    'option3' => 'Cotton',
                    'price' => 15.00,
                    'sku' => 'TSHIRT-L-BLUE-COTTON',
                    'inventory_quantity' => 20,
                ],
            ],
        ];

        // Mock ShopifyService to return a proper response
        $mockShopify = \Mockery::mock(ShopifyService::class);
        $mockShopify->shouldReceive('syncProduct')
            ->once()
            ->andReturn([
                'id' => 'gid://shopify/Product/123456789',
                'title' => 'Test T-Shirt',
                'variants' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => 'gid://shopify/ProductVariant/987654321',
                                'title' => 'Small / Red',
                                'sku' => 'TSHIRT-S-RED',
                                'price' => '10.00',
                            ],
                        ],
                        [
                            'node' => [
                                'id' => 'gid://shopify/ProductVariant/987654322',
                                'title' => 'Medium / Red',
                                'sku' => 'TSHIRT-M-RED',
                                'price' => '12.00',
                            ],
                        ],
                        [
                            'node' => [
                                'id' => 'gid://shopify/ProductVariant/987654323',
                                'title' => 'Large / Blue / Cotton',
                                'sku' => 'TSHIRT-L-BLUE-COTTON',
                                'price' => '15.00',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(ShopifyService::class, $mockShopify);

        $response = $this->actingAs($user)->postJson('/api/v1/products', $data);

        $response->assertStatus(201);

        // Verify product was created
        $this->assertDatabaseHas('products', [
            'title' => 'Test T-Shirt',
            'shopify_product_id' => 123456789,
        ]);

        // Verify variants were created with correct data
        $this->assertDatabaseHas('variants', [
            'product_id' => Product::where('title', 'Test T-Shirt')->first()->id,
            'sku' => 'TSHIRT-S-RED',
            'option1' => 'Small',
            'option2' => 'Red',
            'price' => 10.00,
            'inventory_quantity' => 10,
            'shopify_variant_id' => 987654321,
        ]);

        $this->assertDatabaseHas('variants', [
            'sku' => 'TSHIRT-M-RED',
            'option1' => 'Medium',
            'option2' => 'Red',
            'price' => 12.00,
            'inventory_quantity' => 15,
            'shopify_variant_id' => 987654322,
        ]);

        $this->assertDatabaseHas('variants', [
            'sku' => 'TSHIRT-L-BLUE-COTTON',
            'option1' => 'Large',
            'option2' => 'Blue',
            'option3' => 'Cotton',
            'price' => 15.00,
            'inventory_quantity' => 20,
            'shopify_variant_id' => 987654323,
        ]);

        // Verify total variant count
        $product = Product::where('title', 'Test T-Shirt')->first();
        $this->assertEquals(3, $product->variants->count(), 'Product should have 3 variants');

        // Print variant details for debugging
        $product->load('variants');
        foreach ($product->variants as $variant) {
            echo "Variant: {$variant->sku} - {$variant->option1}/{$variant->option2}/{$variant->option3} - \${$variant->price} (Shopify ID: {$variant->shopify_variant_id})\n";
        }
    }
}
