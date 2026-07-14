<?php

namespace Modules\StockTransfer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Branch\Models\Branch;
use Modules\Product\Models\Product;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\StockTransfer\Events\StockTransferUpdate;
use Modules\StockTransfer\Models\StockTransfer;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "StockTransfers")]
class StockTransferController extends Controller
{
    #[OA\Get(
        path: "/stock_transfers",
        tags: ["StockTransfers"],
        summary: "List stock transfers",
        description: "Get paginated stock transfers, with optional search by product stock transfers",
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
                description: "Stock transfers fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "product", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "branch", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "sale", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $stockTransfer = StockTransfer::with(['status', 'branch', 'sale', 'product', 'user'])
        ->when($request->search, function ($query) use ($request) {
            $query->orWhereHas('status', function ($statusQuery) use ($request) {
                $statusQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('branch', function ($branchQuery) use ($request) {
                $branchQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('sale', function ($saleQuery) use ($request) {
                $saleQuery->where('invoice_no', 'like', "%{$request->search}%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('contact', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
            })
            ->orWhereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', "%{$request->search}%")
                ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        })
        ->paginate(10)->onEachSide(0);

        $products = Product::all();
        $stockTransferStatus = Status::where('name', 'stock transfer')->first();
        $statuses = Status::where('parent_id', $stockTransferStatus->id)->get();
        $branch = Branch::all();
        $sales = Sale::all();

        return response()->json([
            'status' => 'success',
            'stockTransfer' => $stockTransfer,
            'statuses' => $statuses,
            'products' => $products,
            'branch' => $branch,
            'sales' => $sales
        ]);
    }

    // #[OA\Get(
    //     path: "/users/edit/{id}",
    //     tags: ["Users"],
    //     summary: "Show specific user",
    //     parameters: [
    //         new OA\Parameter(
    //             name: "id",
    //             in: "path",
    //             required: true,
    //             description: "ID of the user",
    //             schema: new OA\Schema(type: "integer")
    //         )
    //     ],
    //     responses: [
    //         new OA\Response(response: 200, description: "User fetched successfully")
    //     ],
    //     security: [["bearerAuth" => []]]
    // )]
    // public function show(Request $request, $id)
    // {
    //     $users = User::with('role', 'area')
    //     ->when($request->search, function ($query) use ($request) {
    //         return $query->whereAny([
    //             'name',
    //             'contact',
    //             'email',
    //         ], 'like', '%' . $request->search . '%')
    //         ->orWhereHas('role', function ($areaQuery) use ($request) {
    //             $areaQuery->where('name', 'like', "%{$request->search}%");
    //         });
    //     })
    //     ->paginate(15)->onEachSide(0);

    //     $releaseUsers = User::with('role', 'area')
    //     ->where('status', 0)
    //     ->whereHas('area')
    //     ->get();

    //     $areaLessUsers = User::with('role', 'area')
    //     ->where('status', 0)
    //     ->whereDoesntHave('area')->get();

    //     $roles = Role::all();
    //     $areas = Area::all();

    //     $user = User::with('area')->findOrFail($id);

    //     Gate::authorize('edit-user', $user->id);

    //     return response()->json([
    //         'status' => 'success',
    //         'users' => $users,
    //         'user' => $user,
    //         'areas' => $areas,
    //         'roles' => $roles,
    //         'areaLessUsers' => $areaLessUsers,
    //         'releaseUsers' => $releaseUsers
    //     ]);
    // }

    #[OA\Post(
        path: "/stock_transfers",
        summary: "Create new stock transfer",
        tags: ["StockTransfers"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status", "branch_from", "product", "sale", "branch_to", "qty", "shipping_cost"],
                properties: [
                    new OA\Property(property: "branch_from", type: "integer"),
                    new OA\Property(property: "branch_to", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "sale", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "shipping_cost", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Stock transfer created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'branch_from' => 'required',
            'branch_to' => 'required',
            'status' => 'required',
            'product' => 'required',
            'sale' => 'required',
            'qty' => 'required|numeric|max:999999999.999',
            'shipping_cost' => 'required|numeric|max:999999999.999',
            'remarks' => 'string|max:500'
        ]);

        $stockTransfer = new StockTransfer();
        $stockTransfer->branch_from = $request->branch_from;
        $stockTransfer->branch_to = $request->branch_to;
        $stockTransfer->status_id = $request->status;
        $stockTransfer->product_id = $request->product;
        $stockTransfer->sale_id = $request->sale;
        $stockTransfer->user_id = Auth::id();
        $stockTransfer->qty = $request->qty;
        $stockTransfer->shipping_cost = $request->shipping_cost;
        $stockTransfer->remarks = $request->remarks;
        $stockTransfer->save();

        event(new StockTransferUpdate($stockTransfer));

        return response()->json([
            'status' => 'success',
            'message' => 'Stock transfer created successfully',
            'stockTransfer' => $stockTransfer
        ]);
    }

    #[OA\Post(
        path: "/stock_transfers/update/{id}",
        summary: "Update stock transfer",
        tags: ["StockTransfers"],
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
                required: ["status", "branch_from", "product", "sale", "branch_to", "qty", "shipping_cost"],
                properties: [
                    new OA\Property(property: "branch_from", type: "integer"),
                    new OA\Property(property: "branch_to", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "product", type: "integer"),
                    new OA\Property(property: "sale", type: "integer"),
                    new OA\Property(property: "qty", type: "number", format: "float"),
                    new OA\Property(property: "shipping_cost", type: "number", format: "float"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Stock transfer updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'branch_from' => 'required',
            'branch_to' => 'required',
            'status' => 'required',
            'product' => 'required',
            'sale' => 'required',
            'qty' => 'required|numeric|max:999999999.999',
            'shipping_cost' => 'required|numeric|max:999999999.999',
            'remarks' => 'string|max:500'
        ]);

        $stockTransfer = StockTransfer::findOrFail($id);
        $stockTransfer->branch_from = $request->branch_from;
        $stockTransfer->branch_to = $request->branch_to;
        $stockTransfer->status_id = $request->status;
        $stockTransfer->product_id = $request->product;
        $stockTransfer->sale_id = $request->sale;
        $stockTransfer->user_id = Auth::id();
        $stockTransfer->qty = $request->qty;
        $stockTransfer->shipping_cost = $request->shipping_cost;
        $stockTransfer->remarks = $request->remarks;
        $stockTransfer->save();
        $stockTransfer->update();

        event(new StockTransferUpdate($stockTransfer));

        return response()->json([
            'status' => 'success',
            'message' => 'Stock Transfer updated successfully',
            'stockTransfer' => $stockTransfer
        ]);
    }

    #[OA\Post(
        path: "/stock_transfers/delete/{id}",
        tags: ["StockTransfers"],
        summary: "Delete stock transfer",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the stock transfer",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Stock transfer deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $stockTransfer = StockTransfer::findOrFail($id);

        $stockTransferData = $stockTransfer->toArray($stockTransfer);

        $stockTransfer->delete();

        event(new StockTransferUpdate($stockTransferData));

        return response()->json([
            'status' => 'success',
            'message' => 'Stock transfer deleted successfully'
        ]);
    }
}
