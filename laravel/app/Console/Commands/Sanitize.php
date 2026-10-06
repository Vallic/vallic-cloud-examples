<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scrubs people out of a database that arrived from somewhere else.
 *
 * Called from .vallic/commands/sanitization.yml, which the platform runs after
 * a copy or a restore into staging or development — never on production. The
 * platform cannot know which of your tables hold people, so this is where you
 * say.
 *
 * https://docs.vallic.com/backup-storage#sanitising-what-arrives
 */
class Sanitize extends Command
{
    protected $signature = 'app:sanitize';

    protected $description = 'Replace personal data with placeholders, for a copy of production';

    public function handle(): int
    {
        // Belt and braces: the platform never runs this on production, and
        // neither should anybody by hand.
        if (getenv('VALLIC_ENVIRONMENT_TYPE') === 'production') {
            $this->error('Refusing to sanitise production.');

            return self::FAILURE;
        }

        DB::table('users')->update([
            'email' => DB::raw("CONCAT('user', id, '@example.test')"),
            // One known password for every account, so the copy can be logged
            // in to — and nobody's real hash leaves production.
            'password' => bcrypt('sanitized'),
            'remember_token' => null,
        ]);

        DB::table('password_reset_tokens')->truncate();
        DB::table('sessions')->truncate();

        // Your own tables that hold people, for example:
        // DB::table('orders')->update(['customer_email' => DB::raw("CONCAT('order', id, '@example.test')")]);

        $this->info('Sanitised.');

        return self::SUCCESS;
    }
}
