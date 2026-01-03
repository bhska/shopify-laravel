<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variant extends Model
{
    /** @use HasFactory<\Database\Factories\VariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'shopify_variant_id',
        'option1',
        'option2',
        'option3',
        'price',
        'sku',
        'inventory_quantity',
    ];

    protected $casts = [
        'shopify_variant_id' => 'integer',
        'price' => 'decimal:2',
        'inventory_quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
