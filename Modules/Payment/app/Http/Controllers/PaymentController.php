<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Credit\Events\CreditUpdate;
use Modules\Credit\Models\Credit;
use Modules\Payment\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;

class PaymentController extends Controller
{
    public function index()
    {
        return view('payment::index');
    }

    public function create()
    {
        return view('payment::create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'payment_invoice_no' => 'required|string',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_type' => 'required|in:sale,purchase,order',
            'amount' => 'required|numeric|min:0.01',
            'trx_id' => 'nullable',
            'note' => 'nullable|max:500',
            'payment_date' => 'required|date',
        ]);

        $paidStatus = Status::where('name', 'paid')
            ->whereHas('parent', fn($q) => $q->where('name', 'payment'))
            ->first();

        $payment = new Payment();
        $payment->user_id = Auth::id();
        $payment->currency = 'BDT';
        $payment->payment_method_id = $request->payment_method_id;
        $payment->payment_type = $request->payment_type;
        $payment->status_id = $paidStatus?->id ?? 1;
        $payment->payment_invoice_no = $request->payment_invoice_no;
        $payment->trx_id = $request->trx_id;
        $payment->payment_date = $request->payment_date;
        $payment->payer_no = $request->payer_no ?? null;
        $payment->amount = $request->amount;
        $payment->note = $request->note;
        $payment->save();

        if ($request->payment_type === 'sale') {
            $invoiceNo = $request->payment_invoice_no;

            $totalAmount = Sale::where('invoice_no', $invoiceNo)
                ->get()
                ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

            $totalPaid = Payment::where('payment_invoice_no', $invoiceNo)
                ->get()
                ->sum(fn($p) => (float)$p->amount);

            $existingCredit = Credit::where('invoice_no', $invoiceNo)
                ->where('credit_type', 'sale')
                ->first();

            if ($existingCredit) {
                $existingCredit->updated_by = Auth::id();
                $existingCredit->paid_amount = $totalPaid;
                $existingCredit->due_amount = max(0, $totalAmount - $totalPaid);
                $existingCredit->save();

                event(new CreditUpdate($existingCredit));
            } elseif ($totalAmount > 0) {
                $credit = new Credit();
                $credit->credit_type = 'sale';
                $credit->invoice_no = $invoiceNo;
                $credit->user_id = Auth::id();
                $credit->total_amount = $totalAmount;
                $credit->paid_amount = $totalPaid;
                $credit->due_amount = max(0, $totalAmount - $totalPaid);
                $credit->save();

                event(new CreditUpdate($credit));
            }

            if ($totalPaid >= $totalAmount) {
                $tempStatus = Status::where('name', 'temp')->first();
                if ($tempStatus) {
                    $partialCompletedStatus = Status::where('name', 'partial completed')
                        ->where('parent_id', $tempStatus->id)
                        ->first();
                    $completedStatus = Status::where('name', 'completed')
                        ->where('parent_id', $tempStatus->id)
                        ->first();

                    if ($partialCompletedStatus && $completedStatus) {
                        $partialSales = Sale::where('invoice_no', $invoiceNo)
                            ->where('status_id', $partialCompletedStatus->id)
                            ->get();

                        foreach ($partialSales as $sale) {
                            $sale->status_id = $completedStatus->id;
                            $sale->updated_by = Auth::id();
                            $sale->save();

                            event(new \Modules\Sale\Events\SaleUpdate($sale));
                        }
                    }
                }
            }
        }

        if ($request->payment_type === 'purchase') {
            $invoiceNo = $request->payment_invoice_no;

            $totalAmount = Purchase::where('invoice_no', $invoiceNo)
                ->get()
                ->sum(fn($p) => ($p->qty * $p->price) + ($p->qty * ($p->vat ?? 0)) - ($p->qty * ($p->discount ?? 0)));

            $totalPaid = Payment::where('payment_invoice_no', $invoiceNo)
                ->get()
                ->sum(fn($p) => (float)$p->amount);

            $purchaseStatus = Status::where('name', 'purchase')->first();
            if ($purchaseStatus) {
                $receivedStatus = Status::where('name', 'received')
                    ->where('parent_id', $purchaseStatus->id)
                    ->first();
                if ($receivedStatus) {
                    $hasReceived = Purchase::where('invoice_no', $invoiceNo)
                        ->where('status_id', $receivedStatus->id)
                        ->exists();
                    if ($hasReceived && $totalPaid < $totalAmount) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invoice has fully received items. Full payment is required.',
                        ], 422);
                    }
                }
            }

            $existingCredit = Credit::where('invoice_no', $invoiceNo)
                ->where('credit_type', 'purchase')
                ->first();

            if ($existingCredit) {
                $existingCredit->updated_by = Auth::id();
                $existingCredit->paid_amount = $totalPaid;
                $existingCredit->due_amount = max(0, $totalAmount - $totalPaid);
                $existingCredit->save();

                event(new CreditUpdate($existingCredit));
            } elseif ($totalAmount > 0) {
                $credit = new Credit();
                $credit->credit_type = 'purchase';
                $credit->invoice_no = $invoiceNo;
                $credit->user_id = Auth::id();
                $credit->total_amount = $totalAmount;
                $credit->paid_amount = $totalPaid;
                $credit->due_amount = max(0, $totalAmount - $totalPaid);
                $credit->save();

                event(new CreditUpdate($credit));
            }

            if ($totalPaid >= $totalAmount) {
                $purchaseStatus = Status::where('name', 'purchase')->first();
                if ($purchaseStatus) {
                    $partialReceivedStatus = Status::where('name', 'partial received')
                        ->where('parent_id', $purchaseStatus->id)
                        ->first();
                    $receivedStatus = Status::where('name', 'received')
                        ->where('parent_id', $purchaseStatus->id)
                        ->first();

                    if ($partialReceivedStatus && $receivedStatus) {
                        $partialPurchases = Purchase::where('invoice_no', $invoiceNo)
                            ->where('status_id', $partialReceivedStatus->id)
                            ->get();

                        foreach ($partialPurchases as $purchase) {
                            $purchase->status_id = $receivedStatus->id;
                            $purchase->updated_by = Auth::id();
                            $purchase->save();

                            event(new \Modules\Purchase\Events\PurchaseUpdate($purchase));
                        }
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded successfully',
            'payment' => $payment,
        ]);
    }

    public function show($id)
    {
        return view('payment::show');
    }

    public function totalByInvoice(string $invoiceNo)
    {
        $totalPaid = Payment::where('payment_invoice_no', $invoiceNo)
            ->get()
            ->sum(fn($p) => (float)$p->amount);

        return response()->json([
            'status' => 'success',
            'invoice_no' => $invoiceNo,
            'total_paid' => $totalPaid,
        ]);
    }

    public function edit($id)
    {
        return view('payment::edit');
    }

    public function update(Request $request, $id) {}

    public function destroy($id) {}
}
