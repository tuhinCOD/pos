<?php

namespace Modules\Repair\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Damage\Models\Damage;
use Modules\Product\Models\Product;
use Modules\Repair\Events\RepairUpdate;
use Modules\Repair\Models\Repair;
use Modules\Status\Models\Status;
use Modules\Unit\Models\Unit;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "Repairs")]
class RepairController extends Controller
{
    #[OA\Get(
        path: "/repairs",
        tags: ["Repairs"],
        summary: "List repair products",
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Repair products fetched successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $repairs = Repair::with(['status', 'product', 'damage', 'user', 'updatedBy', 'branch', 'unit'])
        ->when($request->search, function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->orWhereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
                })
                ->orWhereHas('status', function ($statusQuery) use ($search) {
                    $statusQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('damage', function ($damageQuery) use ($search) {
                    $damageQuery->where('id', 'like', "%{$search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($search) {
                    $branchQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('repair_shop', 'like', "%{$search}%")
                ->orWhere('remarks', 'like', "%{$search}%");
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

        $repairStatus = Status::where('name', 'repair')->first();
        $statuses = $repairStatus ? Status::where('parent_id', $repairStatus->id)->get() : [];
        $products = Product::all();
        $damages = Damage::all();
        $branches = Branch::all();
        $units = Unit::all();

        return response()->json([
            'status' => 'success',
            'repairs' => $repairs,
            'statuses' => $statuses,
            'products' => $products,
            'damages' => $damages,
            'branches' => $branches,
            'units' => $units
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Repair::min('created_at');

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

        $repairs = Repair::with(['status', 'product', 'damage', 'user', 'updatedBy', 'branch', 'unit'])
            ->when($request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('status', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('damage', fn($dq) => $dq->where('id', 'like', "%{$search}%"))
                    ->orWhereHas('branch', fn($bq) => $bq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhere('repair_shop', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
                });
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

        $data = $repairs->map(fn($r) => [
            'ID' => $r->id,
            'Product' => $r->product?->name ?? '-',
            'Repair Shop' => $r->repair_shop ?? '-',
            'Branch' => $r->branch?->name ?? '-',
            'Qty' => $r->qty,
            'Status' => $r->status?->name ?? '-',
            'Remarks' => $r->remarks ?? '-',
            'Created By' => $r->user?->name ?? '-',
            'Updated By' => $r->updatedBy?->name ?? '-',
            'Created At' => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $r->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Repair Shop', 'Branch', 'Qty', 'Status', 'Remarks', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'repairs_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Repairs', 'repairs');
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
        $repair = Repair::with(['status', 'product', 'damage', 'user', 'updatedBy', 'branch', 'unit'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'repair' => $repair
        ]);
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'product' => 'required',
    //         'damage' => 'required',
    //         'status' => 'required',
    //         'unit' => 'required',
    //         'branch' => 'required',
    //         'repair_shop' => 'required|string|max:100',
    //         'qty' => 'required|numeric|max:99999999.999',
    //         'repair_cost' => 'required|numeric|max:9999999999.999',
    //         'remarks' => 'max:500'
    //     ]);

    //     $repair = new Repair();
    //     $repair->product_id = $request->product;
    //     $repair->damage_id = $request->damage;
    //     $repair->status_id = $request->status;
    //     $repair->unit_id = $request->unit;
    //     $repair->branch_id = $request->branch;
    //     $repair->user_id = Auth::id();
    //     $repair->repair_shop = $request->repair_shop;
    //     $repair->qty = $request->qty;
    //     $repair->repair_cost = $request->repair_cost;
    //     $repair->remarks = $request->remarks;
    //     $repair->save();

    //     $repairParent = Status::where('name', 'repair')->first();
    //     if ($repairParent) {
    //         $completedStatus = Status::where('name', 'completed')
    //             ->where('parent_id', $repairParent->id)
    //             ->first();

    //         if ($completedStatus && (int)$request->status === $completedStatus->id) {
    //             $level = Level::where('name', 'repair')->first();

    //             if ($level) {
    //                 $latestStock = Stock::where('product_id', $repair->product_id)
    //                     ->where('branch_id', $repair->branch_id)
    //                     ->latest('id')
    //                     ->first();
    //                 $previousQty = $latestStock ? $latestStock->stock_qty : 0;

    //                 $stock = new Stock();
    //                 $stock->branch_id = $repair->branch_id;
    //                 $stock->product_id = $repair->product_id;
    //                 $stock->unit_id = $repair->unit_id;
    //                 $stock->level_id = $level->id;
    //                 $stock->level_specific_id = $repair->id;
    //                 $stock->level_specific_type = Repair::class;
    //                 $stock->previous_qty = $previousQty;
    //                 $stock->current_qty = $repair->qty;
    //                 $stock->stock_qty = $previousQty + $repair->qty;
    //                 $stock->remarks = $repair->remarks;
    //                 $stock->save();
    //             }
    //         }
    //     }

    //     event(new RepairUpdate($repair));

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Repair product created successfully',
    //         'repair' => $repair
    //     ]);
    // }

    public function update (Request $request, int  $id) {
        $request->validate([
            'product' => 'required',
            'damage' => 'required',
            'status' => 'required',
            'unit' => 'required',
            'branch' => 'required',
            'repair_shop' => 'required|string|max:100',
            'qty' => 'required|numeric|max:99999999.999',
            'repair_cost' => 'required|numeric|max:9999999999.999',
            'remarks' => 'max:500'
        ]);

        $repair = Repair::findOrFail($id);

        $repair->product_id = $request->product;
        $repair->damage_id = $request->damage;
        $repair->status_id = $request->status;
        $repair->unit_id = $request->unit;
        $repair->branch_id = $request->branch;
        $repair->updated_by = Auth::id();
        $repair->repair_shop = $request->repair_shop;
        $repair->qty = $request->qty;
        $repair->repair_cost = $request->repair_cost;
        $repair->remarks = $request->remarks;
        $repair->update();

        $repairParent = Status::where('name', 'repair')->first();
        $damageParent = Status::where('name', 'damage')->first();

        if ($repairParent && $damageParent) {
            $completedStatus = Status::where('name', 'completed')
                ->where('parent_id', $repairParent->id)
                ->first();
            $failedStatus = Status::where('name', 'failed')
                ->where('parent_id', $repairParent->id)
                ->first();
            $pendingStatus = Status::where('name', 'pending')
                ->where('parent_id', $repairParent->id)
                ->first();
            $inProgressStatus = Status::where('name', 'in progress')
                ->where('parent_id', $repairParent->id)
                ->first();

            if ($completedStatus && (int)$request->status === $completedStatus->id) {
                $repairedStatus = Status::where('name', 'repaired')
                    ->where('parent_id', $damageParent->id)
                    ->first();
                if ($repairedStatus) {
                    Damage::where('id', $repair->damage_id)
                        ->update(['status_id' => $repairedStatus->id, 'updated_by' => Auth::id()]);
                }
            } elseif ($failedStatus && (int)$request->status === $failedStatus->id) {
                $damagedStatus = Status::where('name', 'damaged')
                    ->where('parent_id', $damageParent->id)
                    ->first();
                if ($damagedStatus) {
                    Damage::where('id', $repair->damage_id)
                        ->update(['status_id' => $damagedStatus->id, 'updated_by' => Auth::id()]);
                }
            } elseif (
                ($pendingStatus && (int)$request->status === $pendingStatus->id) ||
                ($inProgressStatus && (int)$request->status === $inProgressStatus->id)
            ) {
                $repairingStatus = Status::where('name', 'repairing')
                    ->where('parent_id', $damageParent->id)
                    ->first();
                if ($repairingStatus) {
                    Damage::where('id', $repair->damage_id)
                        ->update(['status_id' => $repairingStatus->id, 'updated_by' => Auth::id()]);
                }
            }
        }

        event(new RepairUpdate($repair));

        return response()->json([
            'status' => 'success',
            'message' => 'Repair updated successfully',
            'repair' => $repair
        ]);
    }

    public function destroy(int $id)
    {
        $repair = Repair::findOrFail($id);

        $damageParent = Status::where('name', 'damage')->first();
        if ($damageParent) {
            $repairedStatus = Status::where('name', 'repaired')
                ->where('parent_id', $damageParent->id)
                ->first();
            if ($repairedStatus) {
                Damage::where('id', $repair->damage_id)
                    ->update(['status_id' => $repairedStatus->id, 'updated_by' => Auth::id()]);
            }
        }

        $repairData = $repair->toArray();

        $repair->delete();

        event(new RepairUpdate($repairData));

        return response()->json([
            'status' => 'success',
            'message' => 'Repair product deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:repairs,id']);
        Repair::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new RepairUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
