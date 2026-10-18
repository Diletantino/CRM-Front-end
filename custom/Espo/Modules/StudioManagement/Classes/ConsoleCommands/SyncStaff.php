<?php

namespace Espo\Modules\StudioManagement\Classes\ConsoleCommands;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Modules\StudioManagement\Tools\StaffMigration;

class SyncStaff implements Command
{
    public function __construct(private StaffMigration $migration) {}

    public function run(Params $params, IO $io): void
    {
        $result = $this->migration->run();
        $io->writeLine(sprintf('Studio staff synchronized: %d users, %d shift owners.', $result['users'], $result['shifts']));
        $io->writeLine(sprintf('Studio teams synchronized: %d; require manual slot selection: %d.',
            $result['teams'], $result['incompleteTeams']));
    }
}
