<?php

namespace Modules\SupplierReturn\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\Purchase\Models\Purchase;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\SupplierReturn\Events\SupplierReturnUpdate;
use Modules\SupplierReturn\Models\SupplierReturn;
use Modules\Unit\Models\Unit;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "SupplierReturns")]
class SupplierReturnController extends Controller
{
    #[OA\Get(
        path: "/supplier_returns",
        tags: ["SupplierReturns"],
        summary: "List supplier return products",
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "products",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "status",
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
                description: "Supplier return products fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "supplierReturns", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "statuses", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "products", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "purchases", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "units", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function show(int $id)
    {
        $supplierReturn = SupplierReturn::with(['status', 'product', 'purchase', 'user', 'unit', 'oldUnit', 'branch'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'supplierReturn' => $supplierReturn
        ]);
    }

    public function index(Request $request)
    {
        $supplierReturns = SupplierReturn::with(['status', 'product', 'purchase', 'user', 'updatedBy', 'unit', 'oldUnit', 'branch'])
        ->when($request->search, function ($query) use ($request) {
            $query->where(function ($q) use ($request) {
                $q->orWhereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                })
                ->orWhereHas('purchase', function ($purchaseQuery) use ($request) {
                    $purchaseQuery->where('invoice_no', 'like', "%{$request->search}%");
                })
                ->orWhereHas('status', function ($statusQuery) use ($request) {
                    $statusQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($request) {
                    $branchQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
                });
            });
        })
        ->when($request->products, function ($query) use ($request) {
            $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
            $query->whereIn('product_id', $productIds);
        })
        ->when($request->status, function ($query) use ($request) {
            $query->where('status_id', $request->status);
        })
        ->when($request->branch, function ($query) use ($request) {
            $query->where('branch_id', $request->branch);
        })
        ->latest('id')
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $supplierStatus = Status::where('name', 'supplier return')->first();
        $statuses = $supplierStatus ? Status::where('parent_id', $supplierStatus->id)->get() : [];
        $products = Product::all();
        $purchases = Purchase::with('product')->get();
        $units = Unit::all();
        $branches = Branch::all();

