<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\EngagementService;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceTestController extends Controller
{
    protected AuthService $authService;
    protected EngagementService $engagementService;
    protected TrackingService $trackingService;

    public function __construct(
        AuthService $authService,
        EngagementService $engagementService,
        TrackingService $trackingService
    ) {
        $this->authService = $authService;
        $this->engagementService = $engagementService;
        $this->trackingService = $trackingService;
    }

    public function testAuthService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            $tests = [
                'user_profile' => $this->authService->getUserProfile($userId, $token),
                'user_access_validation' => $this->authService->validateUserAccess($userId, $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Auth service test completed',
                'service' => 'auth',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auth service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testEngagementService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            $tests = [
                'email_opened_tracking' => $this->engagementService->trackEmailOpened($userId, 'welcome', $token),
                'notification_sent_tracking' => $this->engagementService->trackNotificationSent($userId, 'achievement', $token),
                'user_interaction_tracking' => $this->engagementService->trackUserInteraction($userId, 'email_click', ['message' => 'User clicked welcome email'], $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Engagement service test completed',
                'service' => 'engagement',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Engagement service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testTrackingService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            $tests = [
                'user_activity_data' => $this->trackingService->getUserActivityData($userId, $token),
                'workout_progress' => $this->trackingService->getWorkoutProgress($userId, $token),
                'communication_event_recording' => $this->trackingService->recordCommunicationEvent($userId, 'email_sent', ['email_type' => 'verification'], $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Tracking service test completed',
                'service' => 'tracking',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testAllServices(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $allTests = [
                'auth_service' => $this->testAuthService($request)->getData(),
                'engagement_service' => $this->testEngagementService($request)->getData(),
                'tracking_service' => $this->testTrackingService($request)->getData()
            ];

            $overallSuccess = true;
            foreach ($allTests as $test) {
                if (!$test->success) {
                    $overallSuccess = false;
                    break;
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'All service tests completed successfully' : 'Some service tests failed',
                'results' => $allTests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service testing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}