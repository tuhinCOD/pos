<?php

namespace Modules\PaymentMethod\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Payment\Models\Payment;

// use Modules\PaymentMethod\Database\Factories\PaymentMethodFactory;

class PaymentMethod extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function payment () {
        return $this->hasOne(Payment::class, 'payment_method_id');
    }
}
