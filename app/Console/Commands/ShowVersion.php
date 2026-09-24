<?php

namespace App\Console\Commands;

use App\Release\Version;
use Illuminate\Console\Command;

/**
 * Prints the version that is deployed.
 *
 * `scripts/deploy.sh` runs it at the end so that the deploy says what it
 * published, and it asks the application rather than reading the changelog on
 * its own: what it prints is then the same answer the screens give, and not a
 * second reading of the same file that could disagree with them.
 */
final class ShowVersion extends Command
{
    protected $signature = 'app:version';

    protected $description = 'Muestra la versión desplegada, según CHANGELOG.md.';

    public function handle(): int
    {
        $this->line(Version::label());

        return self::SUCCESS;
    }
}
