<?php

namespace InWeb\Base\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InWeb\Base\Console\Commands\ModelCommand;
use InWeb\Base\Console\Commands\PublishCommand;

class AppServiceProvider extends ServiceProvider
{
    protected static $packagePath = __DIR__ . '/../../';
    protected static $packageAlias = 'inweb';

    public static function getPackageAlias(): string
    {
        return self::$packageAlias;
    }

    public static function getPackagePath(): string
    {
        return self::$packagePath;
    }

    /**
     * Bootstrap any package services.
     *
     * @param Router $router
     * @return void
     */
    public function boot(Router $router)
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register()
    {
        $this->registerResources();
    }

    /**
     * Register the package resources such as routes, templates, etc.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function registerResources()
    {
        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(
                self::getPackagePath() . 'config/config.php',
                self::getPackageAlias()
            );
        }
    }

    private function registerPublishing()
    {
        // Config
        $this->publishes([
            self::$packagePath . 'config/config.php' => config_path(self::$packageAlias . '.php'),
        ], ['config', 'inweb-config']);
    }

    public function registerCommands()
    {
        $this->commands([
            PublishCommand::class,
            ModelCommand::class,
        ]);
    }
}