        return response()->json([
            'status' => 'success',
            'supplierReturns' => $supplierReturns,
            'statuses' => $statuses,
            'products' => $products,
            'purchases' => $purchases,
            'units' => $units,
            'branches' => $branches,
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = SupplierReturn::min('created_at');

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

        $supplierReturns = SupplierReturn::with(['status', 'product', 'purchase', 'user', 'updatedBy', 'unit', 'oldUnit', 'branch'])
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->orWhereHas('product', function ($productQuery) use ($request) {
                        $productQuery->where('name', 'like', "%{$request->search}%")
                        ->orWhere('barcode', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('purchase', fn($pq) => $pq->where('invoice_no', 'like', "%{$request->search}%"))
                    ->orWhereHas('status', fn($sq) => $sq->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('branch', fn($bq) => $bq->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$request->search}%"));
                });
            })
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->products, function ($query) use ($request) {
                $ids = is_array($request->products) ? $request->products : explode(',', $request->products);
                $query->whereIn('product_id', $ids);
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->latest('id')
            ->get();

        $data = $supplierReturns->map(fn($r) => [
            'ID' => $r->id,
            'Product' => $r->product?->name ?? '-',
            'Purchase Invoice' => $r->purchase?->invoice_no ?? '-',
            'Branch' => $r->branch?->name ?? '-',
            'Qty' => $r->qty,
            'Status' => $r->status?->name ?? '-',
            'Remarks' => $r->remarks ?? '-',
            'Created By' => $r->user?->name ?? '-',
            'Updated By' => $r->updatedBy?->name ?? '-',
            'Created At' => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $r->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Purchase Invoice', 'Branch', 'Qty', 'Status', 'Remarks', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'supplier_returns_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'SupplierReturns', 'supplier_returns');

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

    #[OA\Post(
        path: "/supplier_returns",
        summary: "Create new supplier return product",
        tags: ["SupplierReturns"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product", "purchase", "status", "product_unit_qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "purchase", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "product_unit_id", type: "integer"),
                    new OA\Property(property: "product_unit_qty", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Supplier Return product created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'product' => 'required',
            'purchase' => 'required',
            'status' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'product_unit_id' => 'required',
            'branch' => 'required',
            'product_unit_qty' => 'required|numeric|max:9999999999.999',
            'attributes' => 'nullable|json',
            'remarks' => 'max:500'
        ]);

        $purchase = Purchase::findOrFail($request->purchase);
        $alreadyReturned = SupplierReturn::where('purchase_id', $request->purchase)->sum('product_unit_qty');
        $totalReturned = (float)$alreadyReturned + (float)$request->product_unit_qty;
        if ($totalReturned > (float)$purchase->product_unit_qty) {
            $remaining = max(0, (float)$purchase->product_unit_qty - (float)$alreadyReturned);
            return response()->json([
                'status' => 'error',
                'message' => 'Return quantity exceeds purchase quantity',
                'errors' => ['product_unit_qty' => ["Return qty ($request->product_unit_qty) exceeds remaining purchase qty ($remaining)"]]
            ], 422);
        }

        $requestAttrs = null;
        $attributes = $request->input('attributes');
        if ($attributes) {
            $requestAttrs = is_string($attributes) ? json_decode($attributes, true) : (array) $attributes;
            if (!is_array($requestAttrs)) {
                $requestAttrs = null;
            }
        }
        if (is_array($requestAttrs) && empty($requestAttrs)) {
            $requestAttrs = null;
        }

        $stockQty = $this->getStockQty((int)$request->product, (int)$request->branch, $requestAttrs ?? []);
        if ((float)$request->product_unit_qty > $stockQty) {
            return response()->json([
                'status' => 'error',
                'message' => 'Return quantity exceeds available stock',
                'errors' => ['product_unit_qty' => ["Return qty ($request->product_unit_qty) exceeds available stock ($stockQty)"]]
            ], 422);
        }

        $supplierReturn = new SupplierReturn();
        $supplierReturn->product_id = $request->product;
        $supplierReturn->unit_id = $request->unit;
        $supplierReturn->qty = $request->qty;
        $supplierReturn->product_unit_id = $request->product_unit_id;
        $supplierReturn->purchase_id = $request->purchase;
        $supplierReturn->status_id = $request->status;
        $supplierReturn->branch_id = $request->branch;
        $supplierReturn->user_id = Auth::id();
        $supplierReturn->product_unit_qty = $request->product_unit_qty;
        $supplierReturn->attributes = $requestAttrs;
        $supplierReturn->remarks = $request->remarks;
        $supplierReturn->save();

        $supplierReturnParent = Status::where('name', 'supplier return')->first();
        if ($supplierReturnParent) {
            $completedStatus = Status::where('name', 'completed')
                ->where('parent_id', $supplierReturnParent->id)
                ->first();

            if ($completedStatus && (int)$request->status === $completedStatus->id) {
                $level = Level::where('name', 'supplier return')->first();

                if ($level) {
                    $latestStock = Stock::where('product_id', $supplierReturn->product_id)
                        ->where('branch_id', $supplierReturn->branch_id);
                    if (is_array($requestAttrs)) {
                        foreach ($requestAttrs as $k => $v) {
                            $latestStock->where("attributes->{$k}", $v);
                        }
                    } else {
                        $latestStock->whereNull('attributes');
                    }
                    $latestStock = $latestStock->latest('id')->first();
                    $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                    $srBaseQty = (float)$supplierReturn->product_unit_qty;

                    $stock = new Stock();
                    $stock->branch_id = $supplierReturn->branch_id;
                    $stock->product_id = $supplierReturn->product_id;
                    $stock->unit_id = (int)$supplierReturn->product_unit_id;
                    $stock->level_id = $level->id;
                    $stock->level_specific_id = $supplierReturn->id;
                    $stock->level_specific_type = SupplierReturn::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = -$srBaseQty;
                    $stock->stock_qty = $previousQty - $srBaseQty;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $supplierReturn->remarks;
                    $stock->save();
                }
            }
        }

        event(new SupplierReturnUpdate($supplierReturn));

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier return created successfully',
            'supplierReturn' => $supplierReturn
        ]);
    }

