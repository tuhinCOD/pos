<?php

namespace Modules\Branch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\City\Models\City;
use Modules\Damage\Models\Damage;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\Purchase\Models\Purchase;
use Modules\Sale\Models\Sale;
use Modules\Stock\Models\Stock;
use Modules\StockTransfer\Models\StockTransfer;
use Modules\Temp\Models\Temp;
use Modules\User\Models\User;

// use Modules\Branch\Database\Factories\BranchFactory;

class Branch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'contact',
        'address',
        'city_id',
        'user_id',
        'updated_by',
    ];

    public function city () {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedUser () {
        return $this->hasOne(User::class, 'branch_id');
    }

    public function sale () {
        return $this->hasOne(Sale::class, 'branch_id');
    }

    public function purchase () {
        return $this->hasOne(Purchase::class, 'branch_id');
    }

    public function temp () {
        return $this->hasOne(Temp::class, 'branch_id');
    }

    public function damage () {
        return $this->hasOne(Damage::class, 'branch_id');
    }

    public function stock () {
        return $this->hasOne(Stock::class, 'branch_id');
    }

    public function stockTransferFrom () {
        return $this->hasOne(StockTransfer::class, 'branch_from');
    }

    public function stockTransferTo () {
        return $this->hasOne(StockTransfer::class, 'branch_to');
    }

    public function productDiscount () {
        return $this->hasOne(ProductDiscount::class, 'branch_id');
    }
}
