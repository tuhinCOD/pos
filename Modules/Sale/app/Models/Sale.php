<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
// use Modules\Client\Models\Client;
use Modules\Credit\Models\Credit;
use Modules\ClientReturn\Models\ClientReturn;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Status\Models\Status;
use Modules\StockTransfer\Models\StockTransfer;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

// use Modules\Sale\Database\Factories\SaleFactory;

class Sale extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected $casts = [
        'attributes' => 'array',
    ];

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

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productPrice()
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function credit()
    {
        return $this->hasOne(Credit::class, 'invoice_no', 'invoice_no')->where('credit_type', 'sale');
    }

    public function clientReturn()
    {
        return $this->hasOne(ClientReturn::class, 'sale_id');
    }

    public function stockTransfer()
    {
        return $this->hasOne(StockTransfer::class, 'sale_id');
    }
}
