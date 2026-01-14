<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     tags={"Notifications"},
     *     summary="Get user notifications",
     *     description="Get paginated list of user's notifications",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="unread_only",
     *         in="query",
     *         description="Only return unread notifications",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by notification type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);

            $notifications = $this->notificationService->getUserNotifications($user->id, $perPage);

            // Apply filters if requested
            if ($request->boolean('unread_only')) {
                $notifications->getCollection()->transform(function ($notification) {
                    return $notification->is_read ? null : $notification;
                })->filter();
            }

            if ($request->has('type')) {
                $type = $request->input('type');
                $notifications->getCollection()->transform(function ($notification) use ($type) {
                    return $notification->type === $type ? $notification : null;
                })->filter();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'last_page' => $notifications->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve notifications', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/unread-count",
     *     tags={"Notifications"},
     *     summary="Get unread notifications count",
     *     description="Get count of unread notifications for the current user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="unread_count", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $user = auth()->user();
            $count = $this->notificationService->getUnreadCount($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Unread count retrieved successfully',
                'data' => [
                    'unread_count' => $count,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve unread count', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unread count',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Mark notification as read",
     *     description="Mark a specific notification as read",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification marked as read")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $marked = $this->notificationService->markAsRead($id, $user->id);

            if (!$marked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or already read',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'notification_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/mark-all-read",
     *     tags={"Notifications"},
     *     summary="Mark all notifications as read",
     *     description="Mark all unread notifications as read for the current user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All notifications marked as read"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="marked_count", type="integer", example=8)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = auth()->user();
            $markedCount = $this->notificationService->markAllAsRead($user->id);

            return response()->json([
                'success' => true,
                'message' => "Marked {$markedCount} notifications as read",
                'data' => [
                    'marked_count' => $markedCount,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/recent",
     *     tags={"Notifications"},
     *     summary="Get recent notifications",
     *     description="Get the most recent 10 notifications for quick display",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Recent notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function recent(): JsonResponse
    {
        try {
            $user = auth()->user();
            $notifications = $this->notificationService->getUserNotifications($user->id, 10);

            return response()->json([
                'success' => true,
                'message' => 'Recent notifications retrieved successfully',
                'data' => NotificationResource::collection($notifications),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve recent notifications', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent notifications',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Create a test user notification
     *
     * @OA\Post(
     *     path="/api/notifications/test",
     *     tags={"Notifications"},
     *     summary="Create a test user notification",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"user", "order", "item", "driver", "system"}, example="system"),
     *             @OA\Property(property="title", type="string", example="Test User Notification"),
     *             @OA\Property(property="message", type="string", example="This is a test notification")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test notification created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test notification created successfully")
     *         )
     *     )
     * )
     */
    public function storeTest(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'sometimes|in:user,order,item,driver,system',
                'title' => 'sometimes|string|max:255',
                'message' => 'required|string',
            ]);

            Notification::create([
                'user_id' => auth()->id(),
                'type' => $validated['type'] ?? 'system',
                'title' => $validated['title'] ?? 'Test User Notification',
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            Log::info('Test user notification created', [
                'user_id' => auth()->id(),
                'type' => $validated['type'] ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test notification created successfully',
            ], 200);

        } catch (ValidationException $e) {
            throw $e;

        } catch (\Exception $e) {
            Log::error('Failed to create test notification', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create test notification',
            ], 500);
        }
    }
}
