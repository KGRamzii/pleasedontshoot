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
            if (!method_exists($notification, 'toDiscord')) {
                Log::info('Notification does not support Discord channel');
                return;
            }

            if (!$notifiable->discord_id) {
                Log::warning('User has no Discord ID', ['user_id' => $notifiable->id]);
                return;
            }

            $message = $notification->toDiscord($notifiable);
            
            if (empty($message)) {
                Log::info('No message to send to Discord');
                return;
            }

            Log::info('Sending message through Discord channel', [
                'user_id' => $notifiable->id,
                'discord_id' => $notifiable->discord_id
            ]);

            return $this->discord->sendDirectMessage(
                $notifiable->discord_id,
                $message
            );
        } catch (\Exception $e) {
            Log::error('Failed to send Discord notification', [
                'error' => $e->getMessage(),
                'user_id' => $notifiable->id ?? 'unknown'
            ]);
            throw $e;
        }
    }
}
