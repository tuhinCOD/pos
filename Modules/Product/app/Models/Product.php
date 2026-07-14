<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Cart\Models\Cart;
use Modules\Category\Models\Category;
use Modules\ClientReturn\Models\ClientReturn;
use Modules\Barcode\Models\Barcode;
use Modules\Damage\Models\Damage;
use Modules\Order\Models\Order;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\ProductImage\Models\ProductImage;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\ProductReview\Models\ProductReview;
use Modules\Purchase\Models\Purchase;
use Modules\Repair\Models\Repair;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\StockTransfer\Models\StockTransfer;
use Modules\SupplierReturn\Models\SupplierReturn;
use Modules\Temp\Models\Temp;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

// use Modules\Product\Database\Factories\ProductFactory;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
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

    public function sale()
    {
        return $this->hasOne(Sale::class, 'product_id');
    }

    public function purchase()
    {
        return $this->hasOne(Purchase::class, 'product_id');
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'product_id');
    }

    public function temp()
    {
        return $this->hasOne(Temp::class, 'product_id');
    }

    public function productPrice()
    {
        return $this->hasOne(ProductPrice::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function productDiscount()
    {
        return $this->hasOne(ProductDiscount::class, 'product_id');
    }

    public function productReview()
    {
        return $this->hasOne(ProductReview::class, 'product_id');
    }

    public function cart()
    {
        return $this->hasMany(Cart::class, 'product_id');
    }

    public function supplierReturn()
    {
        return $this->hasOne(SupplierReturn::class, 'product_id');
    }

    public function clientReturn()
    {
        return $this->hasOne(ClientReturn::class, 'product_id');
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'product_id');
    }

    public function repair()
    {
        return $this->hasOne(Repair::class, 'product_id');
    }

    public function stockTransfer()
    {
        return $this->hasOne(StockTransfer::class, 'product_id');
    }

    public function damage()
    {
        return $this->hasOne(Damage::class, 'product_id');
    }

    public function barcodes()
    {
        return $this->hasMany(Barcode::class, 'product_id');
    }
}
