<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function startChat(Request $request)
    {
        $user = $request->user();

        if (!$user->email_verified_at) {
            return response()->json([
                'error' => 'Email verification required for AI chat access',
                'requires_verification' => true
            ], 403);
        }

        try {
            $userData = $this->getUserProfile($request->bearerToken(), $user->user_id);

            $session = ChatSession::create([
                'user_id' => $user->user_id,
                'session_type' => 'ai_instructor',
                'session_title' => 'AI Fitness Assistant',
                'context_data' => [
                    'fitness_level' => $userData['fitness_level'] ?? 'beginner',
                    'fitness_goals' => $userData['fitness_goals'] ?? [],
                    'experience_years' => $userData['workout_experience_years'] ?? 0
                ]
            ]);

            return response()->json([
                'session_id' => $session->chat_session_id,
                'message' => 'AI chat session started. How can I help with your fitness journey?'
            ]);

        } catch (Exception $e) {
            Log::error('Failed to start AI chat', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to start chat session'], 500);
        }
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer',
            'message' => 'required|string'
        ]);

        $user = $request->user();
        $session = ChatSession::where('chat_session_id', $request->session_id)
            ->where('user_id', $user->user_id)
            ->where('is_active', true)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Chat session not found'], 404);
        }

        try {
            ChatMessage::create([
                'chat_session_id' => $session->chat_session_id,
                'sender_type' => 'user',
                'message_content' => $request->message
            ]);

            $aiResponse = $this->generateAIResponse($session, $request->message);

            ChatMessage::create([
                'chat_session_id' => $session->chat_session_id,
                'sender_type' => 'ai',
                'message_content' => $aiResponse
            ]);

            return response()->json([
                'ai_response' => $aiResponse,
                'timestamp' => now()
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send chat message', [
                'session_id' => $request->session_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    public function getUserSessions(Request $request, $userId)
    {
        $user = $request->user();

        if ($user->user_id !== (int) $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sessions = ChatSession::where('user_id', $userId)
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json($sessions);
    }

    public function getSessionMessages(Request $request, $sessionId)
    {
        $user = $request->user();
        $session = ChatSession::where('chat_session_id', $sessionId)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $messages = ChatMessage::where('chat_session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'session' => $session,
            'messages' => $messages
        ]);
    }

    public function endSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer'
        ]);

        $user = $request->user();
        $session = ChatSession::where('chat_session_id', $request->session_id)
            ->where('user_id', $user->user_id)
            ->where('is_active', true)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Active session not found'], 404);
        }

        $session->update([
            'is_active' => false,
            'ended_at' => now()
        ]);

        return response()->json(['message' => 'Chat session ended successfully']);
    }

    public function advancedChat(Request $request)
    {
        // This method is for verified email users with advanced features
        $user = $request->user();

        try {
            $userData = $this->getUserProfile($request->bearerToken(), $user->user_id);

            $session = ChatSession::create([
                'user_id' => $user->user_id,
                'session_type' => 'ai_instructor',
                'session_title' => 'Advanced AI Fitness Assistant',
                'context_data' => [
                    'fitness_level' => $userData['fitness_level'] ?? 'beginner',
                    'fitness_goals' => $userData['fitness_goals'] ?? [],
                    'experience_years' => $userData['workout_experience_years'] ?? 0,
                    'advanced_features' => true,
                    'verified_email' => true
                ]
            ]);

            return response()->json([
                'session_id' => $session->chat_session_id,
                'message' => 'Advanced AI chat session started with premium features. How can I help optimize your fitness journey?',
                'features' => ['personalized_nutrition', 'advanced_analytics', 'premium_workouts']
            ]);

        } catch (Exception $e) {
            Log::error('Failed to start advanced AI chat', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to start advanced chat session'], 500);
        }
    }

    private function getUserProfile($token, $userId)
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

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Failed to fetch user profile');
            }

            return json_decode($response->getBody(), true);

        } catch (Exception $e) {
            Log::error('Failed to fetch user profile', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'fitness_level' => 'beginner',
                'fitness_goals' => [],
                'workout_experience_years' => 0
            ];
        }
    }

    private function generateAIResponse($session, $userMessage)
    {
        $contextData = $session->context_data;
        $fitnessLevel = $contextData['fitness_level'] ?? 'beginner';
        $goals = $contextData['fitness_goals'] ?? [];

        $responses = [
            'beginner' => [
                'That\'s a great question! As a beginner, I recommend starting with basic bodyweight exercises. Would you like me to suggest a simple routine?',
                'For beginners, consistency is key. Start with 15-20 minute sessions, 3 times per week. What specific area would you like to focus on?',
                'I understand you\'re starting your fitness journey. Let\'s take it step by step. What\'s your main fitness goal right now?'
            ],
            'intermediate' => [
                'Great question! Since you have some experience, we can explore more challenging exercises. What equipment do you have access to?',
                'For your intermediate level, I suggest progressive overload. Are you looking to build strength, endurance, or both?',
                'That\'s an excellent point. Let\'s customize your workout based on your experience. What\'s been working well for you so far?'
            ],
            'advanced' => [
                'Excellent question! With your advanced level, we can focus on specialized techniques. Are you preparing for any specific goals?',
                'Given your expertise, let\'s dive into advanced programming. What\'s your current training split?',
                'That\'s a sophisticated approach. For advanced training, periodization becomes crucial. What\'s your target timeframe?'
            ]
        ];

        $levelResponses = $responses[$fitnessLevel] ?? $responses['beginner'];
        return $levelResponses[array_rand($levelResponses)];
    }
}