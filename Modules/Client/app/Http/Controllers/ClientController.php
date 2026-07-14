<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\City\Models\City;
use Modules\Client\Events\ClientUpdate;
use Modules\Client\Models\Client;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Clients")]
class ClientController extends Controller
{
    // #[OA\Get(
    //     path: "/clients",
    //     tags: ["Clients"],
    //     summary: "List clients",
    //     description: "Get paginated clients, with optional search by name, email, contact",
    //     parameters: [
    //         new OA\Parameter(
    //             name: "search",
    //             in: "query",
    //             description: "Search term",
    //             required: false,
    //             schema: new OA\Schema(type: "string")
    //         ),
    //         new OA\Parameter(
    //             name: "page",
    //             in: "query",
    //             description: "Pagination",
    //             required: false,
    //             schema: new OA\Schema(type: "string")
    //         )
    //     ],
    //     responses: [
    //         new OA\Response(
    //             response: 200,
    //             description: "Clients fetched successfully",
    //             content: new OA\JsonContent(
    //                 type: "object",
    //                 properties: [
    //                     new OA\Property(property: "status", type: "string", example: "success"),
    //                     new OA\Property(property: "clients", type: "array", items: new OA\Items(type: "object")),
    //                     new OA\Property(property: "client", type: "object", nullable: true),
    //                     new OA\Property(property: "cities", type: "array", items: new OA\Items(type: "object")),
    //                 ]
    //             )
    //         )
    //     ],
    //     security: [["bearerAuth" => []]]
    // )]
    // public function index(Request $request)
    // {
    //     $clients = Client::with('status', 'city', 'user')
    //     ->when($request->search, function ($query) use ($request) {
    //         return $query->whereAny([
    //             'name',
    //             'contact',
    //             'email',
    //         ], 'like', '%' . $request->search . '%')
    //         ->orWhereHas('city', function ($cityQuery) use ($request) {
    //             $cityQuery->where('name', 'like', "%{$request->search}%");
    //         })
    //         ->orWhereHas('status', function ($statusQuery) use ($request) {
    //             $statusQuery->where('name', 'like', "%{$request->search}%");
    //         })
    //         ->orWhereHas('user', function ($userQuery) use ($request) {
    //             $userQuery->where(['name', 'contact', 'email'], 'like', "%{$request->search}%");
    //         });
    //     })
    //     ->paginate(15)->onEachSide(0);

    //     $cities = City::all();
    //     $client = null;

    //     return response()->json([
    //         'status' => 'success',
    //         'clients' => $clients,
    //         'client' => $client,
    //         'cities' => $cities,
    //     ]);
    // }

    // // #[OA\Get(
    // //     path: "/clients/edit/{id}",
    // //     tags: ["Clients"],
    // //     summary: "Show specific client",
    // //     parameters: [
    // //         new OA\Parameter(
    // //             name: "id",
    // //             in: "path",
    // //             required: true,
    // //             description: "ID of the client",
    // //             schema: new OA\Schema(type: "integer")
    // //         )
    // //     ],
    // //     responses: [
    // //         new OA\Response(response: 200, description: "Client fetched successfully")
    // //     ],
    // //     security: [["bearerAuth" => []]]
    // // )]
    // // public function show(Request $request, $id)
    // // {
    // //     $clients = Client::with('role', 'area')
    // //     ->when($request->search, function ($query) use ($request) {
    // //         return $query->whereAny([
    // //             'name',
    // //             'contact',
    // //             'email',
    // //         ], 'like', '%' . $request->search . '%')
    // //         ->orWhereHas('role', function ($areaQuery) use ($request) {
    // //             $areaQuery->where('name', 'like', "%{$request->search}%");
    // //         });
    // //     })
    // //     ->paginate(15)->onEachSide(0);

    // //     $user = Client::with('area')->findOrFail($id);

    // //     Gate::authorize('edit-user', $user->id);

    // //     return response()->json([
    // //         'status' => 'success',
    // //         'users' => $users,
    // //         'user' => $user,
    // //     ]);
    // // }

