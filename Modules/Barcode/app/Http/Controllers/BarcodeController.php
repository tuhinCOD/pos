<?php

namespace Modules\Barcode\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Barcode\Events\BarcodeUpdate;
use Modules\Barcode\Models\Barcode;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Purchase\Models\Purchase;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class BarcodeController extends Controller
{
    public function index(Request $request)
    {
        $barcodes = Barcode::with(['product', 'productPrice', 'purchase', 'branch', 'unit', 'status', 'user', 'updatedBy'])
        ->when($request->search, function ($query) use ($request) {
            return $query->where('barcode', 'like', '%' . $request->search . '%')
            ->orWhereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('barcode', 'like', "%{$request->search}%");
            })
            ->orWhereHas('purchase', function ($q) use ($request) {
                $q->where('invoice_no', 'like', "%{$request->search}%");
            });
        })
        ->when($request->product_id, function ($query) use ($request) {
            $query->where('product_id', $request->product_id);
        })
        ->when($request->purchase_id, function ($query) use ($request) {
            $query->where('purchase_id', $request->purchase_id);
        })
        ->when($request->branch_id, function ($query) use ($request) {
            $query->where('branch_id', $request->branch_id);
        })
        ->when($request->status_id, function ($query) use ($request) {
            $query->where('status_id', $request->status_id);
        })
        ->latest('id')
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $branches = Branch::all();
        $products = Product::all();
        $purchases = Purchase::all();
        $units = Unit::all();

        return response()->json([
            'status' => 'success',
            'barcodes' => $barcodes,
            'branches' => $branches,
            'products' => $products,
            'purchases' => $purchases,
            'units' => $units,
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Barcode::min('created_at');

            if ($oldest) {
                $maxDays = (int) Carbon::parse($oldest)->diffInDays(now());

                if ($days > $maxDays) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Data is only available for the last {$maxDays} day(s). Please enter a value of {$maxDays} or less.",
                    ], 422);
                }
            }
        }

        $barcodes = Barcode::with(['product', 'productPrice', 'purchase', 'branch', 'unit', 'status', 'user', 'updatedBy'])
            ->when($request->search, function ($query) use ($request) {
                return $query->where('barcode', 'like', '%' . $request->search . '%')
                ->orWhereHas('product', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('barcode', 'like', "%{$request->search}%");
                })
                ->orWhereHas('purchase', fn($pq) => $pq->where('invoice_no', 'like', "%{$request->search}%"));
            })
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->purchase_id, fn($q) => $q->where('purchase_id', $request->purchase_id))
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->status_id, fn($q) => $q->where('status_id', $request->status_id))
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->latest('id')
            ->get();

        $data = $barcodes->map(fn($b) => [
            'ID' => $b->id,
            'Barcode' => $b->barcode ?? '-',
            'Product' => $b->product?->name ?? '-',
            'Purchase Invoice' => $b->purchase?->invoice_no ?? '-',
            'Branch' => $b->branch?->name ?? '-',
            'Unit' => $b->unit?->name ?? '-',
            'Price' => $b->productPrice?->price ?? '-',
            'Qty' => $b->qty,
            'Status' => $b->status?->name ?? '-',
            'Created By' => $b->user?->name ?? '-',
            'Updated By' => $b->updatedBy?->name ?? '-',
            'Created At' => $b->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $b->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Barcode', 'Product', 'Purchase Invoice', 'Branch', 'Unit', 'Price', 'Qty', 'Status', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'barcodes_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Barcodes', 'barcodes');
        return response()->json(['message' => 'Export started successfully.', 'file' => $filename]);
    }

    public function download($filename)
    {
        $path = 'exports/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File is not ready yet.'], 404);
        }
        return response()->download(
            Storage::disk('public')->path($path),
            $filename
        )->deleteFileAfterSend(true);
    }

    public function show(int $id)
    {
        $barcode = Barcode::with(['product', 'purchase', 'branch', 'unit', 'status', 'user'])
            ->findOrFail($id);

        $productPrice = ProductPrice::where('product_id', $barcode->product_id)->first();

        return response()->json([
            'status' => 'success',
            'barcode' => $barcode,
            'product_price' => $productPrice,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'qty' => 'required|numeric|min:0.001',
            'price' => 'required|numeric|min:0',
            'vat' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'point' => 'nullable|numeric|min:0',
            'attributes' => 'nullable|json',
            'product_price_id' => 'nullable|exists:product_prices,id',
        ]);

        $purchase = Purchase::with('product')->findOrFail($request->purchase_id);
        $product = $purchase->product;

        $existingQty = $purchase->barcodes()->sum('qty');
        $remainingQty = $purchase->qty - $existingQty;

        if ($remainingQty <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Barcodes already generated for this purchase',
                'count' => $purchase->barcodes()->count()
            ], 409);
        }

        $barcodeType = $product->barcode_type ?? 'single';

        if ($barcodeType === 'single') {
            return response()->json([
                'status' => 'error',
                'message' => 'This product uses single barcode type, no generation needed'
            ], 422);
        }

        $productPrice = ProductPrice::where('product_id', $product->id)->first();

        $price = $request->input('price', $productPrice?->price ?? 0);
        $vat = $request->input('vat', $productPrice?->vat ?? 0);
        $discount = $request->input('discount', $productPrice?->discount ?? 0);
        $point = $request->input('point', $productPrice?->point ?? 0);

        $requestAttrs = $request->input('attributes') ? json_decode($request->input('attributes'), true) : [];

        $variants = null;
        if ($purchase->attributes && isset($purchase->attributes['variants'])) {
            $variants = $purchase->attributes['variants'];
        }

        $barcodes = [];

        if ($barcodeType === 'piece') {
            if ($variants) {
                $totalNewQty = array_sum(array_column($variants, 'qty')) ?: count($variants);
                if ($totalNewQty > $remainingQty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Total variant quantity ($totalNewQty) exceeds remaining quantity ($remainingQty)"
                    ], 422);
                }
                $seq = $existingQty + 1;
                foreach ($variants as $variant) {
                    $qty = $variant['qty'] ?? 1;
                    $merged = array_merge($requestAttrs, $variant);
                    for ($i = 0; $i < $qty; $i++) {
                        $barcodes[] = $this->buildBarcodeData($purchase, $product, $seq, $merged, $price, $vat, $discount, $point, 1, $productPrice?->id);
                        $seq++;
                    }
                }
            } else {
                $qty = (int) $request->input('qty', $remainingQty);
                if ($qty > $remainingQty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Requested quantity ($qty) exceeds remaining quantity ($remainingQty)"
                    ], 422);
                }
                $seq = $existingQty + 1;
                for ($i = 0; $i < $qty; $i++) {
                    $barcodes[] = $this->buildBarcodeData($purchase, $product, $seq + $i, $requestAttrs, $price, $vat, $discount, $point, 1, $productPrice?->id);
                }
            }
        } elseif ($barcodeType === 'weight') {
            $existingWeightBarcode = Barcode::where('purchase_id', $purchase->id)
                ->where('product_id', $product->id)
                ->first();

            if ($existingWeightBarcode) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Weight barcode already exists for this purchase',
                    'barcode' => $existingWeightBarcode,
                ]);
            }

            $weightUnitId = $productPrice?->unit_id;
            $barcodes[] = $this->buildBarcodeData($purchase, $product, $existingQty + 1, $requestAttrs, $price, $vat, $discount, $point, 1, $productPrice?->id, $weightUnitId);
        }

        Barcode::insert($barcodes);

        $count = count($barcodes);

        event(new BarcodeUpdate($purchase));

        return response()->json([
            'status' => 'success',
            'message' => "{$count} barcodes generated successfully",
            'count' => $count,
            'total' => $existingQty + $count,
        ]);
    }

    public function generateSingle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'unit_id' => 'required|exists:units,id',
            'qty' => 'required|numeric|min:0.001',
            'price' => 'required|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'point' => 'nullable|numeric|min:0',
            'attributes' => 'nullable|json',
        ]);

        $product = Product::findOrFail($request->product_id);

        $productPrice = ProductPrice::where('product_id', $product->id)->first();

        $seq = Barcode::where('product_id', $product->id)->count() + 1;

        do {
            $barcodeStr = now()->format('YmdHis') . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
        } while (Barcode::where('barcode', $barcodeStr)->exists());

        $meta = [];
        if ($request->filled('attributes')) {
            $meta = json_decode($request->input('attributes'), true) ?? [];
        }

        $barcode = new Barcode();
        $barcode->barcode = $barcodeStr;
        $barcode->branch_id = $request->branch_id;
        $barcode->status_id = 1;
        $barcode->purchase_id = null;
        $barcode->product_id = $product->id;
        $barcode->unit_id = $request->unit_id;
        $barcode->user_id = Auth::id();
        $barcode->qty = $request->qty;
        $barcode->price = $request->price;
        $barcode->vat = $request->vat ?? 0;
        $barcode->discount = $request->discount ?? 0;
        $barcode->point = $request->point ?? 0;
        $barcode->product_price_id = $productPrice?->id;
        $barcode->attributes = !empty($meta) ? $meta : null;
        $barcode->save();

        $barcode->load(['product', 'branch', 'unit']);

        event(new BarcodeUpdate($barcode));

        return response()->json([
            'status' => 'success',
            'message' => 'Barcode generated successfully',
            'barcode' => $barcode,
        ]);
    }

    private function buildBarcodeData($purchase, $product, int $seq, array $variant, float $price, float $vat, float $discount, float $point, float $qty = 1, $productPriceId = null, $unitId = null): array
    {
        $meta = [];
        foreach ($variant as $key => $val) {
            if (!is_null($val) && $val !== '' && $key !== 'qty') {
                $meta[$key] = $val;
            }
        }

        do {
            $barcodeStr = now()->format('YmdHis') . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
        } while (Barcode::where('barcode', $barcodeStr)->exists());

        return [
            'barcode'     => $barcodeStr,
            'branch_id'   => $purchase->branch_id,
            'status_id'   => 1,
            'purchase_id' => $purchase->id,
            'product_id'  => $product->id,
            'unit_id'     => $unitId ?? $purchase->product_unit_id,
            'user_id'     => Auth::id(),
            'qty'         => $qty,
            'price'       => $price,
            'vat'         => $vat,
            'discount'    => $discount,
            'point'       => $point,
            'product_price_id' => $productPriceId,
            'attributes'  => !empty($meta) ? json_encode($meta) : null,
            'remarks'     => $purchase->remarks,
        ];
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'qty' => 'nullable|numeric|min:0.001',
            'price' => 'nullable|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'point' => 'nullable|numeric|min:0',
            'attributes' => 'nullable|json',
            'status_id' => 'nullable|exists:statuses,id',
            'product_price_id' => 'nullable|exists:product_prices,id',
        ]);

        $barcode = Barcode::findOrFail($id);

        if ($request->has('qty')) $barcode->qty = $request->qty;
        if ($request->has('price')) $barcode->price = $request->price;
        if ($request->has('vat')) $barcode->vat = $request->vat;
        if ($request->has('discount')) $barcode->discount = $request->discount;
        if ($request->has('point')) $barcode->point = $request->point;
        if ($request->has('status_id')) $barcode->status_id = $request->status_id;
        if ($request->has('attributes')) $barcode->attributes = json_decode($request->input('attributes'), true);
        if ($request->has('product_price_id')) $barcode->product_price_id = $request->product_price_id;
        $barcode->updated_by = Auth::id();
        $barcode->update();

        event(new BarcodeUpdate($barcode));

        return response()->json([
            'status' => 'success',
            'message' => 'Barcode updated successfully',
            'barcode' => $barcode,
        ]);
    }

    public function byPurchase(int $purchaseId)
    {
        $barcodes = Barcode::with(['product', 'unit', 'status'])
            ->where('purchase_id', $purchaseId)
            ->get();

        $purchase = Purchase::with('product')->findOrFail($purchaseId);

        return response()->json([
            'status' => 'success',
            'barcodes' => $barcodes,
            'purchase' => $purchase,
        ]);
    }

    public function byProduct(int $productId)
    {
        $barcodes = Barcode::with(['product', 'purchase', 'unit', 'status'])
            ->where('product_id', $productId)
            ->latest('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'barcodes' => $barcodes,
        ]);
    }

    public function destroy(int $id)
    {
        $barcode = Barcode::findOrFail($id);

        $barcode->delete();
        
        $barcodeData = $barcode->toArray($barcode);
        
        event(new BarcodeUpdate($barcodeData));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Barcode deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:barcodes,id']);

        Barcode::whereIn('id', $request->ids)->delete();

        foreach ($request->ids as $id) {
            event(new BarcodeUpdate(['id' => $id]));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' barcodes deleted successfully'
        ]);
    }
}
