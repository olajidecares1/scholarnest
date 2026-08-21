<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generates the 32-character token that gates the Basic-plan portal.
 *
 * The token is not a password: everyone who uses the Basic portal shares it,
 * and it identifies nobody. Its only job is to keep the entry point from being
 * found by someone idly typing /portal. Rotate it if it leaks somewhere it
 * should not have, remembering that every Basic school will need the new URL.
 */
class GenerateBasicPortalToken extends Command
{
    protected $signature = 'basic-portal:token {--show : Print the token currently configured instead of generating a new one}';

    protected $description = 'Generate the 32-character token for the Basic-plan portal URL';

    public function handle(): int
    {
        $configured = (string) config('basic_portal.token');

        if ($this->option('show')) {
            if ($configured === '') {
                $this->components->error('No BASIC_PORTAL_TOKEN is set. The Basic portal returns 404 until one is.');

                return self::FAILURE;
            }

            $this->components->info('Basic portal URL:');
            $this->line('  '.url('/portal/'.$configured));

            return self::SUCCESS;
        }

        // 16 random bytes rendered as hex gives exactly 32 characters, drawn
        // from a cryptographically secure source.
        $token = bin2hex(random_bytes(16));

        $this->newLine();
        $this->components->info('Generated a new Basic-plan portal token.');
        $this->newLine();
        $this->line('  Add this line to your .env file:');
        $this->newLine();
        $this->line('    BASIC_PORTAL_TOKEN='.$token);
        $this->newLine();
        $this->line('  The portal will then be at:');
        $this->newLine();
        $this->line('    '.url('/portal/'.$token));
        $this->newLine();

        if ($configured !== '') {
            $this->components->warn('A token is already configured. Replacing it will break the URL every Basic school currently uses.');
        }

        $this->components->warn('Run "php artisan config:clear" after editing .env.');
        $this->newLine();

        // Deliberately does not write to .env itself. Editing .env from a
        // command risks mangling a file that also holds database credentials,
        // and on a live server the change belongs in the deployment's secret
        // store rather than in a file this process happens to be able to write.
        $this->line('  '.Str::of('This command never edits .env for you - paste the line above yourself.')->toString());

        return self::SUCCESS;
    }
}
