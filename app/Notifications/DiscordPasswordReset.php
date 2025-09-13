<?php

namespace App\Notifications;

use App\Services\DiscordService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class DiscordPasswordReset extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['discord'];
    }

    public function toDiscord($notifiable)
    {
        if (!$notifiable->discord_id) {
            Log::warning('Cannot send Discord password reset - User has no discord_id', [
                'user_id' => $notifiable->id,
            ]);
            return null;
        }

        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        // Basic message for fallback and better user experience
        $message = "🔐 Password Reset Request - You have requested a password reset for your account.";

        $embed = [
            'title' => '🔐 Password Reset Request',
            'description' => "You are receiving this message because we received a password reset request for your account.",
            'color' => 0x5865F2, // Discord Blurple
            'fields' => [
                [
                    'name' => '🔗 Reset Link',
                    'value' => $resetUrl,
                    'inline' => false
                ],
                [
                    'name' => '⚠️ Important',
                    'value' => "This password reset link will expire in " . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . " minutes.\n\nIf you did not request a password reset, no further action is required.",
                    'inline' => false
                ]
            ],
            'timestamp' => now()->toIso8601String()
        ];

        try {
            $result = app(DiscordService::class)->sendDirectMessage($notifiable->discord_id, $message, $embed);
            
            if (!$result) {
                Log::error('Failed to send Discord password reset notification', [
                    'user_id' => $notifiable->id,
                    'discord_id' => $notifiable->discord_id
                ]);
                throw new \Exception('Failed to send Discord message');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Exception while sending Discord password reset', [
                'user_id' => $notifiable->id,
                'discord_id' => $notifiable->discord_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
