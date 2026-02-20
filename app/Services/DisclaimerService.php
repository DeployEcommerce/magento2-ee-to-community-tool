<?php

namespace App\Services;

use Illuminate\Console\Command;

class DisclaimerService
{
    private bool $confirmed = false;

    public function accept(): void
    {
        $this->confirmed = true;
    }

    public function requireConfirmation(Command $command): void
    {
        if ($this->confirmed) {
            return;
        }

        $command->line($this->renderWarningBox());

        $choice = $command->choice(
            'Do you accept these terms and wish to proceed?',
            ['I Do Not Agree', 'I Agree'],
            0
        );

        if ($choice !== 'I Agree') {
            $command->error('You did not agree to the terms. Exiting.');
            exit(1);
        }

        $this->confirmed = true;
    }

    private function renderWarningBox(): string
    {
        return <<<'BOX'

╔══════════════════════════════════════════════════════════════╗
║         ⚠  DESTRUCTIVE OPERATION — READ CAREFULLY  ⚠         ║
╠══════════════════════════════════════════════════════════════╣
║                                                              ║
║  This tool will make IRREVERSIBLE changes to your database   ║
║  and project files, including:                               ║
║                                                              ║
║  • Dropping approximately 90 database tables                 ║
║  • Removing and rewriting primary keys across core tables    ║
║  • Modifying your project's composer.json                    ║
║                                                              ║
║  These changes cannot be automatically rolled back.          ║
║  You MUST take a full database backup (mysqldump) before     ║
║  running this tool.                                          ║
║                                                              ║
╠══════════════════════════════════════════════════════════════╣
║  DISCLAIMER                                                  ║
║                                                              ║
║  This software is provided "as is", without warranty of     ║
║  any kind, express or implied. Deploy Ecommerce Ltd and      ║
║  its contributors shall not be liable for any direct,        ║
║  indirect, incidental, special, or consequential damages     ║
║  (including but not limited to data loss, system downtime,   ║
║  or loss of business) arising from the use of or inability   ║
║  to use this tool, even if advised of the possibility of     ║
║  such damages.                                               ║
║                                                              ║
║  Use of this tool is entirely at your own risk. By           ║
║  proceeding you confirm you have taken an appropriate        ║
║  backup and accept full responsibility for the outcome.      ║
╚══════════════════════════════════════════════════════════════╝

BOX;
    }
}
