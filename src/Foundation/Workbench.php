<?php

namespace Orchestra\Testbench\Foundation;

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Contracts\Config as ConfigContract;

use function Orchestra\Testbench\after_resolving;
use function Orchestra\Testbench\workbench_path;

/**
 * @phpstan-import-type TWorkbenchDiscoversConfig from \Orchestra\Testbench\Foundation\Config
 */
class Workbench
{
    /**
     * The cached test case configuration.
     *
     * @var \Orchestra\Testbench\Contracts\Config|null
     */
    protected static $cachedConfiguration;

    /**
     * The cached core workbench bindings.
     *
     * @var array{kernel: array{console?: string|null, http?: string|null}, handler: array{exception?: string|null}}
     */
    public static $cachedCoreBindings = [
        'kernel' => [],
        'handler' => [],
    ];

    /**
     * Start Workbench.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Orchestra\Testbench\Contracts\Config  $config
     * @return void
     */
    public static function start(ApplicationContract $app, ConfigContract $config): void
    {
        $app->singleton(ConfigContract::class, static function () use ($config) {
            return $config;
        });
    }

    /**
     * Discover Workbench routes.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Orchestra\Testbench\Contracts\Config  $config
     * @return void
     */
    public static function discoverRoutes(ApplicationContract $app, ConfigContract $config): void
    {
        /** @var TWorkbenchDiscoversConfig $discoversConfig */
        $discoversConfig = $config->getWorkbenchDiscoversAttributes();

        $app->booted(function ($app) use ($discoversConfig) {
            tap($app->make('router'), static function (Router $router) use ($discoversConfig) {
                foreach (['web', 'api'] as $group) {
                    if (($discoversConfig[$group] ?? false) === true) {
                        if (file_exists($route = workbench_path("routes/{$group}.php"))) {
                            $router->middleware($group)->group($route);
                        }
                    }
                }
            });

            if ($app->runningInConsole() && ($discoversConfig['commands'] ?? false) === true) {
                if (file_exists($console = workbench_path('routes/console.php'))) {
                    require $console;
                }
            }
        });

        after_resolving($app, 'translator', static function ($translator) {
            /** @var \Illuminate\Contracts\Translation\Loader $translator */
            $translator->addNamespace(
                'workbench',
                is_dir(workbench_path('/lang')) ? workbench_path('/lang') : workbench_path('/resources/lang')
            );
        });

        after_resolving($app, 'view', static function ($view) use ($discoversConfig) {
            /** @var \Illuminate\Contracts\View\Factory|\Illuminate\View\Factory $view */
            $path = workbench_path('/resources/views');

            if (($discoversConfig['views'] ?? false) === true && method_exists($view, 'addLocation')) {
                $view->addLocation($path);
            } else {
                $view->addNamespace('workbench', $path);
            }
        });

        after_resolving($app, 'blade.compiler', static function ($blade) use ($discoversConfig) {
            /** @var \Illuminate\View\Compilers\BladeCompiler $blade */
            if (($discoversConfig['views'] ?? false) === false) {
                $blade->componentNamespace('Workbench\\App\\View\\Components', 'workbench');
            }
        });
    }

    /**
     * Resolve the configuration.
     *
     * @return \Orchestra\Testbench\Contracts\Config
     */
    public static function configuration(): ConfigContract
    {
        if (\is_null(static::$cachedConfiguration)) {
            $workingPath = getcwd();

            if (\defined('TESTBENCH_WORKING_PATH')) {
                $workingPath = TESTBENCH_WORKING_PATH;
            } elseif (! \is_null(Env::get('TESTBENCH_WORKING_PATH'))) {
                $workingPath = Env::get('TESTBENCH_WORKING_PATH');
            }

            static::$cachedConfiguration = Config::cacheFromYaml($workingPath);
        }

        return static::$cachedConfiguration;
    }

    /**
     * Get application Console Kernel implementation.
     *
     * @return string|null
     */
    public static function applicationConsoleKernel(): ?string
    {
        if (! isset(static::$cachedCoreBindings['kernel']['console'])) {
            static::$cachedCoreBindings['kernel']['console'] = file_exists(workbench_path('/app/Console/Kernel.php'))
                ? 'Workbench\App\Console\Kernel'
                : null;
        }

        return static::$cachedCoreBindings['kernel']['console'];
    }

    /**
     * Get application HTTP Kernel implementation using Workbench.
     *
     * @return string|null
     */
    public static function applicationHttpKernel(): ?string
    {
        if (! isset(static::$cachedCoreBindings['kernel']['http'])) {
            static::$cachedCoreBindings['kernel']['http'] = file_exists(workbench_path('/app/Http/Kernel.php'))
                ? 'Workbench\App\Http\Kernel'
                : null;
        }

        return static::$cachedCoreBindings['kernel']['http'];
    }

    /**
     * Get application HTTP exception handler using Workbench.
     *
     * @return string|null
     */
    public static function applicationExceptionHandler(): ?string
    {
        if (! isset(static::$cachedCoreBindings['handler']['exception'])) {
            static::$cachedCoreBindings['handler']['exception'] = file_exists(workbench_path('/app/Exceptions/Exceptions.php'))
                ? 'Workbench\App\Exceptions\Handler'
                : null;
        }

        return static::$cachedCoreBindings['handler']['exception'];
    }

    /**
     * Flush the cached configuration.
     *
     * @return void
     */
    public static function flush(): void
    {
        static::$cachedConfiguration = null;

        static::$cachedCoreBindings = [
            'kernel' => [],
            'handler' => [],
        ];
    }
}