    #[OA\Post(
        path: "/supplier_returns/update/{id}",
        summary: "Update supplier return product",
        tags: ["SupplierReturns"],
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
                required: ["product", "purchase", "status", "product_unit_qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "purchase", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "product_unit_id", type: "integer"),
                    new OA\Property(property: "product_unit_qty", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Supplier return product updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update (Request $request, int $id) {
        $request->validate([
            'product' => 'required',
            'purchase' => 'required',
            'status' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'product_unit_id' => 'required',
            'branch' => 'required',
            'product_unit_qty' => 'required|numeric|max:9999999999.999',
            'attributes' => 'nullable|json',
            'remarks' => 'max:500'
        ]);

        $purchase = Purchase::findOrFail($request->purchase);
        $alreadyReturned = SupplierReturn::where('purchase_id', $request->purchase)
            ->where('id', '!=', $id)
            ->sum('product_unit_qty');
        $totalReturned = (float)$alreadyReturned + (float)$request->product_unit_qty;
        if ($totalReturned > (float)$purchase->product_unit_qty) {
            $remaining = max(0, (float)$purchase->product_unit_qty - (float)$alreadyReturned);
            return response()->json([
                'status' => 'error',
                'message' => 'Return quantity exceeds purchase quantity',
                'errors' => ['product_unit_qty' => ["Return qty ($request->product_unit_qty) exceeds remaining purchase qty ($remaining)"]]
            ], 422);
        }

        $requestAttrs = null;
        $attributes = $request->input('attributes');
        if ($attributes) {
            $requestAttrs = is_string($attributes) ? json_decode($attributes, true) : (array) $attributes;
            if (!is_array($requestAttrs)) {
                $requestAttrs = null;
            }
        }
        if (is_array($requestAttrs) && empty($requestAttrs)) {
            $requestAttrs = null;
        }

        $supplierReturn = SupplierReturn::findOrFail($id);
        $oldStatusId = $supplierReturn->status_id;
        $oldQty = (float)$supplierReturn->product_unit_qty;
        $oldAttrs = $supplierReturn->attributes;

        $attrsSame = $requestAttrs == $oldAttrs;
        $availableQty = $this->getStockQty((int)$request->product, (int)$request->branch, $requestAttrs ?? []);
        if ($attrsSame) {
            $availableQty += $oldQty;
        }
        if ((float)$request->product_unit_qty > $availableQty) {
            return response()->json([
                'status' => 'error',
                'message' => 'Return quantity exceeds available stock',
                'errors' => ['product_unit_qty' => ["Return qty ($request->product_unit_qty) exceeds available stock ($availableQty)"]]
            ], 422);
        }

        $supplierReturn->product_id = $request->product;
        $supplierReturn->unit_id = $request->unit;
        $supplierReturn->qty = $request->qty;
        $supplierReturn->product_unit_id = $request->product_unit_id;
        $supplierReturn->purchase_id = $request->purchase;
        $supplierReturn->status_id = $request->status;
        $supplierReturn->branch_id = $request->branch;
        $supplierReturn->updated_by = Auth::id();
        $supplierReturn->product_unit_qty = $request->product_unit_qty;
        $supplierReturn->attributes = $requestAttrs;
        $supplierReturn->remarks = $request->remarks;
        $supplierReturn->update();

        $supplierReturnParent = Status::where('name', 'supplier return')->first();
        if ($supplierReturnParent) {
            $completedStatus = Status::where('name', 'completed')
                ->where('parent_id', $supplierReturnParent->id)
                ->first();

            if ($completedStatus) {
                $isCompleted = (int)$request->status === $completedStatus->id;

                $existingStock = Stock::where('level_specific_id', $supplierReturn->id)
                    ->where('level_specific_type', SupplierReturn::class)
                    ->latest('id')
                    ->first();

                if ($existingStock) {
                    $oldAttrsArray = is_array($oldAttrs) ? $oldAttrs : null;
                    $latestOldStock = Stock::where('product_id', $existingStock->product_id)
                        ->where('branch_id', $existingStock->branch_id);
                    if (is_array($oldAttrsArray)) {
                        foreach ($oldAttrsArray as $k => $v) {
                            $latestOldStock->where("attributes->{$k}", $v);
                        }
                    } else {
                        $latestOldStock->whereNull('attributes');
                    }
                    $latestOldStock = $latestOldStock->latest('id')->first();
                    $oldPreviousQty = $latestOldStock ? $latestOldStock->stock_qty : 0;

                    $latestNewStock = Stock::where('product_id', $supplierReturn->product_id)
                        ->where('branch_id', $supplierReturn->branch_id);
                    if (is_array($requestAttrs)) {
                        foreach ($requestAttrs as $k => $v) {
                            $latestNewStock->where("attributes->{$k}", $v);
                        }
                    } else {
                        $latestNewStock->whereNull('attributes');
                    }
                    $latestNewStock = $latestNewStock->latest('id')->first();
                    $newPreviousQty = $latestNewStock ? $latestNewStock->stock_qty : 0;

                    $isLatest = $latestNewStock && $existingStock->id === $latestNewStock->id;

                    if ($isCompleted) {
                        if ($isLatest && $existingStock->current_qty < 0) {
                            $stockQtyBefore = $existingStock->stock_qty - $existingStock->current_qty;
                            $existingStock->branch_id = $supplierReturn->branch_id;
                            $existingStock->product_id = $supplierReturn->product_id;
                            $existingStock->unit_id = $supplierReturn->product_unit_id;
                            $existingStock->previous_qty = $stockQtyBefore;
                            $existingStock->current_qty = -$supplierReturn->product_unit_qty;
                            $existingStock->stock_qty = $stockQtyBefore - $supplierReturn->product_unit_qty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $supplierReturn->remarks;
                            $existingStock->save();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $supplierReturn->id;
                            $reversal->level_specific_type = SupplierReturn::class;
                            $reversal->previous_qty = $oldPreviousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $oldPreviousQty - $existingStock->current_qty;
                            $reversal->attributes = $existingStock->attributes;
                            $reversal->remarks = 'Reversal of supplier return #' . $supplierReturn->id;
                            $reversal->save();

                            $level = Level::where('name', 'supplier return')->first();
                            $updateSrBaseQty = (float)$supplierReturn->product_unit_qty;

                            $stock = new Stock();
                            $stock->branch_id = $supplierReturn->branch_id;
                            $stock->product_id = $supplierReturn->product_id;
                            $stock->unit_id = (int)$supplierReturn->product_unit_id;
                            $stock->level_id = $level ? $level->id : 1;
                            $stock->level_specific_id = $supplierReturn->id;
                            $stock->level_specific_type = SupplierReturn::class;
                            $stock->current_qty = -$updateSrBaseQty;
                            $stock->attributes = $requestAttrs;
                            $stock->remarks = $supplierReturn->remarks;
                            if ($attrsSame) {
                                $stock->previous_qty = $reversal->stock_qty;
                                $stock->stock_qty = $reversal->stock_qty - $updateSrBaseQty;
                            } else {
                                $stock->previous_qty = $newPreviousQty;
                                $stock->stock_qty = $newPreviousQty - $updateSrBaseQty;
                            }
                            $stock->save();
                        }
                    } else {
                        if ($isLatest) {
                            $existingStock->delete();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $supplierReturn->id;
                            $reversal->level_specific_type = SupplierReturn::class;
                            $reversal->previous_qty = $oldPreviousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $oldPreviousQty - $existingStock->current_qty;
                            $reversal->attributes = $existingStock->attributes;
                            $reversal->remarks = 'Reversal of supplier return #' . $supplierReturn->id;
                            $reversal->save();
                        }
                    }
                } elseif ($isCompleted) {
                    $level = Level::where('name', 'supplier return')->first();
                    $latestNewStock = Stock::where('product_id', $supplierReturn->product_id)
                        ->where('branch_id', $supplierReturn->branch_id);
                    if (is_array($requestAttrs)) {
                        foreach ($requestAttrs as $k => $v) {
                            $latestNewStock->where("attributes->{$k}", $v);
                        }
                    } else {
                        $latestNewStock->whereNull('attributes');
                    }
                    $latestNewStock = $latestNewStock->latest('id')->first();
                    $newPreviousQty = $latestNewStock ? $latestNewStock->stock_qty : 0;

                    if ($level) {
                        $updateSrNewBaseQty = (float)$supplierReturn->product_unit_qty;

                        $stock = new Stock();
                        $stock->branch_id = $supplierReturn->branch_id;
                        $stock->product_id = $supplierReturn->product_id;
                        $stock->unit_id = (int)$supplierReturn->product_unit_id;
                        $stock->level_id = $level->id;
                        $stock->level_specific_id = $supplierReturn->id;
                        $stock->level_specific_type = SupplierReturn::class;
                        $stock->previous_qty = $newPreviousQty;
                        $stock->current_qty = -$updateSrNewBaseQty;
                        $stock->stock_qty = $newPreviousQty - $updateSrNewBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $supplierReturn->remarks;
                        $stock->save();
                    }
                }
            }
        }

        event(new SupplierReturnUpdate($supplierReturn));

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier return updated successfully',
            'supplierReturn' => $supplierReturn
        ]);
    }

