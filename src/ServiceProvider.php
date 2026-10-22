<?php

namespace Estouai\MapPoint;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => ['resources/js/cp.js'],
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/map-point.php', 'map-point');

        $this->publishes([
            __DIR__.'/../config/map-point.php' => config_path('map-point.php'),
        ], 'map-point-config');
    }

    // Same split as the sibling cookie-consent addon: compiled assets are
    // always force-republished (generated output, never hand-edited by a host
    // app); config is never forced (it's the one file a site customizes).
    protected function bootPublishAfterInstall()
    {
        Statamic::afterInstalled(function ($command) {
            $command->call('vendor:publish', ['--tag' => 'map-point', '--force' => true]);
            $command->call('vendor:publish', ['--tag' => 'map-point-config']);
        });

        return $this;
    }
}
