<?php

namespace Modules\ClientReturn\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\ClientReturn\Events\ClientReturnUpdate;
use Modules\ClientReturn\Models\ClientReturn;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\Unit\Models\Unit;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "ClientReturns")]
class ClientReturnController extends Controller
{
    #[OA\Get(
        path: "/client_returns",
        tags: ["ClientReturns"],
        summary: "List client return products",
        description: "Get paginated client return products, with optional search by product",
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
                description: "Client return products fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "sale", type: "array", items: new OA\Items(type: "object")),
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
        $clientReturns = ClientReturn::with(['status', 'product', 'sale', 'user', 'updatedBy', 'branch', 'unit', 'oldUnit'])
        ->when($request->search, function ($query) use ($request) {
            $query->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            })
            ->orWhereHas('sale', function ($saleQuery) use ($request) {
                $saleQuery->where('invoice_no', 'like', "%{$request->search}%");
            })
            ->orWhereHas('status', function ($statusQuery) use ($request) {
                $statusQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
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

        $clientReturnStatus = Status::where('name', 'client return')->first();
        $statuses = Status::where('parent_id', $clientReturnStatus->id)->get();
        $products = Product::with('unit')->get();
        $sales = Sale::with('product')->get();

        $branches = Branch::all();
        $units = Unit::all();

        return response()->json([
            'status' => 'success',
            'clientReturns' => $clientReturns,
            'statuses' => $statuses,
            'products' => $products,
            'branches' => $branches,
            'units' => $units,
            'sales' => $sales
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = ClientReturn::min('created_at');

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

        $clientReturns = ClientReturn::with(['status', 'product', 'sale', 'user', 'updatedBy', 'branch', 'unit', 'oldUnit'])
            ->when($request->search, function ($query) use ($request) {
                $query->orWhereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                })
                ->orWhereHas('sale', fn($sq) => $sq->where('invoice_no', 'like', "%{$request->search}%"))
                ->orWhereHas('status', fn($sq) => $sq->where('name', 'like', "%{$request->search}%"))
                ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%$request->search%"));
            })
            ->when($request->products, function ($query) use ($request) {
                $ids = is_array($request->products) ? $request->products : explode(',', $request->products);
                $query->whereIn('product_id', $ids);
            })
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->branch, fn($q) => $q->where('branch_id', $request->branch))
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->latest('id')
            ->get();

        $data = $clientReturns->map(fn($r) => [
            'ID' => $r->id,
            'Product' => $r->product?->name ?? '-',
            'Sale Invoice' => $r->sale?->invoice_no ?? '-',
            'Branch' => $r->branch?->name ?? '-',
            'Qty' => $r->qty,
            'Status' => $r->status?->name ?? '-',
            'Remarks' => $r->remarks ?? '-',
            'Created By' => $r->user?->name ?? '-',
            'Updated By' => $r->updatedBy?->name ?? '-',
            'Created At' => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $r->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Sale Invoice', 'Branch', 'Qty', 'Status', 'Remarks', 'Created By', 'Updated By', 'Created At', 'Updated At'];
        $filename = 'client_returns_' . now()->timestamp . '.xlsx';

        ExportData::dispatch($data, $headings, $filename, 'ClientReturns', 'client_returns');

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
        $clientReturn = ClientReturn::with(['status', 'product', 'sale', 'user', 'branch', 'unit', 'oldUnit'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'clientReturn' => $clientReturn
        ]);
    }

    #[OA\Post(
        path: "/client_returns",
        summary: "Create new client return product",
        tags: ["ClientReturns"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product", "sale", "status", "qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "sale", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Client Return product created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'product' => 'required',
            'sale' => 'required',
            'status' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.999',
            'product_unit_id' => 'required',
            'product_unit_qty' => 'required|numeric|max:99999999.999',
            'branch' => 'required',
            'attributes' => 'nullable|json',
            'remarks' => 'max:500'
        ]);

        $sale = Sale::findOrFail($request->sale);
        $alreadyReturned = ClientReturn::where('sale_id', $request->sale)->sum('product_unit_qty');
        $totalReturned = (float)$alreadyReturned + (float)$request->product_unit_qty;
        if ($totalReturned > (float)$sale->qty) {
            $remaining = max(0, (float)$sale->qty - (float)$alreadyReturned);
            return response()->json([
                'status' => 'error',
                'message' => 'Return quantity exceeds sale quantity',
                'errors' => ['product_unit_qty' => ["Return qty ($request->product_unit_qty) exceeds remaining sale qty ($remaining)"]]
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

        $clientReturn = new ClientReturn();
        $clientReturn->product_id = $request->product;
        $clientReturn->sale_id = $request->sale;
        $clientReturn->status_id = $request->status;
        $clientReturn->unit_id = $request->unit;
        $clientReturn->qty = $request->qty;
        $clientReturn->product_unit_id = $request->product_unit_id;
        $clientReturn->product_unit_qty = $request->product_unit_qty;
        $clientReturn->branch_id = $request->branch;
        $clientReturn->user_id = Auth::id();
        $clientReturn->attributes = $requestAttrs;
        $clientReturn->remarks = $request->remarks;
        $clientReturn->save();

        $clientReturnParent = Status::where('name', 'client return')->first();
        if ($clientReturnParent) {
            $completedStatus = Status::where('name', 'completed')
                ->where('parent_id', $clientReturnParent->id)
                ->first();

            if ($completedStatus && (int)$request->status === $completedStatus->id) {
                $level = Level::where('name', 'client return')->first();

                if ($level) {
                    $latestStock = Stock::where('product_id', $clientReturn->product_id)
                        ->where('branch_id', $clientReturn->branch_id);
                    if (is_array($requestAttrs)) {
                        foreach ($requestAttrs as $k => $v) {
                            $latestStock->where("attributes->{$k}", $v);
                        }
                    } else {
                        $latestStock->whereNull('attributes');
                    }
                    $latestStock = $latestStock->latest('id')->first();
                    $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                    $crBaseQty = (float)$clientReturn->product_unit_qty;

                    $stock = new Stock();
                    $stock->branch_id = $clientReturn->branch_id;
                    $stock->product_id = $clientReturn->product_id;
                    $stock->unit_id = (int)$clientReturn->product_unit_id;
                    $stock->level_id = $level->id;
                    $stock->level_specific_id = $clientReturn->id;
                    $stock->level_specific_type = ClientReturn::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = $crBaseQty;
                    $stock->stock_qty = $previousQty + $crBaseQty;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $clientReturn->remarks;
                    $stock->save();
                }
            }
        }

        event(new ClientReturnUpdate($clientReturn));

        return response()->json([
            'status' => 'success',
            'message' => 'Client return product created successfully',
            'clientReturn' => $clientReturn
        ]);
    }

    #[OA\Post(
        path: "/client_returns/update/{id}",
        summary: "Update client return product",
        tags: ["ClientReturns"],
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
                required: ["product", "sale", "status", "qty"],
                properties: [
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "sale", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Client return product updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'product' => 'required',
            'sale' => 'required',
            'status' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'product_unit_id' => 'required',
            'product_unit_qty' => 'required|numeric|max:99999999.999',
            'branch' => 'required',
            'attributes' => 'nullable|json',
            'remarks' => 'max:500'
        ]);

        $sale = Sale::findOrFail($request->sale);
        $alreadyReturned = ClientReturn::where('sale_id', $request->sale)
            ->where('id', '!=', $id)
            ->sum('product_unit_qty');
        $totalReturned = (float)$alreadyReturned + (float)$request->product_unit_qty;
        if ($totalReturned > (float)$sale->qty) {
            $remaining = max(0, (float)$sale->qty - (float)$alreadyReturned);
            return response()->json([
                'status' => 'error',
                'message' => 'Return quantity exceeds sale quantity',
                'errors' => ['product_unit_qty' => ["Return qty ($request->product_unit_qty) exceeds remaining sale qty ($remaining)"]]
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

        $clientReturn = ClientReturn::findOrFail($id);
        $oldStatusId = $clientReturn->status_id;
        $oldQty = (float)$clientReturn->qty;
        $oldAttrs = $clientReturn->attributes;

        $attrsSame = $requestAttrs == $oldAttrs;

        $clientReturn->product_id = $request->product;
        $clientReturn->sale_id = $request->sale;
        $clientReturn->status_id = $request->status;
        $clientReturn->unit_id = $request->unit;
        $clientReturn->qty = $request->qty;
        $clientReturn->product_unit_id = $request->product_unit_id;
        $clientReturn->product_unit_qty = $request->product_unit_qty;
        $clientReturn->branch_id = $request->branch;
        $clientReturn->updated_by = Auth::id();
        $clientReturn->attributes = $requestAttrs;
        $clientReturn->remarks = $request->remarks;
        $clientReturn->update();

        $clientReturnParent = Status::where('name', 'client return')->first();
        if ($clientReturnParent) {
            $completedStatus = Status::where('name', 'completed')
                ->where('parent_id', $clientReturnParent->id)
                ->first();

            if ($completedStatus) {
                $isCompleted = (int)$request->status === $completedStatus->id;

                $existingStock = Stock::where('level_specific_id', $clientReturn->id)
                    ->where('level_specific_type', ClientReturn::class)
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

                    $latestNewStock = Stock::where('product_id', $clientReturn->product_id)
                        ->where('branch_id', $clientReturn->branch_id);
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
                        if ($isLatest && $existingStock->current_qty > 0) {
                            $stockQtyBefore = $existingStock->stock_qty - $existingStock->current_qty;
                            $existingStock->branch_id = $clientReturn->branch_id;
                            $existingStock->product_id = $clientReturn->product_id;
                            $existingStock->unit_id = (int)$clientReturn->product_unit_id;
                            $existingStock->previous_qty = $stockQtyBefore;
                            $existingStock->current_qty = $clientReturn->product_unit_qty;
                            $existingStock->stock_qty = $stockQtyBefore + $clientReturn->product_unit_qty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $clientReturn->remarks;
                            $existingStock->save();
                        } else {
                            $reversal = new Stock();
                            $reversal->branch_id = $existingStock->branch_id;
                            $reversal->product_id = $existingStock->product_id;
                            $reversal->unit_id = $existingStock->unit_id;
                            $reversal->level_id = $existingStock->level_id;
                            $reversal->level_specific_id = $clientReturn->id;
                            $reversal->level_specific_type = ClientReturn::class;
                            $reversal->previous_qty = $oldPreviousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $oldPreviousQty - $existingStock->current_qty;
                            $reversal->attributes = $existingStock->attributes;
                            $reversal->remarks = 'Reversal of client return #' . $clientReturn->id;
                            $reversal->save();

                            $level = Level::where('name', 'client return')->first();
                            $updateCrBaseQty = (float)$clientReturn->product_unit_qty;

                            $stock = new Stock();
                            $stock->branch_id = $clientReturn->branch_id;
                            $stock->product_id = $clientReturn->product_id;
                            $stock->unit_id = (int)$clientReturn->product_unit_id;
                            $stock->level_id = $level ? $level->id : 1;
                            $stock->level_specific_id = $clientReturn->id;
                            $stock->level_specific_type = ClientReturn::class;
                            $stock->current_qty = $updateCrBaseQty;
                            $stock->attributes = $requestAttrs;
                            $stock->remarks = $clientReturn->remarks;
                            if ($attrsSame) {
                                $stock->previous_qty = $reversal->stock_qty;
                                $stock->stock_qty = $reversal->stock_qty + $updateCrBaseQty;
                            } else {
                                $stock->previous_qty = $newPreviousQty;
                                $stock->stock_qty = $newPreviousQty + $updateCrBaseQty;
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
                            $reversal->level_specific_id = $clientReturn->id;
                            $reversal->level_specific_type = ClientReturn::class;
                            $reversal->previous_qty = $oldPreviousQty;
                            $reversal->current_qty = -$existingStock->current_qty;
                            $reversal->stock_qty = $oldPreviousQty - $existingStock->current_qty;
                            $reversal->attributes = $existingStock->attributes;
                            $reversal->remarks = 'Reversal of client return #' . $clientReturn->id;
                            $reversal->save();
                        }
                    }
                } elseif ($isCompleted) {
                    $level = Level::where('name', 'client return')->first();
                    $latestNewStock = Stock::where('product_id', $clientReturn->product_id)
                        ->where('branch_id', $clientReturn->branch_id);
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
                        $updateCrNewBaseQty = (float)$clientReturn->product_unit_qty;

                        $stock = new Stock();
                        $stock->branch_id = $clientReturn->branch_id;
                        $stock->product_id = $clientReturn->product_id;
                            $stock->unit_id = (int)$clientReturn->product_unit_id;
                            $stock->level_id = $level->id;
                        $stock->level_specific_id = $clientReturn->id;
                        $stock->level_specific_type = ClientReturn::class;
                        $stock->previous_qty = $newPreviousQty;
                        $stock->current_qty = $updateCrNewBaseQty;
                        $stock->stock_qty = $newPreviousQty + $updateCrNewBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $clientReturn->remarks;
                        $stock->save();
                    }
                }
            }
        }

        event(new ClientReturnUpdate($clientReturn));

        return response()->json([
            'status' => 'success',
            'message' => 'Client return updated successfully',
            'clientReturn' => $clientReturn
        ]);
    }

    #[OA\Post(
        path: "/client_returns/delete/{id}",
        tags: ["ClientReturns"],
        summary: "Delete client return product",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the client return product",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Client return product deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $clientReturn = ClientReturn::findOrFail($id);

        $existingStock = Stock::where('level_specific_id', $clientReturn->id)
            ->where('level_specific_type', ClientReturn::class)
            ->first();

        if ($existingStock) {
            $latestStock = Stock::where('product_id', $clientReturn->product_id)
                ->where('branch_id', $clientReturn->branch_id);
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

            $destroyCrBaseQty = (float)$clientReturn->product_unit_qty;

            $stock = new Stock();
            $stock->branch_id = $clientReturn->branch_id;
            $stock->product_id = $clientReturn->product_id;
            $stock->unit_id = (int)$clientReturn->product_unit_id;
            $stock->level_id = $existingStock->level_id;
            $stock->level_specific_id = $clientReturn->id;
            $stock->level_specific_type = ClientReturn::class;
            $stock->previous_qty = $previousQty;
            $stock->current_qty = -$destroyCrBaseQty;
            $stock->stock_qty = $previousQty - $destroyCrBaseQty;
            $stock->attributes = $existingStock->attributes;
            $stock->remarks = 'Client return deleted - reversal';
            $stock->save();
        }

        $clientReturnData = $clientReturn->toArray($clientReturn);

        $clientReturn->delete();

        event(new ClientReturnUpdate($clientReturnData));

        return response()->json([
            'status' => 'success',
            'message' => 'Client return product deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:client_returns,id']);
        $items = ClientReturn::whereIn('id', $request->ids)->get();
        foreach ($items as $clientReturn) {
            $existingStock = Stock::where('level_specific_id', $clientReturn->id)
                ->where('level_specific_type', ClientReturn::class)
                ->first();

            if ($existingStock) {
                $latestStock = Stock::where('product_id', $clientReturn->product_id)
                    ->where('branch_id', $clientReturn->branch_id);
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

                $destroyCrBaseQty = (float)$clientReturn->product_unit_qty;

                $stock = new Stock();
                $stock->branch_id = $clientReturn->branch_id;
                $stock->product_id = $clientReturn->product_id;
                $stock->unit_id = (int)$clientReturn->product_unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $clientReturn->id;
                $stock->level_specific_type = ClientReturn::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = -$destroyCrBaseQty;
                $stock->stock_qty = $previousQty - $destroyCrBaseQty;
                $stock->attributes = $existingStock->attributes;
                $stock->remarks = 'Client return deleted - reversal';
                $stock->save();
            }

            $clientReturn->delete();
            event(new ClientReturnUpdate($clientReturn->toArray()));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
