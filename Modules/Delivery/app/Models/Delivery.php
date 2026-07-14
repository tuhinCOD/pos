<?php

namespace Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Status\Models\Status;

// use Modules\Delivery\Database\Factories\DeliveryFactory;

class Delivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
