<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    /**
     * Delete the authenticated user's own account
     *
     * @OA\Delete(
     *     path="/api/user/delete-account",
     *     summary="Delete own account",
     *     tags={"User Management"},
     *     security={{"bearer": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="MyPassword123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Your account has been permanently deleted.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid password"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function deleteSelfAccount(Request $request)
    {
        try {
            $user = auth()->user();

            Log::info('User account deletion attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            // Validate password confirmation
            $request->validate([
                'password' => 'required|string',
            ]);

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Failed account deletion - incorrect password', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password. Account deletion failed.',
                ], 401);
            }

            // Store user info for logging before deletion
            $deletedUserId = $user->id;
            $deletedUserEmail = $user->email;
            $deletedUserType = $user->user_type;

            // Revoke all tokens
            $user->tokens()->delete();
            $user->refreshTokens()->delete();

            // Delete the user (cascade will handle related records)
            $user->delete();

            Log::info('User account deleted successfully', [
                'deleted_user_id' => $deletedUserId,
                'deleted_user_email' => $deletedUserEmail,
                'deleted_user_type' => $deletedUserType,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your account has been permanently deleted.',
            ], 200);

        } catch (ValidationException $e) {
            Log::error('Account deletion validation failed', [
                'user_id' => auth()->id(),
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);
            throw $e;

        } catch (\Exception $e) {
            Log::error('Account deletion failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Account deletion failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Admin delete any user account
     *
     * @OA\Delete(
     *     path="/api/admin/users/{userId}",
     *     summary="Delete any user (Admin only)",
     *     tags={"Admin - User Management"},
     *     security={{"bearer": {}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID of the user to delete",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Violated terms of service")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User account deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not an admin"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function deleteUserByAdmin(Request $request, int $userId)
    {
        try {
            $admin = auth()->user();

            Log::info('Admin user deletion attempt', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'target_user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            // Prevent admin from deleting themselves
            if ($admin->id === $userId) {
                Log::warning('Admin attempted to delete their own account', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own admin account. Use the self-delete endpoint instead.',
                ], 400);
            }

            // Find the user
            $user = User::find($userId);

            if (!$user) {
                Log::warning('Admin attempted to delete non-existent user', [
                    'admin_id' => $admin->id,
                    'target_user_id' => $userId,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check if trying to delete another admin
            if ($user->hasRole('admin')) {
                Log::warning('Admin attempted to delete another admin', [
                    'admin_id' => $admin->id,
                    'target_admin_id' => $userId,
                    'target_admin_email' => $user->email,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete another admin account.',
                ], 403);
            }

            // Optional deletion reason
            $reason = $request->input('reason', 'No reason provided');

            // Store user info for logging before deletion
            $deletedUserId = $user->id;
            $deletedUserEmail = $user->email;
            $deletedUserName = $user->name;
            $deletedUserType = $user->user_type;

            // Revoke all tokens
            $user->tokens()->delete();
            $user->refreshTokens()->delete();

            // Delete the user
            $user->delete();

            Log::info('Admin deleted user successfully', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'deleted_user_id' => $deletedUserId,
                'deleted_user_email' => $deletedUserEmail,
                'deleted_user_name' => $deletedUserName,
                'deleted_user_type' => $deletedUserType,
                'reason' => $reason,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User account deleted successfully.',
                'deleted_user' => [
                    'id' => $deletedUserId,
                    'name' => $deletedUserName,
                    'email' => $deletedUserEmail,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Admin user deletion failed', [
                'admin_id' => auth()->id(),
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'User deletion failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get list of all users (Admin only)
     *
     * @OA\Get(
     *     path="/api/admin/users",
     *     summary="Get all users (Admin only)",
     *     tags={"Admin - User Management"},
     *     security={{"bearer": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="user_type",
     *         in="query",
     *         description="Filter by user type",
     *         @OA\Schema(type="string", enum={"user", "charity"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users list retrieved successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not an admin")
     * )
     */
    public function getAllUsers(Request $request)
    {
        try {
            Log::info('Admin viewing users list', [
                'admin_id' => auth()->id(),
                'filters' => $request->only(['user_type', 'page', 'per_page']),
            ]);

            $query = User::query();

            // Filter by user type if provided
            if ($request->has('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $users = $query->with('roles')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve users list', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users list.',
            ], 500);
        }
    }
}
