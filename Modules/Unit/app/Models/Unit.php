<?php

namespace Modules\Unit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Order\Models\Order;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Purchase\Models\Purchase;
use Modules\Sale\Models\Sale;
use Modules\Temp\Models\Temp;
use Modules\User\Models\User;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sale () {
        return $this->hasOne(Sale::class, 'unit_id');
    }

    public function purchase () {
        return $this->hasOne(Purchase::class, 'unit_id');
    }

    public function temp () {
        return $this->hasOne(Temp::class, 'unit_id');
    }

    public function productPrice () {
        return $this->hasOne(ProductPrice::class, 'unit_id');
    }

    public function order () {
        return $this->hasOne(Order::class, 'unit_id');
    }

    public function productDiscount () {
        return $this->hasOne(ProductDiscount::class, 'unit_id');
    }
}
