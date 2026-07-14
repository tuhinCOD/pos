<?php

namespace Modules\Wishlist\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Product\Models\Product;
use Modules\User\Models\User;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id', 'product_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
