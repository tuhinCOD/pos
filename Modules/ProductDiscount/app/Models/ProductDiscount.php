<?php

namespace Modules\ProductDiscount\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

// use Modules\ProductDiscount\Database\Factories\ProductDiscountFactory;

class ProductDiscount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function branch () {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function productPrice()
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
