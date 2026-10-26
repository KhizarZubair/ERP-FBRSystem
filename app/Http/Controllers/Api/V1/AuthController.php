<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        abort_if($request->user() !== null, 409, 'Sign out before registering another account.');
        abort_if(config('erp.single_user_mode', true) && User::query()->exists(), 403,
            'Single-user installation active. Please login with your master credentials.');
        $user = $this->auth->register($request->validated());
        $this->auth->startSession($user, $request->session());

        return (new UserResource($this->auth->currentUser($user, $request->session())))
            ->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return (new UserResource($this->auth->login($request->validated(), $request->session())))
            ->response()->setStatusCode(200);
    }

    public function me(Request $request): JsonResponse
    {
        return (new UserResource($this->auth->currentUser($request->user(), $request->session())))
            ->response()->setStatusCode(200);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return response()->json(['message' => 'Your login password has been changed successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->session());

        return response()->json(['message' => 'Signed out successfully.']);
    }
}
