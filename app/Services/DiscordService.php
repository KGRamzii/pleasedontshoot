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
    public function sendDirectMessage($discordUserId, $message = '', $embed = null)
    {
        try {
            // Ensure we have either a message or an embed
            if (empty($message) && empty($embed)) {
                throw new \InvalidArgumentException('Either message or embed must be provided');
            }

            Log::info('Attempting to send Discord DM', [
                'user_id' => $discordUserId,
                'has_message' => !empty($message),
                'has_embed' => !is_null($embed)
            ]);

            $payload = [
                'user_id' => $discordUserId,
                'message' => $message, // Fallback message
            ];

            // Add embed if present
            if ($embed) {
                $payload['embed_title'] = $embed['title'] ?? null;
                $payload['embed_description'] = $embed['description'] ?? null;
                $payload['embed_color'] = $embed['color'] ?? null;

                // Handle fields if present
                if (!empty($embed['fields'])) {
                    $payload['embed_fields'] = $embed['fields']; // No need to json_encode, Http client will handle it
                }

                // Add timestamp if present
                if (!empty($embed['timestamp'])) {
                    $payload['embed_timestamp'] = $embed['timestamp'];
                }
            }

            $response = Http::post("{$this->base}/send-dm", $payload);

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
            // Log the challenge data for debugging
            Log::info('Discord notification data', [
                'challenge_id' => $challenge->id,
                'winner_name' => $challenge->winner->name ?? 'Unknown',
                'loser_name' => $challenge->loser->name ?? 'Unknown',
                'winner_old_rank' => $challenge->winner_old_rank ?? 'Missing',
                'winner_new_rank' => $challenge->winner_new_rank ?? 'Missing',
                'loser_old_rank' => $challenge->loser_old_rank ?? 'Missing',
                'loser_new_rank' => $challenge->loser_new_rank ?? 'Missing',
                'ranks_swapped' => $challenge->ranks_swapped ?? false,
            ]);

            // Use the NEW ranks for current display (after the challenge outcome)
            $winnerCurrentRank = $challenge->winner_new_rank ?? 'Unknown';
            $loserCurrentRank = $challenge->loser_new_rank ?? 'Unknown';

            $fields = [
                [
                    'name' => '🏅 Winner',
                    'value' => "**<@{$challenge->winner->discord_id}>** (Rank: {$winnerCurrentRank})",
                    'inline' => true
                ],
                [
                    'name' => '💔 Loser',
                    'value' => "**<@{$challenge->loser->discord_id}>** (Rank: {$loserCurrentRank})",
                    'inline' => true
                ],
                [
                    'name' => '👀 Witness',
                    'value' => "<@{$witness->discord_id}>",
                    'inline' => true
                ],
            ];

            // Add rank change information
            if (isset($challenge->ranks_swapped) && $challenge->ranks_swapped === true) {
                $rankChangeText = "⚡ **Ranks Swapped!** ";
                $rankChangeText .= "**{$challenge->winner->name}** moved from rank {$challenge->winner_old_rank} to {$challenge->winner_new_rank}, ";
                $rankChangeText .= "**{$challenge->loser->name}** moved from rank {$challenge->loser_old_rank} to {$challenge->loser_new_rank}.";

                $fields[] = [
                    'name' => '📊 Rank Status',
                    'value' => $rankChangeText,
                    'inline' => false
                ];
            } else {
                $fields[] = [
                    'name' => '📊 Rank Status',
                    'value' => 'No rank changes - winner was already ranked higher.',
                    'inline' => false
                ];
            }
        } else {
            // Get current ranks for challenger and opponent
            $challengerRank = 'Unknown';
            $opponentRank = 'Unknown';

            if ($challenger && $team) {
                $challengerTeamUser = $challenger->teams()->where('team_id', $team->id)->first();
                if ($challengerTeamUser) {
                    $challengerRank = $challengerTeamUser->pivot->rank;
                }
            }

            if ($opponent && $team) {
                $opponentTeamUser = $opponent->teams()->where('team_id', $team->id)->first();
                if ($opponentTeamUser) {
                    $opponentRank = $opponentTeamUser->pivot->rank;
                }
            }

            $fields = [
                [
                    'name' => '👊 Challenger',
                    'value' => "@{$challenger->name} (Rank: {$challengerRank})",
                    'inline' => true
                ],
                [
                    'name' => '🎯 Opponent',
                    'value' => "@{$opponent->name} (Rank: {$opponentRank})",
                    'inline' => true
                ],
                [
                    'name' => '👀 Witness',
                    'value' => "@{$witness->name}",
                    'inline' => true
                ],
            ];
        }

        // Add banned agent if present
        if (($type === 'created' || $type === 'completed') && $bannedAgentData) {
            $fields[] = [
                'name' => '🚫 Banned Agent',
                'value' => "**{$bannedAgentData['name']}**",
                'inline' => true,
            ];
        }

        // Build description including fields
        foreach ($fields as $field) {
            $description .= "\n\n**{$field['name']}**\n{$field['value']}";
        }

        if ($type === 'created') {
            $description .= "\n\nChallenge ID: " . $challenge->id . ' • Created at ' . now()->format('M j, Y g:i A');
        } elseif ($type === 'completed') {
            $description .= "\n\nChallenge ID: " . $challenge->id . ' • Completed at ' . now()->format('M j, Y g:i A');
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
        // Use the cached rankings
        $users = $team->getRankings();

        // Generate the ranking list with detailed information
        $rankList = '';
        $totalPlayers = $users->count();
        $topRank = $users->min('pivot.rank');
        $bottomRank = $users->max('pivot.rank');

        foreach ($users as $index => $user) {
            $position = $index + 1;

            // Enhanced position indicators
            $crown = $position === 1 ? '👑 ' : '';
            $medal = $position === 2 ? '🥈 ' : ($position === 3 ? '🥉 ' : '');
            $position_indicator = str_pad($position, 2, '0', STR_PAD_LEFT); // Makes "1" into "01" for better readability

            // Format each player's entry
            $rankList .= "{$crown}{$medal}**#{$position_indicator}** • Rank {$user->pivot->rank} • @{$user->name}";

            // Add alias if exists
            if (!empty($user->alias)) {
                $rankList .= " (" . $user->alias . ")";
            }

            $rankList .= "\n";
        }

        // Create fields for additional information
        $fields = [
            [
                'name' => '👥 Team Information',
                'value' => "**Owner:** @{$team->owner->name}\n" .
                    "**Total Players:** {$totalPlayers}\n" .
                    "**Rank Range:** {$topRank} - {$bottomRank}",
                'inline' => true
            ],
            [
                'name' => '📈 Statistics',
                'value' => "**Top 3 Players:** " . ($totalPlayers >= 3 ? "✅" : "Need " . (3 - $totalPlayers) . " more") . "\n" .
                    "**Active Challenges:** " . $team->challenges()->where('status', 'pending')->count() . "\n" .
                    "**Last Updated:** " . now()->format('M j, Y g:i A'),
                'inline' => true
            ]
        ];

        $embed = [
            'title' => '📊 Rankings Updated in ' . $team->name,
            'description' => "**Current Rankings**\n\n" . $rankList,
            'fields' => $fields,
            'color' => $this->colors['info'],
            'timestamp' => now()->toIso8601String()
        ];

        // Add team icon if available
        if ($team->icon_url) {
            $embed['thumbnail'] = ['url' => $team->icon_url];
        }

        return $this->sendToChannel(
            $team->discord_team_id ?? env('DISCORD_ANNOUNCE_CHANNEL_ID'),
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
