<?php

namespace Modules\City\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
// use Modules\Client\Models\Client;
use Modules\Country\Models\Country;
use Modules\Supplier\Models\Supplier;
use Modules\User\Models\User;

// use Modules\City\Database\Factories\CityFactory;

class City extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    // public function client()
    // {
    //     return $this->hasOne(Client::class, 'city_id');
    // }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'city_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'city_id');
    }

    public function branch()
    {
        return $this->hasOne(Branch::class, 'city_id');
    }    
}
