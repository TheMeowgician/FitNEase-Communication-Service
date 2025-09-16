<?php

namespace App\Http\Controllers;

use App\Models\MusicIntegration;
use App\Models\MusicProvider;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    public function connect(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (!$token->can('social-features')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $request->validate([
            'provider' => 'required|string'
        ]);

        $provider = MusicProvider::where('provider_name', $request->provider)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            return response()->json(['error' => 'Provider not supported'], 400);
        }

        try {
            $oauthUrl = $provider->oauth_authorize_url . '?' . http_build_query([
                'client_id' => $provider->client_id,
                'response_type' => 'code',
                'redirect_uri' => env('APP_URL') . '/music/callback',
                'scope' => implode(' ', $provider->supported_scopes),
                'state' => base64_encode(json_encode([
                    'user_id' => $user->user_id,
                    'provider' => $request->provider
                ]))
            ]);

            return response()->json(['oauth_url' => $oauthUrl]);

        } catch (Exception $e) {
            Log::error('Failed to generate OAuth URL', [
                'user_id' => $user->user_id,
                'provider' => $request->provider,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to initiate connection'], 500);
        }
    }

    public function callback(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string'
        ]);

        try {
            $state = json_decode(base64_decode($request->state), true);
            $userId = $state['user_id'];
            $providerName = $state['provider'];

            $provider = MusicProvider::where('provider_name', $providerName)->first();

            if (!$provider) {
                return response()->json(['error' => 'Invalid provider'], 400);
            }

            $tokens = $this->exchangeCodeForTokens($provider, $request->code);

            MusicIntegration::updateOrCreate(
                ['user_id' => $userId, 'service_name' => $providerName],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'token_expires_at' => isset($tokens['expires_in'])
                        ? now()->addSeconds($tokens['expires_in'])
                        : null,
                    'is_active' => true,
                    'last_sync_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Music service connected successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Music integration callback failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to complete connection'], 500);
        }
    }

    public function getProviders()
    {
        $providers = MusicProvider::where('is_active', true)
            ->select('provider_id', 'provider_name', 'display_name', 'supported_scopes')
            ->get();

        return response()->json($providers);
    }

    public function updateIntegration(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'service_name' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'preferences' => 'sometimes|array'
        ]);

        try {
            $integration = MusicIntegration::where('user_id', $userId)
                ->where('service_name', $request->service_name)
                ->first();

            if (!$integration) {
                return response()->json(['error' => 'Integration not found'], 404);
            }

            $updateData = [];
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            if ($request->has('preferences')) {
                $updateData['user_profile_data'] = array_merge(
                    $integration->user_profile_data ?? [],
                    ['preferences' => $request->preferences]
                );
            }

            $integration->update($updateData);

            return response()->json([
                'message' => 'Integration updated successfully',
                'integration' => $integration
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update music integration', [
                'user_id' => $userId,
                'service_name' => $request->service_name,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to update integration'], 500);
        }
    }

    public function disconnect(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'service_name' => 'required|string'
        ]);

        try {
            $integration = MusicIntegration::where('user_id', $userId)
                ->where('service_name', $request->service_name)
                ->first();

            if (!$integration) {
                return response()->json(['error' => 'Integration not found'], 404);
            }

            $integration->update([
                'is_active' => false,
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null
            ]);

            return response()->json(['message' => 'Music service disconnected successfully']);

        } catch (Exception $e) {
            Log::error('Failed to disconnect music service', [
                'user_id' => $userId,
                'service_name' => $request->service_name,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to disconnect service'], 500);
        }
    }

    public function getUserIntegrations(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $integrations = MusicIntegration::where('user_id', $userId)
            ->with('provider:provider_id,provider_name,display_name')
            ->get();

        return response()->json($integrations);
    }

    public function premiumConnect(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (!$token->can('premium-features')) {
            return response()->json(['error' => 'Premium subscription required'], 403);
        }

        $request->validate([
            'provider' => 'required|string',
            'premium_features' => 'required|array'
        ]);

        try {
            $result = $this->connect($request);

            if ($result->getStatusCode() === 200) {
                $integration = MusicIntegration::where('user_id', $user->user_id)
                    ->where('service_name', $request->provider)
                    ->first();

                if ($integration) {
                    $integration->update([
                        'user_profile_data' => array_merge(
                            $integration->user_profile_data ?? [],
                            ['premium_features' => $request->premium_features]
                        )
                    ]);
                }
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Premium music connection failed', [
                'user_id' => $user->user_id,
                'provider' => $request->provider,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to setup premium connection'], 500);
        }
    }

    private function exchangeCodeForTokens($provider, $code)
    {
        $client = new Client();

        try {
            $response = $client->post($provider->oauth_token_url, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => env('APP_URL') . '/music/callback',
                    'client_id' => $provider->client_id,
                    'client_secret' => $provider->client_secret
                ],
                'timeout' => 10
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Token exchange failed');
            }

            return json_decode($response->getBody(), true);

        } catch (Exception $e) {
            Log::error('OAuth token exchange failed', [
                'provider' => $provider->provider_name,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to exchange authorization code for tokens');
        }
    }

    public function refreshToken(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'service_name' => 'required|string'
        ]);

        try {
            $integration = MusicIntegration::where('user_id', $userId)
                ->where('service_name', $request->service_name)
                ->where('is_active', true)
                ->first();

            if (!$integration || !$integration->refresh_token) {
                return response()->json(['error' => 'No valid refresh token found'], 404);
            }

            $provider = $integration->provider;
            $client = new Client();

            $response = $client->post($provider->oauth_token_url, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $integration->refresh_token,
                    'client_id' => $provider->client_id,
                    'client_secret' => $provider->client_secret
                ],
                'timeout' => 10
            ]);

            $tokens = json_decode($response->getBody(), true);

            $integration->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $integration->refresh_token,
                'token_expires_at' => isset($tokens['expires_in'])
                    ? now()->addSeconds($tokens['expires_in'])
                    : null,
                'last_sync_at' => now()
            ]);

            return response()->json(['message' => 'Token refreshed successfully']);

        } catch (Exception $e) {
            Log::error('Token refresh failed', [
                'user_id' => $userId,
                'service_name' => $request->service_name,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to refresh token'], 500);
        }
    }
}