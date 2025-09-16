<?php

use App\Http\Controllers\EmailController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MusicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->attributes->get('user');
})->middleware('auth.api');

// Email Verification System (Public routes - no authentication required)
Route::prefix('comms')->group(function () {
    Route::post('/send-verification', [EmailController::class, 'sendVerification']);
    Route::post('/send-welcome-email', [EmailController::class, 'sendWelcome']);
});

// API authenticated routes
Route::prefix('comms')->middleware('auth.api')->group(function () {
    // AI Chat System (requires verified email)
    Route::post('/ai-chat', [ChatController::class, 'startChat']);
    Route::post('/send-message', [ChatController::class, 'sendMessage']);
    Route::get('/chat-sessions/{userId}', [ChatController::class, 'getUserSessions']);
    Route::get('/chat-messages/{sessionId}', [ChatController::class, 'getSessionMessages']);
    Route::post('/end-chat-session', [ChatController::class, 'endSession']);

    // Notification Management
    Route::post('/notification', [NotificationController::class, 'sendNotification']);
    Route::get('/notifications/{userId}', [NotificationController::class, 'getUserNotifications']);
    Route::put('/notification/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/achievement-notification', [NotificationController::class, 'achievementNotification']);
    Route::get('/notification-settings/{userId}', [NotificationController::class, 'getNotificationSettings']);
    Route::put('/notification-settings/{userId}', [NotificationController::class, 'updateNotificationSettings']);

    // Music Integration
    Route::post('/music-integration', [MusicController::class, 'connect']);
    Route::get('/music-providers', [MusicController::class, 'getProviders']);
    Route::put('/music-integration/{userId}', [MusicController::class, 'updateIntegration']);
    Route::delete('/music-integration/{userId}', [MusicController::class, 'disconnect']);
    Route::get('/music-integrations/{userId}', [MusicController::class, 'getUserIntegrations']);
    Route::post('/music-integration/refresh-token/{userId}', [MusicController::class, 'refreshToken']);
});

// Email verification required routes
Route::prefix('comms')->middleware(['auth.api', 'verified.email'])->group(function () {
    Route::post('/ai-chat/advanced', [ChatController::class, 'advancedChat']);
    Route::post('/music-integration/premium', [MusicController::class, 'premiumConnect']);
});

// Admin only routes
Route::prefix('comms')->middleware(['auth.api'])->group(function () {
    Route::post('/test-email', [EmailController::class, 'testEmail']);
    Route::get('/email-templates/{type}', [EmailController::class, 'getTemplate']);
    Route::put('/email-templates/{id}', [EmailController::class, 'updateTemplate']);
});

// Public music callback (no auth required for OAuth callback)
Route::get('/music/callback', [MusicController::class, 'callback']);
