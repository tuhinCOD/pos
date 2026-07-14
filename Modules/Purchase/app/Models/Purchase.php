<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\Supplier\Models\Supplier;
use Modules\Barcode\Models\Barcode;
use Modules\Credit\Models\Credit;
use Modules\SupplierReturn\Models\SupplierReturn;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;

// use Modules\Purchase\Database\Factories\PurchaseFactory;

class Purchase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['attributes'];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function branch () {
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

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function credit()
    {
        return $this->hasOne(Credit::class, 'invoice_no', 'invoice_no')->where('credit_type', 'purchase');
    }

    public function supplierReturn()
    {
        return $this->hasOne(SupplierReturn::class, 'purchase_id');
    }

    public function stocks()
    {
        return $this->morphMany(Stock::class, 'level_specific');
    }

    public function barcodes()
    {
        return $this->hasMany(Barcode::class, 'purchase_id');
    }

    public static function getGrandTotalByInvoice(string $invoiceNo): float
    {
        return self::where('invoice_no', $invoiceNo)
            ->get()
            ->sum(fn($p) => ($p->qty * $p->price) + ($p->vat ?? 0) - ($p->discount ?? 0));
    }

    public static function getTotalPaidByInvoice(string $invoiceNo): float
    {
        return \Modules\Payment\Models\Payment::where('payment_invoice_no', $invoiceNo)
            ->get()
            ->sum(fn($p) => (float)$p->amount);
    }

    public static function handlePartialReceivedLogic(string $invoiceNo, int $userId): void
    {
        $grandTotal = self::getGrandTotalByInvoice($invoiceNo);
        $totalPaid = self::getTotalPaidByInvoice($invoiceNo);

        $purchaseStatus = \Modules\Status\Models\Status::where('name', 'purchase')->first();
        if (!$purchaseStatus) {
            return;
        }

        $receivedStatus = \Modules\Status\Models\Status::where('name', 'received')->where('parent_id', $purchaseStatus->id)->first();
        $partialReceivedStatus = \Modules\Status\Models\Status::where('name', 'partial received')->where('parent_id', $purchaseStatus->id)->first();

        if ($totalPaid >= $grandTotal && $receivedStatus && $partialReceivedStatus) {
            $existingCredit = \Modules\Credit\Models\Credit::where('invoice_no', $invoiceNo)
                ->where('credit_type', 'purchase')
                ->first();

            if ($existingCredit) {
                $existingCredit->updated_by = $userId;
                $existingCredit->paid_amount = $totalPaid;
                $existingCredit->due_amount = 0;
                $existingCredit->save();
                event(new \Modules\Credit\Events\CreditUpdate($existingCredit));
            }

            $partialPurchases = self::where('invoice_no', $invoiceNo)
                ->where('status_id', $partialReceivedStatus->id)
                ->get();

            foreach ($partialPurchases as $purchase) {
                $purchase->status_id = $receivedStatus->id;
                $purchase->updated_by = $userId;
                $purchase->save();
                event(new \Modules\Purchase\Events\PurchaseUpdate($purchase));
            }
        } elseif ($partialReceivedStatus) {
            $existingCredit = \Modules\Credit\Models\Credit::where('invoice_no', $invoiceNo)
                ->where('credit_type', 'purchase')
                ->first();

            $dueAmount = max(0, $grandTotal - $totalPaid);

            if ($existingCredit) {
                $existingCredit->updated_by = $userId;
                $existingCredit->total_amount = $grandTotal;
                $existingCredit->paid_amount = $totalPaid;
                $existingCredit->due_amount = $dueAmount;
                $existingCredit->save();
                event(new \Modules\Credit\Events\CreditUpdate($existingCredit));
            } elseif ($dueAmount > 0) {
                $credit = new \Modules\Credit\Models\Credit();
                $credit->credit_type = 'purchase';
                $credit->invoice_no = $invoiceNo;
                $credit->user_id = $userId;
                $credit->total_amount = $grandTotal;
                $credit->paid_amount = $totalPaid;
                $credit->due_amount = $dueAmount;
                $credit->save();
                event(new \Modules\Credit\Events\CreditUpdate($credit));
            }
        }
    }
}
