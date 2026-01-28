<?php

namespace App\Services;

use App\Models\DeviceToken;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
    }

    /**
     * Send a push notification to a specific user
     *
     * @param int $userId The user to send the notification to
     * @param string $title Notification title
     * @param string $body Notification body/message
     * @param array $data Additional data to include (for navigation, etc.)
     * @return array Results of the push operation
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $tokens = DeviceToken::getActiveTokensForUser($userId);

        if (empty($tokens)) {
            Log::info('No active push tokens for user', ['user_id' => $userId]);
            return ['success' => false, 'reason' => 'no_tokens'];
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a push notification to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $body Notification body/message
     * @param array $data Additional data to include
     * @return array Results of the push operation
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $allTokens = [];

        foreach ($userIds as $userId) {
            $tokens = DeviceToken::getActiveTokensForUser($userId);
            $allTokens = array_merge($allTokens, $tokens);
        }

        if (empty($allTokens)) {
            Log::info('No active push tokens for users', ['user_ids' => $userIds]);
            return ['success' => false, 'reason' => 'no_tokens'];
        }

        return $this->sendToTokens($allTokens, $title, $body, $data);
    }

    /**
     * Send push notifications to specific tokens
     *
     * @param array $tokens Array of Expo push tokens
     * @param string $title Notification title
     * @param string $body Notification body/message
     * @param array $data Additional data to include
     * @return array Results of the push operation
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return ['success' => false, 'reason' => 'no_tokens'];
        }

        // Build the messages array
        $messages = [];
        foreach ($tokens as $token) {
            // Validate token format (Expo push tokens start with ExponentPushToken)
            if (!$this->isValidExpoPushToken($token)) {
                Log::warning('Invalid Expo push token format', ['token' => substr($token, 0, 30)]);
                continue;
            }

            $messages[] = [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'default',
            ];
        }

        if (empty($messages)) {
            return ['success' => false, 'reason' => 'no_valid_tokens'];
        }

        try {
            $response = $this->client->post(self::EXPO_PUSH_URL, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Content-Type' => 'application/json',
                ],
                'json' => $messages,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Push notifications sent', [
                'token_count' => count($messages),
                'response' => $result,
            ]);

            // Process response to handle any errors
            $this->processResponse($result, $tokens);

            return [
                'success' => true,
                'sent' => count($messages),
                'response' => $result,
            ];

        } catch (Exception $e) {
            Log::error('Failed to send push notifications', [
                'error' => $e->getMessage(),
                'token_count' => count($messages),
            ]);

            return [
                'success' => false,
                'reason' => 'api_error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate Expo push token format
     */
    private function isValidExpoPushToken(string $token): bool
    {
        // Expo push tokens start with "ExponentPushToken[" or "ExpoPushToken["
        return str_starts_with($token, 'ExponentPushToken[') ||
               str_starts_with($token, 'ExpoPushToken[');
    }

    /**
     * Process the Expo API response and handle any errors
     * Deactivate tokens that are no longer valid
     */
    private function processResponse(array $result, array $tokens): void
    {
        if (!isset($result['data']) || !is_array($result['data'])) {
            return;
        }

        foreach ($result['data'] as $index => $ticketResult) {
            // Check for errors
            if (isset($ticketResult['status']) && $ticketResult['status'] === 'error') {
                $token = $tokens[$index] ?? null;

                if ($token) {
                    $errorDetails = $ticketResult['details'] ?? [];
                    $errorCode = $errorDetails['error'] ?? 'unknown';

                    // If the token is invalid, deactivate it
                    if (in_array($errorCode, ['DeviceNotRegistered', 'InvalidCredentials'])) {
                        Log::warning('Deactivating invalid push token', [
                            'token' => substr($token, 0, 30) . '...',
                            'error' => $errorCode,
                        ]);

                        DeviceToken::where('expo_push_token', $token)
                            ->update(['is_active' => false]);
                    }
                }
            }
        }
    }

    /**
     * Send a silent push notification (data only, no visible alert)
     */
    public function sendSilentToUser(int $userId, array $data): array
    {
        $tokens = DeviceToken::getActiveTokensForUser($userId);

        if (empty($tokens)) {
            return ['success' => false, 'reason' => 'no_tokens'];
        }

        $messages = [];
        foreach ($tokens as $token) {
            if (!$this->isValidExpoPushToken($token)) {
                continue;
            }

            $messages[] = [
                'to' => $token,
                'data' => $data,
                'priority' => 'normal',
                '_contentAvailable' => true, // iOS background fetch
            ];
        }

        if (empty($messages)) {
            return ['success' => false, 'reason' => 'no_valid_tokens'];
        }

        try {
            $response = $this->client->post(self::EXPO_PUSH_URL, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $messages,
            ]);

            return [
                'success' => true,
                'sent' => count($messages),
            ];

        } catch (Exception $e) {
            Log::error('Failed to send silent push', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => 'api_error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
