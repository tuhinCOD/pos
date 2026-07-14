<?php

namespace Modules\Repair\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Damage\Models\Damage;
use Modules\Product\Models\Product;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

class Repair extends Model
{
    use HasFactory;

    protected $fillable = [];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function damage()
    {
        return $this->belongsTo(Damage::class, 'damage_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
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
