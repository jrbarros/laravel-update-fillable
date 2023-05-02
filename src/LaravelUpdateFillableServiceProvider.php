<?php

namespace Jrbarros\LaravelUpdateFillable;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Jrbarros\LaravelUpdateFillable\Commands\LaravelUpdateFillableCommand;

class LaravelUpdateFillableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-update-fillable')
            ->hasCommand(LaravelUpdateFillableCommand::class);
    }
}
