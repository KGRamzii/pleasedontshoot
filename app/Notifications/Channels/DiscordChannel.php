<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Services\DiscordService;
use Illuminate\Support\Facades\Log;

class DiscordChannel
{
    protected $discord;

    public function __construct(DiscordService $discord)
    {
        $this->discord = $discord;
    }

    public function send($notifiable, Notification $notification)
    {
        try {
            // Check if notification supports Discord
            if (!method_exists($notification, 'toDiscord')) {
                Log::info('Notification does not support Discord channel', [
                    'notification_class' => get_class($notification)
                ]);
                return;
            }

            // Validate Discord ID
            if (!$notifiable->discord_id) {
                Log::warning('User has no Discord ID', [
                    'user_id' => $notifiable->id,
                    'notification_class' => get_class($notification)
                ]);
                return;
            }

            // Get Discord message
            $message = $notification->toDiscord($notifiable);
            
            if (empty($message)) {
                Log::info('No message to send to Discord', [
                    'user_id' => $notifiable->id,
                    'notification_class' => get_class($notification)
                ]);
                return;
            }

            Log::info('Attempting to send Discord message', [
                'user_id' => $notifiable->id,
                'discord_id' => $notifiable->discord_id,
                'notification_class' => get_class($notification),
                'is_embed' => is_array($message)
            ]);

            // Send the message
            $result = is_array($message) 
                ? $this->discord->sendDirectMessage(
                    $notifiable->discord_id,
                    '',  // No content message
                    $message  // The embed
                )
                : $this->discord->sendDirectMessage(
                    $notifiable->discord_id,
                    $message
                );

            if ($result === false) {
                Log::error('Failed to send Discord message', [
                    'user_id' => $notifiable->id,
                    'discord_id' => $notifiable->discord_id,
                    'notification_class' => get_class($notification)
                ]);
            } else {
                Log::info('Successfully sent Discord message', [
                    'user_id' => $notifiable->id,
                    'discord_id' => $notifiable->discord_id,
                    'notification_class' => get_class($notification),
                    'result' => $result
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send Discord notification', [
                'error' => $e->getMessage(),
                'user_id' => $notifiable->id ?? 'unknown'
            ]);
            throw $e;
        }
    }
}
