<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class AuthController extends Controller
{
    protected int $accessTtl, $refreshTtl;

    public function __construct()
    {
        $this->accessTtl  = (int) env('JWT_TTL', 60);
        $this->refreshTtl = (int) env('JWT_REFRESH_TTL', 480);
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email'    => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $token = Auth::guard('api')->attempt($credentials);
            if (!$token) {
                return $this->sendErrorOfBadResponse(message: "Invalid Credentials");
            }

            $user = Auth::guard('api')->user();

            JWTAuth::factory()->setTTL($this->accessTtl);
            $accessToken = JWTAuth::claims(['type' => 'ACCESS'])->fromUser($user);

            JWTAuth::factory()->setTTL($this->refreshTtl);
            $refreshToken = JWTAuth::claims(['type' => 'REFRESH'])->fromUser($user);

            $data = [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
            ];

            return $this->sendSuccessResponse("Login successful", $data);
        } catch (Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'refresh_token' => 'required|string',
            ]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $refresh_token = $request->input('refresh_token');
            $payload = JWTAuth::setToken($refresh_token)->getPayload();
            if (($payload['type'] ?? null) !== 'REFRESH') {
                return $this->sendErrorOfUnauthorized("Invalid token type");
            }

            $user = JWTAuth::setToken($refresh_token)->authenticate();
            if (!$user) {
                return $this->sendErrorOfUnauthorized("Invalid token");
            }

            JWTAuth::setToken($refresh_token)->invalidate(true);
            JWTAuth::factory()->setTTL($this->accessTtl);
            $access_token = JWTAuth::claims(['type' => 'ACCESS'])->fromUser($user);

            JWTAuth::factory()->setTTL($this->refreshTtl);
            $refresh_token = JWTAuth::claims(['type' => 'REFRESH'])->fromUser($user);

            $data = [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token
            ];

            return $this->sendSuccessResponse("Token refreshed", $data);
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->sendErrorOfUnauthorized("Refresh token expired");
        } catch (\Throwable $e) {
            return $this->sendErrorOfUnauthorized("Could not refresh token");
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::parseToken()->invalidate(true);
            if ($request->filled('refresh_token')) {
                JWTAuth::setToken($request->input('refresh_token'))->invalidate(true);
            }

            return $this->sendSuccessResponse("User logged out successfully");
        } catch (Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'to' => 'required|email',
                'reset_url' => "required|url"
            ]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $user = User::where('email', $request->to)->first();
            if (!$user) {
                return $this->sendErrorOfBadResponse("User not found");
            }

            $exists = DB::table('password_reset_tokens')
                ->where('email', $request->to)
                ->first();
            if ($exists && Carbon::parse($exists->created_at)->addMinutes(15)->greaterThan(Carbon::now())) {
                return $this->sendErrorOfUnprocessableEntity("You have already requested a password reset link! Try again after 15 minutes.");
            }

            $token = Str::random(60);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => $token,
                    'created_at' => now()
                ]
            );

            $resetLink = $request->reset_url . '?token=' . $token . '&email=' . urlencode($user->email);
            $html = '
                <p>Hello ' . $user->first_name . ', </p>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <p><a href="' . e($resetLink) . '">' . e($resetLink) . '</a></p>
                <p>If you did not request this, please ignore this email.</p>
            ';

            Mail::html($html, function (Message $message) use ($user) {
                $message->to($user->email)
                    ->subject('Password Reset Request');
            });

            DB::commit();
            return $this->sendSuccessResponse('Password reset link sent on your email address');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'new_password' => 'required|min:8',
            ]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $tokenData = DB::table('password_reset_tokens')->where("token", $request->token)->first();
            if (!$tokenData || $tokenData->created_at < Carbon::now()->subMinutes(15)) {
                return $this->sendErrorOfUnprocessableEntity("Token has expired");
            }

            $user = User::where('email', $tokenData->email)->first();
            $user->password = $request->new_password;
            $user->save();

            DB::table('password_reset_tokens')->where('email', $tokenData->email)->delete();

            $html = '
                <p>Hello ' . $user->first_name . ', </p>
                <p>Your password has been successfully reset.</p>
                <p>If you did not perform this action, please contact support immediately.</p>
            ';

            Mail::html($html, function (Message $message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Password Has Been Reset');
            });

            DB::commit();
            return $this->sendSuccessResponse('Password reset successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function getMe(Request $request): JsonResponse
    {
        try {
            $user = User::where('id', Auth::user()->id)
                ->with('roles.permissions')
                ->first();
            if (!$user) {
                return $this->sendErrorOfNotFound404("User not found");
            }

            $user->load('roles');
            foreach ($user->roles as $role) {
                $role->makeHidden(["name", 'pivot', "created_at", "updated_at"]);
            }

            $scopes = collect();
            foreach ($user->roles as $role) {
                if ($role->is_superuser) {
                    $permissions = DB::table('permissions')->get();
                    foreach ($permissions as $permission) {
                        $scopes->add($permission->code);
                    }
                    break;
                } else {
                    foreach ($role->permissions as $permission) {
                        $scopes->add($permission->code);
                    }
                }
            }

            $data = [
                'user' => $user,
                'scopes' => $scopes->toArray(),
            ];

            return $this->sendSuccessResponse("Me", $data);
        } catch (Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }
}
