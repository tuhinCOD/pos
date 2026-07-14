<?php

namespace Modules\Damage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Damage\Events\DamageUpdate;
use Modules\Damage\Models\Damage;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\Repair\Models\Repair;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\Unit\Models\Unit;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "Damages")]
class DamageController extends Controller
{
    #[OA\Get(
        path: "/damages",
        tags: ["Damages"],
        summary: "List damage products",
        description: "Get paginated damage products, with optional search by product",
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Search term",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Pagination",
                required: false,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Damage products fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "branch", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $damages = Damage::with(['status', 'product', 'branch', 'user', 'unit', 'updatedBy'])
        ->when($request->search, function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->orWhereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($search) {
                    $branchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%");
                })
                ->orWhereHas('status', function ($statusQuery) use ($search) {
                    $statusQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('remarks', 'like', "%{$search}%");
            });
        })
        ->when($request->products, function ($query) use ($request) {
            $productIds = explode(',', $request->products);
            $query->whereIn('product_id', $productIds);
        })
        ->when($request->status, function ($query) use ($request) {
            $query->where('status_id', $request->status);
        })
        ->when($request->branch, function ($query) use ($request) {
            $query->where('branch_id', $request->branch);
        })
        ->orderBy('id', 'desc')
        ->paginate($request->perPage ?? 20)->onEachSide(0);

        $damageStatus = Status::where('name', 'damage')->first();
        $statuses = $damageStatus ? Status::where('parent_id', $damageStatus->id)->get() : [];
        $units = Unit::all();
        $products = Product::all();
        $branches = Branch::all();

        return response()->json([
            'status' => 'success',
            'damages' => $damages,
            'statuses' => $statuses,
            'products' => $products,
            'branches' => $branches,
            'units' => $units
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Damage::min('created_at');

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

        $damages = Damage::with(['status', 'product', 'branch', 'user', 'unit', 'updatedBy'])
            ->when($request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('branch', fn($bq) => $bq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('status', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('unit', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhere('remarks', 'like', "%{$search}%");
                });
            })
            ->when($request->products, function ($query) use ($request) {
                $ids = explode(',', $request->products);
                $query->whereIn('product_id', $ids);
            })
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->branch, fn($q) => $q->where('branch_id', $request->branch))
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->orderBy('id', 'desc')
            ->get();

        $data = $damages->map(fn($d) => [
            'ID' => $d->id,
            'Product' => $d->product?->name ?? '-',
            'Branch' => $d->branch?->name ?? '-',
            'Qty' => $d->qty,
            'Unit' => $d->unit?->name ?? '-',
            'Status' => $d->status?->name ?? '-',
            'Remarks' => $d->remarks ?? '-',
            'Created By' => $d->user?->name ?? '-',
            'Updated By' => $d->updatedBy?->name ?? '-',
            'Created At' => $d->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $d->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Branch', 'Qty', 'Unit', 'Status', 'Remarks', 'Created By', 'Updated By', 'Created At', 'Updated At'];
        $filename = 'damages_' . now()->timestamp . '.xlsx';

        ExportData::dispatch($data, $headings, $filename, 'Damages', 'damages');

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

    public function show(int $id)
    {
        $damage = Damage::with(['status', 'product', 'branch', 'user', 'unit', 'updatedBy'])->findOrFail($id);

        $damageStatus = Status::where('name', 'damage')->first();
        $statuses = $damageStatus ? Status::where('parent_id', $damageStatus->id)->get() : [];
        $units = Unit::all();
        $products = Product::all();
        $branches = Branch::all();

        return response()->json([
            'status' => 'success',
            'damage' => $damage,
            'statuses' => $statuses,
            'products' => $products,
            'branches' => $branches,
            'units' => $units
        ]);
    }

    #[OA\Post(
        path: "/damages",
        summary: "Create new damage product",
        tags: ["Damages"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product", "branch", "status", "qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Damage product created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'product' => 'required',
            'unit' => 'required',
            'branch' => 'required',
            'status' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'attributes' => 'nullable|json',
            'remarks' => 'max:500'
        ]);

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

        $damageStatus = Status::where('name', 'damage')->first();
        $sellableStatus = null;
        $sellableDiscountStatus = null;
        $damagedStatus = null;
        $repairingStatus = null;
        $repairedStatus = null;
        $isSellable = false;
        $isSellableDiscount = false;
        $isDamaged = false;
        $isRepairing = false;
        $isRepaired = false;
        $isStockOut = false;
        $isStockIn = false;

        if ($damageStatus) {
            $sellableStatus = Status::where('name', 'sellable')->where('parent_id', $damageStatus->id)->first();
            $sellableDiscountStatus = Status::where('name', 'sellable discount')->where('parent_id', $damageStatus->id)->first();
            $damagedStatus = Status::where('name', 'damaged')->where('parent_id', $damageStatus->id)->first();
            $repairingStatus = Status::where('name', 'repairing')->where('parent_id', $damageStatus->id)->first();
            $repairedStatus = Status::where('name', 'repaired')->where('parent_id', $damageStatus->id)->first();

            $statusId = (int)$request->status;
            $isSellable = $sellableStatus && $statusId === $sellableStatus->id;
            $isSellableDiscount = $sellableDiscountStatus && $statusId === $sellableDiscountStatus->id;
            $isDamaged = $damagedStatus && $statusId === $damagedStatus->id;
            $isRepairing = $repairingStatus && $statusId === $repairingStatus->id;
            $isRepaired = $repairedStatus && $statusId === $repairedStatus->id;

            $isStockOut = $isDamaged || $isRepairing || $isRepaired;
            $isStockIn = $isSellable || $isSellableDiscount;

            if ($isStockOut) {
                $availableQty = $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                if ($availableQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                    ], 422);
                }
            }
        }

        $damage = new Damage();
        $damage->status_id = $request->status;
        $damage->branch_id = $request->branch;
        $damage->product_id = $request->product;
        $damage->unit_id = $request->unit;
        $damage->user_id = Auth::id();
        $damage->qty = $request->qty;
        $damage->attributes = $requestAttrs;
        $damage->remarks = $request->remarks;
        $damage->save();

        if ($damageStatus && ($isStockOut || $isStockIn)) {
            $level = Level::where('name', 'damage')->first();
            $levelId = $level ? $level->id : 5;

            $latestStock = Stock::where('product_id', $request->product)
                ->where('branch_id', $request->branch)
                ->when($requestAttrs, function ($q) use ($requestAttrs) {
                    foreach ($requestAttrs as $k => $v) {
                        $q->where("attributes->{$k}", $v);
                    }
                })
                ->when(!$requestAttrs, fn($q) => $q->whereNull('attributes'))
                ->latest('id')
                ->first();
            $previousQty = $latestStock ? $latestStock->stock_qty : 0;

            $damageBaseQty = (float)$request->qty;

            $stock = new Stock();
            $stock->branch_id = $request->branch;
            $stock->product_id = $request->product;
            $stock->unit_id = (int)$request->unit;
            $stock->level_id = $levelId;
            $stock->level_specific_id = $damage->id;
            $stock->level_specific_type = Damage::class;
            $stock->previous_qty = $previousQty;
            $stock->current_qty = $isStockIn ? $damageBaseQty : -$damageBaseQty;
            $stock->stock_qty = $isStockIn ? $previousQty + $damageBaseQty : $previousQty - $damageBaseQty;
            $stock->attributes = $damage->attributes;
            $stock->remarks = $request->remarks;
            $stock->save();

            $this->updateProductStockStatus($request->product);
        }

        if ($isRepairing) {
            $repairStatus = Status::where('name', 'repair')->first();
            $repairPendingStatus = Status::where('name', 'pending')->where('parent_id', $repairStatus->id)->first();

            $repair = new Repair();
            $repair->damage_id = $damage->id;
            $repair->status_id = $repairPendingStatus->id;
            $repair->product_id = $request->product;
            $repair->branch_id = $request->branch;
            $repair->unit_id = $request->unit;
            $repair->user_id = Auth::id();
            $repair->qty = $request->qty;
            $repair->save();
        }

        event(new DamageUpdate($damage));

        return response()->json([
            'status' => 'success',
            'message' => 'Damage product created successfully',
            'damage' => $damage
        ]);
    }

    #[OA\Post(
        path: "/damages/update/{id}",
        summary: "Damage return product",
        tags: ["Damages"],
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
                required: ["product", "branch", "status", "qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Damage product updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'product' => 'required',
            'unit' => 'required',
            'branch' => 'required',
            'status' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'attributes' => 'nullable|json',
            'remarks' => 'max:500'
        ]);

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

        $damage = Damage::findOrFail($id);
        $oldStatusId = $damage->status_id;
        $oldAttrs = $damage->attributes;

        $damageStatus = Status::where('name', 'damage')->first();
        if ($damageStatus) {
            $sellableStatus = Status::where('name', 'sellable')->where('parent_id', $damageStatus->id)->first();
            $sellableDiscountStatus = Status::where('name', 'sellable discount')->where('parent_id', $damageStatus->id)->first();
            $damagedStatus = Status::where('name', 'damaged')->where('parent_id', $damageStatus->id)->first();
            $repairingStatus = Status::where('name', 'repairing')->where('parent_id', $damageStatus->id)->first();
            $repairedStatus = Status::where('name', 'repaired')->where('parent_id', $damageStatus->id)->first();

            $statusId = (int)$request->status;
            $isSellable = $sellableStatus && $statusId === $sellableStatus->id;
            $isSellableDiscount = $sellableDiscountStatus && $statusId === $sellableDiscountStatus->id;
            $isDamaged = $damagedStatus && $statusId === $damagedStatus->id;
            $isRepairing = $repairingStatus && $statusId === $repairingStatus->id;
            $isRepaired = $repairedStatus && $statusId === $repairedStatus->id;

            $isStockOut = $isDamaged || $isRepairing || $isRepaired;
            $isStockIn = $isSellable || $isSellableDiscount;
            $currentType = $isStockIn ? 'stockin' : ($isStockOut ? 'stockout' : null);

            $oldType = null;
            if ($oldStatusId !== null) {
                $isOldSellable = $sellableStatus && $oldStatusId === $sellableStatus->id;
                $isOldSellableDiscount = $sellableDiscountStatus && $oldStatusId === $sellableDiscountStatus->id;
                $isOldDamaged = $damagedStatus && $oldStatusId === $damagedStatus->id;
                $isOldRepairing = $repairingStatus && $oldStatusId === $repairingStatus->id;
                $isOldRepaired = $repairedStatus && $oldStatusId === $repairedStatus->id;
                $isOldStockIn = $isOldSellable || $isOldSellableDiscount;
                $isOldStockOut = $isOldDamaged || $isOldRepairing || $isOldRepaired;
                $oldType = $isOldStockIn ? 'stockin' : ($isOldStockOut ? 'stockout' : null);
            }

            if ($isStockOut || $isStockIn) {
                $level = Level::where('name', 'damage')->first();
                $levelId = $level ? $level->id : 5;

                $existingStock = Stock::where('level_specific_id', $damage->id)
                    ->where('level_specific_type', Damage::class)
                    ->first();

                $oldAttrsArray = is_array($oldAttrs) ? $oldAttrs : null;
                $latestOldStock = Stock::where('product_id', $damage->product_id)
                    ->where('branch_id', $damage->branch_id)
                    ->when($oldAttrsArray, function ($q) use ($oldAttrsArray) {
                        foreach ($oldAttrsArray as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    })
                    ->when(!$oldAttrsArray, fn($q) => $q->whereNull('attributes'))
                    ->latest('id')
                    ->first();
                $oldPreviousQty = $latestOldStock ? $latestOldStock->stock_qty : 0;

                $latestNewStock = Stock::where('product_id', $request->product)
                    ->where('branch_id', $request->branch)
                    ->when($requestAttrs, function ($q) use ($requestAttrs) {
                        foreach ($requestAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    })
                    ->when(!$requestAttrs, fn($q) => $q->whereNull('attributes'))
                    ->latest('id')
                    ->first();
                $newPreviousQty = $latestNewStock ? $latestNewStock->stock_qty : 0;
                $isLatest = $existingStock && $latestNewStock && $existingStock->id === $latestNewStock->id;
                $attrsSame = $requestAttrs == $oldAttrsArray;

                $isCrossTypeChange = $oldType !== null && $currentType !== null && $oldType !== $currentType;

                if ($isCrossTypeChange) {
                if ($isStockOut) {
                    $mainQty = $damage->qty + $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                    if ($mainQty < $request->qty) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Insufficient stock',
                                'errors' => ['qty' => ["Not enough stock. Available: $mainQty, Required: $request->qty"]]
                            ], 422);
                        }
                    }

                    $damageBaseQty = (float)$request->qty;

                    $stock = new Stock();
                    $stock->branch_id = $request->branch;
                    $stock->product_id = $request->product;
                    $stock->unit_id = (int)$request->unit;
                    $stock->level_id = $levelId;
                    $stock->level_specific_id = $damage->id;
                    $stock->level_specific_type = Damage::class;
                    $stock->previous_qty = $newPreviousQty;
                    $stock->current_qty = $isStockIn ? $damageBaseQty : -$damageBaseQty;
                    $stock->stock_qty = $isStockIn ? $newPreviousQty + $damageBaseQty : $newPreviousQty - $damageBaseQty;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $request->remarks;
                    $stock->save();

                    $this->updateProductStockStatus($request->product);
                } elseif ($isStockOut) {
                    $mainQty = $damage->qty + $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                    if ($mainQty < $request->qty) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Insufficient stock',
                            'errors' => ['qty' => ["Not enough stock. Available: $mainQty, Required: $request->qty"]]
                        ], 422);
                    }
                    if ($existingStock) {
                        if ($isLatest) {
                            $existingStock->branch_id = $request->branch;
                            $existingStock->product_id = $request->product;
                            $existingStock->unit_id = $request->unit;
                            $existingStock->current_qty = -$request->qty;
                            $existingStock->stock_qty = $existingStock->previous_qty + $existingStock->current_qty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $request->remarks;
                            $existingStock->save();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $damage->id;
                            $reversal->level_specific_type = Damage::class;
                            $reversal->previous_qty = $oldPreviousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $oldPreviousQty - $existingStock->current_qty;
                            $reversal->attributes = $existingStock->attributes;
                            $reversal->remarks = 'Reversal of damage #'.$damage->id;
                            $reversal->save();
            
                            $damageBaseQty = (float)$request->qty;
                            $stock = new Stock();
                            $stock->branch_id = $request->branch;
                            $stock->product_id = $request->product;
                            $stock->unit_id = (int)$request->unit;
                            $stock->level_id = $levelId;
                            $stock->level_specific_id = $damage->id;
                            $stock->level_specific_type = Damage::class;
                            if ($attrsSame) {
                                $stock->previous_qty = $reversal->stock_qty;
                                $stock->current_qty = -$damageBaseQty;
                                $stock->stock_qty = $reversal->stock_qty - $damageBaseQty;
                            } else {
                                $stock->previous_qty = $newPreviousQty;
                                $stock->current_qty = -$damageBaseQty;
                                $stock->stock_qty = $newPreviousQty - $damageBaseQty;
                            }
                            $stock->attributes = $requestAttrs;
                            $stock->remarks = $request->remarks;
                            $stock->save();
                        }
                    } else {
                        $damageBaseQty = (float)$request->qty;

                        $stock = new Stock();
                        $stock->branch_id = $request->branch;
                        $stock->product_id = $request->product;
                        $stock->unit_id = (int)$request->unit;
                        $stock->level_id = $levelId;
                        $stock->level_specific_id = $damage->id;
                        $stock->level_specific_type = Damage::class;
                        $stock->previous_qty = $newPreviousQty;
                        $stock->current_qty = -$damageBaseQty;
                        $stock->stock_qty = $newPreviousQty - $damageBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $request->remarks;
                        $stock->save();
                    }

                    $this->updateProductStockStatus($request->product);
                } elseif ($isStockIn) {
                    if ($existingStock) {
                        if ($isLatest) {
                            $existingStock->branch_id = $request->branch;
                            $existingStock->product_id = $request->product;
                            $existingStock->unit_id = $request->unit;
                            $existingStock->current_qty = $request->qty;
                            $existingStock->stock_qty = $existingStock->previous_qty + $existingStock->current_qty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $request->remarks;
                            $existingStock->save();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $damage->id;
                            $reversal->level_specific_type = Damage::class;
                            $reversal->previous_qty = $oldPreviousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $oldPreviousQty - $existingStock->current_qty;
                            $reversal->attributes = $existingStock->attributes;
                            $reversal->remarks = 'Reversal of damage #'.$damage->id;
                            $reversal->save();
            
                            $damageBaseQty = (float)$request->qty;

                            $stock = new Stock();
                            $stock->branch_id = $request->branch;
                            $stock->product_id = $request->product;
                            $stock->unit_id = (int)$request->unit;
                            $stock->level_id = $levelId;
                            $stock->level_specific_id = $damage->id;
                            $stock->level_specific_type = Damage::class;
                            if ($attrsSame) {
                                $stock->previous_qty = $reversal->stock_qty;
                                $stock->current_qty = $damageBaseQty;
                                $stock->stock_qty = $reversal->stock_qty + $damageBaseQty;
                            } else {
                                $stock->previous_qty = $newPreviousQty;
                                $stock->current_qty = $damageBaseQty;
                                $stock->stock_qty = $newPreviousQty + $damageBaseQty;
                            }
                            $stock->attributes = $requestAttrs;
                            $stock->remarks = $request->remarks;
                            $stock->save();
                        }
                    } else {
                        $damageBaseQty = (float)$request->qty;

                        $stock = new Stock();
                        $stock->branch_id = $request->branch;
                        $stock->product_id = $request->product;
                        $stock->unit_id = (int)$request->unit;
                        $stock->level_id = $levelId;
                        $stock->level_specific_id = $damage->id;
                        $stock->level_specific_type = Damage::class;
                        $stock->previous_qty = $newPreviousQty;
                        $stock->current_qty = $damageBaseQty;
                        $stock->stock_qty = $newPreviousQty + $damageBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $request->remarks;
                        $stock->save();
                    }

                    $this->updateProductStockStatus($request->product);
                }
            }
        }

        $damage->status_id = $request->status;
        $damage->branch_id = $request->branch;
        $damage->product_id = $request->product;
        $damage->unit_id = $request->unit;
        $damage->updated_by = Auth::id();
        $damage->qty = $request->qty;
        $damage->attributes = $requestAttrs;
        $damage->remarks = $request->remarks;
        $damage->save();

        if ($damageStatus && $repairingStatus && (int)$request->status === $repairingStatus->id) {
            $repair = Repair::where('damage_id', $damage->id)->first();
            $repairStatus = Status::where('name', 'repair')->first();
            $repairPendingStatus = Status::where('name', 'pending')->where('parent_id', $repairStatus->id)->first();
            if ($repair) {
                $repair->status_id = $repairPendingStatus->id;
                $repair->product_id = $request->product;
                $repair->branch_id = $request->branch;
                $repair->unit_id = $request->unit;
                $repair->updated_by = Auth::id();
                $repair->qty = $request->qty;
                $repair->save();
            } else {
                $repair = new Repair();
                $repair->damage_id = $damage->id;
                $repair->status_id = $repairPendingStatus->id;
                $repair->product_id = $request->product;
                $repair->branch_id = $request->branch;
                $repair->unit_id = $request->unit;
                $repair->user_id = Auth::id();
                $repair->qty = $request->qty;
                $repair->save();
            }
        }

        event(new DamageUpdate($damage));

        return response()->json([
            'status' => 'success',
            'message' => 'Damage updated successfully',
            'damage' => $damage
        ]);
    }

    #[OA\Post(
        path: "/damages/delete/{id}",
        tags: ["Damages"],
        summary: "Delete damage product",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the damage product",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Damage product deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {
        $damage = Damage::findOrFail($id);

        $damageStatus = Status::where('name', 'damage')->first();
        $isStockIn = false;
        if ($damageStatus) {
            $sellableStatus = Status::where('name', 'sellable')->where('parent_id', $damageStatus->id)->first();
            $sellableDiscountStatus = Status::where('name', 'sellable discount')->where('parent_id', $damageStatus->id)->first();
            $isSellable = $sellableStatus && $damage->status_id === $sellableStatus->id;
            $isSellableDiscount = $sellableDiscountStatus && $damage->status_id === $sellableDiscountStatus->id;
            $isStockIn = $isSellable || $isSellableDiscount;
        }

        $existingStock = Stock::where('level_specific_id', $damage->id)
            ->where('level_specific_type', Damage::class)
            ->first();

        if ($existingStock && !$isStockIn) {
            $damageAttrs = $damage->attributes;
            $latestStock = Stock::where('product_id', $damage->product_id)
                ->where('branch_id', $damage->branch_id)
                ->when($damageAttrs, function ($q) use ($damageAttrs) {
                    foreach ($damageAttrs as $k => $v) {
                        $q->where("attributes->{$k}", $v);
                    }
                })
                ->when(!$damageAttrs, fn($q) => $q->whereNull('attributes'))
                ->latest('id')
                ->first();
            $previousQty = $latestStock ? $latestStock->stock_qty : 0;

            $destroyDmgBaseQty = (float)$damage->qty;
            $destroyDmgSign = $existingStock->current_qty >= 0 ? -1 : 1;

            $stock = new Stock();
            $stock->branch_id = $damage->branch_id;
            $stock->product_id = $damage->product_id;
            $stock->unit_id = (int)$damage->unit_id;
            $level = Level::where('name', 'damage')->first();
            $stock->level_id = $level ? $level->id : 1;
            $stock->level_specific_id = $damage->id;
            $stock->level_specific_type = Damage::class;
            $stock->previous_qty = $previousQty;
            $stock->current_qty = $destroyDmgSign * $destroyDmgBaseQty;
            $stock->stock_qty = $previousQty + ($destroyDmgSign * $destroyDmgBaseQty);
            $stock->attributes = $damage->attributes;
            $stock->remarks = 'Deleted damage #' . $damage->id;
            $stock->save();
        }

        $damageData = $damage->toArray();

        $damage->delete();

        event(new DamageUpdate($damageData));

        return response()->json([
            'status' => 'success',
            'message' => 'Damage product deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:damages,id']);
        $items = Damage::whereIn('id', $request->ids)->get();
        foreach ($items as $damage) {
            $damageStatus = Status::where('name', 'damage')->first();
            $isStockIn = false;
            if ($damageStatus) {
                $sellableStatus = Status::where('name', 'sellable')->where('parent_id', $damageStatus->id)->first();
                $sellableDiscountStatus = Status::where('name', 'sellable discount')->where('parent_id', $damageStatus->id)->first();
                $isSellable = $sellableStatus && $damage->status_id === $sellableStatus->id;
                $isSellableDiscount = $sellableDiscountStatus && $damage->status_id === $sellableDiscountStatus->id;
                $isStockIn = $isSellable || $isSellableDiscount;
            }

            $existingStock = Stock::where('level_specific_id', $damage->id)
                ->where('level_specific_type', Damage::class)
                ->first();

            if ($existingStock && !$isStockIn) {
                $damageAttrs = $damage->attributes;
                $latestStock = Stock::where('product_id', $damage->product_id)
                    ->where('branch_id', $damage->branch_id)
                    ->when($damageAttrs, function ($q) use ($damageAttrs) {
                        foreach ($damageAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    })
                    ->when(!$damageAttrs, fn($q) => $q->whereNull('attributes'))
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $destroyDmgBaseQty = (float)$damage->qty;
                $destroyDmgSign = $existingStock->current_qty >= 0 ? -1 : 1;

                $stock = new Stock();
                $stock->branch_id = $damage->branch_id;
                $stock->product_id = $damage->product_id;
                $stock->unit_id = (int)$damage->unit_id;
                $level = Level::where('name', 'damage')->first();
                $stock->level_id = $level ? $level->id : 1;
                $stock->level_specific_id = $damage->id;
                $stock->level_specific_type = Damage::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = $destroyDmgSign * $destroyDmgBaseQty;
                $stock->stock_qty = $previousQty + ($destroyDmgSign * $destroyDmgBaseQty);
                $stock->attributes = $damage->attributes;
                $stock->remarks = 'Deleted damage #' . $damage->id;
                $stock->save();
            }

            $damage->delete();
            event(new DamageUpdate($damage->toArray()));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
