<?php

namespace Jrbarros\LaravelUpdateFillable\Commands;

use Illuminate\Console\Command;
use Jrbarros\LaravelUpdateFillable\LaravelUpdateFillableUpdater;

class LaravelUpdateFillableCommand extends Command
{
    protected $signature = 'update:fillable {--write : Write changes to the model files} {--exclude= : Comma-separated list of column names to exclude} {--path= : The path to the project} {--directories= : Comma-separated list of directories where the models are located} {model?}';

    protected $description = 'Update the $fillable property of Eloquent models based on the current database schema';

    public function handle()
    {
        $writeChanges = $this->option('write');
        $specificModel = $this->argument('model');
        $excludedColumns = $this->option('exclude') ? explode(',', $this->option('exclude')) : ['id'];

        $fillableUpdater = new LaravelUpdateFillableUpdater();

        $projectPath = $this->option('path') ?: base_path();
        $modelDirectories = $this->option('directories') ? explode(',', $this->option('directories')) : ['app'];

        $fillableUpdater->updateAllFillables(
            $writeChanges,
            $specificModel,
            $excludedColumns,
            $projectPath,
            $modelDirectories
        );

        $this->info('Fillable properties have been updated.');
    }
}
