<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport;

use ByJesper\DecisionSupport\Conditions\ConditionEvaluatorChain;
use ByJesper\DecisionSupport\Conditions\ExpressionConditionEvaluator;
use ByJesper\DecisionSupport\Conditions\StructuredConditionEvaluator;
use ByJesper\DecisionSupport\Contracts\ConditionEvaluator;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Profiles\FreeformProfile;
use ByJesper\DecisionSupport\Profiles\PhasedProfile;
use ByJesper\DecisionSupport\Publishing\GuidePublisher;
use ByJesper\DecisionSupport\Registry\FactProviderRegistry;
use ByJesper\DecisionSupport\Registry\GuideProfileRegistry;
use ByJesper\DecisionSupport\Registry\NodeTypeRegistry;
use ByJesper\DecisionSupport\Runtime\GuideRunner;
use ByJesper\DecisionSupport\Validation\PublishValidator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class DecisionSupportServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/decision-support.php', 'decision-support');

        $this->app->singleton(NodeTypeRegistry::class);
        $this->app->singleton(GuideProfileRegistry::class);
        $this->app->singleton(
            FactProviderRegistry::class,
            static fn (Application $app): FactProviderRegistry => new FactProviderRegistry($app),
        );

        $this->app->singleton(
            ConditionEvaluator::class,
            static fn (): ConditionEvaluatorChain => new ConditionEvaluatorChain(
                new StructuredConditionEvaluator,
                new ExpressionConditionEvaluator,
            ),
        );

        $this->app->singleton(
            DecisionSupportManager::class,
            static fn (Application $app): DecisionSupportManager => new DecisionSupportManager(
                $app->make(NodeTypeRegistry::class),
                $app->make(FactProviderRegistry::class),
                $app->make(GuideProfileRegistry::class),
            ),
        );

        $this->app->bind(
            PublishValidator::class,
            static fn (Application $app): PublishValidator => new PublishValidator($app->make(NodeTypeRegistry::class)),
        );

        $this->app->bind(
            GuideRunner::class,
            static fn (Application $app): GuideRunner => new GuideRunner(
                $app->make(NodeTypeRegistry::class),
                $app->make(FactProviderRegistry::class),
                $app->make(ConditionEvaluator::class),
                $app->make(Dispatcher::class),
                (int) $app->make('config')->get('decision-support.max_steps', 200),
            ),
        );

        $this->app->bind(
            GuidePublisher::class,
            static fn (Application $app): GuidePublisher => new GuidePublisher(
                $app->make(PublishValidator::class),
                $app->make(GuideProfileRegistry::class),
                $app->make(FactProviderRegistry::class),
                $app->make(Dispatcher::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->registerBuiltins();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/decision-support.php' => config_path('decision-support.php'),
            ], 'decision-support-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'decision-support-migrations');
        }
    }

    private function registerBuiltins(): void
    {
        $manager = $this->app->make(DecisionSupportManager::class);

        $manager->registerNodeType(new QuestionNode);
        $manager->registerNodeType(new FactNode);
        $manager->registerNodeType(new DecisionNode);
        $manager->registerNodeType(new OutcomeNode);

        $manager->registerProfile(new PhasedProfile);
        $manager->registerProfile(new FreeformProfile);
    }
}
