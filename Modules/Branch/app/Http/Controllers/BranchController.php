<?php

namespace Modules\Branch\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ExportData;
use Illuminate\Support\Facades\Storage;
use Modules\Branch\Events\BranchUpdate;
use Modules\Branch\Models\Branch;
use Modules\City\Models\City;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Branches")]
class BranchController extends Controller
{
    #[OA\Get(
        path: "/branches",
        tags: ["Branches"],
        summary: "List braches",
        description: "Get paginated branches, with optional search by name, contact, city name",
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
                description: "Branch fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "city", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $branches = Branch::with(['city', 'user', 'updatedBy'])
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'name',
                'contact'
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('city', function ($cityQuery) use ($request) {
                $cityQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
            });
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $cities = City::all();

        return response()->json([
            'status' => 'success',
            'branches' => $branches,
            'cities' => $cities,
        ]);
    }

    public function export(Request $request)
    {
        $branches = Branch::with(['city', 'user', 'updatedBy'])
            ->orderBy('id', 'desc')
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['name', 'contact'], 'like', '%' . $request->search . '%')
                ->orWhereHas('city', function ($cityQuery) use ($request) {
                    $cityQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%$request->search%")
                        ->orWhere('contact', 'like', "%$request->search%")
                        ->orWhere('email', 'like', "%$request->search%");
                });
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->get();

        $data = $branches->map(fn($b) => [
            'ID' => $b->id,
            'Name' => $b->name ?? '-',
            'Contact' => $b->contact ?? '-',
            'City' => $b->city?->name ?? '-',
            'Address' => $b->address ?? '-',
            'Created By' => $b->user?->name ?? '-',
            'Updated By' => $b->updatedBy?->name ?? '-',
        ])->toArray();

        $headings = ['ID', 'Name', 'Contact', 'City', 'Address', 'Created By', 'Updated By'];

        $filename = 'branches_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Branches', 'branches');
        return response()->json(['message' => 'Export started successfully.', 'file' => $filename]);
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
        path: "/branches",
        summary: "Create new branch",
        tags: ["Branches"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "contact", "address", "city"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "contact", type: "string"),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "city", type: "integer"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Branch created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'contact' => 'required|string|max:14',
            'address' => 'required|string|max:500',
            'city' => 'required',
        ]);

        $branch = new Branch();
        $branch->name = $request->name;
        $branch->contact = $request->contact;
        $branch->address = $request->address;
        $branch->city_id = $request->city;
        $branch->user_id = Auth::id();
        $branch->save();

        event(new BranchUpdate($branch));

        return response()->json([
            'status' => 'success',
            'message' => 'Branch created successfully',
            'branch' => $branch
        ]);
    }

    #[OA\Post(
        path: "/branches/update/{id}",
        summary: "Update branch",
        tags: ["Branches"],
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
                required: ["name", "contact", "address"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "contact", type: "string"),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "city", type: "integer"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Branch updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'name' => 'required|string|max:150',
            'contact' => 'required|string|max:150',
            'address' => 'required|string|max:150',
            "city" => 'required'
        ]);

        $branch = Branch::findOrFail($id);
        $branch->name = $request->name;
        $branch->contact = $request->contact;
        $branch->address = $request->address;
        $branch->city_id = $request->city;
        $branch->updated_by = Auth::id();
        $branch->update();

        event(new BranchUpdate($branch));

        return response()->json([
            'status' => 'success',
            'message' => 'Branch updated successfully',
            'branch' => $branch
        ]);
    }

    #[OA\Post(
        path: "/branches/delete/{id}",
        tags: ["Branches"],
        summary: "Delete branch",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the branch",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Branch deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $branch = Branch::findOrFail($id);

        $branch->delete();

        $branchData = $branch->toArray($branch);

        event(new BranchUpdate($branchData));

        return response()->json([
            'status' => 'success',
            'message' => 'Branch deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:branches,id']);
        Branch::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new BranchUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
