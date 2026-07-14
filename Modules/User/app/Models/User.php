<?php

namespace Modules\User\Models;

use Illuminate\Console\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Branch\Models\Branch;
use Modules\Cart\Models\Cart;
use Modules\Category\Models\Category;
use Modules\City\Models\City;
// use Modules\Client\Models\Client;
use Modules\ClientReturn\Models\ClientReturn;
use Modules\Coupon\Models\Coupon;
use Modules\Damage\Models\Damage;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Modules\ProductDiscount\Models\ProductDiscount;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\ProductReview\Models\ProductReview;
use Modules\Purchase\Models\Purchase;
use Modules\Role\Models\Role;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\StockTransfer\Models\StockTransfer;
use Modules\Supplier\Models\Supplier;
use Modules\SupplierReturn\Models\SupplierReturn;
use Modules\Temp\Models\Temp;
use Modules\Unit\Models\Unit;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

// use Modules\User\Database\Factories\UserFactory;
#[Fillable(['name', 'email', 'password', 'branch_id', 'point'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject 
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array&lt;int, string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'password',
        'point',
    ];
 
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array&lt;int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = ['role'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
 
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function branch () {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cart () {
        return $this->hasMany(Cart::class, 'client_id');
    }

    public function sale () {
        return $this->hasOne(Sale::class, 'user_id');
    }

    public function category () {
        return $this->hasOne(Category::class, 'user_id');
    }

    public function categoryUpdatedBy () {
        return $this->hasOne(Category::class, 'updated_by');
    }

    public function unit () {
        return $this->hasOne(Unit::class, 'user_id');
    }

    public function unitUpdatedBy () {
        return $this->hasOne(Unit::class, 'updated_by');
    }

    public function saleClient () {
        return $this->hasOne(Sale::class, 'client_id');
    }

    public function purchase () {
        return $this->hasOne(Purchase::class, 'user_id');
    }

    public function order () {
        return $this->hasOne(Order::class, 'client_id');
    }

    public function temp () {
        return $this->hasOne(Temp::class, 'user_id');
    }

    public function tempClient () {
        return $this->hasOne(Temp::class, 'client_id');
    }

    public function damage () {
        return $this->hasOne(Damage::class, 'user_id');
    }

    public function payment () {
        return $this->hasOne(Payment::class, 'user_id');
    }

    public function product () {
        return $this->hasOne(Product::class, 'user_id');
    }

    public function coupon () {
        return $this->hasOne(Coupon::class, 'user_id');
    }

    // public function client () {
    //     return $this->hasOne(Client::class, 'user_id');
    // }

    public function supplier () {
        return $this->hasOne(Supplier::class, 'user_id');
    }

    public function clientReturn () {
        return $this->hasOne(ClientReturn::class, 'user_id');
    }

    public function supplierReturn () {
        return $this->hasOne(SupplierReturn::class, 'user_id');
    }

    public function stockTransfer () {
        return $this->hasOne(StockTransfer::class, 'user_id');
    }

    public function productPrice () {
        return $this->hasOne(ProductPrice::class, 'user_id');
    }

    public function productDiscount () {
        return $this->hasOne(ProductDiscount::class, 'user_id');
    }

    public function productReview () {
        return $this->hasOne(ProductReview::class, ' client_id');
    }
}
