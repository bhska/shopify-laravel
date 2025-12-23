<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    public function test_can_list_products()
    {
        $user = User::factory()->create();
        Product::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_product_syncs_to_shopify()
    {
        $user = User::factory()->create();

        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('syncProduct')
                ->once()
                ->andReturn([
                    'id' => 'gid://shopify/Product/123456789',
                    'title' => 'New Product',
                    'variants' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/ProductVariant/987654321',
                                    'title' => 'Default Title',
                                    'sku' => 'TEST-SKU-001',
                                    'price' => '10.00',
                                ],
                            ],
                        ],
                    ],
                ]);
        });

        $data = [
            'title' => 'New Product',
            'status' => 'draft',
            'variants' => [
                [
                    'title' => 'Default Title',
                    'sku' => 'TEST-SKU-001',
                    'price' => 10.00,
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/products', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'title' => 'New Product',
            'shopify_product_id' => 123456789,
        ]);

        $this->assertDatabaseHas('variants', [
            'shopify_variant_id' => 987654321,
        ]);
    }

    public function test_can_update_product_syncs_to_shopify()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['shopify_product_id' => 12345]);

        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('syncProduct')
                ->once()
                ->andReturn([
                    'id' => 'gid://shopify/Product/12345',
                    'title' => 'Updated Title',
                ]);
        });

        $response = $this->actingAs($user)->putJson("/api/v1/products/{$product->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', ['title' => 'Updated Title']);
    }

    public function test_can_delete_product_syncs_to_shopify()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['shopify_product_id' => 12345]);

        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('deleteProduct')
                ->once()
                ->with(12345);
        });

        $response = $this->actingAs($user)->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
