<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\RankHistory;
use Carbon\Carbon;

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
     * Create a consistently styled embed array.
     */
    protected function createEmbed(string $title, string $description = '', array $fields = [], $color = 'info', ?string $thumbnail = null, ?string $timestamp = null): array
    {
        $embed = [
            'title' => $title,
            'description' => $description,
            'color' => is_string($color) ? ($this->colors[$color] ?? $this->colors['info']) : $color,
            'fields' => array_map(function ($field) {
                return [
                    'name' => $field['name'],
                    'value' => $field['value'],
                    'inline' => $field['inline'] ?? true,
                ];
            }, $fields),
            'timestamp' => $timestamp ?? now()->toIso8601String(),
        ];

        if ($thumbnail) {
            $embed['thumbnail'] = ['url' => $thumbnail];
        }

        return $embed;
    }

    /**
     * Send a message to a channel via your relay API.
     */
    public function sendToChannel($channelId, $message = '', $embed = null)
    {
        try {
            $payload = [
                'channel_id' => $channelId
            ];

            if (!empty($message)) {
                $payload['message'] = $message;
            }

            if ($embed) {
                // Map embed fields expected by your relay API
                $payload['embed_title'] = $embed['title'] ?? null;
                $payload['embed_description'] = $embed['description'] ?? null;
                $payload['embed_color'] = $embed['color'] ?? 0x00ff00;

                if (isset($embed['thumbnail']['url'])) {
                    $payload['embed_thumbnail'] = $embed['thumbnail']['url'];
                }

                // include embed_fields if present (relay will assemble)
                if (!empty($embed['fields'])) {
                    $payload['embed_fields'] = $embed['fields'];
                }

                if (!empty($embed['timestamp'])) {
                    $payload['embed_timestamp'] = $embed['timestamp'];
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
     * Send a direct message to a user by Discord ID via your relay API.
     */
    public function sendDirectMessage($discordUserId, $message = '', $embed = null)
    {
        try {
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
                'message' => $message,
            ];

            if ($embed) {
                $payload['embed_title'] = $embed['title'] ?? null;
                $payload['embed_description'] = $embed['description'] ?? null;
                $payload['embed_color'] = $embed['color'] ?? null;
                if (!empty($embed['fields'])) {
                    $payload['embed_fields'] = $embed['fields'];
                }
                if (!empty($embed['timestamp'])) {
                    $payload['embed_timestamp'] = $embed['timestamp'];
                }
                if (isset($embed['thumbnail']['url'])) {
                    $payload['embed_thumbnail'] = $embed['thumbnail']['url'];
                }
            }

            $response = Http::timeout(30)->post("{$this->base}/send-dm", $payload);

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
     * Send a challenge notification (created/accepted/declined/completed).
     */
    public function sendChallengeNotification($challenge, $type = 'created')
    {
        $challenger = $challenge->challenger ?? null;
        $opponent = $challenge->opponent ?? null;
        $witness = $challenge->witness ?? null;
        $team = $challenge->team ?? null;
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
            'created' => 0x7289da,
            'accepted' => 0x4CAF50,
            'declined' => 0xf04747,
            'completed' => 0x43b581,
            default => 0x7289da
        };

        $fields = [];

        if ($type === 'completed' && isset($challenge->winner) && isset($challenge->loser)) {
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

            if (isset($challenge->ranks_swapped) && $challenge->ranks_swapped) {
                $rankChangeText = "📈 **Ranks Swapped!**\n";
                $rankChangeText .= "🏅 **{$challenge->winner->name}:** {$challenge->winner_old_rank} → {$challenge->winner_new_rank}\n";
                $rankChangeText .= "💔 **{$challenge->loser->name}:** {$challenge->loser_old_rank} → {$challenge->loser_new_rank}";

                $fields[] = [
                    'name' => '📊 Rank Changes',
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
                    'value' => "<@{$challenger->discord_id}> (Rank: {$challengerRank})",
                    'inline' => true
                ],
                [
                    'name' => '🎯 Opponent',
                    'value' => "<@{$opponent->discord_id}> (Rank: {$opponentRank})",
                    'inline' => true
                ],
                [
                    'name' => '👀 Witness',
                    'value' => "<@{$witness->discord_id}>",
                    'inline' => true
                ],
            ];
        }

        if ($type === 'created' || $type === 'completed') {
            if ($bannedAgentData) {
                $fields[] = [
                    'name' => '🚫 Banned Agent',
                    'value' => "**{$bannedAgentData['name']}**",
                    'inline' => true,
                ];
            } elseif ($type === 'created') {
                $fields[] = [
                    'name' => '🚫 Banned Agent',
                    'value' => 'None',
                    'inline' => true,
                ];
            }
        }

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

        if (!empty($bannedAgentData['icon'])) {
            $embed['thumbnail'] = ['url' => $bannedAgentData['icon']];
        }

        return $this->sendToChannel(
            env('DISCORD_ANNOUNCE_CHANNEL_ID'),
            '',
            $embed
        );
    }

    /**
     * Send updated rankings + recent history + pending challenges
     */
    public function sendRankingsUpdate($team)
    {
        if (!$team) {
            Log::warning('sendRankingsUpdate called with null $team');
            return false;
        }

        // Get current rankings (assumes getRankings exists and returns collection ordered by rank)
        $users = $team->getRankings();
        $rankList = '';

        foreach ($users as $index => $user) {
            $position = $index + 1;
            $crown = $position === 1 ? '👑 ' : '';
            $medal = $position === 2 ? '🥈 ' : ($position === 3 ? '🥉 ' : '');
            $position_indicator = str_pad($position, 2, '0', STR_PAD_LEFT);

            $alias = !empty($user->alias) ? " ({$user->alias})" : '';
            $rankList .= "{$crown}{$medal}**#{$position_indicator}** • Rank {$user->pivot->rank} • <@{$user->discord_id}>{$alias}\n";

            if (strlen($rankList) > 3000) {
                $rankList .= "... (truncated)\n";
                break;
            }
        }

        $totalPlayers = $users->count();
        $topRank = $users->isNotEmpty() ? $users->min('pivot.rank') : 'N/A';
        $bottomRank = $users->isNotEmpty() ? $users->max('pivot.rank') : 'N/A';

        $fields = [
            [
                'name' => '👥 Team Information',
                'value' => "**Owner:** <@{$team->owner->discord_id}>\n" .
                           "**Total Players:** {$totalPlayers}\n" .
                           "**Rank Range:** {$topRank} - {$bottomRank}",
                'inline' => true
            ],
            [
                'name' => '📈 Snapshot',
                'value' => "**Top 3:** " . ($totalPlayers >= 3 ? "✅" : "Need " . max(0, 3 - $totalPlayers) . " more") . "\n" .
                           "**Active Challenges (pending):** " . $team->challenges()->where('status', 'pending')->count() . "\n" .
                           "**Last Updated:** " . now()->format('M j, Y g:i A'),
                'inline' => true
            ]
        ];

        $embedMain = [
            'title' => '📊 Rankings in ' . $team->name,
            'description' => "**Current Rankings**\n\n" . ($rankList ?: 'No players'),
            'fields' => $fields,
            'color' => $this->colors['info'],
            'timestamp' => now()->toIso8601String()
        ];

        if (!empty($team->icon_url)) {
            $embedMain['thumbnail'] = ['url' => $team->icon_url];
        }

        // Send primary embed
        $this->sendToChannel(
            $team->discord_team_id ?? env('DISCORD_ANNOUNCE_CHANNEL_ID'),
            '',
            $embedMain
        );

        // --- Second embed: last 3 rank history entries + pending challenges ---
        $recentHistory = RankHistory::with(['user', 'challenge'])
            ->where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $historyText = '';
        if ($recentHistory->isEmpty()) {
            $historyText = 'No recent rank changes.';
        } else {
            foreach ($recentHistory as $rh) {
                $userName = $rh->user->name ?? "User #{$rh->user_id}";
                $from = $rh->previous_rank;
                $to = $rh->new_rank;
                $movement = $from - $to;
                $movementText = $movement > 0 ? "Moved up {$movement}" : ($movement < 0 ? "Moved down " . abs($movement) : "No change");
                $when = $rh->created_at->diffForHumans();
                $challengePart = $rh->challenge ? (" (challenge #" . $rh->challenge->id . ")") : '';
                $historyText .= "**{$userName}**: {$from} ➔ {$to} — {$movementText}{$challengePart} • {$when}\n";
            }
        }

        $pending = $team->challenges()->where('status', 'pending')->with(['challenger', 'opponent'])->orderBy('created_at', 'asc')->limit(5)->get();

        $pendingText = '';
        if ($pending->isEmpty()) {
            $pendingText = 'No pending challenges.';
        } else {
            foreach ($pending as $p) {
                $challenger = $p->challenger ? ($p->challenger->name . " <@{$p->challenger->discord_id}>") : "User #{$p->challenger_id}";
                $opponent = $p->opponent ? ($p->opponent->name . " <@{$p->opponent->discord_id}>") : "User #{$p->opponent_id}";
                $created = $p->created_at->diffForHumans();
                $pendingText .= "**#{$p->id}** — {$challenger} vs {$opponent} • {$created}\n";
            }
        }

        $embedSecond = [
            'title' => "🧾 Recent Activity & Pending Challenges — {$team->name}",
            'description' => '',
            'fields' => [
                [
                    'name' => '🔁 Recent Rank Changes (last 3)',
                    'value' => $historyText,
                    'inline' => false
                ],
                [
                    'name' => '⏳ Pending Challenges (up to 5)',
                    'value' => $pendingText,
                    'inline' => false
                ],
            ],
            'color' => $this->colors['info'],
            'timestamp' => now()->toIso8601String()
        ];

        return $this->sendToChannel(
            $team->discord_team_id ?? env('DISCORD_ANNOUNCE_CHANNEL_ID'),
            '',
            $embedSecond
        );
    }

    /**
     * Optional - small test function to exercise endpoints.
     */
    public function testEndpoints()
    {
        Log::info('Starting Discord endpoint tests...');
        $results = [
            'dm' => null,
            'channel' => null,
        ];

        try {
            $results['dm'] = Http::post("{$this->base}/send-dm", [
                'user_id' => '768838379865374730',
                'message' => '🧪 Test message from Discord bot'
            ])->json();
        } catch (\Exception $e) {
            Log::error('DM endpoint test failed', ['error' => $e->getMessage()]);
        }

        try {
            $results['channel'] = Http::post("{$this->base}/send-channel-message", [
                'channel_id' => env('DISCORD_ANNOUNCE_CHANNEL_ID'),
                'message' => '🧪 Test message from Discord bot'
            ])->json();
        } catch (\Exception $e) {
            Log::error('Channel endpoint test failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }
}
