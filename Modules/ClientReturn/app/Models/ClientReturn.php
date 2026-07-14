<?php

namespace Modules\ClientReturn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

// use Modules\ClientReturn\Database\Factories\ClientReturnFactory;

class ClientReturn extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['attributes'];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'product_unit_id');
    }

    public function oldUnit()
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

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
