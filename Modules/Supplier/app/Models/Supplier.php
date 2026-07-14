<?php

namespace Modules\Supplier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\City\Models\City;
use Modules\Purchase\Models\Purchase;
use Modules\User\Models\User;
// use Modules\Supplier\Database\Factories\SupplierFactory;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Suppliers")]
class Supplier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function city()
    {
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

    public function purchase()
    {
        return $this->hasOne(Purchase::class, 'supplier_id');
    }
}
