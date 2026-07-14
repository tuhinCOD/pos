<?php

namespace Modules\Temp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Level\Models\Level;
use Modules\Product\Models\Product;
use Modules\ProductPrice\Models\ProductPrice;
use Modules\Role\Models\Role;
use Modules\Credit\Events\CreditUpdate;
use Modules\Credit\Models\Credit;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\Stock\Models\Stock;
use Modules\Temp\Models\Temp;
use Modules\Temp\Events\TempUpdate;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;
use Modules\Barcode\Models\Barcode;
use Modules\Payment\Models\Payment;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "Temps")]
class TempController extends Controller
{
    #[OA\Get(
        path: "/temps",
        tags: ["Temps"],
        summary: "List temps",
        description: "Get paginated temps, with optional search by product",
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
                description: "Temps fetched successfully",
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
        $temps = Temp::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'updatedBy'])
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

        $tempStatus = Status::where('name', 'temp')->first();
        $statuses = Status::where('parent_id', $tempStatus->id)->get();
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
            'temps' => $temps,
            'clients' => $clients,
            'branches' => $branches,
            'statuses' => $statuses,
            'products' => $products,
            'product_price' => $product_price,
            'unit' => $unit,
            'barcodes' => $barcodes,
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Temp::min('created_at');

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

        $temps = Temp::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'updatedBy'])
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
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->branch, fn($q) => $q->where('branch_id', $request->branch))
            ->when($request->products, function ($query) use ($request) {
                $ids = is_array($request->products) ? $request->products : explode(',', $request->products);
                $query->whereIn('product_id', $ids);
            })
            ->when($request->invoice_nos, fn($q) => $q->whereIn('invoice_no', explode(',', $request->invoice_nos)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->latest('id')
            ->get();

        $data = $temps->map(fn($t) => [
            'Invoice No' => $t->invoice_no,
            'Product' => $t->product?->name ?? '-',
            'Client' => $t->client?->name ?? $t->client?->contact ?? '-',
            'Branch' => $t->branch?->name ?? '-',
            'Qty' => $t->qty,
            'Price' => $t->price,
            'Vat' => $t->vat,
            'Discount' => $t->discount ?? 0,
            'Total' => ($t->qty * $t->price) + ($t->qty * ($t->vat ?? 0)) - ($t->qty * ($t->discount ?? 0)),
            'Status' => $t->status?->name ?? '-',
            'Created By' => $t->user?->name ?? '-',
            'Updated By' => $t->updatedBy?->name ?? '-',
            'Created At' => $t->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $t->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['Invoice No', 'Product', 'Client', 'Branch', 'Qty', 'Price', 'Vat', 'Discount', 'Total', 'Status', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'temps_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Temps', 'temps');

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
        $temp = Temp::with(['branch', 'status', 'product', 'productPrice', 'client', 'user'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'temp' => $temp
        ]);
    }

    public function today()
    {
        $lastTemp = Temp::latest('id')->first();

        if (!$lastTemp) {
            return response()->json([
                'status' => 'success',
                'temps' => []
            ]);
        }

        $invoiceNo = $lastTemp->invoice_no;

        $temps = Temp::with(['product', 'status', 'branch', 'client', 'user', 'unit', 'productPrice'])
            ->where('invoice_no', $invoiceNo)
            ->get();

        return response()->json([
            'status' => 'success',
            'temps' => $temps
        ]);
    }

    #[OA\Post(
        path: "/temps",
        summary: "Create new temp",
        tags: ["Temps"],
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
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Temp created"),
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

        $existingTemp = Temp::where('invoice_no', $request->invoice_no)
            ->where('product_id', $request->product)
            ->where('branch_id', $request->branch)
            ->where('client_id', $clientId)
            ->where('status_id', $request->status)
            ->where('unit_id', $request->unit)
            ->where('price', $request->price)
            ->where('vat', $request->vat)
            ->where('discount', $request->discount)
            ->where('point', $request->point)
            ->when($requestAttrs, function ($q) use ($requestAttrs) {
                foreach ($requestAttrs as $k => $v) {
                    $q->where("attributes->{$k}", $v);
                }
            }, function ($q) {
                $q->whereNull('attributes');
            })
            ->first();

        $existingSale = Sale::where('invoice_no', $request->invoice_no)
            ->where('product_id', $request->product)
            ->where('branch_id', $request->branch)
            ->where('client_id', $clientId)
            ->where('status_id', $request->status)
            ->where('unit_id', $request->unit)
            ->where('price', $request->price)
            ->where('vat', $request->vat)
            ->where('discount', $request->discount)
            ->where('point', $request->point)
            ->when($requestAttrs, function ($q) use ($requestAttrs) {
                foreach ($requestAttrs as $k => $v) {
                    $q->where("attributes->{$k}", $v);
                }
            }, function ($q) {
                $q->whereNull('attributes');
            })
            ->first();

        $existingSaleValidation = Sale::where('invoice_no', $request->invoice_no)
            ->where('product_id', $request->product)
            ->where('branch_id', $request->branch)
            ->where('client_id', $clientId)
            ->where('unit_id', $request->unit)
            ->where('price', $request->price)
            ->where('vat', $request->vat)
            ->where('discount', $request->discount)
            ->where('point', $request->point)
            ->when($requestAttrs, function ($q) use ($requestAttrs) {
                foreach ($requestAttrs as $k => $v) {
                    $q->where("attributes->{$k}", $v);
                }
            }, function ($q) {
                $q->whereNull('attributes');
            })
            ->first();

        if ($existingSaleValidation) {
            $availableQty = $existingSaleValidation->qty + $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
            if ($availableQty < $request->qty) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock',
                    'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                ], 422);
            }
        }

        if ($existingTemp) {
            $totalQty = $existingTemp->qty + $request->qty;
            $qtyForSale = $existingSale ? $existingSale->qty + $totalQty : $totalQty;
            if ($existingSale) {
                $mainQty = $existingSale->qty + $this->getStockQty($existingTemp->product_id, $existingTemp->branch_id, $requestAttrs ?? []);
                if ($mainQty < $qtyForSale) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $mainQty, Required: $qtyForSale"]]
                    ], 422);
                }
            } else {
                $availableQty = $this->getStockQty($existingTemp->product_id, $existingTemp->branch_id, $requestAttrs ?? []) - $existingTemp->qty;
                if ($availableQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                    ], 422);
                }
            }

            $existingTemp->client_id = $clientId;
            $existingTemp->qty += $request->qty;
            $existingTemp->remarks = $request->remarks;
            $existingTemp->updated_by = Auth::id();
            $existingTemp->save();
            event(new TempUpdate($existingTemp));

            $tempStatus = Status::where('name', 'temp')->first();

            if ($tempStatus) {
                $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
                $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();
                $cancelledStatus = Status::where('name', 'cancelled')->where('parent_id', $tempStatus->id)->first();

                $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
                $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;
                $isCancelled = $cancelledStatus && (int)$request->status === $cancelledStatus->id;

                if ($isCancelled) {
                    $existingTemp->delete();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Temp cancelled successfully',
                    ]);
                }

                if ($isCompleted || $isPartialCompleted) {
                    $saleLevel = Level::where('name', 'sale')->first();

                    $latestStockOverall = Stock::where('product_id', $existingTemp->product_id)
                        ->where('branch_id', $existingTemp->branch_id)
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

                    if ($existingSale) {
                        $existingSale->client_id = $clientId;
                        $existingSale->qty = $qtyForSale;
                        $existingSale->attributes = $existingTemp->attributes;
                        $existingSale->remarks = $existingTemp->remarks;
                        $existingSale->updated_by = Auth::id();
                        $existingSale->save();

                        $sale = $existingSale;
                    } else {
                        $sale = new Sale();
                        $sale->invoice_no = $existingTemp->invoice_no;
                        $sale->status_id = $request->status;
                        $sale->client_id = $existingTemp->client_id;
                        $sale->branch_id = $existingTemp->branch_id;
                        $sale->product_id = $existingTemp->product_id;
                        $sale->product_price_id = $request->product_price;
                        $sale->unit_id = $existingTemp->unit_id;
                        $sale->user_id = $existingTemp->user_id;
                        $sale->qty = $totalQty;
                        $sale->price = $existingTemp->price;
                        $sale->vat = $existingTemp->vat;
                        $sale->discount = $existingTemp->discount;
                        $sale->point = $existingTemp->point;
                        $sale->attributes = $existingTemp->attributes;
                        $sale->remarks = $existingTemp->remarks;
                        $sale->save();

                        $this->addPointToUser($sale->client_id, (float)$sale->point);
                    }

                    $existingStock = Stock::where('level_specific_id', $sale->id)
                        ->where('level_specific_type', Sale::class)
                        ->latest('id')
                        ->first();

                    if ($existingStock) {
                        $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

                        if ($isLatest) {
                            $existingStock->current_qty = -$qtyForSale;
                            $existingStock->stock_qty = $existingStock->previous_qty - $qtyForSale;
                            $existingStock->remarks = $existingTemp->remarks;
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
                            $reversal->remarks = 'Reversal of sale #'.$sale->id;
                            $reversal->save();

                            $tempBaseQty = (float)$qtyForSale;

                            $stock = new Stock();
                            $stock->branch_id = $existingTemp->branch_id;
                            $stock->product_id = $existingTemp->product_id;
                            $stock->unit_id = (int)$existingTemp->unit_id;
                            $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                            $stock->level_specific_id = $sale->id;
                            $stock->level_specific_type = Sale::class;
                            $stock->previous_qty = $reversal->stock_qty;
                            $stock->current_qty = -$tempBaseQty;
                            $stock->stock_qty = $reversal->stock_qty - $tempBaseQty;
                            $stock->attributes = $requestAttrs;
                            $stock->remarks = $existingTemp->remarks;
                            $stock->save();
                        }
                    } else {
                        $tempBaseQty = (float)$qtyForSale;

                        $stock = new Stock();
                        $stock->branch_id = $existingTemp->branch_id;
                        $stock->product_id = $existingTemp->product_id;
                        $stock->unit_id = (int)$existingTemp->unit_id;
                        $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                        $stock->level_specific_id = $sale->id;
                        $stock->level_specific_type = Sale::class;
                        $stock->previous_qty = $previousQty;
                        $stock->current_qty = -$tempBaseQty;
                        $stock->stock_qty = $previousQty - $tempBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $existingTemp->remarks;
                        $stock->save();
                    }

                    $this->updateProductStockStatus($existingTemp->product_id);

                    if ($isPartialCompleted) {
                        $totalAmount = Sale::where('invoice_no', $sale->invoice_no)
                            ->get()
                            ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

                        $existingCredit = Credit::where('invoice_no', $sale->invoice_no)->where('credit_type', 'sale')->first();
                        if ($totalAmount > 0) {
                            if ($existingCredit) {
                                $existingCredit->updated_by = Auth::id();
                                $existingCredit->total_amount = $totalAmount;
                                $existingCredit->due_amount = $totalAmount - $existingCredit->paid_amount;
                                $existingCredit->save();
                            } else {
                                $credit = new Credit();
                                $credit->credit_type = 'sale';
                                $credit->invoice_no = $sale->invoice_no;
                                $credit->user_id = Auth::id();
                                $credit->total_amount = $totalAmount;
                                $totalPaidForInvoice = Payment::where('payment_invoice_no', $credit->invoice_no)->sum('amount');
                                $credit->paid_amount = $totalPaidForInvoice;
                                $credit->due_amount = $totalAmount - $totalPaidForInvoice;
                                $credit->save();

                                event(new CreditUpdate($credit));
                            }
                        } elseif ($existingCredit) {
                            $existingCredit->delete();
                        }
                    }

                    $tempId = $existingTemp->id;
                    $existingTemp->delete();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Temp converted to sale successfully',
                        'sale' => $sale
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Temp updated successfully',
                'temp' => $existingTemp
            ]);
        } else if ($existingSale) {
            $tempStatus = Status::where('name', 'temp')->first();

            if ($tempStatus) {
                $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
                $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();
                $cancelledStatus = Status::where('name', 'cancelled')->where('parent_id', $tempStatus->id)->first();

                $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
                $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;
                $isCancelled = $cancelledStatus && (int)$request->status === $cancelledStatus->id;

                $availableQty = $existingSale->qty + $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                if ($availableQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                    ], 422);
                }
            }

            if ($isCancelled) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No changes made',
                ]);
            }

            if ($isCompleted || $isPartialCompleted) {
                $existingSale->qty += $request->qty;
                $existingSale->invoice_no = $request->invoice_no;
                $existingSale->status_id = $request->status;
                $existingSale->client_id = $clientId;
                $existingSale->branch_id = $request->branch;
                $existingSale->product_id = $request->product;
                $existingSale->product_price_id = $request->product_price;
                $existingSale->unit_id = $request->unit;
                $existingSale->updated_by = Auth::id();
                $existingSale->price = $request->price;
                $existingSale->vat = $request->vat;
                $existingSale->discount = $request->discount;
                $existingSale->point = $request->point;
                $existingSale->attributes = $request->input('attributes') ? json_decode($request->input('attributes'), true) : null;
                $existingSale->remarks = $request->remarks;
                $existingSale->update();

                $saleLevel = Level::where('name', 'sale')->first();

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

                $existingStock = Stock::where('level_specific_id', $existingSale->id)
                    ->where('level_specific_type', Sale::class)
                    ->latest('id')
                    ->first();

                if ($existingStock) {
                    $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

                    if ($isLatest) {
                        $existingStock->current_qty -= $request->qty;
                        $existingStock->stock_qty -= $request->qty;
                        $existingStock->attributes = $requestAttrs;
                        $existingStock->remarks = $request->remarks;
                        $existingStock->save();
                    } else {
                        $existingSaleBaseQty = (float)$existingSale->qty;
                        $reversal = new Stock();
                        $reversal->branch_id = $existingStock->branch_id;
                        $reversal->product_id = $existingStock->product_id;
                        $reversal->unit_id = $existingStock->unit_id;
                        $reversal->level_id = $existingStock->level_id;
                        $reversal->level_specific_id = $existingSale->id;
                        $reversal->level_specific_type = Sale::class;
                        $reversal->previous_qty = $previousQty;
                        $reversal->current_qty = -$existingStock->current_qty;
                        $reversal->stock_qty = $previousQty - $existingStock->current_qty;
                        $reversal->attributes = $requestAttrs;
                        $reversal->remarks = 'Reversal of sale #'.$existingSale->id;
                        $reversal->save();

                        $stock = new Stock();
                        $stock->branch_id = $request->branch;
                        $stock->product_id = $request->product;
                        $stock->unit_id = (int)$request->unit;
                        $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                        $stock->level_specific_id = $existingSale->id;
                        $stock->level_specific_type = Sale::class;
                        $stock->previous_qty = $reversal->stock_qty;
                        $stock->current_qty = -$existingSaleBaseQty;
                        $stock->stock_qty = $reversal->stock_qty - $existingSaleBaseQty;
                        $stock->attributes = $requestAttrs;
                        $stock->remarks = $request->remarks;
                        $stock->save();
                    }
                } else {
                    $requestBaseQty = (float)$request->qty;

                    $stock = new Stock();
                    $stock->branch_id = $request->branch;
                    $stock->product_id = $request->product;
                    $stock->unit_id = (int)$request->unit;
                    $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                    $stock->level_specific_id = $existingSale->id;
                    $stock->level_specific_type = Sale::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = -$requestBaseQty;
                    $stock->stock_qty = $previousQty - $requestBaseQty;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $request->remarks;
                    $stock->save();
                }

                $this->updateProductStockStatus($request->product);

                if ($isPartialCompleted) {
                    $invoiceTotal = Sale::where('invoice_no', $existingSale->invoice_no)
                        ->get()
                        ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

                    $existingCredit = Credit::where('invoice_no', $existingSale->invoice_no)->where('credit_type', 'sale')->first();
                    if ($invoiceTotal > 0) {
                        if ($existingCredit) {
                            $existingCredit->updated_by = Auth::id();
                            $existingCredit->total_amount = $invoiceTotal;
                            $existingCredit->due_amount = $invoiceTotal - $existingCredit->paid_amount;
                            $existingCredit->save();
                        } else {
                            $credit = new Credit();
                            $credit->credit_type = 'sale';
                            $credit->invoice_no = $existingSale->invoice_no;
                            $credit->user_id = Auth::id();
                                $credit->total_amount = $invoiceTotal;
                            $totalPaidForInvoice = Payment::where('payment_invoice_no', $credit->invoice_no)->sum('amount');
                            $credit->paid_amount = $totalPaidForInvoice;
                            $credit->due_amount = $invoiceTotal - $totalPaidForInvoice;
                            $credit->save();

                            event(new CreditUpdate($credit));
                        }
                    } elseif ($existingCredit) {
                        $existingCredit->delete();
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale updated successfully',
                    'sale' => $existingSale
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sale updated successfully',
                'sale' => $existingSale
            ]);
        } else {
            $tempStatus = Status::where('name', 'temp')->first();

            if ($tempStatus) {
                $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
                $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();
                $cancelledStatus = Status::where('name', 'cancelled')->where('parent_id', $tempStatus->id)->first();

                $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
                $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;
                $isCancelled = $cancelledStatus && (int)$request->status === $cancelledStatus->id;

                $availableQty = $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                if ($availableQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                    ], 422);
                }
            }

            $temp = new Temp();
            $temp->invoice_no = $request->invoice_no;
            $temp->status_id = $request->status;
            $temp->client_id = $clientId;
            $temp->branch_id = $request->branch;
            $temp->product_id = $request->product;
            $temp->product_price_id = $request->product_price;
            $temp->unit_id = $request->unit;
            $temp->user_id = Auth::id();
            $temp->qty = $request->qty;
            $temp->price = $request->price;
            $temp->vat = $request->vat;
            $temp->discount = $request->discount;
            $temp->point = $request->point;
            $temp->attributes = $requestAttrs;
            $temp->remarks = $request->remarks;
            $temp->save();
            event(new TempUpdate($temp));

            if ($tempStatus) {
                if ($isCancelled) {
                    $temp->delete();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Temp cancelled successfully',
                    ]);
                }

                if ($isCompleted || $isPartialCompleted) {
                    $saleLevel = Level::where('name', 'sale')->first();

                    $latestStock = Stock::where('product_id', $temp->product_id)
                        ->where('branch_id', $temp->branch_id)
                        ->when($requestAttrs, function ($q) use ($requestAttrs) {
                            foreach ($requestAttrs as $k => $v) {
                                $q->where("attributes->{$k}", $v);
                            }
                        }, function ($q) {
                            $q->whereNull('attributes');
                        })
                        ->latest('id')
                        ->first();
                    $previousQty = $latestStock ? $latestStock->stock_qty : 0;

                    $sale = new Sale();
                    $sale->invoice_no = $temp->invoice_no;
                    $sale->status_id = $temp->status_id;
                    $sale->client_id = $temp->client_id;
                    $sale->branch_id = $temp->branch_id;
                    $sale->product_id = $temp->product_id;
                    $sale->product_price_id = $temp->product_price_id;
                    $sale->unit_id = $temp->unit_id;
                    $sale->user_id = $temp->user_id;
                    $sale->qty = $temp->qty;
                    $sale->price = $temp->price;
                    $sale->vat = $temp->vat;
                    $sale->discount = $temp->discount;
                    $sale->point = $temp->point;
                    $sale->attributes = $temp->attributes;
                    $sale->remarks = $temp->remarks;
                    $sale->save();

                    $this->addPointToUser($sale->client_id, (float)$sale->point);

                    $tempQtyForSale = (float)$temp->qty;

                    $stock = new Stock();
                    $stock->branch_id = $temp->branch_id;
                    $stock->product_id = $temp->product_id;
                    $stock->unit_id = (int)$temp->unit_id;
                    $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                    $stock->level_specific_id = $sale->id;
                    $stock->level_specific_type = Sale::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = -$tempQtyForSale;
                    $stock->stock_qty = $previousQty - $tempQtyForSale;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $temp->remarks;
                    $stock->save();

                    $this->updateProductStockStatus($temp->product_id);

                    if ($isPartialCompleted) {
                        $invoiceTotal = Sale::where('invoice_no', $sale->invoice_no)
                            ->get()
                            ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

                        $existingCredit = Credit::where('invoice_no', $sale->invoice_no)->where('credit_type', 'sale')->first();
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
                                $totalPaidForInvoice = Payment::where('payment_invoice_no', $credit->invoice_no)->sum('amount');
                                $credit->paid_amount = $totalPaidForInvoice;
                                $credit->due_amount = $invoiceTotal - $totalPaidForInvoice;
                                $credit->save();

                                event(new CreditUpdate($credit));
                            }
                        } elseif ($existingCredit) {
                            $existingCredit->delete();
                        }
                    }

                    $tempId = $temp->id;
                    $temp->delete();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Temp converted to sale successfully',
                        'sale' => $sale
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Temp created successfully',
                'temp' => $temp
            ]);
        }
    }

    public function validateBatch(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.invoice_no' => 'required|string|max:16',
            'items.*.branch' => 'required',
            'items.*.product' => 'required',
            'items.*.qty' => 'required|numeric|max:99999999.99',
            'items.*.price' => 'required|numeric|max:9999999999.99',
            'items.*.vat' => 'required|numeric|max:9999999999.99',
            'items.*.discount' => 'max:999999999.99',
            'items.*.point' => 'max:99999999.99',
            'items.*.attributes' => 'nullable|string',
        ]);

        $errors = [];
        $clientId = $request->input('items.0.client');

        foreach ($request->items as $index => $item) {
            $productId = (int)$item['product'];
            $branchId = (int)$item['branch'];
            $qty = (float)$item['qty'];
            $itemAttrs = !empty($item['attributes']) ? json_decode($item['attributes'], true) : null;

            $existingTemp = null;
            $existingSale = null;

            if ($item['invoice_no']) {
                $existingTemp = Temp::where('invoice_no', $item['invoice_no'])
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->where('client_id', $clientId)
                    ->where('status_id', $item['status'] ?? null)
                    ->where('unit_id', $item['unit'] ?? null)
                    ->where('price', $item['price'] ?? 0)
                    ->where('vat', $item['vat'] ?? 0)
                    ->where('discount', $item['discount'] ?? 0)
                    ->where('point', $item['point'] ?? 0)
                    ->when($itemAttrs, function ($q) use ($itemAttrs) {
                        foreach ($itemAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->first();

                $existingSale = Sale::where('invoice_no', $item['invoice_no'])
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->where('client_id', $clientId)
                    ->where('status_id', $item['status'] ?? null)
                    ->where('unit_id', $item['unit'] ?? null)
                    ->where('price', $item['price'] ?? 0)
                    ->where('vat', $item['vat'] ?? 0)
                    ->where('discount', $item['discount'] ?? 0)
                    ->where('point', $item['point'] ?? 0)
                    ->when($itemAttrs, function ($q) use ($itemAttrs) {
                        foreach ($itemAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->first();

                $existingSaleValidation = Sale::where('invoice_no', $item['invoice_no'])
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->where('client_id', $clientId)
                    ->where('unit_id', $item['unit'] ?? null)
                    ->where('price', $item['price'] ?? 0)
                    ->where('vat', $item['vat'] ?? 0)
                    ->where('discount', $item['discount'] ?? 0)
                    ->where('point', $item['point'] ?? 0)
                    ->when($itemAttrs, function ($q) use ($itemAttrs) {
                        foreach ($itemAttrs as $k => $v) {
                            $q->where("attributes->{$k}", $v);
                        }
                    }, function ($q) {
                        $q->whereNull('attributes');
                    })
                    ->first();

                if ($existingSaleValidation) {
                    $availableQty = $existingSaleValidation->qty + $this->getStockQty($productId, $branchId, $itemAttrs ?? []);
                    if ($availableQty < $qty) {
                        $errors[] = "Item ".($index + 1).": Not enough stock. Available: $availableQty, Required: $qty";
                        continue;
                    }
                }
            }

            if ($existingTemp) {
                $totalQty = $existingTemp->qty + $qty;
                $qtyForSale = $existingSale ? $existingSale->qty + $totalQty : $totalQty;

                if ($existingSale) {
                    $mainQty = $existingSale->qty + $this->getStockQty($existingTemp->product_id, $existingTemp->branch_id, $itemAttrs ?? []);
                    if ($mainQty < $qtyForSale) {
                        $errors[] = "Item ".($index + 1).": Not enough stock. Available: $mainQty, Required: $qtyForSale";
                    }
                } else {
                    $availableQty = $this->getStockQty($existingTemp->product_id, $existingTemp->branch_id, $itemAttrs ?? []) - $existingTemp->qty;
                    if ($availableQty < $qty) {
                        $errors[] = "Item ".($index + 1).": Not enough stock. Available: $availableQty, Required: $qty";
                    }
                }
            } elseif ($existingSale && !$existingSaleValidation) {
                $availableQty = $existingSale->qty + $this->getStockQty($productId, $branchId, $itemAttrs ?? []);
                if ($availableQty < $qty) {
                    $errors[] = "Item ".($index + 1).": Not enough stock. Available: $availableQty, Required: $qty";
                }
            } elseif (!$existingSaleValidation) {
                $availableQty = $this->getStockQty($productId, $branchId, $itemAttrs ?? []);
                if ($availableQty < $qty) {
                    $errors[] = "Item ".($index + 1).": Not enough stock. Available: $availableQty, Required: $qty";
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

    #[OA\Post(
        path: "/temps/update/{id}",
        summary: "Update temp",
        tags: ["Temps"],
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
                    new OA\Property(property: "client_phone", type: "string"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "product_price", type: "integer"),
                    new OA\Property(property: "unit", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "price", type: "number", format: "float"),
                    new OA\Property(property: "vat", type: "number", format: "float"),
                    new OA\Property(property: "discount", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Temp updated"),
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

        $temp = Temp::findOrFail($id);

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

        $existingSale = Sale::where('invoice_no', $request->invoice_no)
            ->where('product_id', $request->product)
            ->where('branch_id', $request->branch)
            ->where('client_id', $clientId)
            ->where('status_id', $request->status)
            ->where('unit_id', $request->unit)
            ->where('price', $request->price)
            ->where('vat', $request->vat)
            ->where('discount', $request->discount)
            ->where('point', $request->point)
            ->when($requestAttrs, function ($q) use ($requestAttrs) {
                foreach ($requestAttrs as $k => $v) {
                    $q->where("attributes->{$k}", $v);
                }
            }, function ($q) {
                $q->whereNull('attributes');
            })
            ->first();

        $existingSaleValidation = Sale::where('invoice_no', $request->invoice_no)
            ->where('product_id', $request->product)
            ->where('branch_id', $request->branch)
            ->where('client_id', $clientId)
            ->where('unit_id', $request->unit)
            ->where('price', $request->price)
            ->where('vat', $request->vat)
            ->where('discount', $request->discount)
            ->where('point', $request->point)
            ->when($requestAttrs, function ($q) use ($requestAttrs) {
                foreach ($requestAttrs as $k => $v) {
                    $q->where("attributes->{$k}", $v);
                }
            }, function ($q) {
                $q->whereNull('attributes');
            })
            ->first();

        $tempStatus = Status::where('name', 'temp')->first();

        if ($tempStatus) {
            $completedStatus = Status::where('name', 'completed')->where('parent_id', $tempStatus->id)->first();
            $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $tempStatus->id)->first();
            $cancelledStatus = Status::where('name', 'cancelled')->where('parent_id', $tempStatus->id)->first();

            $isCompleted = $completedStatus && (int)$request->status === $completedStatus->id;
            $isPartialCompleted = $partialCompletedStatus && (int)$request->status === $partialCompletedStatus->id;
            $isCancelled = $cancelledStatus && (int)$request->status === $cancelledStatus->id;

            if ($existingSaleValidation) {
                $mainQty = $existingSaleValidation->qty + $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                if ($mainQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $mainQty, Required: $request->qty"]]
                    ], 422);
                }
            } else {
                $availableQty = $this->getStockQty($request->product, $request->branch, $requestAttrs ?? []);
                if ($availableQty < $request->qty) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient stock',
                        'errors' => ['qty' => ["Not enough stock. Available: $availableQty, Required: $request->qty"]]
                    ], 422);
                }
            }

            $temp->invoice_no = $request->invoice_no;
        $temp->status_id = $request->status;
        $temp->client_id = $clientId;
        $temp->branch_id = $request->branch;
        $temp->product_id = $request->product;
        $temp->product_price_id = $request->product_price;
        $temp->unit_id = $request->unit;
        $temp->updated_by = Auth::id();
        $temp->qty = $request->qty;
        $temp->price = $request->price;
        $temp->vat = $request->vat;
        $temp->discount = $request->discount;
        $temp->point = $request->point;
        $temp->attributes = $requestAttrs;
        $temp->remarks = $request->remarks;
        $temp->update();
        event(new TempUpdate($temp));

        if ($tempStatus) {
            if ($isCancelled) {
                $temp->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Temp cancelled successfully',
                ]);
            }

                if ($isCompleted) {
                    $invoiceTotal = Temp::where('invoice_no', $temp->invoice_no)
                        ->get()
                        ->sum(fn($t) => ($t->qty * $t->price) + ($t->qty * ($t->vat ?? 0)) - ($t->qty * ($t->discount ?? 0)));

                    $totalPaid = Payment::where('payment_invoice_no', $temp->invoice_no)
                        ->get()
                        ->sum(fn($p) => (float)$p->amount);

                    if ($totalPaid < $invoiceTotal) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invoice is not fully paid. Complete the payment first.',
                        ], 422);
                    }
                }

                if ($isCompleted || $isPartialCompleted) {
                    $saleLevel = Level::where('name', 'sale')->first();

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

                if ($existingSale) {
                    $existingSale->invoice_no = $request->invoice_no;
                    $existingSale->status_id = $request->status;
                    $existingSale->client_id = $clientId;
                    $existingSale->branch_id = $request->branch;
                    $existingSale->product_id = $request->product;
                    $existingSale->product_price_id = $request->product_price;
                    $existingSale->unit_id = $request->unit;
                    $existingSale->updated_by = Auth::id();
                    $existingSale->qty += $temp->qty;
                    $existingSale->price = $request->price;
                    $existingSale->vat = $request->vat;
                    $existingSale->discount = $request->discount;
                    $existingSale->point = $request->point;
                    $existingSale->attributes = $temp->attributes;
                    $existingSale->remarks = $request->remarks;
                    $existingSale->update();

                    $sale = $existingSale;

                    $existingStock = Stock::where('level_specific_id', $sale->id)
                        ->where('level_specific_type', Sale::class)
                        ->latest('id')
                        ->first();

                    if ($existingStock) {
                        $isLatest = $latestStockOverall && $existingStock->id === $latestStockOverall->id;

                        if ($isLatest) {
                            $existingStock->current_qty = -$existingSale->qty;
                            $existingStock->stock_qty = $previousQty - $existingSale->qty;
                            $existingStock->attributes = $requestAttrs;
                            $existingStock->remarks = $request->remarks;
                            $existingStock->save();
                    } else {
                        $updateSaleBaseQty = (float)$existingSale->qty;
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
                        $reversal->remarks = 'Reversal of sale #'.$sale->id;
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
                } else {
                    $updateTempBaseQty = (float)$temp->qty;

                    $stock = new Stock();
                    $stock->branch_id = $request->branch;
                    $stock->product_id = $request->product;
                    $stock->unit_id = (int)$request->unit;
                    $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                    $stock->level_specific_id = $sale->id;
                    $stock->level_specific_type = Sale::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = -$updateTempBaseQty;
                    $stock->stock_qty = $previousQty - $updateTempBaseQty;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $request->remarks;
                    $stock->save();
                    }
                } else {
                    $sale = new Sale();
                    $sale->invoice_no = $request->invoice_no;
                    $sale->status_id = $request->status;
                    $sale->client_id = $clientId;
                    $sale->branch_id = $request->branch;
                    $sale->product_id = $request->product;
                    $sale->product_price_id = $request->product_price;
                    $sale->unit_id = $request->unit;
                    $sale->user_id = $temp->user_id;
                    $sale->qty = $temp->qty;
                    $sale->price = $request->price;
                    $sale->vat = $request->vat;
                    $sale->discount = $request->discount;
                    $sale->point = $request->point;
                    $sale->attributes = $temp->attributes;
                    $sale->remarks = $request->remarks;
                    $sale->save();

                    $this->addPointToUser($sale->client_id, (float)$sale->point);

                    $updateNewSaleBaseQty = (float)$temp->qty;

                    $stock = new Stock();
                    $stock->branch_id = $request->branch;
                    $stock->product_id = $request->product;
                    $stock->unit_id = (int)$request->unit;
                    $stock->level_id = $saleLevel ? $saleLevel->id : 1;
                    $stock->level_specific_id = $sale->id;
                    $stock->level_specific_type = Sale::class;
                    $stock->previous_qty = $previousQty;
                    $stock->current_qty = -$updateNewSaleBaseQty;
                    $stock->stock_qty = $previousQty - $updateNewSaleBaseQty;
                    $stock->attributes = $requestAttrs;
                    $stock->remarks = $request->remarks;
                    $stock->save();
                }

                $this->updateProductStockStatus($request->product);

                if (isset($sale) && $isPartialCompleted) {
                    $invoiceTotal = Sale::where('invoice_no', $sale->invoice_no)
                        ->get()
                        ->sum(fn($s) => ($s->qty * $s->price) + ($s->qty * ($s->vat ?? 0)) - ($s->qty * ($s->discount ?? 0)));

                    $existingCredit = Credit::where('invoice_no', $sale->invoice_no)->where('credit_type', 'sale')->first();
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
                            $totalPaidForInvoice = Payment::where('payment_invoice_no', $credit->invoice_no)->sum('amount');
                            $credit->paid_amount = $totalPaidForInvoice;
                            $credit->due_amount = $invoiceTotal - $totalPaidForInvoice;
                            $credit->save();

                            event(new CreditUpdate($credit));
                        }
                    } elseif ($existingCredit) {
                        $existingCredit->delete();
                    }
                }

                $temp->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Temp converted to sale successfully',
                    'sale' => $sale
                ]);
            }
        }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Temp updated successfully',
            'temp' => $temp
        ]);
    }

    public function grouped(Request $request)
    {
        $perPage = $request->perPage ?? 15;
        $page = $request->page ?? 1;

        $invoiceQuery = Temp::select('invoice_no')
            ->selectRaw('MIN(id) as min_id')
            ->selectRaw('MAX(created_at) as last_created')
            ->groupBy('invoice_no')
            ->orderByRaw('MAX(id) desc');

        if ($request->search) {
            $search = $request->search;
            $invoiceQuery->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('branch', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->status) {
            $invoiceQuery->where('status_id', $request->status);
        }

        if ($request->branch) {
            $invoiceQuery->where('branch_id', $request->branch);
        }

        if ($request->products) {
            $productIds = is_array($request->products) ? $request->products : explode(',', $request->products);
            $invoiceQuery->whereIn('product_id', $productIds);
        }

        $invoices = $invoiceQuery->paginate($perPage, ['*'], 'page', $page);

        $invoiceNos = $invoices->pluck('invoice_no');
        $allTemps = Temp::with(['branch', 'status', 'product', 'client', 'user', 'updatedBy', 'unit'])
            ->whereIn('invoice_no', $invoiceNos)
            ->orderBy('id')
            ->get()
            ->groupBy('invoice_no');

        $grouped = [];
        foreach ($invoices as $inv) {
            $invNo = $inv->invoice_no;
            $items = $allTemps->get($invNo, collect());
            $first = $items->first();
            $grouped[] = [
                'invoice_no' => $invNo,
                'items' => $items,
                'branch' => $first?->branch,
                'client' => $first?->client,
                'status' => $first?->status,
                'user' => $first?->user,
                'created_at' => $first?->created_at,
            ];
        }

        $tempParent = Status::where('name', 'temp')->first();
        $statuses = $tempParent ? Status::where('parent_id', $tempParent->id)->get() : collect();
        $branches = Branch::all();
        $products = Product::all();

        return response()->json([
            'status' => 'success',
            'temps' => $grouped,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ],
            'statuses' => $statuses,
            'branches' => $branches,
            'products' => $products,
        ]);
    }

    public function byInvoice(string $invoiceNo)
    {
        $temps = Temp::with(['branch', 'status', 'product', 'productPrice', 'client', 'user', 'unit'])
            ->where('invoice_no', $invoiceNo)
            ->get();

        return response()->json([
            'status' => 'success',
            'temps' => $temps
        ]);
    }

    public function destroyByInvoice(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string',
        ]);

        $temps = Temp::where('invoice_no', $request->invoice_no)->get();

        if ($temps->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No temps found for this invoice'
            ], 404);
        }

        foreach ($temps as $temp) {
            $tempData = $temp->toArray();
            $temp->delete();
            event(new TempUpdate($tempData));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($temps) . ' temps deleted successfully'
        ]);
    }

    #[OA\Post(
        path: "/temps/delete/{id}",
        tags: ["Temps"],
        summary: "Delete temp",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the temp",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Temp deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $temp = Temp::findOrFail($id);

        $tempData = $temp->toArray($temp);

        $temp->delete();
        
        event(new TempUpdate($tempData));

        return response()->json([
            'status' => 'success',
            'message' => 'Temp deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['invoice_nos' => 'required|array', 'invoice_nos.*' => 'string']);

        Temp::whereIn('invoice_no', $request->invoice_nos)->delete();

        foreach ($request->invoice_nos as $inv) {
            event(new \Modules\Temp\Events\TempUpdate(['invoice_no' => $inv]));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($request->invoice_nos) . ' invoices deleted successfully'
        ]);
    }

    public function batchStatus(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string',
            'status_id' => 'required|exists:statuses,id',
        ]);

        $temps = Temp::where('invoice_no', $request->invoice_no)->get();

        if ($temps->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No temps found for this invoice'
            ], 404);
        }

        foreach ($temps as $temp) {
            $temp->status_id = $request->status_id;
            $temp->updated_by = Auth::id();
            $temp->update();
            event(new TempUpdate($temp->toArray()));
        }

        return response()->json([
            'status' => 'success',
            'message' => count($temps) . ' temps updated successfully'
        ]);
    }

    private function addPointToUser(?int $clientId, float $point): void
    {
        if ($clientId && $point > 0) {
            $user = User::find($clientId);
            if ($user) {
                $user->increment('point', $point);
            }
        }
    }
}
