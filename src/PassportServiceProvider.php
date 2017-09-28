<?php

namespace Faris\Passport;

use Illuminate\Support\ServiceProvider;

class PassportServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/passport.php' => config_path('passport.php')
        ], 'config');
    }

    public function register()
    {
        // Merge configs
        $this->mergeConfigFrom(
            __DIR__.'/../config/passport.php', 'passport'
        );

        // Bind captcha
        $this->app->singleton('passport', function($app)
        {
            return new Passport(
                $app['Illuminate\Config\Repository']
            );
        });
    }
}