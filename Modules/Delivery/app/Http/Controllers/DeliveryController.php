<?php

namespace Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Delivery\Events\DeliveryUpdate;
use Modules\Delivery\Models\Delivery;
use Modules\Order\Models\Order;
use Modules\Status\Models\Status;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Deliveries")]
class DeliveryController extends Controller
{
    #[OA\Get(
        path: "/deliveries",
        tags: ["Deliveries"],
        summary: "List deliveries",
        description: "Get paginated product deliveries, with optional search by product",
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
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "order", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $delivery = Delivery::with(['status', 'order', 'user'])
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'courier_name',
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
            })
            ->orWhereHas('status', function ($statusQuery) use ($request) {
                $statusQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('order', function ($orderQuery) use ($request) {
                $orderQuery->where('invoice_no', 'like', "%{$request->search}%");
            });
        })
        ->paginate(20)->onEachSide(0);

        $deliveryStatus = Status::where('name', 'delivery')->first();
        $statuses = Status::where('parent_id', $deliveryStatus->id)->get();
        $order = Order::all();

        return response()->json([
            'status' => 'success',
            'delivery' => $delivery,
            'statuses' => $statuses,
            'order' => $order
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
        path: "/deliveries",
        summary: "Create new product delivery",
        tags: ["Deliveries"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["order", "status", "courier_name"],
                properties: [
                    new OA\Property(property: "order", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "courier_name", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product delivery created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'order' => 'required',
            'status' => 'required',
            'courier_name' => 'required|string',
        ]);

        $delivery = new Delivery();
        $delivery->status_id = $request->status;
        $delivery->order_id = $request->order;
        $delivery->user_id = Auth::id();
        $delivery->courier_name = $request->courier_name;
        $delivery->save();

        event(new DeliveryUpdate($delivery));

        return response()->json([
            'status' => 'success',
            'message' => 'Product delivery created successfully',
            'delivery' => $delivery
        ]);
    }

    #[OA\Post(
        path: "/deliveries/update/{id}",
        summary: "Update product delivery",
        tags: ["Deliveries"],
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
                required: ["order", "status", "courier_name"],
                properties: [
                    new OA\Property(property: "order", type: "string"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "courier_name", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product delivery updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'order' => 'required',
            'status' => 'required',
            'courier_name' => 'required|string',
        ]);

        $delivery = Delivery::findOrFail($id);
        $delivery->status_id = $request->status;
        $delivery->order_id = $request->order;
        $delivery->user_id = Auth::id();
        $delivery->courier_name = $request->courier_name;
        $delivery->update();

        event(new DeliveryUpdate($delivery));

        return response()->json([
            'status' => 'success',
            'message' => 'Product delivery updated successfully',
            'delivery' => $delivery
        ]);
    }

    #[OA\Post(
        path: "/deliveries/delete/{id}",
        tags: ["Deliveries"],
        summary: "Delete product delivery",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the product delivery",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Product delivery deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $delivery = Delivery::findOrFail($id);

        $deliveryData = $delivery->toArray($delivery);

        $delivery->delete();

        event(new DeliveryUpdate($deliveryData));

        return response()->json([
            'status' => 'success',
            'message' => 'Product delivery deleted successfully'
        ]);
    }
}