    #[OA\Post(
        path: "/supplier_returns/delete/{id}",
        tags: ["SupplierReturns"],
        summary: "Delete supplier return product",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the supplier return product",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Supplier return product deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {
        $supplierReturn = SupplierReturn::findOrFail($id);

        $existingStock = Stock::where('level_specific_id', $supplierReturn->id)
            ->where('level_specific_type', SupplierReturn::class)
            ->first();

        if ($existingStock) {
            $latestStock = Stock::where('product_id', $supplierReturn->product_id)
                ->where('branch_id', $supplierReturn->branch_id);
            $stockAttrs = is_string($existingStock->attributes) ? json_decode($existingStock->attributes, true) : $existingStock->attributes;
            if (is_array($stockAttrs) && !empty($stockAttrs)) {
                foreach ($stockAttrs as $k => $v) {
                    $latestStock->where("attributes->{$k}", $v);
                }
            } else {
                $latestStock->whereNull('attributes');
            }
            $latestStock = $latestStock->latest('id')->first();
            $previousQty = $latestStock ? $latestStock->stock_qty : 0;

            $destroySrBaseQty = (float)$supplierReturn->product_unit_qty;

            $stock = new Stock();
            $stock->branch_id = $supplierReturn->branch_id;
            $stock->product_id = $supplierReturn->product_id;
            $stock->unit_id = (int)$supplierReturn->product_unit_id;
            $stock->level_id = $existingStock->level_id;
            $stock->level_specific_id = $supplierReturn->id;
            $stock->level_specific_type = SupplierReturn::class;
            $stock->previous_qty = $previousQty;
            $stock->current_qty = $destroySrBaseQty;
            $stock->stock_qty = $previousQty + $destroySrBaseQty;
            $stock->attributes = $existingStock->attributes;
            $stock->remarks = 'Supplier return deleted - reversal';
            $stock->save();
        }

