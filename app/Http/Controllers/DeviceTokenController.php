<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    /**
     * Register a device token for push notifications
     * POST /api/comms/device-tokens
     */
    public function registerToken(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'expo_push_token' => 'required|string',
            'platform' => 'required|in:ios,android',
        ]);

        try {
            // Check if user is authorized to register this token
            $authenticatedUser = $request->attributes->get('user');
            if ($authenticatedUser['user_id'] !== (int) $request->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Upsert the token - update if exists, create if not
            $deviceToken = DeviceToken::updateOrCreate(
                ['expo_push_token' => $request->expo_push_token],
                [
                    'user_id' => $request->user_id,
                    'platform' => $request->platform,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );

            Log::info('Device token registered', [
                'device_token_id' => $deviceToken->device_token_id,
                'user_id' => $request->user_id,
                'platform' => $request->platform,
            ]);

            return response()->json([
                'message' => 'Device token registered successfully',
                'device_token_id' => $deviceToken->device_token_id,
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to register device token', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to register device token',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a device token (for logout or token refresh)
     * DELETE /api/comms/device-tokens
     */
    public function removeToken(Request $request)
    {
        $request->validate([
            'expo_push_token' => 'required|string',
        ]);

        try {
            $deleted = DeviceToken::where('expo_push_token', $request->expo_push_token)
                ->delete();

            if ($deleted) {
                Log::info('Device token removed', [
                    'expo_push_token' => substr($request->expo_push_token, 0, 20) . '...',
                ]);

                return response()->json([
                    'message' => 'Device token removed successfully',
                ]);
            }

            return response()->json([
                'message' => 'Token not found',
            ], 404);

        } catch (Exception $e) {
            Log::error('Failed to remove device token', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to remove device token',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all device tokens for a user (for admin/debugging)
     * GET /api/comms/device-tokens/{userId}
     */
    public function getUserTokens(Request $request, $userId)
    {
        try {
            // Check if user is authorized
            $authenticatedUser = $request->attributes->get('user');
            if ($authenticatedUser['user_id'] !== (int) $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $tokens = DeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->get(['device_token_id', 'platform', 'last_used_at', 'created_at']);

            return response()->json([
                'tokens' => $tokens,
                'count' => $tokens->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get user tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get device tokens',
            ], 500);
        }
    }

    /**
     * Deactivate all tokens for a user (for logout from all devices)
     * DELETE /api/comms/device-tokens/user/{userId}
     */
    public function deactivateAllUserTokens(Request $request, $userId)
    {
        try {
            // Check if user is authorized
            $authenticatedUser = $request->attributes->get('user');
            if ($authenticatedUser['user_id'] !== (int) $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $updated = DeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            Log::info('All device tokens deactivated for user', [
                'user_id' => $userId,
                'count' => $updated,
            ]);

            return response()->json([
                'message' => 'All device tokens deactivated',
                'count' => $updated,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to deactivate user tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to deactivate device tokens',
            ], 500);
        }
    }
}
