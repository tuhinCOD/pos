<?php

namespace Modules\Barcode\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Purchase\Models\Purchase;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

class Barcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'branch_id',
        'status_id',
        'purchase_id',
        'product_id',
        'unit_id',
        'user_id',
        'updated_by',
        'qty',
        'price',
        'vat',
        'discount',
        'point',
        'product_price_id',
        'updated_by',
        'attributes',
        'remarks',
    ];

    protected $casts = [
        'attributes' => 'array',
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
        'vat' => 'decimal:2',
        'discount' => 'decimal:2',
        'point' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productPrice()
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
