<?php

namespace App\Repositories;

use App\Interfaces\AuthInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthRepository implements AuthInterface
{
    /**
     * Create a new class instance.
     */
    protected $model;
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function register(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        return $this->model->create($data);
    }

    public function login(string $email, string $password)
    {
        $user = $this->model->where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }
        return $user->createToken('auth_token')->plainTextToken;
    }

    public function logout($model)
    {
        $model->tokens()->delete();
        return true;
    }
}
