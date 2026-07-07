<?php

namespace Raikia\SeatSpyHunter;

use Raikia\SeatSpyHunter\Console\Commands\RefreshIntelReports;
use Raikia\SeatSpyHunter\Console\Commands\ProcessEveWhoQueue;
use Raikia\SeatSpyHunter\Console\Commands\ProcessVpnLookupQueue;
use Raikia\SeatSpyHunter\Database\Seeders\ScheduleSeeder;
use Seat\Services\AbstractSeatPlugin;

class SeatSpyHunterServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        $this->addRoutes();
        $this->addViews();
        $this->addTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->registerPermissions(__DIR__ . '/Config/seat-spy-hunter.permissions.php', 'seat-spy-hunter');
        $this->registerCommands();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/seat-spy-hunter.sidebar.php', 'package.sidebar'
        );
        $this->registerDatabaseSeeders([
            ScheduleSeeder::class,
        ]);
    }

    public function getName(): string
    {
        return 'SeAT Spy Hunter';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/raikia/seat-spy-hunter';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-spy-hunter';
    }

    public function getPackagistVendorName(): string
    {
        return 'raikia';
    }

    private function addRoutes()
    {
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
    }

    private function addViews()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'seat-spy-hunter');
    }

    private function addTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'seat-spy-hunter');
    }

    private function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessEveWhoQueue::class,
                ProcessVpnLookupQueue::class,
                RefreshIntelReports::class,
            ]);
        }
    }
}
