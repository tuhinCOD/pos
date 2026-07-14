<?php

namespace Modules\ProductImage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Models\Product;

// use Modules\ProductImage\Database\Factories\ProductImageFactory;

class ProductImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_id',
        'image',
        'title',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
