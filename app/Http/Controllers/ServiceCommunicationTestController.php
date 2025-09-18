<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ServiceCommunicationTestController extends Controller
{
    public function testServiceConnectivity(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $services = [
                'auth' => env('AUTH_SERVICE_URL', 'http://fitnease-auth'),
                'engagement' => env('ENGAGEMENT_SERVICE_URL', 'http://fitnease-engagement'),
                'tracking' => env('TRACKING_SERVICE_URL', 'http://fitnease-tracking'),
                'content' => env('CONTENT_SERVICE_URL', 'http://fitnease-content')
            ];

            $connectivity = [];

            foreach ($services as $serviceName => $serviceUrl) {
                try {
                    $response = Http::timeout(10)->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json'
                    ])->get($serviceUrl . '/api/health');

                    $connectivity[$serviceName] = [
                        'url' => $serviceUrl,
                        'status' => $response->successful() ? 'connected' : 'failed',
                        'response_code' => $response->status(),
                        'response_time' => $response->handlerStats()['total_time'] ?? 'unknown'
                    ];

                } catch (\Exception $e) {
                    $connectivity[$serviceName] = [
                        'url' => $serviceUrl,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $overallHealth = true;
            foreach ($connectivity as $service) {
                if ($service['status'] !== 'connected') {
                    $overallHealth = false;
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Service connectivity test completed',
                'overall_health' => $overallHealth ? 'healthy' : 'degraded',
                'services' => $connectivity,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service connectivity test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testCommsTokenValidation(Request $request): JsonResponse
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

            return response()->json([
                'success' => true,
                'message' => 'Token validation successful in comms service',
                'comms_service_status' => 'connected',
                'user_data' => $user,
                'token_info' => [
                    'token_preview' => substr($token, 0, 10) . '...',
                    'validated_at' => now()->toISOString()
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comms token validation test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testServiceIntegration(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            $user = $request->attributes->get('user');

            if (!$token || !$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $userId = $user['user_id'];
            $testResults = [];

            // Test if other services can accept communications from this comms service
            $authServiceUrl = env('AUTH_SERVICE_URL', 'http://fitnease-auth');
            try {
                $authResponse = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])->get($authServiceUrl . '/api/auth/user-profile/' . $userId);

                $testResults['auth_service'] = [
                    'communication_accepted' => $authResponse->successful() ? 'success' : 'failed',
                    'response_code' => $authResponse->status(),
                    'service_response' => $authResponse->successful() ? 'accessible' : 'rejected'
                ];
            } catch (\Exception $e) {
                $testResults['auth_service'] = [
                    'communication_accepted' => 'error',
                    'error' => $e->getMessage()
                ];
            }

            // Test engagement service connectivity
            $engagementServiceUrl = env('ENGAGEMENT_SERVICE_URL', 'http://fitnease-engagement');
            try {
                $engagementResponse = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])->get($engagementServiceUrl . '/api/health');

                $testResults['engagement_service'] = [
                    'connectivity' => $engagementResponse->successful() ? 'success' : 'failed',
                    'response_code' => $engagementResponse->status()
                ];
            } catch (\Exception $e) {
                $testResults['engagement_service'] = [
                    'connectivity' => 'error',
                    'error' => $e->getMessage()
                ];
            }

            // Test tracking service connectivity
            $trackingServiceUrl = env('TRACKING_SERVICE_URL', 'http://fitnease-tracking');
            try {
                $trackingResponse = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])->get($trackingServiceUrl . '/api/health');

                $testResults['tracking_service'] = [
                    'connectivity' => $trackingResponse->successful() ? 'success' : 'failed',
                    'response_code' => $trackingResponse->status()
                ];
            } catch (\Exception $e) {
                $testResults['tracking_service'] = [
                    'connectivity' => 'error',
                    'error' => $e->getMessage()
                ];
            }

            $overallSuccess = true;
            foreach ($testResults as $test) {
                foreach ($test as $status) {
                    if ($status === 'failed' || $status === 'error') {
                        $overallSuccess = false;
                        break 2;
                    }
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'Service integration test completed successfully' : 'Service integration test encountered issues',
                'test_results' => $testResults,
                'comms_service_info' => [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId,
                    'token_valid' => true
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service integration test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}