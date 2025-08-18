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
        if (!$notifiable->discord_id) {
            Log::info('Skipping Discord notification - no discord_id', [
                'user' => $notifiable->id,
                'email' => $notifiable->email
            ]);
            return null;
        }
        
        Log::info('Preparing Discord invitation message', [
            'user' => $notifiable->id,
            'discord_id' => $notifiable->discord_id,
            'team' => $this->team->name
        ]);
        
        $inviteUrl = route('teams.invitations');
        return "🎮 You have been invited to join the team: {$this->team->name}!\n\n".
               "Click here to respond to the invitation: {$inviteUrl}";
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
