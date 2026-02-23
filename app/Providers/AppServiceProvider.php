<?php

namespace App\Providers;

use App\Contracts\ComposerAnalyserInterface;
use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\MagentoPathResolverInterface;
use App\Contracts\RowIdScannerInterface;
use App\Contracts\SnapshotComparatorInterface;
use App\Contracts\SnapshotInterface;
use App\Contracts\SqlLoggerInterface;
use App\Contracts\SqlRunnerInterface;
use App\Services\Composer\ComposerAnalyser;
use App\Services\Database\MagentoDatabaseConnection;
use App\Services\Database\SqlFileRunner;
use App\Services\Database\SqlLogger;
use App\Services\DisclaimerService;
use App\Services\MagentoPathResolver;
use App\Services\Scanner\RowIdScanner;
use App\Services\Snapshot\DatabaseSnapshot;
use App\Services\Snapshot\SnapshotComparator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DisclaimerService::class);
        $this->app->singleton(SqlLoggerInterface::class, SqlLogger::class);

        $this->app->bind(MagentoPathResolverInterface::class, MagentoPathResolver::class);
        $this->app->singleton(DatabaseConnectionInterface::class, MagentoDatabaseConnection::class);
        $this->app->bind(SqlRunnerInterface::class, SqlFileRunner::class);
        $this->app->bind(SnapshotInterface::class, DatabaseSnapshot::class);
        $this->app->bind(SnapshotComparatorInterface::class, SnapshotComparator::class);
        $this->app->bind(ComposerAnalyserInterface::class, ComposerAnalyser::class);
        $this->app->bind(RowIdScannerInterface::class, RowIdScanner::class);

        $this->app->when(SqlFileRunner::class)
            ->needs('$sqlDirectory')
            ->give(base_path('sql'));
    }

    public function boot(): void
    {
        //
    }
}
