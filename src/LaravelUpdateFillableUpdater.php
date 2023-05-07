<?php

namespace Jrbarros\LaravelUpdateFillable;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\SplFileInfo;

class LaravelUpdateFillableUpdater
{
    public function updateAllFillables(
        bool $writeChanges = false,
        string $specificModel = null,
        array $excludedColumns = ['id'],
        string $projectPath = null,
        array $modelDirectories = ['app']
    ): void {
        $models = $specificModel ? [$specificModel] : $this->getAllModels($projectPath, $modelDirectories);

        foreach ($models as $modelClass) {
            $modelInstance = new $modelClass;
            if (!$modelInstance instanceof Model) {
                continue;
            }

            $table = $modelInstance->getTable();
            //$this->updateAllProperties($modelClass, $table);

            $fillableColumns = $this->getFillableColumns($modelClass, $table, $excludedColumns);

            $newFillableCode = $this->generateFillableCode($fillableColumns);

            if ($writeChanges) {
                $this->writeFillableCodeToModel($modelClass, $newFillableCode);
            } else {
                $this->printFillableCode($modelClass, $newFillableCode);
            }
        }
    }

    public function getColumnsForTable(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    public function getColumnDataType(string $table, string $column): string
    {
        return Schema::getColumnType($table, $column);
    }

    public function getFillableColumns(string $modelClass, string $table, array $excludedColumns = []): array
    {
        $columns = $this->getColumnsForTable($table);

        $reflection = new ReflectionClass($modelClass);
        if ($reflection->hasProperty('nonFillable')) {
            $nonFillableProperty = $reflection->getProperty('nonFillable');
            $nonFillableColumns = $nonFillableProperty->getValue($reflection->newInstanceWithoutConstructor());
            $excludedColumns = array_merge($excludedColumns, $nonFillableColumns);
        }

        return array_filter($columns, function ($column) use ($excludedColumns) {
            return !in_array($column, $excludedColumns);
        });
    }

    public function updateAllProperties(string $modelClass, string $table): void
    {
        $fillableColumns = $this->getFillableColumns($modelClass, $table);

        $this->updateFillable($modelClass, $fillableColumns);

        $datesColumns = $this->getDatesColumns($table);

        $this->updateDates($modelClass, $datesColumns);
    }

    protected function generateFillableCode(array $fillableColumns): string
    {
        $fillableCode = [];

        foreach ($fillableColumns as $column) {
            $fillableCode[] = "        '{$column}',";
        }

        $fillableCode = implode("\n", $fillableCode);

        return "[\n" . implode("\n", $fillableCode) . "\n    ]";
    }

    public function getModelFilePath(string $modelClass): bool|string
    {
        $reflection = new ReflectionClass($modelClass);
        return $reflection->getFileName();
    }

    protected function getDatesColumns(string $table): array
    {
        $columns = $this->getColumnsForTable($table);
        $dateColumns = [];

        foreach ($columns as $column) {
            $dataType = $this->getColumnDataType($table, $column);

            if (in_array($dataType, ['date', 'datetime', 'timestamp'])) {
                if (!in_array($column, ['created_at', 'updated_at'])) {
                    $dateColumns[] = $column;
                }
            }
        }

        return $dateColumns;
    }

    public function updateDates(string $modelClass, array $dateColumns): void
    {
        if (empty($dateColumns)) {
            return;
        }

        $modelFile = $this->getModelFilePath($modelClass);

        if (file_exists($modelFile)) {
            $modelCode = $this->readModelCode($modelFile);

            // Regex para encontrar a posição em que a propriedade $fillable termina
            $fillableEndRegex = '/\$fillable\s*=.*?;\n/m';
            preg_match($fillableEndRegex, $modelCode, $fillableEndMatches, PREG_OFFSET_CAPTURE);
            $fillableEndPos = !empty($fillableEndMatches) ? $fillableEndMatches[0][1] + strlen($fillableEndMatches[0][0]) : 0;

            // Regex para encontrar a posição em que a propriedade $dates termina
            $datesEndRegex = '/\$dates\s*=.*?;\n/m';
            preg_match($datesEndRegex, $modelCode, $datesEndMatches, PREG_OFFSET_CAPTURE);
            $datesEndPos = !empty($datesEndMatches) ? $datesEndMatches[0][1] + strlen($datesEndMatches[0][0]) : 0;

            // Incluir novas propriedades abaixo de $fillable ou $dates
            $newDatesCode = "protected \$dates = ['" . implode("', '", $dateColumns) . "'];\n\n";
            if ($datesEndPos > $fillableEndPos) {
                $modelCode = substr_replace($modelCode, $newDatesCode, $datesEndPos, 0);
            } else {
                $modelCode = substr_replace($modelCode, $newDatesCode, $fillableEndPos, 0);
            }

            $this->writeModelCode($modelFile, $modelCode);
        }
    }

    private function readModelCode(string $modelFile): string
    {
        $modelCode = file_get_contents($modelFile);

        if (strpos($modelCode, '<?php') === 0) {
            $modelCode = preg_replace('/^<\?(php)?/i', "<?php", $modelCode, 1);

            if (!preg_match('/<\?php\s+\n/', $modelCode)) {
                $modelCode = preg_replace('/<\?php\s+/', "<?php\n\n", $modelCode);
            }
        } else {
            $modelCode = str_replace('<?php', "<?php\n\n", $modelCode);
            $modelCode = str_replace('<?', "<?php\n\n", $modelCode);
        }

        return $modelCode;
    }

    private function writeModelCode(string $modelFile, string $modelCode): void
    {
        $handle = fopen($modelFile, 'c+');
        if ($handle) {
            fwrite($handle, $modelCode);
            fclose($handle);
        }
    }

    public function updateFillable(string $modelClass, array $fillableColumns): void
    {
        $fillableCode = $this->generateFillableCode($fillableColumns);

        $modelFilePath = $this->getModelFilePath($modelClass);

        $this->writeFillableCodeToModel($modelFilePath, $fillableCode);
    }

    protected function writeFillableCodeToModel(string $modelFilePath, string $newFillableCode): void
    {
        $modelFilePath = $this->getModelFilePath($modelFilePath);

        $handle = fopen($modelFilePath, 'r+');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/protected\s*\$fillable\s*=\s*\[.*?\];/s', $line)) {
                    fseek($handle, -strlen($line), SEEK_CUR);
                    fwrite($handle, $newFillableCode);
                    break;
                }
            }
            fclose($handle);
        }
    }

    protected function printFillableCode(string $modelClass, string $newFillableCode): void
    {
        $oldFillableCode = $this->getCurrentFillableCode($modelClass);

        $diff = $this->generateFillableDiff($oldFillableCode, $newFillableCode);

        echo "Model: {$modelClass}\n";
        echo $diff . "\n";
    }

    protected function getCurrentFillableCode(string $modelClass): string
    {
        $modelFilePath = $this->getModelFilePath($modelClass);
        $fileContent = file_get_contents($modelFilePath);

        if (preg_match('/protected\s*\$fillable\s*=\s*\[.*?\];/s', $fileContent, $matches)) {
            return $matches[0];
        }

        return '';
    }

    public function generateFillableDiff(string $oldFillableCode, string $newFillableCode): string
    {
        $diff = '';
        $oldFillableLines = explode("\n", $oldFillableCode);
        $newFillableLines = explode("\n", $newFillableCode);

        foreach ($oldFillableLines as $line) {
            if (!in_array($line, $newFillableLines)) {
                $diff .= "- " . $line . "\n";
            }
        }

        foreach ($newFillableLines as $line) {
            if (!in_array($line, $oldFillableLines)) {
                $diff .= "+ " . $line . "\n";
            }
        }

        return $diff;
    }

    public function getAllModels($projectPath = '', $modelDirectories = ['app']): array
    {
        $models = [];
        $projectPath = !empty($projectPath) ? $projectPath : base_path();

        foreach ($modelDirectories as $directory) {
            $finder = new Finder();
            $finder->files()->in($projectPath . '/' . $directory)->name('*.php');

            foreach ($finder as $file) {
                $className = $this->getClassNameFromFile($file);

                if (!$className) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                if ($reflection->isSubclassOf(Model::class) && !$reflection->isAbstract()) {
                    $models[] = $className;
                }
            }
        }

        return $models;
    }

    protected function getClassNameFromFile(SplFileInfo $file): ?string
    {
        $namespace = null;
        $class = null;

        $path = $file->getRealPath() !== false ? $file->getRealPath() : $file->getPathname();

        $tokens = token_get_all(file_get_contents($path));

        for ($i = 0; $i < count($tokens); $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] == T_NAMESPACE) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] == T_STRING) {
                        $namespace .= '\\' . $tokens[$j][1];
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($tokens[$i][0] == T_CLASS) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === '{') {
                        $class = $tokens[$i + 2][1];
                    }
                }
            }
        }

        if (!$namespace || !$class) {
            return null;
        }

        return $namespace . '\\' . $class;
    }
}
