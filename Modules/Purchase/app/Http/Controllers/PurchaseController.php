<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\Credit\Events\CreditUpdate;
use Modules\Credit\Models\Credit;
use Modules\Purchase\Events\PurchaseUpdate;
use Modules\Purchase\Models\Purchase;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\Supplier\Models\Supplier;
use Modules\Unit\Models\Unit;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Purchases")]
class PurchaseController extends Controller
{
    #[OA\Get(
        path: "/purchases",
        tags: ["Purchases"],
        summary: "List purchases",
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Purchases fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "supplier", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "branch", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $purchases = Purchase::with(['branch', 'status', 'product', 'supplier', 'user', 'unit', 'updatedBy'])
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'invoice_no',
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            })
            ->orWhereHas('branch', function ($branchQuery) use ($request) {
                $branchQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('supplier', function ($supplierQuery) use ($request) {
                $supplierQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
            });
        })
        ->when($request->status, function ($query) use ($request) {
            $query->where('status_id', $request->status);
        })
        ->when($request->branch, function ($query) use ($request) {
            $query->where('branch_id', $request->branch);
        })
        ->when($request->products, function ($query) use ($request) {
            $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
            $query->whereIn('product_id', $productIds);
        })
        ->latest('id')
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $purchaseParent = Status::where('name', 'purchase')->first();
        $statuses = $purchaseParent ? Status::where('parent_id', $purchaseParent->id)->get() : [];
        $branches = Branch::all();
        $products = Product::with('unit')->get();
        $suppliers = Supplier::all();
        $units = Unit::all();

        return response()->json([
            'status' => 'success',
            'purchases' => $purchases,
            'branches' => $branches,
            'statuses' => $statuses,
            'products' => $products,
            'suppliers' => $suppliers,
            'unit' => $units
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Purchase::min('created_at');

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

        $purchases = Purchase::with(['branch', 'status', 'product', 'supplier', 'user', 'unit', 'updatedBy'])
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['invoice_no'], 'like', '%' . $request->search . '%')
                ->orWhereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($request) {
                    $branchQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('supplier', function ($supplierQuery) use ($request) {
                    $supplierQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status_id', $request->status);
            })
            ->when($request->branch, function ($query) use ($request) {
                $query->where('branch_id', $request->branch);
            })
            ->when($request->products, function ($query) use ($request) {
                $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
                $query->whereIn('product_id', $productIds);
            })
            ->when($request->invoice_nos, fn($q) => $q->whereIn('invoice_no', explode(',', $request->invoice_nos)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->latest('id')
            ->get();

        $data = $purchases->map(fn($p) => [
            'Invoice No' => $p->invoice_no,
            'Product' => $p->product?->name ?? '-',
            'Supplier' => $p->supplier?->name ?? '-',
            'Branch' => $p->branch?->name ?? '-',
            'Qty' => $p->qty,
            'Price' => $p->price,
            'Vat' => $p->vat,
            'Discount' => $p->discount ?? 0,
            'Total' => ($p->qty * $p->price) + ($p->vat ?? 0) - ($p->discount ?? 0),
            'Status' => $p->status?->name ?? '-',
            'Unit' => $p->unit?->name ?? '-',
            'Product Unit Qty' => $p->product_unit_qty,
            'Created By' => $p->user?->name ?? '-',
            'Updated By' => $p->updatedBy?->name ?? '-',
            'Created At' => $p->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $p->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['Invoice No', 'Product', 'Supplier', 'Branch', 'Qty', 'Price', 'Vat', 'Discount', 'Total', 'Status', 'Unit', 'Product Unit Qty', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'purchases_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Purchases', 'purchases');

        return response()->json(['file' => $filename]);
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

    #[OA\Get(
        path: "/purchases/today",
        tags: ["Purchases"],
        summary: "Get today's purchases",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Today's purchases fetched")
        ]
    )]
    public function show(int $id)
    {
        $purchase = Purchase::with(['branch', 'status', 'product', 'supplier', 'user', 'unit', 'credit'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'purchase' => $purchase
        ]);
    }

    public function today()
    {
        $lastPurchase = Purchase::latest('id')->first();

        if (!$lastPurchase) {
            return response()->json([
                'status' => 'success',
                'purchases' => []
            ]);
        }

        $invoiceNo = $lastPurchase->invoice_no;

        $purchases = Purchase::with(['product', 'status', 'branch', 'supplier', 'user', 'unit'])
            ->where('invoice_no', $invoiceNo)
            ->get();

        return response()->json([
            'status' => 'success',
            'purchases' => $purchases
        ]);
    }

    #[OA\Post(
        path: "/purchases",
        summary: "Create new purchase",
        tags: ["Purchases"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["invoice_no", "status", "branch", "product", "product_unit_id", "product_unit_qty", "price", "vat"],
                properties: [
                    new OA\Property(property: "invoice_no", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "supplier", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_unit_id", type: "integer"),
                    new OA\Property(property: "product_unit_qty", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Purchase created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string|max:16',
            'status' => 'required',
            'branch' => 'required',
            'product' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'product_unit_id' => 'required',
            'product_unit_qty' => 'required|numeric|max:99999999.999',
            'price' => 'required|numeric|max:9999999999.999',
            'vat' => 'required|numeric|max:9999999999.999',
            'discount' => 'max:999999999.999',
            'remarks' => 'max:500',
            'attributes' => 'nullable|json',
        ]);

        $purchaseParentStatus = Status::where('name', 'purchase')->first();
        if ($purchaseParentStatus) {
            $receivedStatusId = Status::where('name', 'received')
                ->where('parent_id', $purchaseParentStatus->id)
                ->value('id');
            if ($receivedStatusId && (int)$request->status === $receivedStatusId) {
                $existingGrandTotal = Purchase::getGrandTotalByInvoice($request->invoice_no);
                $lineTotal = ($request->qty * $request->price) + ($request->vat ?? 0) - ($request->discount ?? 0);
                $newGrandTotal = $existingGrandTotal + $lineTotal;
                $totalPaid = Purchase::getTotalPaidByInvoice($request->invoice_no);
                if ($totalPaid < $newGrandTotal) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invoice is not fully paid. Complete the payment first.',
                    ], 422);
                }
            }
        }

        $requestAttrs = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;

        $existingPurchase = Purchase::where('invoice_no', $request->invoice_no)
            ->where('product_id', $request->product)
            ->where('branch_id', $request->branch)
            ->where('supplier_id', $request->supplier)
            ->where('status_id', $request->status)
            ->where('product_unit_id', $request->product_unit_id)
            ->where('price', $request->price)
            ->where('vat', $request->vat)
            ->where('discount', $request->discount)
            ->when($requestAttrs, function ($q) use ($requestAttrs) {
                foreach ($requestAttrs as $k => $v) {
                    $q->where("attributes->{$k}", $v);
                }
            }, function ($q) {
                $q->whereNull('attributes');
            })
            ->first();

        if ($existingPurchase) {
            $existingPurchase->unit_id = $request->unit;
            $existingPurchase->qty = $request->qty;
            $existingPurchase->product_unit_qty += $request->product_unit_qty;
            $existingPurchase->vat = $request->vat;
            $existingPurchase->discount = $request->discount;
            $existingPurchase->remarks = $request->remarks;
            $existingPurchase->updated_by = Auth::id();
            $existingPurchase->save();

            $purchaseParent = Status::where('name', 'purchase')->first();

            if ($purchaseParent) {
                $receivedStatus = Status::where('name', 'received')->where('parent_id', $purchaseParent->id)->first();
                $partialReceivedStatus = Status::where('name', 'partial received')->where('parent_id', $purchaseParent->id)->first();

                if (($receivedStatus && (int)$request->status === $receivedStatus->id) ||($partialReceivedStatus && (int)$request->status === $partialReceivedStatus->id)) {
                    $level = Level::where('name', 'purchase')->first();
                    $latestStockOverall = Stock::where('product_id', $existingPurchase->product_id)
                        ->where('branch_id', $existingPurchase->branch_id)
                        ->when($requestAttrs, function ($q) use ($requestAttrs) {
                            foreach ($requestAttrs as $k => $v) {
                                $q->where("attributes->{$k}", $v);
                            }
                        }, function ($q) {
                            $q->whereNull('attributes');
                        })
                        ->latest('id')
                        ->first();
                    $previousQty = $latestStockOverall ? $latestStockOverall->stock_qty : 0;

                    $existingStock = Stock::where('level_specific_id', $existingPurchase->id)
                        ->where('level_specific_type', Purchase::class)
                        ->first();

                    $requestBaseQty = (float)$request->product_unit_qty;

                    if ($existingStock) {
                        $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

                        if ($isLatest) {
                            $existingStock->current_qty += $requestBaseQty;
                            $existingStock->stock_qty += $requestBaseQty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $request->remarks;
                            $existingStock->save();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $existingPurchase->id;
                            $reversal->level_specific_type = Purchase::class;
                            $reversal->previous_qty = $previousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $previousQty - $existingStock->current_qty;
                            $reversal->attributes = $requestAttrs;
                            $reversal->remarks = 'Reversal of purchase #'.$existingPurchase->id;
                            $reversal->save();

                            $baseProductUnitId = (int)$request->product_unit_id;

                            $stock = new Stock();
                            $stock->branch_id = $existingPurchase->branch_id;
                            $stock->product_id = $existingPurchase->product_id;
                            $stock->unit_id = $baseProductUnitId;
                            $stock->level_id = $level ? $level->id : 1;
                            $stock->level_specific_id = $existingPurchase->id;
                            $stock->level_specific_type = Purchase::class;
                            $stock->previous_qty = $reversal->stock_qty;
                            $stock->current_qty = $existingStock->current_qty + $requestBaseQty;
                            $stock->stock_qty = $reversal->stock_qty + $existingStock->current_qty + $requestBaseQty;
                            $stock->attributes = $requestAttrs;
                            $stock->remarks = $request->remarks;
                            $stock->save();
                        }

                        $this->updateProductStockStatus($request->product);
                    } else {
                        $baseProductUnitId = (int)$request->product_unit_id;

                        $stock = new Stock();
                        $stock->branch_id = $existingPurchase->branch_id;
                        $stock->product_id = $existingPurchase->product_id;
                        $stock->unit_id = $baseProductUnitId;
                        $stock->level_id = $level ? $level->id : 1;
                        $stock->level_specific_id = $existingPurchase->id;
                        $stock->level_specific_type = Purchase::class;
                        $stock->previous_qty = $previousQty;
                        $stock->current_qty = $requestBaseQty;
                        $stock->stock_qty = $previousQty + $requestBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $request->remarks;
                        $stock->save();

                        $this->updateProductStockStatus($request->product);
                    }
                }

                if ($partialReceivedStatus && (int)$request->status === $partialReceivedStatus->id) {
                    Purchase::handlePartialReceivedLogic($existingPurchase->invoice_no, Auth::id());
                }

            event(new PurchaseUpdate($existingPurchase));

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase updated successfully',
                'purchase' => $existingPurchase
            ]);
        }
    }

        $purchase = new Purchase();
        $purchase->invoice_no = $request->invoice_no;
        $purchase->status_id = $request->status;
        $purchase->supplier_id = $request->supplier;
        $purchase->branch_id = $request->branch;
        $purchase->product_id = $request->product;
        $purchase->unit_id = $request->unit;
        $purchase->qty = $request->qty;
        $purchase->product_unit_id = $request->product_unit_id;
        $purchase->user_id = Auth::id();
        $purchase->product_unit_qty = $request->product_unit_qty;
        $purchase->price = $request->price;
        $purchase->vat = $request->vat;
        $purchase->discount = $request->discount;
        $purchase->remarks = $request->remarks;
        $purchase->attributes = $requestAttrs;
        $purchase->save();

        $purchaseParent = Status::where('name', 'purchase')->first();

        if ($purchaseParent) {
            $receivedStatus = Status::where('name', 'received')->where('parent_id', $purchaseParent->id)->first();
            $partialReceivedStatus = Status::where('name', 'partial received')->where('parent_id', $purchaseParent->id)->first();

            if (($receivedStatus && (int)$request->status === $receivedStatus->id) ||($partialReceivedStatus && (int)$request->status === $partialReceivedStatus->id)) {
                $level = Level::where('name', 'purchase')->first();
                $attrs = $purchase->attributes;
                $latestStock = Stock::where('product_id', $purchase->product_id)
                    ->where('branch_id', $purchase->branch_id)
                    ->when($attrs, function ($q) use ($attrs) {
                        foreach ($attrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $baseProductUnitId = (int)$purchase->product_unit_id;
                $baseQty = (float)$purchase->product_unit_qty;

                $stock = new Stock();
                $stock->branch_id = $purchase->branch_id;
                $stock->product_id = $purchase->product_id;
                $stock->unit_id = $baseProductUnitId;
                $stock->level_id = $level ? $level->id : 1;
                $stock->level_specific_id = $purchase->id;
                $stock->level_specific_type = Purchase::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = $baseQty;
                $stock->stock_qty = $previousQty + $baseQty;
                $stock->attributes = $attrs;
                $stock->remarks = $purchase->remarks;
                $stock->save();

                $this->updateProductStockStatus($purchase->product_id);
            }

            if ($partialReceivedStatus && (int)$request->status === $partialReceivedStatus->id) {
                Purchase::handlePartialReceivedLogic($purchase->invoice_no, Auth::id());
            }
        }

        event(new PurchaseUpdate($purchase));

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase created successfully',
            'purchase' => $purchase
        ]);
    }

    #[OA\Post(
        path: "/purchases/update/{id}",
        summary: "Update purchase",
        tags: ["Purchases"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["invoice_no", "status", "branch", "product", "product_unit_id", "product_unit_qty", "price", "vat"],
                properties: [
                    new OA\Property(property: "invoice_no", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "supplier", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_unit_id", type: "integer"),
                    new OA\Property(property: "product_unit_qty", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Purchase updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, int $id)
    {
        $request->validate([
            'invoice_no' => 'required|string|max:16',
            'status' => 'required',
            'branch' => 'required',
            'product' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'product_unit_id' => 'required',
            'product_unit_qty' => 'required|numeric|max:99999999.999',
            'price' => 'required|numeric|max:9999999999.999',
            'vat' => 'required|numeric|max:9999999999.999',
            'discount' => 'max:999999999.999',
            'remarks' => 'max:500',
            'attributes' => 'nullable|json',
        ]);

        $purchase = Purchase::findOrFail($id);

        $newAttrs = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;

        $purchaseParent = Status::where('name', 'purchase')->first();
        $level = Level::where('name', 'purchase')->first();
        $levelId = $level ? $level->id : 1;

        if ($purchaseParent) {
            $receivedStatus = Status::where('name', 'received')->where('parent_id', $purchaseParent->id)->first();
            if ($receivedStatus && (int)$request->status === $receivedStatus->id) {
                $existingGrandTotal = Purchase::getGrandTotalByInvoice($purchase->invoice_no);
                $oldLineTotal = ($purchase->qty * $purchase->price) + ($purchase->vat ?? 0) - ($purchase->discount ?? 0);
                $newLineTotal = ($request->qty * $request->price) + ($request->vat ?? 0) - ($request->discount ?? 0);
                $adjustedGrandTotal = $existingGrandTotal - $oldLineTotal + $newLineTotal;
                $totalPaid = Purchase::getTotalPaidByInvoice($purchase->invoice_no);
                if ($totalPaid < $adjustedGrandTotal) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invoice is not fully paid. Complete the payment first.',
                    ], 422);
                }
            }

            $partialReceivedStatus = Status::where('name', 'partial received')->where('parent_id', $purchaseParent->id)->first();

            if ($receivedStatus || $partialReceivedStatus) {
                $newIsPartialReceived = (int)$request->status === $partialReceivedStatus->id;
                $newIsReceived = (int)$request->status === $receivedStatus->id;
                $isStockActive = $newIsReceived || $newIsPartialReceived;

                $existingStock = Stock::where('level_specific_id', $purchase->id)
                    ->where('level_specific_type', Purchase::class)
                    ->first();

                $latestStockOverall = Stock::where('product_id', $request->product)
                    ->where('branch_id', $request->branch)
                    ->when($newAttrs, function ($q) use ($newAttrs) {
                        foreach ($newAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStockOverall ? $latestStockOverall->stock_qty : 0;

                $updateBaseQty = (float)$request->product_unit_qty;

                if ($existingStock) {
                    $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

                    if ($isStockActive) {
                        if ($isLatest) {
                            $existingStock->branch_id = $request->branch;
                            $existingStock->product_id = $request->product;
                            $existingStock->unit_id = (int)$request->product_unit_id;
                            $existingStock->current_qty = $updateBaseQty;
                            $existingStock->stock_qty = $existingStock->previous_qty + $updateBaseQty;
                            $existingStock->attributes = $newAttrs;
                            $existingStock->remarks = $request->remarks;
                            $existingStock->save();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $purchase->id;
                            $reversal->level_specific_type = Purchase::class;
                            $reversal->previous_qty = $previousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $previousQty - $existingStock->current_qty;
                            $reversal->attributes = $newAttrs;
                            $reversal->remarks = 'Reversal of purchase #' . $purchase->id;
                            $reversal->save();

                            $stock = new Stock();
                            $stock->branch_id = $request->branch;
                            $stock->product_id = $request->product;
                            $stock->unit_id = (int)$request->product_unit_id;
                            $stock->level_id = $levelId;
                            $stock->level_specific_id = $purchase->id;
                            $stock->level_specific_type = Purchase::class;
                            $stock->previous_qty = $reversal->stock_qty;
                            $stock->current_qty = $updateBaseQty;
                            $stock->stock_qty = $reversal->stock_qty + $updateBaseQty;
                            $stock->attributes = $newAttrs;
                            $stock->remarks = $request->remarks;
                            $stock->save();
                        }

                        $this->updateProductStockStatus($request->product);
                    } elseif ($isLatest) {
                        $existingStock->delete();
                    } else {
                        $reversal = new Stock();
                        $reversal->branch_id = $existingStock->branch_id;
                        $reversal->product_id = $existingStock->product_id;
                        $reversal->unit_id = $existingStock->unit_id;
                        $reversal->level_id = $existingStock->level_id;
                        $reversal->level_specific_id = $purchase->id;
                        $reversal->level_specific_type = Purchase::class;
                        $reversal->previous_qty = $previousQty;
                        $reversal->current_qty = -$existingStock->current_qty;
                        $reversal->stock_qty = $previousQty - $existingStock->current_qty;
                        $reversal->attributes = $newAttrs;
                        $reversal->remarks = 'Reversal of purchase #' . $purchase->id;
                        $reversal->save();
                    }
                } elseif ($isStockActive) {
                    $stock = new Stock();
                    $stock->branch_id = $request->branch;
                    $stock->product_id = $request->product;
                    $stock->unit_id = (int)$request->product_unit_id;
                    $stock->level_id = $levelId;
                    $stock->level_specific_id = $purchase->id;
                    $stock->level_specific_type = Purchase::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = $updateBaseQty;
                    $stock->stock_qty = $previousQty + $updateBaseQty;
                    $stock->attributes = $newAttrs;
                    $stock->remarks = $request->remarks;
                    $stock->save();

                    $this->updateProductStockStatus($request->product);
                }
            }

            if ($partialReceivedStatus) {
                $newIsPartialReceived = (int)$request->status === $partialReceivedStatus->id;
                $oldIsPartialReceived = (int)$purchase->status_id === $partialReceivedStatus->id;

                if ($newIsPartialReceived) {
                    Purchase::handlePartialReceivedLogic($purchase->invoice_no, Auth::id());
                    $totalPaid = Purchase::getTotalPaidByInvoice($purchase->invoice_no);
                    if ($totalPaid >= Purchase::getGrandTotalByInvoice($purchase->invoice_no) && $receivedStatus) {
                        $request->merge(['status' => $receivedStatus->id]);
                    }
                } elseif ($oldIsPartialReceived) {
                    $existingCredit = Credit::where('invoice_no', $purchase->invoice_no)
                        ->where('credit_type', 'purchase')
                        ->first();

                    if ($existingCredit) {
                        if ($existingCredit->paid_amount > 0) {
                            $invoiceGrandTotal = Purchase::getGrandTotalByInvoice($purchase->invoice_no);
                            $existingCredit->updated_by = Auth::id();
                            $existingCredit->total_amount = $invoiceGrandTotal;
                            $existingCredit->due_amount = max(0, $invoiceGrandTotal - $existingCredit->paid_amount);
                            $existingCredit->save();
                            event(new CreditUpdate($existingCredit));
                        } else {
                            $existingCredit->delete();
                        }
                    }
                }
            }
        }

        $cancelledStatus = Status::where('name', 'cancelled')->where('parent_id', $purchaseParent->id)->first();
        $isCancelled = $cancelledStatus && (int)$request->status === $cancelledStatus->id;

        if ($isCancelled) {
            $existingStock = Stock::where('level_specific_id', $purchase->id)
                ->where('level_specific_type', Purchase::class)
                ->first();

            if ($existingStock) {
                $latestStock = Stock::where('product_id', $purchase->product_id)
                    ->where('branch_id', $purchase->branch_id)
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $cancelBaseQty = (float)$purchase->product_unit_qty;

                $stock = new Stock();
                $stock->branch_id = $purchase->branch_id;
                $stock->product_id = $purchase->product_id;
                $stock->unit_id = (int)$purchase->product_unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $purchase->id;
                $stock->level_specific_type = Purchase::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = -$cancelBaseQty;
                $stock->stock_qty = $previousQty - $cancelBaseQty;
                $stock->remarks = 'Purchase cancelled - reversal';
                $stock->save();

                $this->updateProductStockStatus($purchase->product_id);
            }

            $existingCredit = Credit::where('invoice_no', $purchase->invoice_no)
                ->where('credit_type', 'purchase')
                ->first();

            if ($existingCredit) {
                $existingCredit->delete();
            }

            $purchase->delete();

            event(new PurchaseUpdate($purchase));

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase cancelled successfully',
            ]);
        }

        $purchase->invoice_no = $request->invoice_no;
        $purchase->status_id = $request->status;
        $purchase->supplier_id = $request->supplier;
        $purchase->branch_id = $request->branch;
        $purchase->product_id = $request->product;
        $purchase->unit_id = $request->unit;
        $purchase->qty = $request->qty;
        $purchase->product_unit_id = $request->product_unit_id;
        $purchase->updated_by = Auth::id();
        $purchase->product_unit_qty = $request->product_unit_qty;
        $purchase->price = $request->price;
        $purchase->vat = $request->vat;
        $purchase->discount = $request->discount;
        $purchase->remarks = $request->remarks;
        $purchase->attributes = $newAttrs;
        $purchase->update();

        event(new PurchaseUpdate($purchase));

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase updated successfully',
            'data' => $purchase
        ]);
    }

    #[OA\Post(
        path: "/purchases/delete/{id}",
        tags: ["Purchases"],
        summary: "Delete purchase",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Purchase deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {
        $purchase = Purchase::findOrFail($id);

        $existingStock = Stock::where('level_specific_id', $purchase->id)
            ->where('level_specific_type', Purchase::class)
            ->first();

        if ($existingStock) {
            $attrs = $purchase->attributes;
            $latestStock = Stock::where('product_id', $purchase->product_id)
                ->where('branch_id', $purchase->branch_id)
                ->when($attrs, function ($q) use ($attrs) {
                    foreach ($attrs as $k => $v) {
                        $q->where("attributes->{$k}", $v);
                    }
                }, function ($q) {
                    $q->whereNull('attributes');
                })
                ->latest('id')
                ->first();
            $previousQty = $latestStock ? $latestStock->stock_qty : 0;

            $destroyBaseQty = (float)$purchase->product_unit_qty;

            $stock = new Stock();
            $stock->branch_id = $purchase->branch_id;
            $stock->product_id = $purchase->product_id;
            $stock->unit_id = (int)$purchase->product_unit_id;
            $stock->level_id = $existingStock->level_id;
            $stock->level_specific_id = $purchase->id;
            $stock->level_specific_type = Purchase::class;
            $stock->previous_qty = $previousQty;
            $stock->current_qty = -$destroyBaseQty;
            $stock->stock_qty = $previousQty - $destroyBaseQty;
            $stock->attributes = $attrs;
            $stock->remarks = 'Purchase deleted - reversal';
            $stock->save();

            $this->updateProductStockStatus($purchase->product_id);
        }

        $purchaseData = $purchase->toArray();

        $purchase->delete();

        event(new PurchaseUpdate($purchaseData));

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['invoice_nos' => 'required|array', 'invoice_nos.*' => 'string']);

        $purchases = Purchase::whereIn('invoice_no', $request->invoice_nos)->get();

        foreach ($purchases as $purchase) {
            $existingStock = \Modules\Stock\Models\Stock::where('level_specific_id', $purchase->id)
                ->where('level_specific_type', Purchase::class)
                ->first();

            if ($existingStock) {
                $attrs = $purchase->attributes;
                $latestStock = \Modules\Stock\Models\Stock::where('product_id', $purchase->product_id)
                    ->where('branch_id', $purchase->branch_id)
                    ->when($attrs, function ($q) use ($attrs) {
                        foreach ($attrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $stock = new \Modules\Stock\Models\Stock();
                $stock->branch_id = $purchase->branch_id;
                $stock->product_id = $purchase->product_id;
                $stock->unit_id = (int)$purchase->product_unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $purchase->id;
                $stock->level_specific_type = Purchase::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = -(float)$purchase->product_unit_qty;
                $stock->stock_qty = $previousQty - (float)$purchase->product_unit_qty;
                $stock->attributes = $attrs;
                $stock->remarks = 'Purchase deleted - reversal';
                $stock->save();

                $this->updateProductStockStatus($purchase->product_id);
            }

            $purchaseData = $purchase->toArray();
            $purchase->delete();
            event(new \Modules\Purchase\Events\PurchaseUpdate($purchaseData));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->invoice_nos) . ' invoices deleted successfully'
        ]);
    }

    public function byInvoice(string $invoiceNo)
    {
        $purchases = Purchase::with(['branch', 'status', 'product', 'supplier', 'user', 'updatedBy', 'unit', 'oldUnit'])
            ->where('invoice_no', $invoiceNo)
            ->get();

        return response()->json([
            'status' => 'success',
            'purchases' => $purchases
        ]);
    }

    public function destroyByInvoice(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string',
        ]);

        $purchases = Purchase::where('invoice_no', $request->invoice_no)->get();

        if ($purchases->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No purchases found for this invoice'
            ], 404);
        }

        foreach ($purchases as $purchase) {
            $existingStock = Stock::where('level_specific_id', $purchase->id)
                ->where('level_specific_type', Purchase::class)
                ->first();

            if ($existingStock) {
                $attrs = $purchase->attributes;
                $latestStock = Stock::where('product_id', $purchase->product_id)
                    ->where('branch_id', $purchase->branch_id)
                    ->when($attrs, function ($q) use ($attrs) {
                        foreach ($attrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $delBaseQty = (float)$purchase->product_unit_qty;

                $stock = new Stock();
                $stock->branch_id = $purchase->branch_id;
                $stock->product_id = $purchase->product_id;
                $stock->unit_id = (int)$purchase->product_unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $purchase->id;
                $stock->level_specific_type = Purchase::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = -$delBaseQty;
                $stock->stock_qty = $previousQty - $delBaseQty;
                $stock->attributes = $attrs;
                $stock->remarks = 'Purchase deleted - reversal';
                $stock->save();

                $this->updateProductStockStatus($purchase->product_id);
            }

            $purchaseData = $purchase->toArray();
            $purchase->delete();
            event(new PurchaseUpdate($purchaseData));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($purchases) . ' purchases deleted successfully'
        ]);
    }
}
