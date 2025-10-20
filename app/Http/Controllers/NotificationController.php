<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Events\NotificationCreated;
use App\Events\UnreadCountUpdated;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class NotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'notification_type' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'action_data' => 'sometimes|array',
            'scheduled_time' => 'sometimes|date'
        ]);

        try {
            $notification = Notification::create([
                'user_id' => $request->user_id,
                'notification_type' => $request->notification_type,
                'title' => $request->title,
                'message' => $request->message,
                'action_data' => $request->action_data,
                'scheduled_time' => $request->scheduled_time,
                'is_sent' => $request->scheduled_time ? false : true,
                'sent_at' => $request->scheduled_time ? null : now()
            ]);

            return response()->json([
                'message' => 'Notification created successfully',
                'notification' => $notification
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create notification', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to create notification'], 500);
        }
    }

    public function getUserNotifications(Request $request, $userId)
    {
        $user = $request->attributes->get('user');

        if ($user['user_id'] !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('notification_id', $id)->first();

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        // Broadcast updated unread count for instant badge update
        $unreadCount = Notification::where('user_id', $notification->user_id)
            ->where('is_read', false)
            ->count();
        broadcast(new UnreadCountUpdated($notification->user_id, $unreadCount));

        Log::info('Notification marked as read, unread count updated', [
            'notification_id' => $notification->notification_id,
            'user_id' => $notification->user_id,
            'unread_count' => $unreadCount
        ]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function getUnreadCount(Request $request, $userId)
    {
        $user = $request->attributes->get('user');

        if ($user['user_id'] !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $count = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markAllAsRead(Request $request, $userId)
    {
        $user = $request->attributes->get('user');

        if ($user['user_id'] !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        // Broadcast updated unread count (should be 0)
        broadcast(new UnreadCountUpdated($userId, 0));

        Log::info('All notifications marked as read, unread count reset', [
            'user_id' => $userId,
            'unread_count' => 0
        ]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function groupInvitation(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'group_id' => 'required|integer',
            'group_name' => 'required|string',
            'invited_user_id' => 'required|integer',
            'inviter_user_id' => 'required|integer',
            'group_code' => 'required|string'
        ]);

        try {
            $notification = Notification::create([
                'user_id' => $request->invited_user_id,
                'notification_type' => 'group_invite',
                'title' => 'Group Invitation',
                'message' => "You've been invited to join {$request->group_name}",
                'action_data' => [
                    'type' => 'group_invite',
                    'group_id' => $request->group_id,
                    'group_name' => $request->group_name,
                    'group_code' => $request->group_code,
                    'inviter_user_id' => $request->inviter_user_id
                ],
                'is_sent' => true,
                'sent_at' => now()
            ]);

            // Broadcast notification in real-time
            broadcast(new NotificationCreated($notification))->toOthers();

            // Broadcast updated unread count for instant badge update
            $unreadCount = Notification::where('user_id', $request->invited_user_id)
                ->where('is_read', false)
                ->count();
            broadcast(new UnreadCountUpdated($request->invited_user_id, $unreadCount));

            Log::info('Group invitation notification created and broadcast', [
                'notification_id' => $notification->notification_id,
                'invited_user_id' => $request->invited_user_id,
                'group_id' => $request->group_id,
                'unread_count' => $unreadCount,
                'channel' => 'user.' . $request->invited_user_id
            ]);

            return response()->json([
                'message' => 'Group invitation notification created',
                'notification' => $notification
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create group invitation notification', [
                'invited_user_id' => $request->invited_user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to create notification'], 500);
        }
    }

    public function groupInvitationDeclined(Request $request)
    {
        Log::info('Group invitation declined request received', [
            'request_data' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'inviter_user_id' => 'required|integer',
                'group_name' => 'required|string',
                'declined_user_id' => 'required|integer',
            ]);

            Log::info('Validation passed', ['validated_data' => $validated]);

            // Fetch username from auth service
            $declinedUsername = $this->getUsernameFromAuthService($request->declined_user_id);
            if (!$declinedUsername) {
                $declinedUsername = 'Someone'; // Fallback
            }

            Log::info('Creating notification in database');

            $notification = Notification::create([
                'user_id' => $request->inviter_user_id,
                'notification_type' => 'social',
                'title' => 'Invitation Declined',
                'message' => "{$declinedUsername} declined your invitation to join {$request->group_name}",
                'action_data' => [
                    'type' => 'group_invite_declined',
                    'group_name' => $request->group_name,
                    'declined_user_id' => $request->declined_user_id,
                ],
                'is_sent' => true,
                'sent_at' => now()
            ]);

            Log::info('Notification created successfully', [
                'notification_id' => $notification->notification_id
            ]);

            // Broadcast notification in real-time
            Log::info('Broadcasting notification');
            broadcast(new NotificationCreated($notification))->toOthers();
            Log::info('Broadcast completed');

            // Broadcast updated unread count for instant badge update
            $unreadCount = Notification::where('user_id', $request->inviter_user_id)
                ->where('is_read', false)
                ->count();
            broadcast(new UnreadCountUpdated($request->inviter_user_id, $unreadCount));
            Log::info('Unread count broadcast sent', ['unread_count' => $unreadCount]);

            Log::info('Group invitation declined notification created and broadcast', [
                'notification_id' => $notification->notification_id,
                'inviter_user_id' => $request->inviter_user_id,
                'declined_user_id' => $request->declined_user_id,
                'channel' => 'user.' . $request->inviter_user_id
            ]);

            return response()->json([
                'message' => 'Group invitation declined notification created',
                'notification' => $notification
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for group invitation declined', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Failed to create group invitation declined notification', [
                'inviter_user_id' => $request->inviter_user_id ?? null,
                'declined_user_id' => $request->declined_user_id ?? null,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to create notification',
                'message' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    public function groupInvitationAccepted(Request $request)
    {
        Log::info('Group invitation accepted request received', [
            'request_data' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'inviter_user_id' => 'required|integer',
                'group_name' => 'required|string',
                'accepted_user_id' => 'required|integer',
            ]);

            Log::info('Validation passed', ['validated_data' => $validated]);

            // Fetch username from auth service
            $acceptedUsername = $this->getUsernameFromAuthService($request->accepted_user_id);
            if (!$acceptedUsername) {
                $acceptedUsername = 'Someone'; // Fallback
            }

            $notification = Notification::create([
                'user_id' => $request->inviter_user_id,
                'notification_type' => 'social',
                'title' => 'Invitation Accepted',
                'message' => "{$acceptedUsername} accepted your invitation to join {$request->group_name}",
                'action_data' => [
                    'type' => 'group_invite_accepted',
                    'group_name' => $request->group_name,
                    'accepted_user_id' => $request->accepted_user_id,
                ],
                'is_sent' => true,
                'sent_at' => now()
            ]);

            Log::info('Notification created successfully', [
                'notification_id' => $notification->notification_id,
                'username' => $acceptedUsername
            ]);

            // Broadcast notification in real-time
            Log::info('Broadcasting notification');
            broadcast(new NotificationCreated($notification))->toOthers();
            Log::info('Broadcast completed');

            // Broadcast updated unread count for instant badge update
            $unreadCount = Notification::where('user_id', $request->inviter_user_id)
                ->where('is_read', false)
                ->count();
            broadcast(new UnreadCountUpdated($request->inviter_user_id, $unreadCount));
            Log::info('Unread count broadcast sent', ['unread_count' => $unreadCount]);

            Log::info('Group invitation accepted notification created and broadcast', [
                'notification_id' => $notification->notification_id,
                'inviter_user_id' => $request->inviter_user_id,
                'accepted_user_id' => $request->accepted_user_id,
                'channel' => 'user.' . $request->inviter_user_id
            ]);

            return response()->json([
                'message' => 'Group invitation accepted notification created',
                'notification' => $notification
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for group invitation accepted', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Failed to create group invitation accepted notification', [
                'inviter_user_id' => $request->inviter_user_id ?? null,
                'accepted_user_id' => $request->accepted_user_id ?? null,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to create notification',
                'message' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    public function deleteNotification(Request $request, $id)
    {
        $user = $request->attributes->get('user');

        $notification = Notification::where('notification_id', $id)->first();

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        // Ensure user can only delete their own notifications
        if ($notification->user_id !== $user['user_id']) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->delete();

        Log::info('Notification deleted', [
            'notification_id' => $id,
            'user_id' => $user['user_id']
        ]);

        return response()->json(['message' => 'Notification deleted successfully']);
    }

    public function deleteAllNotifications(Request $request, $userId)
    {
        $user = $request->attributes->get('user');

        if ($user['user_id'] !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $deletedCount = Notification::where('user_id', $userId)->delete();

        Log::info('All notifications deleted', [
            'user_id' => $userId,
            'deleted_count' => $deletedCount
        ]);

        return response()->json([
            'message' => 'All notifications deleted successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    public function achievementNotification(Request $request)
    {
        return $this->handleAchievementNotification($request);
    }

    public function handleAchievementNotification(Request $request)
    {
        // Validate request has valid Sanctum token
        $user = $this->validateUserAccess($request);

        $userId = $request->input('user_id');
        $achievementId = $request->input('achievement_id');

        // Get user data from Auth service using Sanctum token
        $authClient = new Client();
        try {
            $userResponse = $authClient->get(env('AUTH_SERVICE_URL') . '/auth/user-profile/' . $userId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->bearerToken(),
                    'Accept' => 'application/json'
                ]
            ]);

            if ($userResponse->getStatusCode() !== 200) {
                return response()->json(['error' => 'Failed to fetch user data'], 400);
            }

            $userData = json_decode($userResponse->getBody(), true);

        } catch (Exception $e) {
            Log::error('Failed to fetch user data for achievement notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Service communication failed'], 500);
        }

        // Get behavioral patterns for timing optimization
        try {
            $mlClient = new Client();
            $patternsResponse = $mlClient->get(env('ML_SERVICE_URL') . '/api/v1/user-patterns/' . $userId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->bearerToken()
                ]
            ]);

            $patterns = json_decode($patternsResponse->getBody(), true);
        } catch (Exception $e) {
            // Continue without ML patterns if service unavailable
            $patterns = null;
        }

        // Create personalized achievement notification
        $this->createPersonalizedNotification($userId, $achievementId, $userData, $patterns);

        return response()->json(['message' => 'Achievement notification processed']);
    }

    public function getNotificationSettings(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $settings = NotificationSetting::where('user_id', $userId)->get();

        return response()->json($settings);
    }

    public function updateNotificationSettings(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.*.notification_type' => 'required|string',
            'settings.*.enabled' => 'required|boolean',
            'settings.*.email_enabled' => 'sometimes|boolean',
            'settings.*.push_enabled' => 'sometimes|boolean'
        ]);

        try {
            foreach ($request->settings as $settingData) {
                NotificationSetting::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'notification_type' => $settingData['notification_type']
                    ],
                    $settingData
                );
            }

            return response()->json(['message' => 'Notification settings updated successfully']);

        } catch (Exception $e) {
            Log::error('Failed to update notification settings', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to update settings'], 500);
        }
    }

    private function getUserData($token, $userId)
    {
        $client = new Client();

        try {
            $response = $client->get(env('AUTH_SERVICE_URL') . '/auth/user-profile/' . $userId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 10
            ]);

            return json_decode($response->getBody(), true);

        } catch (Exception $e) {
            Log::warning('Failed to fetch user data for notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    private function getUserPatterns($token, $userId)
    {
        $client = new Client();

        try {
            $response = $client->get(env('ML_SERVICE_URL') . '/api/v1/user-patterns/' . $userId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 10
            ]);

            return json_decode($response->getBody(), true);

        } catch (Exception $e) {
            Log::info('ML patterns service unavailable', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function createPersonalizedNotification($userId, $achievementId, $userData, $patterns)
    {
        $userName = $userData['name'] ?? 'there';
        $fitnessLevel = $userData['fitness_level'] ?? 'beginner';

        $personalizations = [
            'beginner' => "Great job, {$userName}! You've unlocked a new achievement! You're building great habits!",
            'intermediate' => "Awesome work, {$userName}! You've earned a new achievement! Your dedication is paying off!",
            'advanced' => "Outstanding achievement, {$userName}! You've reached a new milestone! You're setting a great example!"
        ];

        $personalizedMessage = $personalizations[$fitnessLevel] ?? $personalizations['beginner'];

        return Notification::create([
            'user_id' => $userId,
            'notification_type' => 'achievement',
            'title' => 'New Achievement Unlocked!',
            'message' => $personalizedMessage,
            'action_data' => [
                'achievement_id' => $achievementId,
                'type' => 'achievement_unlock'
            ],
            'is_sent' => true,
            'sent_at' => now()
        ]);
    }

    private function personalizeMessage($baseMessage, $userData, $patterns)
    {
        $userName = $userData['name'] ?? 'there';
        $fitnessLevel = $userData['fitness_level'] ?? 'beginner';

        $personalizations = [
            'beginner' => "Great job, {$userName}! " . $baseMessage . " You're building great habits!",
            'intermediate' => "Awesome work, {$userName}! " . $baseMessage . " Your dedication is paying off!",
            'advanced' => "Outstanding achievement, {$userName}! " . $baseMessage . " You're setting a great example!"
        ];

        return $personalizations[$fitnessLevel] ?? $personalizations['beginner'];
    }

    private function validateUserAccess(Request $request)
    {
        // Sanctum middleware automatically validates the token
        $user = $request->user();

        if (!$user) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid or missing token');
        }

        return $user;
    }

    public function deleteEmailVerificationNotification(Request $request, $userId)
    {
        try {
            $deleted = Notification::where('user_id', $userId)
                ->where('notification_type', 'email_verification')
                ->delete();

            Log::info('Email verification notification deleted', [
                'user_id' => $userId,
                'deleted_count' => $deleted
            ]);

            return response()->json([
                'message' => 'Email verification notification deleted successfully',
                'deleted_count' => $deleted
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete email verification notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to delete notification'], 500);
        }
    }

    /**
     * Create notification when a user is kicked from a group
     */
    public function groupMemberKicked(Request $request)
    {
        Log::info('Group member kicked notification request received', [
            'request_data' => $request->all()
        ]);

        try {
            $validated = $request->validate([
                'kicked_user_id' => 'required|integer',
                'group_name' => 'required|string',
                'group_id' => 'required|integer',
                'kicked_by_user_id' => 'required|integer',
            ]);

            Log::info('Validation passed', ['validated_data' => $validated]);

            // Fetch username of the person who kicked
            $kickedByUsername = $this->getUsernameFromAuthService($request->kicked_by_user_id);
            if (!$kickedByUsername) {
                $kickedByUsername = 'Admin'; // Fallback
            }

            $notification = Notification::create([
                'user_id' => $request->kicked_user_id,
                'notification_type' => 'social',
                'title' => 'Removed from Group',
                'message' => "You were removed from {$request->group_name} by {$kickedByUsername}",
                'action_data' => [
                    'type' => 'group_member_kicked',
                    'group_name' => $request->group_name,
                    'group_id' => $request->group_id,
                    'kicked_by_user_id' => $request->kicked_by_user_id,
                ],
                'is_sent' => true,
                'sent_at' => now()
            ]);

            Log::info('Notification created successfully', [
                'notification_id' => $notification->notification_id,
                'kicked_by_username' => $kickedByUsername
            ]);

            // Broadcast notification in real-time
            Log::info('Broadcasting notification');
            broadcast(new NotificationCreated($notification))->toOthers();
            Log::info('Broadcast completed');

            // Broadcast updated unread count for instant badge update
            $unreadCount = Notification::where('user_id', $request->kicked_user_id)
                ->where('is_read', false)
                ->count();
            broadcast(new UnreadCountUpdated($request->kicked_user_id, $unreadCount));
            Log::info('Unread count broadcast sent', ['unread_count' => $unreadCount]);

            Log::info('Group member kicked notification created and broadcast', [
                'notification_id' => $notification->notification_id,
                'kicked_user_id' => $request->kicked_user_id,
                'kicked_by_user_id' => $request->kicked_by_user_id,
                'channel' => 'user.' . $request->kicked_user_id
            ]);

            return response()->json([
                'message' => 'Group member kicked notification created',
                'notification' => $notification
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for group member kicked', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Failed to create group member kicked notification', [
                'kicked_user_id' => $request->kicked_user_id ?? null,
                'kicked_by_user_id' => $request->kicked_by_user_id ?? null,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to create notification',
                'message' => $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    /**
     * Fetch username from auth service
     * Helper method to get username for notification messages
     */
    private function getUsernameFromAuthService(int $userId): ?string
    {
        try {
            $client = new Client();
            // Use internal endpoint that doesn't require authentication
            $response = $client->get(env('AUTH_SERVICE_URL') . '/api/internal/user-profile/' . $userId, [
                'timeout' => 5,
                'headers' => [
                    'Accept' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                // Auth service returns user object directly, not wrapped in 'data'
                $username = $data['username'] ?? $data['data']['username'] ?? null;

                Log::info('Username fetched from auth service', [
                    'user_id' => $userId,
                    'username' => $username,
                    'response_keys' => array_keys($data)
                ]);

                return $username;
            }

            return null;

        } catch (Exception $e) {
            Log::warning('Failed to fetch username from auth service', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}