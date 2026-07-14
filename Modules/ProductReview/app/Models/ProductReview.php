<?php

namespace Modules\ProductReview\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Models\Product;
use Modules\ProductReviewImage\Models\ProductReviewImage;
use Modules\User\Models\User;

// use Modules\ProductReview\Database\Factories\ProductReviewFactory;

class ProductReview extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductReviewImage::class);
    }
}