        $supplierReturnData = $supplierReturn->toArray();

        $supplierReturn->delete();

        event(new SupplierReturnUpdate($supplierReturnData));

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier return product deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:supplier_returns,id']);
        $items = SupplierReturn::whereIn('id', $request->ids)->get();
        foreach ($items as $supplierReturn) {
            $existingStock = Stock::where('level_specific_id', $supplierReturn->id)
                ->where('level_specific_type', SupplierReturn::class)
                ->first();

            if ($existingStock) {
                $latestStock = Stock::where('product_id', $supplierReturn->product_id)
                    ->where('branch_id', $supplierReturn->branch_id);
                $stockAttrs = is_string($existingStock->attributes) ? json_decode($existingStock->attributes, true) : $existingStock->attributes;
                if (is_array($stockAttrs) && !empty($stockAttrs)) {
                    foreach ($stockAttrs as $k => $v) {
                        $latestStock->where("attributes->{$k}", $v);
                    }
                } else {
                    $latestStock->whereNull('attributes');
                }
                $latestStock = $latestStock->latest('id')->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $destroySrBaseQty = (float)$supplierReturn->product_unit_qty;

                $stock = new Stock();
                $stock->branch_id = $supplierReturn->branch_id;
                $stock->product_id = $supplierReturn->product_id;
                $stock->unit_id = (int)$supplierReturn->product_unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $supplierReturn->id;
                $stock->level_specific_type = SupplierReturn::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = $destroySrBaseQty;
                $stock->stock_qty = $previousQty + $destroySrBaseQty;
                $stock->attributes = $existingStock->attributes;
                $stock->remarks = 'Supplier return deleted - reversal';
                $stock->save();
            }

            $supplierReturn->delete();
            event(new SupplierReturnUpdate($supplierReturn->toArray()));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
