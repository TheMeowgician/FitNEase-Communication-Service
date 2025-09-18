<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('TRACKING_SERVICE_URL', 'http://fitnease-tracking');
    }

    public function getUserActivityData($userId, $token)
    {
        try {
            Log::info('Getting user activity data from tracking service', [
                'service' => 'fitnease-comms',
                'user_id' => $userId,
                'tracking_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/session-stats/' . $userId);

            if ($response->successful()) {
                Log::info('User activity data retrieved successfully', [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId
                ]);

                return $response->json();
            }

            Log::warning('Failed to get user activity data', [
                'service' => 'fitnease-comms',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Tracking service communication error', [
                'service' => 'fitnease-comms',
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'tracking_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function getWorkoutProgress($userId, $token)
    {
        try {
            Log::info('Getting workout progress from tracking service', [
                'service' => 'fitnease-comms',
                'user_id' => $userId,
                'tracking_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/workouts/progress/' . $userId);

            if ($response->successful()) {
                Log::info('Workout progress retrieved successfully', [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId
                ]);

                return $response->json();
            }

            Log::warning('Failed to get workout progress', [
                'service' => 'fitnease-comms',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Tracking service communication error', [
                'service' => 'fitnease-comms',
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'tracking_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function recordCommunicationEvent($userId, $eventType, $eventData, $token)
    {
        try {
            Log::info('Recording communication event in tracking service', [
                'service' => 'fitnease-comms',
                'user_id' => $userId,
                'event_type' => $eventType,
                'tracking_service_url' => $this->baseUrl
            ]);

            $eventPayload = [
                'user_id' => $userId,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'recorded_at' => now()->toISOString(),
                'timestamp' => now()->toISOString()
            ];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/tracking/events/communication', $eventPayload);

            if ($response->successful()) {
                Log::info('Communication event recorded successfully', [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId,
                    'event_type' => $eventType
                ]);

                return $response->json();
            }

            Log::warning('Failed to record communication event', [
                'service' => 'fitnease-comms',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Tracking service communication error', [
                'service' => 'fitnease-comms',
                'error' => $e->getMessage(),
                'event_payload' => $eventPayload ?? [],
                'tracking_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }
}