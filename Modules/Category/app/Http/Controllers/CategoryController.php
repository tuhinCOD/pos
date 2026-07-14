<?php

namespace Modules\Category\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ExportData;
use Illuminate\Support\Facades\Storage;
use Modules\Category\Events\CategoryUpdate;
use Modules\Category\Models\Category;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Categories")]
class CategoryController extends Controller
{
    #[OA\Get(
        path: "/categories",
        tags: ["Categories"],
        summary: "List categories",
        description: "Get paginated categories, with optional search by category name",
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
                description: "Categories fetched successfully",
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
        $categories = Category::with(['parent', 'user', 'updatedBy'])
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'name',
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%$request->search%")
                    ->orWhere('contact', 'like', "%$request->search%")
                    ->orWhere('email', 'like', "%$request->search%");
            });
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $allCategories = Category::all();

        return response()->json([
            'status' => 'success',
            'categories' => $categories,
            'allCategories' => $allCategories
        ]);
    }

    public function export(Request $request)
    {
        $categories = Category::with(['parent', 'user', 'updatedBy'])
            ->orderBy('id', 'desc')
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['name'], 'like', '%' . $request->search . '%')
                ->orWhereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', "%$request->search%")
                        ->orWhere('contact', 'like', "%$request->search%")
                        ->orWhere('email', 'like', "%$request->search%");
                });
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->get();

        $data = $categories->map(fn($c) => [
            'ID' => $c->id,
            'Name' => $c->name ?? '-',
            'Parent' => $c->parent?->name ?? '-',
            'Created By' => $c->user?->name ?? '-',
            'Updated By' => $c->updatedBy?->name ?? '-',
        ])->toArray();

        $headings = ['ID', 'Name', 'Parent', 'Created By', 'Updated By'];

        $filename = 'categories_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Categories', 'categories');
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
        path: "/categories",
        summary: "Create new category",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "parent", type: "integer"),
                ])    
            ),
        responses: [
            new OA\Response(response: 200, description: "Category created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $category = new Category();
        $category->name = $request->name;
        $category->parent_id = $request->parent;
        $category->user_id = Auth::id();
        $category->save();

        event(new CategoryUpdate($category));

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'category' => $category
        ]);
    }

    #[OA\Post(
        path: "/categories/update/{id}",
        summary: "Update category",
        tags: ["Categories"],
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
                    new OA\Property(property: "parent", type: "integer"),
                ])    
            ),
        responses: [
            new OA\Response(response: 200, description: "Category updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $category = Category::findOrFail($id);
        $category->name = $request->name;
        $category->parent_id = $request->parent;
        $category->updated_by = Auth::id();
        $category->update();

        event(new CategoryUpdate($category));

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    #[OA\Post(
        path: "/categories/delete/{id}",
        tags: ["Categories"],
        summary: "Delete Category",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the Category",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Category deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $category = Category::findOrFail($id);

        $category->delete();

        $categoryData = $category->toArray($category);

        event(new CategoryUpdate($categoryData));

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:categories,id']);
        Category::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new CategoryUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
