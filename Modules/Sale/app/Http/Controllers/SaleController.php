<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Credit\Events\CreditUpdate;
use Modules\Credit\Models\Credit;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Role\Models\Role;
use Modules\Sale\Events\SaleUpdate;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\Barcode\Models\Barcode;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Sales")]
class SaleController extends Controller
{
    #[OA\Get(
        path: "/sales",
        tags: ["Sales"],
        summary: "List sales",
        description: "Get paginated sales, with optional search by product",
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
                description: "Sales fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "client", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "productPrice", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "branch", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $sales = Sale::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'updatedBy', 'unit'])
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
            ->orWhereHas('client', function ($clientQuery) use ($request) {
                $clientQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
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

        $saleStatus = Status::where('name', 'temp')->first();
        $statuses = Status::where('parent_id', $saleStatus->id)->get();
        $userRole = Role::where('name', 'user')->first();
        $ParentuserStatus = Status::where('name', 'user')->first();
        $userStatus = Status::where('name', 'regular')->where('parent_id', $ParentuserStatus->id)->first();
        $clients = User::where('role_id', $userRole->id)->where('status_id', $userStatus->id)->get();
        $branches = Branch::all();
        $products = Product::all();
        $product_price = ProductPrice::all();
        $unit = Unit::all();
        $barcodes = Barcode::with(['unit', 'productPrice'])->where('status_id', 1)->get();

        return response()->json([
            'status' => 'success',
            'sales' => $sales,
            'clients' => $clients,
            'branches' => $branches,
            'statuses' => $statuses,
            'products' => $products,
            'product_price' => $product_price,
            'unit' => $unit,
            'barcodes' => $barcodes
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Sale::min('created_at');

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

        $sales = Sale::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'updatedBy', 'unit'])
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['invoice_no'], 'like', '%' . $request->search . '%')
                ->orWhereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($request) {
                    $branchQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('client', function ($clientQuery) use ($request) {
                    $clientQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%{$request->search}%")
                    ->orWhere('contact', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
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
            ->when($request->invoice_nos, fn($q) => $q->whereIn('invoice_no', explode(',', $request->invoice_nos)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->latest('id')
            ->get();

        $data = $sales->map(fn($s) => [
            'Invoice No' => $s->invoice_no,
            'Product' => $s->product?->name ?? '-',
            'Client' => $s->client?->name ?? $s->client?->contact ?? '-',
            'Branch' => $s->branch?->name ?? '-',
            'Qty' => $s->qty,
            'Price' => $s->price,
            'Vat' => $s->vat,
            'Discount' => $s->discount ?? 0,
            'Total' => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)),
            'Status' => $s->status?->name ?? '-',
            'Unit' => $s->unit?->name ?? '-',
            'Created By' => $s->user?->name ?? '-',
            'Updated By' => $s->updatedBy?->name ?? '-',
            'Created At' => $s->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $s->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['Invoice No', 'Product', 'Client', 'Branch', 'Qty', 'Price', 'Vat', 'Discount', 'Total', 'Status', 'Unit', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'sales_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Sales', 'sales');
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
        $sale = Sale::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'unit'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'sale' => $sale
        ]);
    }

    public function byInvoice(string $invoiceNo)
    {
        $sales = Sale::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'unit'])
            ->where('invoice_no', $invoiceNo)
            ->get();

        return response()->json([
            'status' => 'success',
            'sales' => $sales
        ]);
    }

    #[OA\Post(
        path: "/sales",
        summary: "Create new sale",
        tags: ["Sales"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["invoice_no", "status", "branch", "product", "product_price", "unit", "qty", "price", "vat"],
                properties: [
                    new OA\Property(property: "invoice_no", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "client", type: "integer"),
                    new OA\Property(property: "client_phone", type: "string"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "point", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Sale created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request) {
        $request->validate([
            'invoice_no' => 'required|string|max:16',
            'status' => 'required',
            'branch' => 'required',
            'product' => 'required',
            'product_price' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.99',
            'price' => 'required|numeric|max:9999999999.99',
            'vat' => 'required|numeric|max:9999999999.99',
            'discount' => 'max:999999999.99',
            'point' => 'max:99999999.99',
            'attributes' => 'nullable|string',
            'remarks' => 'max:500',
        ]);

        $clientId = $request->client;

        if (!$clientId && $request->client_phone) {
            $userRole = Role::where('name', 'user')->first();
            $ParentuserStatus = Status::where('name', 'user')->first();
            $userStatus = Status::where('name', 'regular')->where('parent_id', $ParentuserStatus->id)->first();
            $existingUser = User::where('contact', $request->client_phone)
                ->where('role_id', $userRole->id)
                ->where('status_id', $userStatus->id)
                ->first();

            if ($existingUser) {
                $clientId = $existingUser->id;
            } else {
                $newUser = new User();
                $newUser->contact = $request->client_phone;
                $newUser->role_id = $userRole->id;
                $newUser->status_id = $userStatus->id;
                $newUser->save();
                $clientId = $newUser->id;
            }
        }

        $saleLevel = Level::where('name', 'sale')->first();
        $tempStatus = Status::where('name', 'temp')->first();

        $saleAttrs = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;

        if ($tempStatus) {
            $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
            $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();

            $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
            $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;

            if ($isCompleted || $isPartialCompleted) {
                $availableQty = $this->getStockQty($request->product, $request->branch, $saleAttrs ?? []);
                if ($availableQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                    ], 422);
                }
            }
        }

        $sale = new Sale();
        $sale->invoice_no = $request->invoice_no;
        $sale->status_id = $request->status;
        $sale->client_id = $clientId;
        $sale->branch_id = $request->branch;
        $sale->product_id = $request->product;
        $sale->product_price_id = $request->product_price;
        $sale->unit_id = $request->unit;
        $sale->user_id = Auth::id();
        $sale->qty = $request->qty;
        $sale->price = $request->price;
        $sale->vat = $request->vat;
        $sale->discount = $request->discount;
        $sale->point = $request->point;
        $sale->attributes = $saleAttrs;
        $sale->remarks = $request->remarks;
        $sale->save();

        if ($tempStatus) {
            if ($isCompleted || $isPartialCompleted) {
                $saleAttrs = $sale->attributes;
                $latestStockOverall = Stock::where('product_id', $request->product)
                    ->where('branch_id', $request->branch)
                    ->when($saleAttrs, function ($q) use ($saleAttrs) {
                        foreach ($saleAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStockOverall ? $latestStockOverall->stock_qty : 0;

                $saleBaseQty = (float)$request->qty;

                $stock = new Stock();
                $stock->branch_id = $request->branch;
                $stock->product_id = $request->product;
                $stock->unit_id = (int)$request->unit;
                $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                $stock->level_specific_id = $sale->id;
                $stock->level_specific_type = Sale::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = -$saleBaseQty;
                $stock->stock_qty = $previousQty - $saleBaseQty;
                $stock->attributes = $saleAttrs;
                $stock->remarks = $request->remarks;
                $stock->save();

                $this->updateProductStockStatus($request->product);
            }

            if ($isPartialCompleted) {
                $invoiceTotal = Sale::where('invoice_no', $sale->invoice_no)
                    ->get()
                    ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

                $existingCredit = Credit::where('invoice_no', $sale->invoice_no)
                    ->where('credit_type', 'sale')
                    ->first();

                if ($invoiceTotal > 0) {
                    if ($existingCredit) {
                        $existingCredit->updated_by = Auth::id();
                        $existingCredit->total_amount = $invoiceTotal;
                        $existingCredit->due_amount = $invoiceTotal - $existingCredit->paid_amount;
                        $existingCredit->save();
                    } else {
                        $credit = new Credit();
                        $credit->credit_type = 'sale';
                        $credit->invoice_no = $sale->invoice_no;
                        $credit->user_id = Auth::id();
                        $credit->total_amount = $invoiceTotal;
                        $credit->paid_amount = 0;
                        $credit->due_amount = $invoiceTotal;
                        $credit->save();

                        event(new CreditUpdate($credit));
                    }
                }
            }
        }

        event(new SaleUpdate($sale));

        return response()->json([
            'status' => 'success',
            'message' => 'Sale created successfully',
            'sale' => $sale
        ]);
    }

    public function destroyByInvoice(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string',
        ]);

        $sales = Sale::where('invoice_no', $request->invoice_no)->get();

        foreach ($sales as $sale) {
            $existingStock = Stock::where('level_specific_id', $sale->id)
                ->where('level_specific_type', Sale::class)
                ->first();

            if ($existingStock) {
                $saleAttrs = $sale->attributes;
                $latestStock = Stock::where('product_id', $sale->product_id)
                    ->where('branch_id', $sale->branch_id)
                    ->when($saleAttrs, function ($q) use ($saleAttrs) {
                        foreach ($saleAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $destroyInvBaseQty = (float)$sale->qty;

                $stock = new Stock();
                $stock->branch_id = $sale->branch_id;
                $stock->product_id = $sale->product_id;
                $stock->unit_id = (int)$sale->unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $sale->id;
                $stock->level_specific_type = Sale::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = $destroyInvBaseQty;
                $stock->stock_qty = $previousQty + $destroyInvBaseQty;
                $stock->attributes = $saleAttrs;
                $stock->remarks = 'Sale deleted - reversal';
                $stock->save();
            }

            $saleData = $sale->toArray();
            $sale->delete();
            event(new SaleUpdate($saleData));
        }

        $existingCredit = Credit::where('invoice_no', $request->invoice_no)
            ->where('credit_type', 'sale')
            ->first();

        if ($existingCredit) {
            $existingCredit->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sales deleted successfully'
        ]);
    }

    public function validateBatch(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product' => 'required',
            'items.*.branch' => 'required',
            'items.*.qty' => 'required|numeric|max:99999999.99',
            'items.*.status' => 'required',
            'items.*.invoice_no' => 'nullable|string|max:16',
            'items.*.attributes' => 'nullable|string',
        ]);

        $errors = [];
        $invoiceNo = $request->input('items.0.invoice_no');
        $tempStatus = Status::where('name', 'temp')->first();

        if ($tempStatus) {
            $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
            $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();

            $grouped = [];
            $isCompletedOrPartial = false;

            foreach ($request->items as $item) {
                $isCompleted = $completedStatus && (int)$item['status'] === $completedStatus->id;
                $isPartialCompleted = $partialCompletedStatus && (int)$item['status'] === $partialCompletedStatus->id;
                if ($isCompleted || $isPartialCompleted) {
                    $isCompletedOrPartial = true;
                    $itemAttrs = !empty($item['attributes']) ? json_decode($item['attributes'], true) : null;
                    $attrsKey = $item['attributes'] ?? '';
                    $key = (int)$item['product'].'_'.(int)$item['branch'].'_'.$attrsKey;
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'product_id' => (int)$item['product'],
                            'branch_id' => (int)$item['branch'],
                            'total_qty' => 0,
                            'attributes' => $itemAttrs,
                        ];
                    }
                    $grouped[$key]['total_qty'] += (float)$item['qty'];
                }
            }

            if ($isCompletedOrPartial) {
                foreach ($grouped as $group) {
                    $existingSaleTotal = 0;
                    if ($invoiceNo) {
                        $existingSaleQuery = Sale::where('invoice_no', $invoiceNo)
                            ->where('product_id', $group['product_id'])
                            ->where('branch_id', $group['branch_id']);
                        if ($group['attributes']) {
                            foreach ($group['attributes'] as $k => $v) {
                                $existingSaleQuery->where("attributes->{$k}", $v);
                            }
                        } else {
                            $existingSaleQuery->whereNull('attributes');
                        }
                        $existingSaleTotal = (float)$existingSaleQuery->sum('qty');
                    }

                    $neededStock = max(0, $group['total_qty'] - $existingSaleTotal);
                    $availableQty = $this->getStockQty($group['product_id'], $group['branch_id'], $group['attributes'] ?? []);

                    if ($availableQty < $neededStock) {
                        $errors[] = "Product ID {$group['product_id']}: Not enough stock. Available: $availableQty, Required: {$group['total_qty']}";
                    }
                }
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => implode(' | ', $errors),
                'errors' => $errors
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'All items have sufficient stock'
        ]);
    }

    // public function store (Request $request) {
    //     $request->validate([
    //         'invoice_no' => 'required|string|max:16',
    //         'status' => 'required',
    //         'branch' => 'required',
    //         'product' => 'required',
    //         'product_price' => 'required',
    //         'unit' => 'required',
    //         'qty' => 'required|numeric|max:99999999.999',
    //         'price' => 'required|numeric|max:9999999999.999',
    //         'vat' => 'required|numeric|max:9999999999.999',
    //         'discount' => 'numeric|max:999999999.999',
    //         'point' => 'numeric|max:99999999.999',
    //         'remarks' => 'string|max:500'
    //     ]);

    //     $clientId = $request->client;

    //     if (!$clientId && $request->client_phone) {
    //         $userRole = Role::where('name', 'user')->first();
    //         $ParentuserStatus = Status::where('name', 'user')->first();
    //         $userStatus = Status::where('name', 'regular')->where('parent_id', $ParentuserStatus->id)->first();
    //         $existingUser = User::where('contact', $request->client_phone)
    //             ->where('role_id', $userRole->id)
    //             ->where('status_id', $userStatus->id)
    //             ->first();

    //         if ($existingUser) {
    //             $clientId = $existingUser->id;
    //         } else {
    //             $newUser = new User();
    //             $newUser->contact = $request->client_phone;
    //             $newUser->role_id = $userRole->id;
    //             $newUser->status_id = $userStatus->id;
    //             $newUser->save();
    //             $clientId = $newUser->id;
    //         }
    //     }

    //     $existingSale = Sale::where('invoice_no', $request->invoice_no)
    //         ->where('product_id', $request->product)
    //         ->where('branch_id', $request->branch)
    //         ->where('client_id', $clientId)
    //         ->where('status_id', $request->status)
    //         ->where('unit_id', $request->unit)
    //         ->where('price', $request->price)
    //         ->first();

    //     if ($existingSale) {
    //         $existingSale->qty += $request->qty;
    //         $existingSale->vat = $request->vat;
    //         $existingSale->discount = $request->discount;
    //         $existingSale->point = $request->point;
    //         $existingSale->remarks = $request->remarks;
    //         $existingSale->updated_by = Auth::id();
    //         $existingSale->save();

    //         $saleLevel = Level::where('name', 'sale')->first();
    //         $tempStatus = Status::where('name', 'temp')->first();

    //         if ($tempStatus) {
    //             $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
    //             $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();

    //             $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
    //             $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;
    //             $isStockActive = $isCompleted || $isPartialCompleted;

    //             if ($isStockActive) {
    //                 $latestStockOverall = Stock::where('product_id', $request->product)
    //                     ->where('branch_id', $request->branch)
    //                     ->latest('id')
    //                     ->first();
    //                 $previousQty = $latestStockOverall ? $latestStockOverall->stock_qty : 0;

    //                 $existingStock = Stock::where('level_specific_id', $existingSale->id)
    //                     ->where('level_specific_type', Sale::class)
    //                     ->first();

    //                 if ($existingStock) {
    //                     $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

    //                     if ($isLatest) {
    //                         $existingStock->current_qty -= $request->qty;
    //                         $existingStock->stock_qty -= $request->qty;
    //                         $existingStock->remarks = $request->remarks;
    //                         $existingStock->save();
    //                     } else {
    //                         $reversal = new Stock();
    //                         $reversal->branch_id = $existingStock->branch_id;
    //                         $reversal->product_id = $existingStock->product_id;
    //                         $reversal->unit_id = $existingStock->unit_id;
    //                         $reversal->level_id = $existingStock->level_id;
    //                         $reversal->level_specific_id = $existingSale->id;
    //                         $reversal->level_specific_type = Sale::class;
    //                         $reversal->previous_qty = $previousQty;
    //                         $reversal->current_qty = -$existingStock->current_qty;
    //                         $reversal->stock_qty = $previousQty - $existingStock->current_qty;
    //                         $reversal->remarks = 'Reversal of sale #'.$existingSale->id;
    //                         $reversal->save();

    //                         $stock = new Stock();
    //                         $stock->branch_id = $request->branch;
    //                         $stock->product_id = $request->product;
    //                         $stock->unit_id = $request->unit;
    //                         $stock->level_id = $saleLevel ? $saleLevel->id : 1;
    //                         $stock->level_specific_id = $existingSale->id;
    //                         $stock->level_specific_type = Sale::class;
    //                         $stock->previous_qty = $reversal->stock_qty;
    //                         $stock->current_qty = -($existingStock->current_qty + $request->qty);
    //                         $stock->stock_qty = $reversal->stock_qty - ($existingStock->current_qty + $request->qty);
    //                         $stock->remarks = $request->remarks;
    //                         $stock->save();
    //                     }
    //                 } else {
    //                     $stock = new Stock();
    //                     $stock->branch_id = $request->branch;
    //                     $stock->product_id = $request->product;
    //                     $stock->unit_id = $request->unit;
    //                     $stock->level_id = $saleLevel ? $saleLevel->id : 1;
    //                     $stock->level_specific_id = $existingSale->id;
    //                     $stock->level_specific_type = Sale::class;
    //                     $stock->previous_qty = $previousQty;
    //                     $stock->current_qty = -$request->qty;
    //                     $stock->stock_qty = $previousQty - $request->qty;
    //                     $stock->remarks = $request->remarks;
    //                     $stock->save();
    //                 }
    //             }

    //             if ($isPartialCompleted) {
    //                 $totalAmount = ($existingSale->qty * $existingSale->price) + ($existingSale->qty * $existingSale->vat) - ($existingSale->qty * $existingSale->discount ?? 0);

    //                 $existingCredit = Credit::where('sale_id', $existingSale->id)->first();
    //                 if ($existingCredit) {
    //                     $existingCredit->total_amount = $totalAmount;
    //                     $existingCredit->due_amount = $totalAmount - $existingCredit->paid_amount;
    //                     $existingCredit->save();
    //                 } else {
    //                     $credit = new Credit();
    //                     $credit->sale_id = $existingSale->id;
    //                     $credit->user_id = Auth::id();
    //                     $credit->total_amount = $totalAmount;
    //                     $credit->paid_amount = 0;
    //                     $credit->due_amount = $totalAmount;
    //                     $credit->save();

    //                     event(new CreditUpdate($credit));
    //                 }
    //             }
    //         }

    //         event(new SaleUpdate($existingSale));

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Sale updated successfully',
    //             'sale' => $existingSale
    //         ]);
    //     }

    //     $sale = new Sale();
    //     $sale->invoice_no = $request->invoice_no;
    //     $sale->status_id = $request->status;
    //     $sale->client_id = $clientId;
    //     $sale->branch_id = $request->branch;
    //     $sale->product_id = $request->product;
    //     $sale->product_price_id = $request->product_price;
    //     $sale->unit_id = $request->unit;
    //     $sale->user_id = Auth::id();
    //     $sale->qty = $request->qty;
    //     $sale->price = $request->price;
    //     $sale->vat = $request->vat;
    //     $sale->discount = $request->discount;
    //     $sale->point = $request->point;
    //     $sale->remarks = $request->remarks;
    //     $sale->save();

    //     $saleLevel = Level::where('name', 'sale')->first();
    //     $tempStatus = Status::where('name', 'temp')->first();

    //     if ($tempStatus) {
    //         $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
    //         $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();

    //         $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
    //         $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;

    //         if ($isCompleted || $isPartialCompleted) {
    //             $latestStockOverall = Stock::where('product_id', $request->product)
    //                 ->where('branch_id', $request->branch)
    //                 ->latest('id')
    //                 ->first();
    //             $previousQty = $latestStockOverall ? $latestStockOverall->stock_qty : 0;

    //             $stock = new Stock();
    //             $stock->branch_id = $request->branch;
    //             $stock->product_id = $request->product;
    //             $stock->unit_id = $request->unit;
    //             $stock->level_id = $saleLevel ? $saleLevel->id : 1;
    //             $stock->level_specific_id = $sale->id;
    //             $stock->level_specific_type = Sale::class;
    //             $stock->previous_qty = $previousQty;
    //             $stock->current_qty = -$request->qty;
    //             $stock->stock_qty = $previousQty - $request->qty;
    //             $stock->remarks = $request->remarks;
    //             $stock->save();
    //         }

    //         if ($isPartialCompleted) {
    //             $totalAmount = ($sale->qty * $sale->price) + ($sale->qty * $sale->vat) - ($sale->qty * $sale->discount ?? 0);

    //             $credit = new Credit();
    //             $credit->sale_id = $sale->id;
    //             $credit->user_id = Auth::id();
    //             $credit->total_amount = $totalAmount;
    //             $credit->paid_amount = 0;
    //             $credit->due_amount = $totalAmount;
    //             $credit->save();

    //             event(new CreditUpdate($credit));
    //         }
    //     }

    //     event(new SaleUpdate($sale));

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Sale created successfully',
    //         'sale' => $sale
    //     ]);
    // }

    #[OA\Post(
        path: "/sales/update/{id}",
        summary: "Update sale",
        tags: ["Sales"],
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
                required: ["invoice_no", "status", "branch", "product", "product_price", "unit", "qty", "price", "vat"],
                properties: [
                    new OA\Property(property: "invoice_no", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "client", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "point", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Sale updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'invoice_no' => 'required|string|max:16',
            'status' => 'required',
            'branch' => 'required',
            'product' => 'required',
            'product_price' => 'required',
            'unit' => 'required',
            'qty' => 'required|numeric|max:99999999.99',
            'price' => 'required|numeric|max:9999999999.99',
            'vat' => 'required|numeric|max:9999999999.99',
            'discount' => 'max:999999999.99',
            'point' => 'max:99999999.99',
            'attributes' => 'nullable|string',
            'remarks' => 'max:500',
        ]);

        $requestAttrs = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;

        $sale = Sale::findOrFail($id);

        $clientId = $request->client;

        if (!$clientId && $request->client_phone) {
            $userRole = Role::where('name', 'user')->first();
            $ParentuserStatus = Status::where('name', 'user')->first();
            $userStatus = Status::where('name', 'regular')->where('parent_id', $ParentuserStatus->id)->first();
            $existingUser = User::where('contact', $request->client_phone)
                ->where('role_id', $userRole->id)
                ->where('status_id', $userStatus->id)
                ->first();

            if ($existingUser) {
                $clientId = $existingUser->id;
            } else {
                $newUser = new User();
                $newUser->contact = $request->client_phone;
                $newUser->role_id = $userRole->id;
                $newUser->status_id = $userStatus->id;
                $newUser->save();
                $clientId = $newUser->id;
            }
        }

        $saleLevel = Level::where('name', 'sale')->first();
        $tempStatus = Status::where('name', 'temp')->first();

        if ($tempStatus) {
            $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
            $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();

            $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
            $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;
            $isStockActive = $isCompleted || $isPartialCompleted;

            $mainQty = $sale->qty + $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);

            if ($isStockActive && $mainQty < $request->qty) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock',
                    'errors' => ['qty' => ["Not enough stock. Available: $mainQty, Required: $request->qty"]]
                ], 422);
            }

            $existingStock = Stock::where('level_specific_id', $sale->id)
                ->where('level_specific_type', Sale::class)
                ->latest('id')
                ->first();

            $latestStockOverall = Stock::where('product_id', $request->product)
                ->where('branch_id', $request->branch)
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

                $updateSaleBaseQty = (float)$request->qty;

                if ($existingStock) {
                    $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

                    if ($isStockActive) {
                        if ($isLatest) {
                            $existingStock->branch_id = $request->branch;
                            $existingStock->product_id = $request->product;
                            $existingStock->unit_id = (int)$request->unit;
                            $existingStock->current_qty = -$updateSaleBaseQty;
                            $existingStock->stock_qty = $existingStock->previous_qty - $updateSaleBaseQty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $request->remarks;
                            $existingStock->save();
                    } else {
                        $reversal = new Stock();
                        $reversal->branch_id = $existingStock->branch_id;
                        $reversal->product_id = $existingStock->product_id;
                        $reversal->unit_id = $existingStock->unit_id;
                        $reversal->level_id = $existingStock->level_id;
                        $reversal->level_specific_id = $sale->id;
                        $reversal->level_specific_type = Sale::class;
                        $reversal->previous_qty = $previousQty;
                        $reversal->current_qty = -$existingStock->current_qty;
                        $reversal->stock_qty = $previousQty - $existingStock->current_qty;
                        $reversal->attributes = $requestAttrs;
                        $reversal->remarks = 'Reversal of sale #' . $sale->id;
                        $reversal->save();

                        $stock = new Stock();
                        $stock->branch_id = $request->branch;
                        $stock->product_id = $request->product;
                        $stock->unit_id = (int)$request->unit;
                        $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                        $stock->level_specific_id = $sale->id;
                        $stock->level_specific_type = Sale::class;
                        $stock->previous_qty = $reversal->stock_qty;
                        $stock->current_qty = -$updateSaleBaseQty;
                        $stock->stock_qty = $reversal->stock_qty - $updateSaleBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $request->remarks;
                        $stock->save();
                    }
                } elseif ($isLatest) {
                    $existingStock->delete();
                } else {
                    $reversal = new Stock();
                    $reversal->branch_id = $existingStock->branch_id;
                    $reversal->product_id = $existingStock->product_id;
                    $reversal->unit_id = $existingStock->unit_id;
                    $reversal->level_id = $existingStock->level_id;
                    $reversal->level_specific_id = $sale->id;
                    $reversal->level_specific_type = Sale::class;
                    $reversal->previous_qty = $previousQty;
                    $reversal->current_qty = -$existingStock->current_qty;
                    $reversal->stock_qty = $previousQty - $existingStock->current_qty;
                    $reversal->attributes = $requestAttrs;
                    $reversal->remarks = 'Reversal of sale #' . $sale->id;
                    $reversal->save();
                }
            } elseif ($isStockActive) {
                $stock = new Stock();
                $stock->branch_id = $request->branch;
                $stock->product_id = $request->product;
                $stock->unit_id = (int)$request->unit;
                $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                $stock->level_specific_id = $sale->id;
                $stock->level_specific_type = Sale::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = -$updateSaleBaseQty;
                $stock->stock_qty = $previousQty - $updateSaleBaseQty;
                $stock->attributes = $requestAttrs;
                $stock->remarks = $request->remarks;
                $stock->save();
            }

            $this->updateProductStockStatus($request->product);
        }

        $sale->invoice_no = $request->invoice_no;
        $sale->status_id = $request->status;
        $sale->client_id = $clientId;
        $sale->branch_id = $request->branch;
        $sale->product_id = $request->product;
        $sale->product_price_id = $request->product_price;
        $sale->unit_id = $request->unit;
        $sale->updated_by = Auth::id();
        $sale->qty = $request->qty;
        $sale->price = $request->price;
        $sale->vat = $request->vat;
        $sale->discount = $request->discount;
        $sale->point = $request->point;
        $sale->attributes = $requestAttrs;
        $sale->remarks = $request->remarks;
        $sale->update();

        $invoiceTotal = Sale::where('invoice_no', $sale->invoice_no)
            ->get()
            ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

        $existingCredit = Credit::where('invoice_no', $sale->invoice_no)
            ->where('credit_type', 'sale')
            ->first();

        if ($invoiceTotal > 0) {
            if ($existingCredit) {
                $existingCredit->updated_by = Auth::id();
                $existingCredit->total_amount = $invoiceTotal;
                $existingCredit->due_amount = max(0, $invoiceTotal - $existingCredit->paid_amount);
                $existingCredit->save();
            } else {
                $credit = new Credit();
                $credit->credit_type = 'sale';
                $credit->invoice_no = $sale->invoice_no;
                $credit->user_id = Auth::id();
                $credit->total_amount = $invoiceTotal;
                $credit->paid_amount = 0;
                $credit->due_amount = $invoiceTotal;
                $credit->save();
            }
        } elseif ($existingCredit) {
            $existingCredit->delete();
        }

        event(new SaleUpdate($sale));

        return response()->json([
            'status' => 'success',
            'message' => 'Sale updated successfully',
            'sale' => $sale
        ]);
    }

    #[OA\Post(
        path: "/sales/delete/{id}",
        tags: ["Sales"],
        summary: "Delete sale",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the sale",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Sale deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $sale = Sale::findOrFail($id);

            $existingStock = Stock::where('level_specific_id', $sale->id)
                ->where('level_specific_type', Sale::class)
                ->latest('id')
                ->first();

        if ($existingStock) {
            $saleAttrs = $sale->attributes;
            $latestStock = Stock::where('product_id', $sale->product_id)
                ->where('branch_id', $sale->branch_id)
                ->when($saleAttrs, function ($q) use ($saleAttrs) {
                    foreach ($saleAttrs as $k => $v) {
                        $q->where("attributes->{$k}", $v);
                    }
                }, function ($q) {
                    $q->whereNull('attributes');
                })
                ->latest('id')
                ->first();
            $previousQty = $latestStock ? $latestStock->stock_qty : 0;

            $destroySaleBaseQty = (float)$sale->qty;

            $stock = new Stock();
            $stock->branch_id = $sale->branch_id;
            $stock->product_id = $sale->product_id;
            $stock->unit_id = (int)$sale->unit_id;
            $stock->level_id = $existingStock->level_id;
            $stock->level_specific_id = $sale->id;
            $stock->level_specific_type = Sale::class;
            $stock->previous_qty = $previousQty;
            $stock->current_qty = $destroySaleBaseQty;
            $stock->stock_qty = $previousQty + $destroySaleBaseQty;
            $stock->attributes = $saleAttrs;
            $stock->remarks = 'Sale deleted - reversal';
            $stock->save();
        }

        $invoiceNo = $sale->invoice_no;

        $saleData = $sale->toArray();

        $sale->delete();

        $invoiceTotal = Sale::where('invoice_no', $invoiceNo)
            ->get()
            ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

        $existingCredit = Credit::where('invoice_no', $invoiceNo)
            ->where('credit_type', 'sale')
            ->first();

        if ($invoiceTotal > 0 && $existingCredit) {
            $existingCredit->total_amount = $invoiceTotal;
            $existingCredit->due_amount = max(0, $invoiceTotal - $existingCredit->paid_amount);
            $existingCredit->save();
        } elseif ($invoiceTotal <= 0 && $existingCredit) {
            $existingCredit->delete();
        }

        event(new SaleUpdate($saleData));

        return response()->json([
            'status' => 'success',
            'message' => 'Sale deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['invoice_nos' => 'required|array', 'invoice_nos.*' => 'string']);

        $sales = Sale::whereIn('invoice_no', $request->invoice_nos)->get();

        foreach ($sales as $sale) {
            $existingStock = \Modules\Stock\Models\Stock::where('level_specific_id', $sale->id)
                ->where('level_specific_type', Sale::class)
                ->latest('id')
                ->first();

            if ($existingStock) {
                $saleAttrs = $sale->attributes;
                $latestStock = \Modules\Stock\Models\Stock::where('product_id', $sale->product_id)
                    ->where('branch_id', $sale->branch_id)
                    ->when($saleAttrs, function ($q) use ($saleAttrs) {
                        foreach ($saleAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->latest('id')
                    ->first();
                $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                $stock = new \Modules\Stock\Models\Stock();
                $stock->branch_id = $sale->branch_id;
                $stock->product_id = $sale->product_id;
                $stock->unit_id = (int)$sale->unit_id;
                $stock->level_id = $existingStock->level_id;
                $stock->level_specific_id = $sale->id;
                $stock->level_specific_type = Sale::class;
                $stock->previous_qty = $previousQty;
                $stock->current_qty = (float)$sale->qty;
                $stock->stock_qty = $previousQty + (float)$sale->qty;
                $stock->attributes = $saleAttrs;
                $stock->remarks = 'Sale deleted - reversal';
                $stock->save();
            }

            $invoiceNo = $sale->invoice_no;
            $saleData = $sale->toArray();
            $sale->delete();

            $invoiceTotal = Sale::where('invoice_no', $invoiceNo)
                ->get()
                ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

            $existingCredit = \Modules\Credit\Models\Credit::where('invoice_no', $invoiceNo)
                ->where('credit_type', 'sale')
                ->first();

            if ($invoiceTotal > 0 && $existingCredit) {
                $existingCredit->total_amount = $invoiceTotal;
                $existingCredit->due_amount = max(0, $invoiceTotal - $existingCredit->paid_amount);
                $existingCredit->save();
            } elseif ($invoiceTotal <= 0 && $existingCredit) {
                $existingCredit->delete();
            }

            event(new \Modules\Sale\Events\SaleUpdate($saleData));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->invoice_nos) . ' invoices deleted successfully'
        ]);
    }

    #[OA\Post(
        path: "/sales/product/{id}",
        tags: ["Sales"],
        summary: "Get users by product ID",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product price fetched successfully"),
        ],
        security: [["bearerAuth" => []]]
    )]
    public function getProductPriceByProduct (int $id) {
        if (!$id) {
            return response()->json([]);
        }

        $productPrice = ProductPrice::where('product_id', $id)->first();

        if (!$productPrice) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product price not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'price' => $productPrice->price,
            'point' => $productPrice->point,
            'unit_name' => $productPrice->product->unit->name ?? null    
        ]);
    }
}
