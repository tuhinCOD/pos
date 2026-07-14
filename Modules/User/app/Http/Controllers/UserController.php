<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Branch\Models\Branch;
use Modules\User\Models\User;
use Modules\City\Models\City;
use Modules\Role\Models\Role;
use Modules\Status\Models\Status;
use Modules\User\Events\UserUpdate;
use Modules\User\Jobs\UploadUserImages;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Type\Integer;

#[OA\Tag(name: "Users")]
class UserController extends Controller
{
    #[OA\Get(
        path: "/users",
        tags: ["Users"],
        summary: "List users",
        description: "Get paginated users, with optional search by name, email, contact, role, or branch",
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
                description: "Users fetched successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "users", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "user", type: "object", nullable: true),
                        new OA\Property(property: "branches", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "cities", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "releaseUsers", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            )
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $users = User::with(['role', 'branch', 'status', 'city', 'user', 'updatedBy'])
        ->orderBy('id', 'desc')
        ->when($request->search, function ($query) use ($request) {
            return $query->whereAny([
                'name',
                'contact',
                'email',
                'nid',
            ], 'like', '%' . $request->search . '%')
            ->orWhereHas('role', function ($roleQuery) use ($request) {
                $roleQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('branch', function ($branchQuery) use ($request) {
                $branchQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('status', function ($statusQuery) use ($request) {
                $statusQuery->where('name', 'like', "%{$request->search}%");
            })
            ->orWhereHas('city', function ($cityQuery) use ($request) {
                $cityQuery->where('name', 'like', "%{$request->search}%");
            });
        })
        ->when($request->filled('status'), function ($query) use ($request) {
            $query->where('status_id', $request->status);
        })
        ->when($request->filled('roles'), function ($query) use ($request) {
            $roles = is_array($request->roles) ? $request->roles : explode(',', $request->roles);
            $query->whereIn('role_id', $roles);
        })
        ->paginate($request->perPage ?? 15)->onEachSide(0);

        $userRole = Auth::user()->role->name;
        if ($userRole == 'super admin') {
            $roles = Role::whereIn('name', ['super admin', 'admin'])->get();
        } elseif ($userRole == 'admin') {
            $roles = Role::whereIn('name', ['admin', 'manager','cashier','warehouse staff', 'user'])->get();
        } elseif ($userRole == 'manager') {
            $roles = Role::whereIn('name', ['user'])->get();
        } elseif ($userRole == 'cashier') {
            $roles = Role::whereIn('name', ['user'])->get();
        }

        $branches = Branch::all();
        $cities = City::all();
        $userStatus = Status::where('name', 'user')->first();
        $statuses = Status::where('parent_id', $userStatus->id)->get();

        return response()->json([
            'status' => 'success',
            'users' => $users,
            'branches' => $branches,
            'roles' => $roles,
            'cities' => $cities,
            'statuses' => $statuses,
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = User::min('created_at');

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

        $users = User::with(['role', 'branch', 'status', 'city', 'user', 'updatedBy'])
            ->orderBy('id', 'desc')
            ->when($request->search, function ($query) use ($request) {
                return $query->whereAny(['name', 'contact', 'email', 'nid'], 'like', '%' . $request->search . '%')
                ->orWhereHas('role', function ($roleQuery) use ($request) {
                    $roleQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('branch', function ($branchQuery) use ($request) {
                    $branchQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('status', function ($statusQuery) use ($request) {
                    $statusQuery->where('name', 'like', "%{$request->search}%");
                })
                ->orWhereHas('city', function ($cityQuery) use ($request) {
                    $cityQuery->where('name', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status_id', $request->status);
            })
            ->when($request->filled('roles'), function ($query) use ($request) {
                $roles = is_array($request->roles) ? $request->roles : explode(',', $request->roles);
                $query->whereIn('role_id', $roles);
            })
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->get();

        $data = $users->map(fn($u) => [
            'ID' => $u->id,
            'Name' => $u->name ?? '-',
            'Email' => $u->email ?? '-',
            'Contact' => $u->contact ?? '-',
            'Role' => $u->role?->name ?? '-',
            'Branch' => $u->branch?->name ?? '-',
            'Status' => $u->status?->name ?? '-',
            'City' => $u->city?->name ?? '-',
            'Address' => $u->address ?? '-',
            'NID' => $u->nid ?? '-',
            'Created By' => $u->user?->name ?? '-',
            'Updated By' => $u->updatedBy?->name ?? '-',
            'Created At' => $u->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $u->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Name', 'Email', 'Contact', 'Role', 'Branch', 'Status', 'City', 'Address', 'NID', 'Created By', 'Updated By', 'Created At', 'Updated At'];

        $filename = 'users_' . now()->timestamp . '.xlsx';
        ExportData::dispatch($data, $headings, $filename, 'Users', 'users');

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
        path: "/users",
        summary: "Create new user",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "contact", "email", "password", "address", "city", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "contact", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "password"),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "city", type: "integer"),
                    new OA\Property(property: "nid", type: "string"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "role", type: "integer"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "User created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $role = Role::find($request->role);

        $rules = [
            'name' => 'required|string|max:100',
            'contact' => 'required|string|max:14',
            'role' => 'required|exists:roles,id',
        ];

        if ($role->name !== 'user') {
            $rules['email'] = [
                'required',
                'email',
                'max:100',
                Rule::unique('users', 'email'),
            ];

            $rules['password'] = 'required|string|min:6';
            $rules['address'] = 'required|string';
            $rules['city'] = 'required';
        }

        $request->validate($rules);

        $user = new User();
        $user->name = $request->name;
        $user->contact = $request->contact;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->address = $request->address;
        $user->city_id = $request->city;
        $user->nid = $request->nid;
        $user->branch_id = $request->branch;
        $user->role_id = $request->role;
        $user->user_id = Auth::id();
        $user->remarks = $request->remarks;

        if ($role->name == 'user') {
            if ($request->filled('status')) {
                $user->status_id = $request->status;
            } else {
                $status = Status::where('name', 'regular')->first();
                $user->status_id = $status?->id;
            }
        } else {
            $user->status_id = $request->status;
        }

        $user->save();

        event(new UserUpdate($user));

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => $user
        ]);
    }

    #[OA\Post(
        path: "/users/update/{id}",
        summary: "Update user",
        tags: ["Users"],
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
                required: ["name", "contact", "email", "password", "address", "city", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "contact", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "password"),
                    new OA\Property(property: "address", type: "string"),
                    new OA\Property(property: "city", type: "integer"),
                    new OA\Property(property: "nid", type: "string"),
                    new OA\Property(property: "branch", type: "integer"),
                    new OA\Property(property: "status", type: "integer"),
                    new OA\Property(property: "role", type: "integer"),
                    new OA\Property(property: "remarks", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "User updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]

    public function update (Request $request, int $id) {
        $role = Role::find($request->role);

        $rules = [
            'name' => 'required|string|max:100',
            'contact' => 'required|string|max:14',
            'role' => 'required|exists:roles,id',
        ];

        if ($role && $role->name != 'user') {
            $rules['email'] = [
                'required',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore($id),
            ];

            if ($request->password) {
                $rules['password'] = 'string|min:6';
            }
            $rules['address'] = 'required|string';
            $rules['city'] = 'required';
        }

        $request->validate($rules);

        $user = User::findOrFail($id);
        $user->name = $request->name;
        $user->contact = $request->contact;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->address = $request->address;
        $user->city_id = $request->city;
        $user->nid = $request->nid;
        $user->branch_id = $request->branch;
        $user->status_id = $request->status;
        $user->role_id = $request->role;
        $user->updated_by = Auth::id();
        $user->remarks = $request->remarks;
        $user->update();

        event(new UserUpdate($user));

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    #[OA\Post(
        path: "/users/delete/{id}",
        tags: ["Users"],
        summary: "Delete user",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID of the user",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "User deleted successfully")
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {  
        $user = User::findOrFail($id);

        $userData = $user->toArray($user);
        
        $user->delete();

        event(new UserUpdate($userData));

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:users,id']);
        User::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new UserUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