    // #[OA\Post(
    //     path: "/clients",
    //     summary: "Create new client",
    //     tags: ["Clients"],
    //     security: [["bearerAuth" => []]],
    //     requestBody: new OA\RequestBody(
    //         required: true,
    //         content: new OA\JsonContent(
    //             required: ["contact", "password"],
    //             properties: [
    //                 new OA\Property(property: "name", type: "string"),
    //                 new OA\Property(property: "contact", type: "string"),
    //                 new OA\Property(property: "email", type: "string", format: "email"),
    //                 new OA\Property(property: "password", type: "password"),
    //                 new OA\Property(property: "address", type: "string"),
    //                 new OA\Property(property: "city", type: "integer"),
    //                 new OA\Property(property: "status", type: "integer"),
    //             ]
    //         )
    //     ),
    //     responses: [
    //         new OA\Response(response: 200, description: "Client created"),
    //         new OA\Response(response: 422, description: "Validation error"),
    //     ]
    // )]
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'contact' => 'required|string|max:14',
    //         'password' => 'required|string|min:6',
    //     ]);

    //     $client = new Client();
    //     $client->name = $request->name;
    //     $client->contact = $request->contact;
    //     $client->email = $request->email;
    //     $client->password = Hash::make($request->password);
    //     $client->address = $request->address;
    //     $client->city_id = $request->city;
    //     $client->status_id = $request->status;
    //     $client->user_id = Auth::id();
    //     $client->point = 0;
    //     $client->save();

    //     event(new ClientUpdate($client));

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Client created successfully',
    //         'client' => $client
    //     ]);
    // }

    // #[OA\Post(
    //     path: "/clients/update/{id}",
    //     summary: "Client user",
    //     tags: ["Clients"],
    //     security: [["bearerAuth" => []]],
    //     parameters: [
    //         new OA\Parameter(
    //             name: "id",
    //             in: "path",
    //             required: true,
    //             schema: new OA\Schema(type: "integer")
    //         )
    //     ],
    //     requestBody: new OA\RequestBody(
    //         required: true,
    //         content: new OA\JsonContent(
    //             required: ["contact", "password"],
    //             properties: [
    //                 new OA\Property(property: "name", type: "string"),
    //                 new OA\Property(property: "contact", type: "string"),
    //                 new OA\Property(property: "email", type: "string", format: "email"),
    //                 new OA\Property(property: "password", type: "password"),
    //                 new OA\Property(property: "address", type: "string"),
    //                 new OA\Property(property: "city", type: "integer"),
    //                 new OA\Property(property: "status", type: "integer"),
    //             ]
    //         )
    //     ),
    //     responses: [
    //         new OA\Response(response: 200, description: "Client updated"),
    //         new OA\Response(response: 422, description: "Validation error"),
    //     ]
    // )]

    // public function update (Request $request, $id) {
    //     $request->validate([
    //         'contact' => 'required|string|max:14',
    //         'password' => 'required|string|min:6',
    //     ]);

    //     $client = Client::findOrFail($id);
    //     $client->name = $request->name;
    //     $client->contact = $request->contact;
    //     $client->email = $request->email;
    //     $client->password = Hash::make($request->password);
    //     $client->address = $request->address;
    //     $client->city_id = $request->city;
    //     $client->status_id = $request->status;
    //     $client->user_id = Auth::id();
    //     $client->update();

    //     event(new ClientUpdate($client));

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Client updated successfully',
    //         'client' => $client
    //     ]);
    // }

    // #[OA\Post(
    //     path: "/clients/delete/{id}",
    //     tags: ["Clients"],
    //     summary: "Delete client",
    //     parameters: [
    //         new OA\Parameter(
    //             name: "id",
    //             in: "path",
    //             required: true,
    //             description: "ID of the client",
    //             schema: new OA\Schema(type: "integer")
    //         )
    //     ],
    //     responses: [
    //         new OA\Response(response: 200, description: "Client deleted successfully")
    //     ],
    //     security: [["bearerAuth" => []]]
    // )]
    // public function destroy($id)
    // {  
    //     $client = Client::findOrFail($id);

    //     $clientData = $client->toArray($client);

    //     $client->delete();

    //     event(new ClientUpdate($clientData));

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Client deleted successfully'
    //     ]);
    // }
}
