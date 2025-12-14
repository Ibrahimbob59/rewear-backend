<?php
// app/Auth/JWTGuard.php

namespace App\Auth;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class JWTGuard implements Guard
{
    use GuardHelpers;

    protected $request;
    protected $tokenService;
    protected $lastAttempted;

    public function __construct(UserProvider $provider, Request $request, TokenService $tokenService)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->tokenService = $tokenService;
    }

    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $token = $this->getTokenForRequest();

        if (!empty($token)) {
            try {
                $payload = $this->tokenService->validateToken($token);
                $this->user = $this->provider->retrieveById($payload['user']['id']);
            } catch (\Exception $e) {
                $this->user = null;
            }
        }

        return $this->user;
    }

    public function validate(array $credentials = [])
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        return $this->hasValidCredentials($user, $credentials);
    }

    protected function hasValidCredentials($user, $credentials)
    {
        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    protected function getTokenForRequest()
    {
        $token = $this->request->query('token');

        if (empty($token)) {
            $token = $this->request->input('token');
        }

        if (empty($token)) {
            $token = $this->request->bearerToken();
        }

        return $token;
    }

    public function attempt(array $credentials = [], $remember = false)
    {
        // This method can be implemented if needed for login functionality
        return false;
    }
}
