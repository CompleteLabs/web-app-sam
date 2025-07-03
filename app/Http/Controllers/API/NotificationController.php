<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get notifications for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
                'unread_only' => 'boolean',
            ]);

            $user = Auth::user();
            $query = $user->notifications();

            // Filter by read/unread
            if ($request->has('unread_only') && $request->boolean('unread_only')) {
                $query->whereNull('read_at');
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $notifications = $query->orderBy('created_at', 'desc')
                                  ->paginate($perPage);

            $meta = [
                'unread_count' => $user->unreadNotifications()->count(),
            ];

            return ResponseFormatter::paginated(
                $notifications,
                'Notifications retrieved successfully',
                $meta
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::validation($e->errors());
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to retrieve notifications');
        }
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $count = Auth::user()->unreadNotifications()->count();

            return ResponseFormatter::success(
                ['unread_count' => $count],
                'Unread count retrieved successfully'
            );
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to get unread count');
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        try {
            $notification = Auth::user()->notifications()->find($id);

            if (!$notification) {
                return ResponseFormatter::notFound('Notification not found');
            }

            if ($notification->read_at === null) {
                $notification->markAsRead();
            }

            return ResponseFormatter::success(
                $notification->fresh(),
                'Notification marked as read successfully'
            );
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to mark notification as read');
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $unreadCount = $user->unreadNotifications()->count();

            $user->unreadNotifications->markAsRead();

            return ResponseFormatter::success(
                ['marked_count' => $unreadCount],
                'All notifications marked as read successfully'
            );
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to mark all notifications as read');
        }
    }

    /**
     * Delete notification
     */
    public function delete($id): JsonResponse
    {
        try {
            $notification = Auth::user()->notifications()->find($id);

            if (!$notification) {
                return ResponseFormatter::notFound('Notification not found');
            }

            $notification->delete();

            return ResponseFormatter::success(
                null,
                'Notification deleted successfully'
            );
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to delete notification');
        }
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $deletedCount = $user->readNotifications()->delete();

            return ResponseFormatter::success(
                ['deleted_count' => $deletedCount],
                "Successfully deleted {$deletedCount} read notifications"
            );
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to delete read notifications');
        }
    }

    /**
     * Get notification details
     */
    public function show($id): JsonResponse
    {
        try {
            $notification = Auth::user()->notifications()->find($id);

            if (!$notification) {
                return ResponseFormatter::notFound('Notification not found');
            }

            return ResponseFormatter::success(
                $notification,
                'Notification details retrieved successfully'
            );
        } catch (\Exception $e) {
            return ResponseFormatter::serverError('Failed to retrieve notification details');
        }
    }
}
