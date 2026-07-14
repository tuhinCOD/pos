<?php

namespace App\Http\Controllers;

use App\Models\EmailOtp;
use Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\User\Models\User;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Authentication")]
class AuthController extends Controller
{
    #[OA\Post(
        path: "/login",
        tags: ["Authentication"],
        summary: "User login",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", format: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Login success"),
            new OA\Response(response: 401, description: "Invalid credentials"),
            new OA\Response(response: 403, description: "Account inactive"),
        ]
    )]
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
  
        if (! $token = Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        // if ($user->status != 1) {
        //     Auth::logout();
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Account inactive'
        //     ], 403);
        // }

        $this->respondWithToken($token);

        $cookieName = config('app.cookie_name');

        $cookie = cookie(
            $cookieName,       // cookie name
            $token,      // cookie value (JWT token)
            10080,          // lifetime in minutes
            '/',         // path
            null,        // domain, null means current domain
            false,        // secure (HTTPS only)
            true,        // HttpOnly (JS থেকে access forbidden)
            false,       // raw
            "Strict"
        );

        return response()->json([
            'status' => 'success',
            'user' => $user,
        ])->withCookie($cookie);
    }

    public function me()
    {
        return response()->json(Auth::user());
    }

    #[OA\Post(
        path: "/signup",
        tags: ["Authentication"],
        summary: "Send OTP for signup",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", format: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "OTP sent"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function sendOtp(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $otp = rand(100000, 999999);

        EmailOtp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp'        => $otp,
                'name'       => $request->name,
                'password'   => Hash::make($request->password),
                'expires_at' => now()->addMinutes(5),
            ]
        );

        Mail::raw("Your OTP code is: $otp", function ($message) use ($request) {
            $message->to($request->email)->subject('Email Verification OTP');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to email',
            'email'   => $request->email
        ]);
    }

    #[OA\Post(
        path: "/verification",
        tags: ["Authentication"],
        summary: "Verify OTP and create account",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "otp"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "otp", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Account created"),
            new OA\Response(response: 422, description: "Invalid or expired OTP"),
        ]
    )]
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required',
        ]);

        $record = EmailOtp::query()
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired OTP'
            ], 422);
        }

        $user = User::create([
            'name'     => $record->name,
            'email'    => $record->email,
            'password' => $record->password,
        ]);

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Account created',
            'user' => $user
        ]);
    }

    // resetpass part

    #[OA\Post(
        path: "/forget-pass",
        tags: ["Authentication"],
        summary: "Send password reset link",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Reset link sent"),
        ]
    )]
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );

        $resetLink = url('/reset-pass/' . $token);

        Mail::raw("Reset link: $resetLink", function ($message) use ($request) {
            $message->to($request->email)->subject('Reset Password');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Reset link sent',
            'email'   => $request->email
        ]);
    }

    #[OA\Post(
        path: "/reset-pass",
        tags: ["Authentication"],
        summary: "Reset password using token",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "password", type: "string", format: "password", minLength: 6),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password reset successful"),
            new OA\Response(response: 400, description: "Invalid reset request"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid reset request'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset successful'
        ]);
    }

    // password change

    #[OA\Post(
        path: "/password-change",
        tags: ["Authentication"],
        summary: "Change password",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "new_password", "new_password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string"),
                    new OA\Property(property: "new_password", type: "string"),
                    new OA\Property(property: "new_password_confirmation", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password changed"),
            new OA\Response(response: 422, description: "Invalid password"),
        ]
    )]
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully'
        ]);
    }

    // logout

    #[OA\Post(
        path: "/logout",
        tags: ["Authentication"],
        summary: "Logout user",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Logged out"),
        ]
    )]
    public function logout()
    {
        $cookieName = config('app.cookie_name');
        $cookie = Cookie::forget($cookieName);

        Auth::logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out'
        ])->withCookie($cookie);
    }

    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60
        ]);
    }
}