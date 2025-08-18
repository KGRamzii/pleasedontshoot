<?php

namespace App\Console\Commands;

use App\Services\DiscordService;
use Illuminate\Console\Command;

class TestDiscordEndpoints extends Command
{
    protected $signature = 'discord:test';
    protected $description = 'Test Discord bot endpoints';

    public function handle()
    {
        $this->info('Testing Discord endpoints...');
        
        $discord = app(DiscordService::class);
        $results = $discord->testEndpoints();

        $this->info('Test completed!');
        $this->table(
            ['Endpoint', 'Status'],
            [
                ['DM', $results['dm_status']],
                ['Channel', $results['channel_status']]
            ]
        );

        $this->info('Check the logs (storage/logs/laravel.log) for detailed results.');
    }
}
