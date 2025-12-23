<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'shopify_image_id',
        'path',
    ];

    protected $casts = [
        'shopify_image_id' => 'integer',
    ];

    protected $appends = ['url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: function () {
                // If path is already a full URL (starts with http), return it directly
                if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
                    return $this->path;
                }

                // Otherwise, use Storage facade for local files
                return Storage::url($this->path);
            },
        );
    }
}
