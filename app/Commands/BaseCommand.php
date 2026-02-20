<?php

namespace App\Commands;

use App\Services\DisclaimerService;
use LaravelZero\Framework\Commands\Command;

abstract class BaseCommand extends Command
{
    protected DisclaimerService $disclaimer;

    public function __construct(DisclaimerService $disclaimer)
    {
        parent::__construct();
        $this->disclaimer = $disclaimer;
    }

    protected function requireDisclaimer(): void
    {
        if ($this->option('accept-terms')) {
            $this->disclaimer->accept();
        }

        $this->disclaimer->requireConfirmation($this);
    }
}
