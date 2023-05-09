<?php

namespace Jrbarros\LaravelUpdateFillable;

use Jrbarros\LaravelUpdateFillable\Commands\LaravelUpdateFillableCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelUpdateFillableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-update-fillable')
            ->hasCommand(LaravelUpdateFillableCommand::class);
    }
}
