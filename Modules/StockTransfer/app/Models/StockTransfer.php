<?php

namespace Modules\StockTransfer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\User\Models\User;

// use Modules\StockTransfer\Database\Factories\StockTransferFactory;

class StockTransfer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function branchFrom () {
        return $this->belongsTo(Branch::class, 'branch_from');
    }

    public function branchTo () {
        return $this->belongsTo(Branch::class, 'branch_to');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
