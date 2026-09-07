<?php

namespace BoldlyGrow\Gitlab;

use Illuminate\Support\ServiceProvider;

class GitlabApiServiceProvider extends ServiceProvider
{
    // use ServiceBindings;

    public function boot(): void
    {
        $this->bootRoutes();
        $this->publishConfigFile();
    }

    public function register()
    {
        $this->mergeConfig();
        $this->registerServices();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function bootRoutes()
    {
        //$this->loadRoutesFrom(__DIR__.'/Routes/console.php');
    }

    /**
     * Merge package config file into application config file
     *
     * This allows users to override any module configuration values with their
     * own values in the application config file.
     */
    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/gitlab-api-client.php', 'gitlab-api-client');
    }

    /**
     * Publish config file to application
     *
     * Once the `php artisan vendor::publish` command is run, you can use the
     * configuration file values `$value = config('okta-sdk.option');`
     */
    protected function publishConfigFile(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . '/Config/gitlab-api-client.php' => config_path('gitlab-api-client.php')
                ],
                'gitlab-api-client'
            );
        }
    }

    /**
     * Register package services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        if (property_exists($this, 'serviceBindings')) {
            foreach ($this->serviceBindings as $key => $value) {
                is_numeric($key)
                    ? $this->app->singleton($value)
                    : $this->app->singleton($key, $value);
            }
        }
    }
}
