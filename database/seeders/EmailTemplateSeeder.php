<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Email Verification Template
        EmailTemplate::create([
            'template_name' => 'email_verification',
            'template_type' => 'verification',
            'subject_template' => 'Verify Your {{app_name}} Account',
            'html_template' => '<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email - {{app_name}}</title>
    <style>
        .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
        .button { background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .header { text-align: center; padding: 20px; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{app_name}}!</h1>
        </div>

        <div style="padding: 20px;">
            <p>Hi {{user_name}},</p>

            <p>Thank you for joining {{app_name}}! To complete your registration and start your fitness journey, please verify your email address by clicking the button below:</p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{verification_url}}" class="button">Verify Email Address</a>
            </div>

            <p>If the button doesn\'t work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #666;">{{verification_url}}</p>

            <p>This verification link will expire in 24 hours for security reasons.</p>

            <p>If you didn\'t create a {{app_name}} account, please ignore this email.</p>

            <p>Best regards,<br>The {{app_name}} Team</p>
        </div>
    </div>
</body>
</html>',
            'text_template' => 'Hi {{user_name}},

Thank you for joining {{app_name}}! To complete your registration, please verify your email address by visiting:

{{verification_url}}

This link will expire in 24 hours for security reasons.

If you didn\'t create a {{app_name}} account, please ignore this email.

Best regards,
The {{app_name}} Team',
            'variables' => ["user_name", "verification_url", "app_name"],
            'is_active' => true
        ]);

        // Welcome Email Template
        EmailTemplate::create([
            'template_name' => 'welcome_email',
            'template_type' => 'welcome',
            'subject_template' => 'Welcome to {{app_name}} - Let\'s Get Started!',
            'html_template' => '<!DOCTYPE html>
<html>
<head>
    <title>Welcome to {{app_name}}</title>
    <style>
        .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
        .button { background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .header { text-align: center; padding: 20px; background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{app_name}}!</h1>
        </div>

        <div style="padding: 20px;">
            <p>Hi {{user_name}},</p>

            <p>Your email has been successfully verified! Welcome to {{app_name}} - your personalized fitness companion.</p>

            <h3>Here\'s what you can do next:</h3>
            <ul>
                <li>Complete your fitness assessment for personalized recommendations</li>
                <li>Set up your workout preferences</li>
                <li>Start your first AI-guided workout</li>
                <li>Join fitness groups and connect with others</li>
            </ul>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{app_url}}" class="button">Start Your Fitness Journey</a>
            </div>

            <p>Need help getting started? Our AI fitness instructor is ready to guide you through your journey.</p>

            <p>If you have any questions, feel free to reach out to us at {{support_email}}.</p>

            <p>Best regards,<br>The {{app_name}} Team</p>
        </div>
    </div>
</body>
</html>',
            'text_template' => 'Hi {{user_name}},

Your email has been successfully verified! Welcome to {{app_name}} - your personalized fitness companion.

Here\'s what you can do next:
- Complete your fitness assessment for personalized recommendations
- Set up your workout preferences
- Start your first AI-guided workout
- Join fitness groups and connect with others

Get started at: {{app_url}}

Need help? Contact us at {{support_email}}

Best regards,
The {{app_name}} Team',
            'variables' => ["user_name", "app_url", "support_email", "app_name"],
            'is_active' => true
        ]);

        // Password Reset Template
        EmailTemplate::create([
            'template_name' => 'password_reset',
            'template_type' => 'password_reset',
            'subject_template' => 'Reset Your {{app_name}} Password',
            'html_template' => '<!DOCTYPE html>
<html>
<head>
    <title>Reset Your Password - {{app_name}}</title>
    <style>
        .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
        .button { background-color: #ff6b6b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .header { text-align: center; padding: 20px; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>

        <div style="padding: 20px;">
            <p>Hi {{user_name}},</p>

            <p>We received a request to reset your password for your {{app_name}} account.</p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{reset_url}}" class="button">Reset Password</a>
            </div>

            <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #666;">{{reset_url}}</p>

            <p>This password reset link will expire in 1 hour for security reasons.</p>

            <p>If you didn\'t request a password reset, please ignore this email. Your password will remain unchanged.</p>

            <p>Best regards,<br>The {{app_name}} Team</p>
        </div>
    </div>
</body>
</html>',
            'text_template' => 'Hi {{user_name}},

We received a request to reset your password for your {{app_name}} account.

Reset your password here: {{reset_url}}

This link will expire in 1 hour for security reasons.

If you didn\'t request a password reset, please ignore this email.

Best regards,
The {{app_name}} Team',
            'variables' => ["user_name", "reset_url", "app_name"],
            'is_active' => true
        ]);

        // Notification Email Template
        EmailTemplate::create([
            'template_name' => 'notification_email',
            'template_type' => 'notification',
            'subject_template' => '{{notification_title}} - {{app_name}}',
            'html_template' => '<!DOCTYPE html>
<html>
<head>
    <title>{{notification_title}} - {{app_name}}</title>
    <style>
        .container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
        .button { background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .header { text-align: center; padding: 20px; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{notification_title}}</h1>
        </div>

        <div style="padding: 20px;">
            <p>Hi {{user_name}},</p>

            <p>{{notification_message}}</p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{app_url}}" class="button">View in App</a>
            </div>

            <p>Stay on track with your fitness goals!</p>

            <p>Best regards,<br>The {{app_name}} Team</p>
        </div>
    </div>
</body>
</html>',
            'text_template' => 'Hi {{user_name}},

{{notification_title}}

{{notification_message}}

View in app: {{app_url}}

Stay on track with your fitness goals!

Best regards,
The {{app_name}} Team',
            'variables' => ["user_name", "notification_title", "notification_message", "app_url", "app_name"],
            'is_active' => true
        ]);
    }
}
