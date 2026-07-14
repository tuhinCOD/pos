<?php

namespace Modules\Status\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Client\Models\Client;
use Modules\ClientReturn\Models\ClientReturn;
use Modules\Damage\Models\Damage;
use Modules\Delivery\Models\Delivery;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\Purchase\Models\Purchase;
use Modules\Repair\Models\Repair;
use Modules\Sale\Models\Sale;
use Modules\StockTransfer\Models\StockTransfer;
use Modules\SupplierReturn\Models\SupplierReturn;
use Modules\Temp\Models\Temp;
use Modules\User\Models\User;

// use Modules\Status\Database\Factories\StatusFactory;

class Status extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function parent()
    {
        return $this->belongsTo(Status::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Status::class, 'parent_id');
    }

    public function product () {
        return $this->hasOne(Product::class, 'status_id');
    }

    public function sale () {
        return $this->hasOne(Sale::class, 'status_id');
    }

    public function purchase () {
        return $this->hasOne(Purchase::class, 'status_id');
    }

    public function temp () {
        return $this->hasOne(Temp::class, 'status_id');
    }

    public function user () {
        return $this->hasOne(User::class, 'status_id');
    }

    public function damage () {
        return $this->hasOne(Damage::class, 'status_id');
    }

    public function delivery () {
        return $this->hasOne(Delivery::class, 'status_id');
    }

    public function order () {
        return $this->hasOne(Order::class, 'status_id');
    }

    public function repair () {
        return $this->hasOne(Repair::class, 'status_id');
    }

    public function payment () {
        return $this->hasOne(Payment::class, 'status_id');
    }

    // public function client () {
    //     return $this->hasOne(Client::class, 'status_id');
    // }

    public function clientReturn () {
        return $this->hasOne(ClientReturn::class, 'status_id');
    }

    public function supplierReturn () {
        return $this->hasOne(SupplierReturn::class, 'status_id');
    }

    public function stockTransfer () {
        return $this->hasOne(StockTransfer::class, 'status_id');
    }

    public function productDiscount () {
        return $this->hasOne(ProductDiscount::class, 'status_id');
    }
}
