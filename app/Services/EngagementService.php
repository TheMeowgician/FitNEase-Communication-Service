<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EngagementService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('ENGAGEMENT_SERVICE_URL', 'http://fitnease-engagement');
    }

    public function trackEmailOpened($userId, $emailType, $token)
    {
        try {
            Log::info('Tracking email opened in engagement service', [
                'service' => 'fitnease-comms',
                'user_id' => $userId,
                'email_type' => $emailType,
                'engagement_service_url' => $this->baseUrl
            ]);

            $trackingData = [
                'user_id' => $userId,
                'event_type' => 'email_opened',
                'email_type' => $emailType,
                'opened_at' => now()->toISOString(),
                'timestamp' => now()->toISOString()
            ];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/email-tracking', $trackingData);

            if ($response->successful()) {
                Log::info('Email tracking sent successfully', [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId,
                    'email_type' => $emailType
                ]);

                return $response->json();
            }

            Log::warning('Failed to track email opened', [
                'service' => 'fitnease-comms',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-comms',
                'error' => $e->getMessage(),
                'tracking_data' => $trackingData ?? [],
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function trackNotificationSent($userId, $notificationType, $token)
    {
        try {
            Log::info('Tracking notification sent in engagement service', [
                'service' => 'fitnease-comms',
                'user_id' => $userId,
                'notification_type' => $notificationType,
                'engagement_service_url' => $this->baseUrl
            ]);

            $trackingData = [
                'user_id' => $userId,
                'event_type' => 'notification_sent',
                'notification_type' => $notificationType,
                'sent_at' => now()->toISOString(),
                'timestamp' => now()->toISOString()
            ];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/notification-tracking', $trackingData);

            if ($response->successful()) {
                Log::info('Notification tracking sent successfully', [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId,
                    'notification_type' => $notificationType
                ]);

                return $response->json();
            }

            Log::warning('Failed to track notification sent', [
                'service' => 'fitnease-comms',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-comms',
                'error' => $e->getMessage(),
                'tracking_data' => $trackingData ?? [],
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function trackUserInteraction($userId, $interactionType, $interactionData, $token)
    {
        try {
            Log::info('Tracking user interaction in engagement service', [
                'service' => 'fitnease-comms',
                'user_id' => $userId,
                'interaction_type' => $interactionType,
                'engagement_service_url' => $this->baseUrl
            ]);

            $trackingData = [
                'user_id' => $userId,
                'event_type' => 'user_interaction',
                'interaction_type' => $interactionType,
                'interaction_data' => $interactionData,
                'interaction_time' => now()->toISOString(),
                'timestamp' => now()->toISOString()
            ];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/user-interaction', $trackingData);

            if ($response->successful()) {
                Log::info('User interaction tracked successfully', [
                    'service' => 'fitnease-comms',
                    'user_id' => $userId,
                    'interaction_type' => $interactionType
                ]);

                return $response->json();
            }

            Log::warning('Failed to track user interaction', [
                'service' => 'fitnease-comms',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-comms',
                'error' => $e->getMessage(),
                'tracking_data' => $trackingData ?? [],
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }
}