<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $deviceName = $request->input('device_name', $request->userAgent() ?? 'api');

        $result = $this->service->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $deviceName,
        );

        return response()->json([
            'data' => [
                'user' => UserResource::make($result['user']),
                'token' => $result['token'],
            ],
            'message' => 'Logged in.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make($request->user()),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->service->logout($request->user());

        return response()->noContent();
    }
}
