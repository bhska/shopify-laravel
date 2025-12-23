<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'body_html' => $this->faker->paragraph,
            'vendor' => $this->faker->company,
            'product_type' => 'Default',
            'status' => 'active',
            'shopify_product_id' => $this->faker->randomNumber(9),
        ];
    }
}
