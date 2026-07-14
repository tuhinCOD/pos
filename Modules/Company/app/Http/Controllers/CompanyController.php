<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Company\Jobs\UploadCompanyLogo;
use Modules\Company\Models\Company;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Companies")]
class CompanyController extends Controller
{
    #[OA\Get(
        path: "/companies",
        tags: ["Companies"],
        summary: "List companies",
        description: "Get paginated companies, with optional search by name, email, contact, website",
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
                description: "Companies fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "companies", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $companies = Company::when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'name',
                'contact',
                'email',
                'website',
            ], 'like', '%' . $request->search . '%');
        })
        ->paginate(15)->onEachSide(0);

        return response()->json([
            'status' => 'success',
            'companies' => $companies,
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
        path: "/companies",
        summary: "Create new company",
        tags: ["Companies"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["name", "contact", "email", "address"],
                    properties: [
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "contact", type: "string"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "address", type: "string"),
                        new OA\Property(property: "website", type: "string"),
                        new OA\Property(property: "logo", type: "string", format: "binary"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Company created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'contact' => [
                'max:14',
                Rule::unique('companies'), 
            ],
            'email' => [
                'max:100',
                Rule::unique('companies'), 
            ],
            'address' => 'max:500',
            'website' => 'max:100',
            'logo' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $company = new Company();
        $company->name = $request->name;
        $company->contact = $request->contact;
        $company->email = $request->email;
        $company->address = $request->address;
        $company->website = $request->website;
        $company->save();

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('Company_logo', 'public');

            UploadCompanyLogo::dispatch($logoPath, $company->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Company created successfully',
            'company' => $company
        ]);
    }

    #[OA\Post(
        path: "/companies/update/{id}",
        summary: "Update company",
        tags: ["Companies"],
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
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["name", "contact", "email", "address"],
                    properties: [
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "contact", type: "string"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "address", type: "string"),
                        new OA\Property(property: "website", type: "string"),
                        new OA\Property(property: "logo", type: "string", format: "binary"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Company updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $request->validate([
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('companies')->ignore($id), 
            ],
            'contact' => [
                'required',
                'string',
                'max:14',
                Rule::unique('companies')->ignore($id), 
            ],
            'address' => 'required|string|max:500',
            'website' => 'string|max:100',
            'logo' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $company = Company::findOrFail($id);
        $company->name = $request->name;
        $company->contact = $request->contact;
        $company->email = $request->email;
        $company->address = $request->address;
        $company->website = $request->website;
        $company->update();

        if($request->hasFile('logo')) {
            if($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $path = $request->file('logo')->store('Company_logo', 'public');
            UploadCompanyLogo::dispatch($path, $company->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Company updated successfully',
            'company' => $company
        ]);
    }

    #[OA\Post(
        path: "/companies/delete/{id}",
        tags: ["Companies"],
        summary: "Delete company",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the company",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Company deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $company = Company::findOrFail($id);

        $company->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Company deleted successfully'
        ]);
    }
}
