<?php

namespace App\Notifications;

use App\Models\Team;
use App\Services\DiscordService;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Team $team
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database', 'discord'];
    }

    public function toDiscord($notifiable)
    {
        try {
            if (!$notifiable->discord_id) {
                Log::warning('Cannot send Discord notification - User has no discord_id', [
                    'user_id' => $notifiable->id,
                    'email' => $notifiable->email,
                    'team_id' => $this->team->id,
                    'team_name' => $this->team->name
                ]);
                return null;
            }

            Log::info('Preparing Discord team invitation message', [
                'user_id' => $notifiable->id,
                'discord_id' => $notifiable->discord_id,
                'team_id' => $this->team->id,
                'team_name' => $this->team->name
            ]);

            $inviteUrl = route('teams.invitations');
            $ownerName = $this->team->owner ? $this->team->owner->name : 'Unknown';
            $ownerDiscordId = $this->team->owner ? $this->team->owner->discord_id : null;

            // Create an embedded message for better formatting
            $embed = [
                'title' => '🎮 New Team Invitation!',
                'description' => "You have been invited to join a team!\n\n".
                               "Please respond to this invitation using the link below.",
                'color' => 0x5865F2, // Discord Blurple
                'fields' => [
                    [
                        'name' => '🏷️ Team Name',
                        'value' => "**{$this->team->name}**",
                        'inline' => true
                    ],
                    [
                        'name' => '👑 Team Owner',
                        'value' => $ownerDiscordId ? "<@{$ownerDiscordId}>" : $ownerName,
                        'inline' => true
                    ],
                    [
                        'name' => '🔗 Response Link',
                        'value' => $inviteUrl,
                        'inline' => false
                    ]
                ],
                'timestamp' => now()->toIso8601String()
            ];

            // Send to both DM and team channel if it exists
            if ($this->team->discord_team_id) {
                app(DiscordService::class)->sendToChannel(
                    $this->team->discord_team_id,
                    '',
                    [
                        'title' => '🎮 New Team Member Invited',
                        'description' => "Hey <@{$notifiable->discord_id}>, you've been invited to join **{$this->team->name}**!\n\n" .
                                       "Please check your DMs for the invitation link.",
                        'color' => 0x5865F2
                    ]
                );
            }

            Log::info('Discord message prepared', [
                'has_team_channel' => (bool)$this->team->discord_team_id,
                'user_discord_id' => $notifiable->discord_id
            ]);

            return $embed;
        } catch (\Exception $e) {
            Log::error('Error preparing Discord message', [
                'error' => $e->getMessage(),
                'user_id' => $notifiable->id ?? 'unknown',
                'team_id' => $this->team->id
            ]);
            return null;
        }
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Team Invitation')
            ->line('You have been invited to join ' . $this->team->name)
            ->action('View Invitation', route('teams.invitations'))
            ->line('Please respond to this invitation to join the team.');
    }

    public function toArray($notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'message' => 'You have been invited to join ' . $this->team->name,
            'type' => 'team_invitation'
        ];
    }
}
