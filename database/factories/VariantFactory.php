<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Variant>
 */
class VariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => \App\Models\Product::factory(),
            'shopify_variant_id' => $this->faker->randomNumber(9),
            'option1' => 'Default Title',
            'price' => $this->faker->randomFloat(2, 10, 100),
            'sku' => $this->faker->ean8,
            'inventory_quantity' => 10,
        ];
    }
}
