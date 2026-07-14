<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\Unit\Models\Unit;

// use Modules\Stock\Database\Factories\StockFactory;

class Stock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

     public function levelSpecific()
    {
        return $this->morphTo();
    }

    public function branch () {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function level()
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
