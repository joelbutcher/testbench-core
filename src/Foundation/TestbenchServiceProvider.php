<?php

namespace Orchestra\Testbench\Foundation;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\Commands\TestCommand as CollisionTestCommand;

class TestbenchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! file_exists($this->app->databasePath('database.sqlite')) && config('database.default') === 'sqlite') {
            config(['database.default' => 'testing']);
        }

        /** @var \Orchestra\Testbench\Foundation\Config $config */
        $config = $this->app->bound('testbench.config')
            ? $this->app->make('testbench.config')
            : new Config();

        app(EventDispatcher::class)
            ->listen(DatabaseRefreshed::class, function () use ($config) {
                /** @var class-string|array<int, class-string>|bool $seederClasses */
                $seederClasses = $config->get('seeders') ?? false;

                if (\is_bool($seederClasses) && $seederClasses === false) {
                    return;
                }

                collect(Arr::wrap($seederClasses))
                    ->filter(fn ($seederClass) => ! \is_null($seederClass) && class_exists($seederClass))
                    ->each(function ($seederClass) {
                        app(ConsoleKernel::class)->call('db:seed', [
                            '--class' => $seederClass,
                        ]);
                    });
            });

        Application::authenticationRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                $this->isCollisionDependenciesInstalled()
                    ? Console\TestCommand::class
                    : Console\TestFallbackCommand::class,
            ]);
        }
    }

    /**
     * Check if the parallel dependencies are installed.
     *
     * @return bool
     */
    protected function isCollisionDependenciesInstalled(): bool
    {
        return class_exists(CollisionTestCommand::class);
    }
}
