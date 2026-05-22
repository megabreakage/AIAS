<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class PassportClientSeeder extends Seeder
{
    public function __construct(protected ClientRepository $clientRepository) {}

    public function run(): void
    {
        $this->ensurePersonalAccessClient('users', 'AIAS Personal Access Client');
        $this->ensurePersonalAccessClient('super_admins', 'AIAS SA Personal Access Client');
    }

    private function ensurePersonalAccessClient(string $provider, string $name): void
    {
        $exists = Client::where('provider', $provider)
            ->whereJsonContains('grant_types', 'personal_access')
            ->exists();

        if ($exists) {
            $this->command->line("Personal access client already exists for provider: {$provider}");

            return;
        }

        $this->clientRepository->createPersonalAccessClient(null, $name, 'http://localhost', $provider);

        $this->command->info("Created personal access client for provider: {$provider}");
    }
}
