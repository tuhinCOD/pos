<?php

namespace Modules\ProductPrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\Sale\Models\Sale;
use Modules\User\Models\User;

// use Modules\ProductPrice\Database\Factories\ProductPriceFactory;

class ProductPrice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sale()
    {
        return $this->hasOne(Sale::class, 'product_price_id');
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'product_price_id');
    }

    public function productDiscount()
    {
        return $this->hasOne(ProductDiscount::class, 'product_price_id');
    }
}
