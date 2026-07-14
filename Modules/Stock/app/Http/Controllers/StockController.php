<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\Stock\Events\StockUpdate;
use Modules\Stock\Models\Stock;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

#[OA\Tag(name: "Stock")]
class StockController extends Controller
{
    #[OA\Get(
        path: "/stock",
        tags: ["Stock"],
        summary: "List stock products",
        description: "Get paginated stock products, with optional search by product",
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
                description: "Stock products fetched successfully",
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
        $stock = Stock::with('product', 'branch', 'unit', 'level', 'levelSpecific')
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($request) {
                    $branchQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('unit', function ($unitQuery) use ($request) {
                    $unitQuery->where('name', 'like', "%{$request->search}%");
                });
            });
        })
        ->when($request->products, function ($query) use ($request) {
            $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
            $query->whereIn('product_id', $productIds);
        })
        ->when($request->branches, function ($query) use ($request) {
            $branchIds = is_array($request->branches) ? $request->branches : explode(',', $request->branches);
            $query->whereIn('branch_id', $branchIds);
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $products = Product::all();
        $branches = Branch::all();

        return response()->json([
            'status' => 'success',
            'stock' => $stock,
            'products' => $products,
            'branches' => $branches
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Stock::min('created_at');

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

        $stock = Stock::with('product', 'branch', 'unit', 'level', 'levelSpecific')
            ->orderBy('id', 'desc')
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('product', function ($productQuery) use ($request) {
                        $productQuery->where('name', 'like', "%{$request->search}%")
                        ->orWhere('barcode', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('branch', function ($branchQuery) use ($request) {
                        $branchQuery->where('name', 'like', "%{$request->search}%");
                    });
                });
            })
            ->when($request->products, function ($query) use ($request) {
                $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
                $query->whereIn('product_id', $productIds);
            })
            ->when($request->branches, function ($query) use ($request) {
                $branchIds = is_array($request->branches) ? $request->branches : explode(',', $request->branches);
                $query->whereIn('branch_id', $branchIds);
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->get();

        $data = $stock->map(fn($s) => [
            'ID' => $s->id,
            'Product' => $s->product?->name ?? '-',
            'Branch' => $s->branch?->name ?? '-',
            'Unit' => $s->unit?->name ?? '-',
            'Previous Qty' => $s->previous_qty,
            'Current Qty' => $s->current_qty,
            'Stock Qty' => $s->stock_qty,
            'Level' => $s->level?->name ?? '-',
            'Remarks' => $s->remarks ?? '-',
            'Created At' => $s->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $s->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Branch', 'Unit', 'Previous Qty', 'Current Qty', 'Stock Qty', 'Level', 'Remarks', 'Created At', 'Updated At'];

        $filename = 'stock_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Stock', 'stock');
        return response()->json(['file' => $filename]);
    }

    public function summary(Request $request)
    {
        $latestIds = Stock::selectRaw('MAX(id) as id')
            ->groupBy('product_id', DB::raw('CAST(attributes AS TEXT)'))
            ->get()
            ->pluck('id');

        $stock = Stock::with(['product', 'branch', 'unit'])
            ->whereIn('id', $latestIds)
            ->orderBy('id', 'desc')
            ->when($request->product_id, function ($query) use ($request) {
                $query->where('product_id', $request->product_id);
            })
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('product', function ($pq) use ($request) {
                        $pq->where('name', 'like', "%{$request->search}%")
                            ->orWhere('barcode', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('branch', function ($bq) use ($request) {
                        $bq->where('name', 'like', "%{$request->search}%");
                    });
                });
            })
            ->paginate($request->perPage ?? 20)->onEachSide(0);

        $products = Product::all();

        return response()->json([
            'status' => 'success',
            'stock' => $stock,
            'products' => $products,
        ]);
    }

    public function summaryExport(Request $request)
    {
        $latestIds = Stock::selectRaw('MAX(id) as id')
            ->groupBy('product_id', DB::raw('CAST(attributes AS TEXT)'))
            ->get()
            ->pluck('id');

        $stock = Stock::with(['product', 'branch', 'unit'])
            ->whereIn('id', $latestIds)
            ->orderBy('id', 'desc')
            ->when($request->product_id, function ($query) use ($request) {
                $query->where('product_id', $request->product_id);
            })
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('product', function ($pq) use ($request) {
                        $pq->where('name', 'like', "%{$request->search}%")
                            ->orWhere('barcode', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('branch', function ($bq) use ($request) {
                        $bq->where('name', 'like', "%{$request->search}%");
                    });
                });
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->get();

        $data = $stock->map(fn($s) => [
            'ID' => $s->id,
            'Product' => $s->product?->name ?? '-',
            'Barcode' => $s->product?->barcode ?? '-',
            'Branch' => $s->branch?->name ?? '-',
            'Unit' => $s->unit?->name ?? '-',
            'Attributes' => $s->attributes ? json_encode($s->attributes) : '-',
            'Stock Qty' => $s->stock_qty,
            'Last Updated' => $s->updated_at?->format('Y-m-d H:i') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Product', 'Barcode', 'Branch', 'Unit', 'Attributes', 'Stock Qty', 'Last Updated'];

        $filename = 'stock-summary_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'StockSummary', 'stock');
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

    public function store(Request $request)
    {
        $request->validate([
            'branch' => 'required',
            'product' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'remarks' => 'string|max:500'
        ]);

        $stock = new Stock();
        $stock->branch_id = $request->branch;
        $stock->product_id = $request->product;
        $stock->user_id = Auth::id();
        $stock->qty = $request->qty;
        $stock->remarks = $request->remarks;
        $stock->save();

        event(new StockUpdate($stock));

        return response()->json([
            'status' => 'success',
            'message' => 'Stock product created successfully',
            'stock' => $stock
        ]);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'product' => 'required',
            'branch' => 'required',
            'qty' => 'required|numeric|max:9999999999.999',
            'remarks' => 'string|max:500'
        ]);

        $stock = Stock::findOrFail($id);
        $stock->branch_id = $request->branch;
        $stock->product_id = $request->product;
        $stock->user_id = Auth::id();
        $stock->qty = $request->qty;
        $stock->remarks = $request->remarks;
        $stock->update();

        event(new StockUpdate($stock));

        return response()->json([
            'status' => 'success',
            'message' => 'Stock updated successfully',
            'stock' => $stock
        ]);
    }

    public function destroy(int $id)
    {
        $stock = Stock::findOrFail($id);

        $stockData = $stock->toArray($stock);

        $stock->delete();

        event(new StockUpdate($stockData));

        return response()->json([
            'status' => 'success',
            'message' => 'Stock product deleted successfully'
        ]);
    }
}
