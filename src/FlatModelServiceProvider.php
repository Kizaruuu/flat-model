<?php

namespace Kizaru\FlatModel;

use Illuminate\Support\ServiceProvider;

class FlatModelServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the service provider.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeFlatModel::class,
                MakeFlatResource::class,
                MakeFlatRequest::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }
}
