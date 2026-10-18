<?php

namespace Espo\Modules\StudioManagement\Classes\ConsoleCommands;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Modules\StudioManagement\Tools\RegistrationLinkMigration;

final class MigrateRegistrationLinks implements Command
{
    public function __construct(private RegistrationLinkMigration $migration) {}

    public function run(Params $params, IO $io): void
    {
        $count = $this->migration->run();
        $io->writeLine(sprintf('Registration links migrated: %d.', $count));
    }
}
