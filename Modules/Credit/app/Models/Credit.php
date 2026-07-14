<?php

namespace Modules\Credit\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\User\Models\User;

class Credit extends Model
{
    protected $fillable = ['credit_type', 'invoice_no', 'user_id', 'updated_by', 'total_amount', 'paid_amount', 'due_amount'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
