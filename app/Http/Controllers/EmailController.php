<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Notification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function sendVerification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'email' => 'required|email',
            'token' => 'required|string',
            'verification_code' => 'required|string|size:6',
            'user_name' => 'required|string',
            'verification_url' => 'required|url'
        ]);

        try {
            $template = EmailTemplate::where('template_type', 'verification')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                throw new Exception('Email verification template not found');
            }

            $emailData = $template->replaceVariables([
                'user_name' => $request->user_name,
                'verification_code' => $request->verification_code,
                'verification_url' => $request->verification_url,
                'app_name' => 'FitnEase'
            ]);

            Mail::send([], [], function ($message) use ($request, $emailData) {
                $message->to($request->email)
                    ->subject($emailData['subject'])
                    ->html($emailData['html']);
            });

            Notification::create([
                'user_id' => $request->user_id,
                'notification_type' => 'email_verification',
                'title' => 'Email Verification Required',
                'message' => 'Please verify your email address to activate your account',
                'is_sent' => true,
                'email_sent' => true,
                'sent_at' => now()
            ]);

            return response()->json(['message' => 'Verification email sent successfully']);

        } catch (Exception $e) {
            Log::error('Email verification failed', [
                'user_id' => $request->user_id,
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to send verification email'], 500);
        }
    }

    public function sendWelcome(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'email' => 'required|email',
            'user_name' => 'required|string'
        ]);

        try {
            $template = EmailTemplate::where('template_type', 'welcome')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                throw new Exception('Welcome email template not found');
            }

            $emailData = $template->replaceVariables([
                'user_name' => $request->user_name,
                'app_url' => env('FRONTEND_URL'),
                'support_email' => env('SUPPORT_EMAIL', 'support@fitnease.com'),
                'app_name' => 'FitnEase'
            ]);

            Mail::send([], [], function ($message) use ($request, $emailData) {
                $message->to($request->email)
                    ->subject($emailData['subject'])
                    ->html($emailData['html']);
            });

            Notification::create([
                'user_id' => $request->user_id,
                'notification_type' => 'system',
                'title' => 'Welcome to FitnEase!',
                'message' => 'Your email has been verified. Welcome to your fitness journey!',
                'is_sent' => true,
                'email_sent' => true,
                'sent_at' => now()
            ]);

            return response()->json(['message' => 'Welcome email sent successfully']);

        } catch (Exception $e) {
            Log::error('Welcome email failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to send welcome email'], 500);
        }
    }

    public function getTemplate($type)
    {
        $template = EmailTemplate::where('template_type', $type)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        return response()->json($template);
    }

    public function updateTemplate(Request $request, $id)
    {
        $request->validate([
            'subject_template' => 'sometimes|string',
            'html_template' => 'sometimes|string',
            'text_template' => 'sometimes|string',
            'variables' => 'sometimes|array',
            'is_active' => 'sometimes|boolean'
        ]);

        $template = EmailTemplate::findOrFail($id);
        $template->update($request->all());

        return response()->json(['message' => 'Template updated successfully', 'template' => $template]);
    }

    public function testEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'template_type' => 'required|string'
        ]);

        try {
            $template = EmailTemplate::where('template_type', $request->template_type)
                ->where('is_active', true)
                ->first();

            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            $emailData = $template->replaceVariables([
                'user_name' => 'Test User',
                'app_name' => 'FitnEase',
                'verification_url' => 'https://example.com/verify',
                'app_url' => env('FRONTEND_URL'),
                'support_email' => env('SUPPORT_EMAIL', 'support@fitnease.com')
            ]);

            Mail::send([], [], function ($message) use ($request, $emailData) {
                $message->to($request->email)
                    ->subject('[TEST] ' . $emailData['subject'])
                    ->html($emailData['html']);
            });

            return response()->json(['message' => 'Test email sent successfully']);

        } catch (Exception $e) {
            Log::error('Test email failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to send test email'], 500);
        }
    }
}
