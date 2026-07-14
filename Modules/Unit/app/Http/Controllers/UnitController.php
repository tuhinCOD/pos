<?php

namespace Modules\Unit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Illuminate\Support\Facades\Storage;
use Modules\Unit\Events\UnitUpdate;
use Modules\Unit\Models\Unit;
use Modules\User\Models\User;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Units")]
class UnitController extends Controller
{
    #[OA\Get(
        path: "/units",
        tags: ["Units"],
        summary: "List units",
        description: "Get paginated units, with optional search by unit name",
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
                description: "Units fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $units = Unit::with(['user', 'updatedBy'])
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'name',
                'description'
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
            });
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        return response()->json([
            'status' => 'success',
            'units' => $units
        ]);
    }

    public function export(Request $request)
    {
        $units = Unit::with(['user', 'updatedBy'])
            ->orderBy('id', 'desc')
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['name', 'description'], 'like', '%' . $request->search . '%')
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%$request->search%")
                        ->orWhere('contact', 'like', "%$request->search%")
                        ->orWhere('email', 'like', "%$request->search%");
                });
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->get();

        $data = $units->map(fn($u) => [
            'ID' => $u->id,
            'Name' => $u->name ?? '-',
            'Description' => $u->description ?? '-',
            'Created By' => $u->user?->name ?? '-',
            'Updated By' => $u->updatedBy?->name ?? '-',
        ])->toArray();

        $headings = ['ID', 'Name', 'Description', 'Created By', 'Updated By'];

        $filename = 'units_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Units', 'units');

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

    #[OA\Post(
        path: "/units",
        summary: "Create new unit",
        tags: ["Units"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                ])    
            ),
        responses: [
            new OA\Response(response: 200, description: "Unit created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'max:500'
        ]);

        $unit = new Unit();
        $unit->name = $request->name;
        $unit->description = $request->description;
        $unit->user_id = Auth::id();
        $unit->save();

        event(new UnitUpdate($unit));

        return response()->json([
            'status' => 'success',
            'message' => 'Unit created successfully',
            'unit' => $unit
        ]);
    }

    #[OA\Post(
        path: "/units/update/{id}",
        summary: "Update unit",
        tags: ["Units"],
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
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                ])    
            ),
        responses: [
            new OA\Response(response: 200, description: "Unit updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $unit = Unit::findOrFail($id);
        $unit->name = $request->name;
        $unit->description = $request->description;
        $unit->updated_by = Auth::id();
        $unit->update();

        event(new UnitUpdate($unit));

        return response()->json([
            'status' => 'success',
            'message' => 'Unit updated successfully',
            'unit' => $unit
        ]);
    }

    #[OA\Post(
        path: "/units/delete/{id}",
        tags: ["Units"],
        summary: "Delete Unit",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the Unit",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Unit deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {
        $unit = Unit::findOrFail($id);

        $unit->delete();

        $unitData = $unit->toArray($unit);

        event(new UnitUpdate($unitData));

        return response()->json([
            'status' => 'success',
            'message' => 'Unit deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:units,id']);
        Unit::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new UnitUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
