<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;
    public function __construct(\App\Services\UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(): JsonResponse
    {
        return response()->json($this->userService->getAllUsers());
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        return $user ? response()->json($user) : response()->json(['message' => 'User not found'], 404);
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        return response()->json($this->userService->createUser($data), 201);
    }

    public function update(int $id, UserUpdateRequest $request)
    {
        $data = $request->validated();
        if ($request->filled('password')) {
            $data['password'] = bcrypt($data['password']);
        }
        $user = $this->userService->updateUser($id, $data);
        return $user ? response()->json($user) : response()->json(['message' => 'User not found'], 404);
    }

    public function destroy(int $id)
    {
        return $this->userService->deleteUser($id)
            ? response()->json(['message' => 'User deleted successfully'])
            : response()->json(['message' => 'User not found'], 404);
    }
}
