<?php

namespace Modules\Supplier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\City\Models\City;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Supplier\Events\SupplierUpdate;
use Modules\Supplier\Models\Supplier;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Suppliers")]
class SupplierController extends Controller
{
    #[OA\Get(
        path: "/suppliers",
        tags: ["Suppliers"],
        summary: "List suppliers",
        description: "Get paginated suppliers, with optional search by name, email, contact",
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
                description: "Suppliers fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "suppliers", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "supplier", type: "object", nullable: true),
                        new OA\Property(property: "cities", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $suppliers = Supplier::with('city', 'user', 'updatedBy')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'name',
                'contact',
                'email',
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('city', function ($cityQuery) use ($request) {
                $cityQuery->where('name', 'like', "%{$request->search}%");
            });
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $cities = City::all();
        $supplier = null;

        return response()->json([
            'status' => 'success',
            'suppliers' => $suppliers,
            'supplier' => $supplier,
            'cities' => $cities,
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Supplier::min('created_at');

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

        $suppliers = Supplier::with('city', 'user', 'updatedBy')
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['name', 'contact', 'email'], 'like', '%' . $request->search . '%')
                ->orWhereHas('city', function ($cityQuery) use ($request) {
                    $cityQuery->where('name', 'like', "%{$request->search}%");
                });
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->get();

        $data = $suppliers->map(fn($s) => [
            'ID' => $s->id,
            'Name' => $s->name ?? '-',
            'Email' => $s->email ?? '-',
            'Contact' => $s->contact ?? '-',
            'City' => $s->city?->name ?? '-',
            'Address' => $s->address ?? '-',
            'Remarks' => $s->remarks ?? '-',
            'Created By' => $s->user?->name ?? '-',
            'Updated By' => $s->updatedBy?->name ?? '-',
            'Created At' => $s->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $s->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Name', 'Email', 'Contact', 'City', 'Address', 'Remarks', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'suppliers_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Suppliers', 'suppliers');

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
        path: "/suppliers",
        summary: "Create new supplier",
        tags: ["Suppliers"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "contact"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "contact", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "city", type: "integer"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Supplier created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'contact' => 'required|string|max:14',
        ]);

        $supplier = new Supplier();
        $supplier->name = $request->name;
        $supplier->contact = $request->contact;
        $supplier->email = $request->email;
        $supplier->address = $request->address;
        $supplier->city_id = $request->city;
        $supplier->user_id = Auth::id();
        $supplier->remarks = $request->remarks;
        $supplier->save();

        event(new SupplierUpdate($supplier));

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier created successfully',
            'supplier' => $supplier
        ]);
    }

    #[OA\Post(
        path: "/suppliers/update/{id}",
        summary: "Supplier user",
        tags: ["Suppliers"],
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
                required: ["name", "contact"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "contact", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "city", type: "integer"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Supplier updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'name' => 'required|string|max:100',
            'contact' => 'required|string|max:14',
        ]);

        $supplier = Supplier::findOrFail($id);
        $supplier->name = $request->name;
        $supplier->contact = $request->contact;
        $supplier->email = $request->email;
        $supplier->address = $request->address;
        $supplier->city_id = $request->city;
        $supplier->updated_by = Auth::id();
        $supplier->remarks = $request->remarks;
        $supplier->update();

        event(new SupplierUpdate($supplier));

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier updated successfully',
            'supplier' => $supplier
        ]);
    }

    #[OA\Post(
        path: "/suppliers/delete/{id}",
        tags: ["Suppliers"],
        summary: "Delete supplier",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the supplier",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Supplier deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $supplier = Supplier::findOrFail($id);

        $supplierData = $supplier->toArray($supplier);

        $supplier->delete();

        event(new SupplierUpdate($supplierData));

        return response()->json([
            'status' => 'success',
            'message' => 'supplier deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:suppliers,id']);
        Supplier::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new SupplierUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
