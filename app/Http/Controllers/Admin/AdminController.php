<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /**
     * Create a charity account (Admin only)
     *
     * @OA\Post(
     *     path="/api/admin/charity/create",
     *     summary="Create a charity account (Admin only)",
     *     tags={"Admin Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"organization_name", "email", "phone"},
     *             @OA\Property(property="organization_name", type="string", example="Hope Foundation"),
     *             @OA\Property(property="email", type="string", format="email", example="contact@hopefoundation.org"),
     *             @OA\Property(property="phone", type="string", example="+96170123456"),
     *             @OA\Property(property="organization_description", type="string", example="Supporting underprivileged communities"),
     *             @OA\Property(property="registration_number", type="string", example="REG-2024-001"),
     *             @OA\Property(property="tax_id", type="string", example="TAX-123456"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="city", type="string", example="Beirut"),
     *             @OA\Property(property="country", type="string", example="Lebanon")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Charity created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Charity account created successfully. Credentials sent to email."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="organization_name", type="string", example="Hope Foundation"),
     *                 @OA\Property(property="email", type="string", example="contact@hopefoundation.org"),
     *                 @OA\Property(property="user_type", type="string", example="charity")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not an admin"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createCharity(Request $request): JsonResponse
    {
        try {
            /** @var User $admin */
            $admin = auth()->user();

            Log::info('Admin charity creation attempt', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'organization_name' => $request->organization_name,
                'charity_email' => $request->email,
                'ip' => $request->ip(),
            ]);

            // Validate request
            $validated = $request->validate([
                'organization_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email|max:255',
                'phone' => 'required|string|unique:users,phone|max:20',
                'organization_description' => 'nullable|string|max:1000',
                'registration_number' => 'nullable|string|max:100',
                'tax_id' => 'nullable|string|max:100',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
            ]);

            // Generate random password
            $randomPassword = Str::random(12);

            // Create charity user
            $charity = User::create([
                'name' => $validated['organization_name'],
                'organization_name' => $validated['organization_name'],
                'email' => $validated['email'],
                'password' => Hash::make($randomPassword),
                'phone' => $validated['phone'],
                'user_type' => 'charity',
                'organization_description' => $validated['organization_description'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'tax_id' => $validated['tax_id'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'] ?? null,
                'email_verified_at' => now(), // Auto-verify charity accounts
                'is_active' => true,
            ]);

            // Assign charity role
            $charity->assignRole('charity');

            Log::info('Charity account created successfully', [
                'admin_id' => $admin->id,
                'charity_id' => $charity->id,
                'charity_name' => $charity->organization_name,
                'charity_email' => $charity->email,
            ]);

            // Send credentials email
            try {
                Mail::send('emails.charity_credentials', [
                    'organizationName' => $charity->organization_name,
                    'email' => $charity->email,
                    'password' => $randomPassword,
                ], function ($message) use ($charity) {
                    $message->to($charity->email)
                        ->subject('ReWear - Your Charity Account Credentials');
                });

                Log::info('Charity credentials email sent', [
                    'charity_id' => $charity->id,
                    'charity_email' => $charity->email,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send charity credentials email', [
                    'charity_id' => $charity->id,
                    'charity_email' => $charity->email,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Charity account created successfully. Credentials sent to email.',
                'data' => [
                    'id' => $charity->id,
                    'organization_name' => $charity->organization_name,
                    'email' => $charity->email,
                    'phone' => $charity->phone,
                    'user_type' => $charity->user_type,
                    'created_at' => $charity->created_at,
                ],
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Charity creation validation failed', [
                'admin_id' => auth()->id(),
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            Log::error('Charity creation failed', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create charity account. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while creating the charity account. Please verify all information is correct and try again.',
            ], 500);
        }
    }

    /**
     * Get all charities (Admin only)
     *
     * @OA\Get(
     *     path="/api/admin/charities",
     *     summary="Get all charity accounts (Admin only)",
     *     tags={"Admin Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Charities retrieved successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not an admin")
     * )
     */
    public function getCharities(Request $request): JsonResponse
    {
        try {
            Log::info('Admin viewing charities list', [
                'admin_id' => auth()->id(),
                'page' => $request->input('page', 1),
            ]);

            $charities = User::where('user_type', 'charity')
                ->with('roles')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $charities,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve charities', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve charities list. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while fetching the charities list.',
            ], 500);
        }
    }

    /**
     * Get platform statistics (Public)
     *
     * @OA\Get(
     *     path="/api/admin/stats",
     *     summary="Get platform statistics",
     *     tags={"Admin Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_users", type="integer", example=1250),
     *                 @OA\Property(property="total_charities", type="integer", example=15),
     *                 @OA\Property(property="total_donations", type="integer", example=342),
     *                 @OA\Property(property="total_items_sold", type="integer", example=856),
     *                 @OA\Property(property="items_saved_from_landfill", type="integer", example=1198),
     *                 @OA\Property(property="charities_helped", type="integer", example=12),
     *                 @OA\Property(property="active_listings", type="integer", example=234),
     *                 @OA\Property(property="total_items_listed", type="integer", example=1542)
     *             )
     *         )
     *     )
     * )
     */
    public function getStats(): JsonResponse
    {
        try {
            Log::info('Public viewing platform statistics');

            // Total donations: items with is_donation=true and status='donated' or 'pending'
            $totalDonations = Item::where('is_donation', true)
            ->whereIn('status', ['pending', 'donated'])
            ->count();

            // Total items sold: items with is_donation=false and status='sold' or 'pending'
            $totalItemsSold = Item::where('is_donation', false)
            ->whereIn('status', ['pending', 'sold'])
            ->count();

            // Items saved from landfill: total donations + total items sold
            $itemsSavedFromLandfill = $totalDonations + $totalItemsSold;

            // Charities helped: distinct charities that received at least one donation
            $charitiesHelped = DB::table('orders')
                ->join('items', 'orders.item_id', '=', 'items.id')
                ->join('users', 'orders.buyer_id', '=', 'users.id')
                ->where('items.is_donation', true)
                ->where('users.user_type', 'charity')
                ->whereIn('orders.status', ['pending', 'confirmed', 'in_delivery', 'delivered', 'completed'])
                ->distinct('orders.buyer_id')
                ->count('orders.buyer_id');

            // Active listings: items currently available for sale/donation
            $activeListings = Item::where('status', 'available')->count();

            // Total items listed: all items ever created (including soft deleted)
            $totalItemsListed = Item::withTrashed()->count();

            $stats = [
                'total_users' => User::where('user_type', 'user')->count(),
                'total_charities' => User::where('user_type', 'charity')->count(),
                'total_donations' => $totalDonations,
                'total_items_sold' => $totalItemsSold,
                'items_saved_from_landfill' => $itemsSavedFromLandfill,
                'charities_helped' => $charitiesHelped,
                'active_listings' => $activeListings,
                'total_items_listed' => $totalItemsListed,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve platform statistics. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred while fetching platform statistics.',
            ], 500);
        }
    }
}
