<?php

namespace Modules\ProductReviewImage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\ProductReview\Models\ProductReview;

// use Modules\ProductReviewImage\Database\Factories\ProductReviewImageFactory;

class ProductReviewImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'product_review_id',
        'image',
    ];

    public function productReview()
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }
}
