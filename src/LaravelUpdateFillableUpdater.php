<?php

namespace Jrbarros\LaravelUpdateFillable;

use NunoMaduro\Collision\ConsoleColor;
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
        array $modelDirectories = ['app/Models']
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

        $defaultTimestamps = ['created_at', 'updated_at'];
        $excludedColumns = array_merge($excludedColumns, $defaultTimestamps);

        $reflection = new ReflectionClass($modelClass);
        if ($reflection->hasProperty('nonFillable')) {
            $nonFillableProperty = $reflection->getProperty('nonFillable');
            $nonFillableColumns = $nonFillableProperty->getValue($reflection->newInstanceWithoutConstructor());
            $excludedColumns = array_merge($excludedColumns, $nonFillableColumns);
        }

        if ($reflection->hasProperty('nonFillable')) {
            $guardedProperty = $reflection->getProperty('guarded');
            $guardedColumns = $guardedProperty->getValue($reflection->newInstanceWithoutConstructor());
            $excludedColumns = array_merge($excludedColumns, $guardedColumns);
        }

        return array_values(
            array_filter($columns, function ($column) use ($excludedColumns) {
                return !in_array($column, $excludedColumns);
            })
        );
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
        $content = file_get_contents($modelFilePath);

        preg_match('/protected\s+\$fillable\s+=\s+((\[[^\]]*\])|(array\(\)))\s*;/s', $content, $matches);

        if (!empty($matches)) {
            $start = strpos($content, $matches[0]);
            $end = $start + strlen($matches[0]);

            $newContent = substr($content, 0, $start) . "protected \$fillable = $newFillableCode;\n" . substr($content, $end);

            file_put_contents($modelFilePath, $newContent);
        } else {
            // Se não encontrou a propriedade fillable, adiciona após a propriedade table
            preg_match('/protected\s+\$table\s*=[^;]*;/s', $content, $matches);

            if (!empty($matches)) {
                $start = strpos($content, $matches[0]) + strlen($matches[0]);
                $newContent = substr($content, 0, $start) . "\n\n    protected \$fillable = $newFillableCode;\n" . substr($content, $start);
            } else {
                // Se não encontrou a propriedade table, adiciona no final da classe
                $newContent = $content . "\n\n    protected \$fillable = $newFillableCode;\n";
            }

            file_put_contents($modelFilePath, $newContent);

            $color = new ConsoleColor();
            echo $color->apply('green', 'Fillable properties have been updated.');
        }
    }

    protected function printFillableCode(string $modelClass, string $newFillableCode): void
    {
        $oldFillableCode = $this->getCurrentFillableCode($modelClass);

        $diff = $this->generateFillableDiff($oldFillableCode, $newFillableCode);

        echo "Model: {$modelClass}\n";
        $this->generateFillableDiff($oldFillableCode, $newFillableCode);
        echo '----------------------------------------';
        echo "\n\n";

    }

    protected function getCurrentFillableCode(string $modelClass): array
    {
        $fillableColumns = [];
        $reflection = new ReflectionClass($modelClass);
        if ($reflection->hasProperty('fillable')) {
            $fillableProperty = $reflection->getProperty('fillable');
            $fillableColumns = $fillableProperty->getValue($reflection->newInstanceWithoutConstructor());
        }

        return $fillableColumns;
    }

    public function generateFillableDiff(string $oldFillableCode, string $newFillableCode): string
    {
        $diffRemove = '';
        $diffAdd = '';

        $color = new ConsoleColor();
        foreach ($oldFillableLines as $line) {
            if (!in_array($line, $newFillableLines)) {
                $diffRemove .= "- " . $line . "\n";
            }
        }

        foreach ($newFillableLines as $line) {
            if (!in_array($line, $oldFillableLines)) {
                $diffAdd .= "+ " . $line . "\n";
            }
        }

        if (empty($diffRemove) && empty($diffAdd)) {
            echo  $color->apply('blue', 'No changes' . "\n");
            return;
        }

        if (!empty($diffRemove)) {
            echo $color->apply('red', $diffRemove);
        }

        if (!empty($diffAdd)) {
            echo $color->apply('green', $diffAdd);
        }
    }

    public function getAllModels($projectPath = '', $modelDirectories = ['app/Models']): array
    {
        $models = [];
        $projectPath = !empty($projectPath) ? $projectPath : base_path();

        foreach ($modelDirectories as $directory) {
            $finder = new Finder();
            $finder->files()->in($projectPath . '/' . $directory)->name('*.php');

            foreach ($finder as $file) {
                $className = $this->extractNamespace($file);

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

    public function extractNamespace(SplFileInfo $file) : string
    {
        $path = $file->getRealPath() !== false ? $file->getRealPath() : $file->getPathname();
        $contents = file_exists($path) ? file_get_contents($path) : $path;

        $namespace = '';
        $class = '';
        $gettingNamespace = false;
        $gettingClass = false;

        foreach (token_get_all($contents) as $token) {

            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $gettingNamespace = true;
            }

            if (is_array($token) && $token[0] == T_CLASS) {
                $gettingClass = true;
            }

            if ($gettingNamespace === true) {
                if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NS_SEPARATOR])) {
                    $namespace .= $token[1];
                } else if ($token === ';') {
                    $gettingNamespace = false;
                }
            }

            if ($gettingClass === true) {
                if (is_array($token) && $token[0] == T_STRING) {
                    $class = $token[1];
                    break;
                }
            }
        }

        return $namespace ? $namespace . '\\' . $class : $class;
    }
}
