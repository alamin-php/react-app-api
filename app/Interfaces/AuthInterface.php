<?php

namespace App\Interfaces;

use App\Models\User;

interface AuthInterface
{
    public function register(array $data);
    public function login(string $email, string $password);
    public function logout(array $user);
}
