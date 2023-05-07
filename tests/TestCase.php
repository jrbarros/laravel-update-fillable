<?php

namespace Jrbarros\LaravelUpdateFillable\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jrbarros\LaravelUpdateFillable\LaravelUpdateFillableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Jrbarros\\LaravelUpdateFillable\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelUpdateFillableServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $migration = include __DIR__.'/../database/migrations/create_update_fillable_table.php';
        $migration->up();
    }
}
