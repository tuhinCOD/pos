<?php

namespace Modules\Client\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Cart\Models\Cart;
use Modules\City\Models\City;
use Modules\Order\Models\Order;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\Temp\Models\Temp;
use Modules\User\Models\User;

// use Modules\Client\Database\Factories\ClientFactory;

class Client extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // public function status()
    // {
    //     return $this->belongsTo(Status::class, 'status_id');
    // }

    // public function city()
    // {
    //     return $this->belongsTo(City::class, 'city_id');
    // }

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }

    // public function sale()
    // {
    //     return $this->hasOne(Sale::class, 'client_id');
    // }

    // public function order()
    // {
    //     return $this->hasOne(Order::class, 'client_id');
    // }

    // public function temp()
    // {
    //     return $this->hasOne(Temp::class, 'client_id');
    // }

    // public function cart()
    // {
    //     return $this->hasOne(Cart::class, 'client_id');
    // }
}
