<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="ReWear API Documentation",
 *     version="1.0.0"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     type="http",
 *     securityScheme="bearerAuth",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your JWT token in the format: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User registration, login, and authentication management"
 * )
 *
 * @OA\Tag(
 *     name="Profile",
 *     description="User profile and account management"
 * )
 *
 * @OA\Tag(
 *     name="User Management",
 *     description="User account deletion and management"
 * )
 *
 * @OA\Tag(
 *     name="Admin - User Management",
 *     description="Admin user management operations"
 * )
 *
 * @OA\Tag(
 *     name="Admin - Charity Management",
 *     description="Admin charity account management"
 * )
 *
 * @OA\Tag(
 *     name="Admin - Analytics",
 *     description="Platform statistics and analytics"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
