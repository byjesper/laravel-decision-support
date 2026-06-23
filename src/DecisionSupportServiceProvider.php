<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport;

use Illuminate\Support\ServiceProvider;

class DecisionSupportServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/decision-support.php', 'decision-support');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/decision-support.php' => config_path('decision-support.php'),
            ], 'decision-support-config');
        }
    }
}
