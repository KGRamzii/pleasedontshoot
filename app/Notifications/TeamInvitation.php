<?php

namespace App\Notifications;

use App\Models\Team;
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
        return ['mail', 'database'];
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
