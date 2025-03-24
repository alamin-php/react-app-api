<?php

namespace App\Services;

use App\Repositories\AuthRepository;
use Illuminate\Auth\AuthenticationException;

class AuthService
{
    /**
     * Create a new class instance.
     */
    protected $authRepository;
    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function register(array $data)
    {
        return $this->authRepository->register($data);
    }

    public function login(string $email, string $password)
    {
        $token = $this->authRepository->login($email, $password);
        if (!$token) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $token;
    }

    public function logout($user)
    {
        return $this->authRepository->logout($user);
    }
}
