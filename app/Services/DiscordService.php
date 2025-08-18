<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    protected $base;
    protected $colors = [
        'success' => 5025616,    // Green
        'error' => 15548997,     // Red
        'info' => 5814783,       // Blue
        'warning' => 16763904,   // Orange
    ];

    public function __construct()
    {
        $this->base = env('DISCORD_API_BASE', 'https://pdsapi.fly.dev');

        if (!$this->base) {
            throw new \Exception('DISCORD_API_BASE environment variable is not set');
        }
    }

    /**
     * Create a consistently styled embed for Discord messages
     */
    protected function createEmbed($title, $fields = [], $color = 'info', $thumbnail = null)
    {
        $embed = [
            'title' => $title,
            'color' => is_string($color) ? ($this->colors[$color] ?? $this->colors['info']) : $color,
            'fields' => array_map(function ($field) {
                return [
                    'name' => $field['name'],
                    'value' => $field['value'],
                    'inline' => $field['inline'] ?? true,
                ];
            }, $fields),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($thumbnail) {
            $embed['thumbnail'] = ['url' => $thumbnail];
        }

        return $embed;
    }

    /**
     * Send a message to a Discord channel
     */
    public function sendToChannel($channelId, $message = '', $embed = null)
    {
        try {
            $payload = [
                'channel_id' => $channelId
            ];

            // Only add message if it's not empty
            if (!empty($message)) {
                $payload['message'] = $message;
            }

            if ($embed) {
                // Match the Discord bot's expected format
                $payload['embed_title'] = $embed['title'] ?? null;
                $payload['embed_description'] = $embed['description'] ?? null;
                $payload['embed_color'] = $embed['color'] ?? 0x00ff00;
                
                // Add thumbnail URL if present
                if (isset($embed['thumbnail']['url'])) {
                    $payload['embed_thumbnail'] = $embed['thumbnail']['url'];
                }
            }

            Log::info('Attempting to send Discord channel message', [
                'channel_id' => $channelId,
                'message_length' => strlen($message ?? ''),
                'has_embed' => !is_null($embed),
                'embed_title' => $embed['title'] ?? null,
                'has_thumbnail' => isset($embed['thumbnail']['url'])
            ]);
            
            $response = Http::timeout(30)->post("{$this->base}/send-channel-message", $payload);

            if (!$response->successful()) {
                Log::error('Discord channel message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'channel_id' => $channelId,
                    'payload' => $payload
                ]);
                return false;
            }

            Log::info('Discord channel message sent successfully', [
                'channel_id' => $channelId,
                'response_status' => $response->status()
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Discord channel message exception', [
                'error' => $e->getMessage(),
                'channel_id' => $channelId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }

    /**
     * Send a direct message to a user by Discord ID
     */
    public function sendDirectMessage($discordUserId, $message)
    {
        try {
            Log::info('Attempting to send Discord DM', [
                'user_id' => $discordUserId,
                'message' => $message
            ]);

            $response = Http::post("{$this->base}/send-dm", [
                'user_id' => $discordUserId,
                'message' => $message
            ]);

            if (!$response->successful()) {
                Log::error('Discord DM failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $discordUserId
                ]);
                return false;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Discord DM exception', [
                'error' => $e->getMessage(),
                'user_id' => $discordUserId
            ]);
            return false;
        }
    }

    /**
     * Send a challenge notification
     */
    public function sendChallengeNotification($challenge, $type = 'created')
    {
        $challenger = $challenge->challenger;
        $opponent = $challenge->opponent;
        $witness = $challenge->witness;
        $team = $challenge->team;
        $bannedAgentData = $challenge->banned_agent ? json_decode($challenge->banned_agent, true) : null;

        $title = match ($type) {
            'created' => '🎮 New Challenge Created!',
            'accepted' => '✅ Challenge Accepted!',
            'declined' => '❌ Challenge Declined',
            'completed' => '🏆 Challenge Completed!',
            default => '🔄 Challenge Update'
        };

        $description = $team ? "Challenge in **{$team->name}**" : '';

        $color = match ($type) {
            'created' => 0x7289da,  // Discord Blurple
            'accepted' => 0x4CAF50, // Green
            'declined' => 0xf04747, // Red
            'completed' => 0x43b581, // Discord Online Green
            default => 0x7289da     // Discord Blurple
        };

        $fields = [];

        if ($type === 'completed' && isset($challenge->winner) && isset($challenge->loser)) {
            $fields = [
                ['name' => '🏅 Winner', 'value' => "**<@{$challenge->winner->discord_id}>** (Rank: {$challenge->winner->rank})", 'inline' => true],
                ['name' => '💔 Loser', 'value' => "**<@{$challenge->loser->discord_id}>** (Rank: {$challenge->loser->rank})", 'inline' => true],
                ['name' => '👀 Witness', 'value' => "<@{$witness->discord_id}>", 'inline' => true],
                ['name' => '📊 Rank Status', 'value' => $challenge->winner->rank > $challenge->loser->rank ? 'The winner was already ranked higher.' : 'Ranks have been swapped!', 'inline' => false],
            ];
        } else {
            $fields = [
                ['name' => '👊 Challenger', 'value' => "<@{$challenger->discord_id}>", 'inline' => true],
                ['name' => '🎯 Opponent', 'value' => "<@{$opponent->discord_id}>", 'inline' => true],
                ['name' => '👀 Witness', 'value' => "<@{$witness->discord_id}>", 'inline' => true],
            ];
        }

        // Add banned agent if present
        if ($type === 'created' || $type === 'completed') {
            if ($bannedAgentData) {
                $fields[] = [
                    'name' => '🚫 Banned Agent',
                    'value' => "**{$bannedAgentData['name']}**",
                    'inline' => true,
                ];
            } else if ($type === 'created') {
                $fields[] = [
                    'name' => '🚫 Banned Agent',
                    'value' => 'None',
                    'inline' => true,
                ];
            }
        }

        // Build description including fields
        foreach ($fields as $field) {
            $description .= "\n\n**{$field['name']}**\n{$field['value']}";
        }

        if ($type === 'created') {
            $description .= "\n\nChallenge ID: " . $challenge->id . ' • Created at ' . now()->format('M j, Y g:i A');
        }

        $embed = [
            'title' => $title,
            'description' => $description,
            'color' => $color
        ];

        // Add agent thumbnail if banned agent exists
        if ($bannedAgentData && isset($bannedAgentData['icon'])) {
            $embed['thumbnail'] = [
                'url' => $bannedAgentData['icon']
            ];
        }

        return $this->sendToChannel(
            env('DISCORD_ANNOUNCE_CHANNEL_ID'),
            '',
            $embed
        );
    }

    /**
     * Send updated rankings
     */
    public function sendRankingsUpdate($team)
    {
        $users = $team->users()
            ->orderByPivot('rank')
            ->get();

        $rankList = '';
        foreach ($users as $index => $user) {
            $position = $index + 1;
            $crown = $position === 1 ? '👑 ' : '';
            $medal = $position === 2 ? '🥈 ' : ($position === 3 ? '🥉 ' : '');
            $rankList .= "{$crown}{$medal}**Rank {$user->pivot->rank}**: <@{$user->discord_id}>\n";
        }

        $embed = [
            'title' => '📊 Rankings Updated!',
            'description' => "**{$team->name} Rankings**\n\n" . $rankList,
            'color' => $this->colors['info']
        ];

        return $this->sendToChannel(
            env('DISCORD_ANNOUNCE_CHANNEL_ID'),
            '',
            $embed
        );
    }

    /**
     * Test both Discord endpoints
     */
    public function testEndpoints()
    {
        Log::info('Starting Discord endpoint tests...');
        
        $dmResponse = null;
        $channelResponse = null;

        // Test DM endpoint
        try {
            $dmResponse = Http::post("{$this->base}/send-dm", [
                'user_id' => '768838379865374730',
                'message' => '🧪 Test message from Discord bot'
            ]);

            Log::info('DM endpoint test result', [
                'status' => $dmResponse->status(),
                'body' => $dmResponse->body(),
                'success' => $dmResponse->successful()
            ]);
        } catch (\Exception $e) {
            Log::error('DM endpoint test failed', [
                'error' => $e->getMessage()
            ]);
        }

        // Test channel message endpoint
        try {
            $channelResponse = Http::post("{$this->base}/send-channel-message", [
                'channel_id' => env('DISCORD_ANNOUNCE_CHANNEL_ID'),
                'message' => '🧪 Test message from Discord bot'
            ]);

            Log::info('Channel endpoint test result', [
                'status' => $channelResponse->status(),
                'body' => $channelResponse->body(),
                'success' => $channelResponse->successful()
            ]);
        } catch (\Exception $e) {
            Log::error('Channel endpoint test failed', [
                'error' => $e->getMessage()
            ]);
        }

        return [
            'dm_status' => $dmResponse ? $dmResponse->status() : 'failed',
            'channel_status' => $channelResponse ? $channelResponse->status() : 'failed'
        ];
    }
}