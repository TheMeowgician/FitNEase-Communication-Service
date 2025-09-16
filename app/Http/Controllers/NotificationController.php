<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationSetting;
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
        $user = $request->user();
        $notification = Notification::where('notification_id', $id)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return response()->json(['message' => 'Notification marked as read']);
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
}