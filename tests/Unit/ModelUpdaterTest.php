<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;

use Jrbarros\LaravelUpdateFillable\LaravelUpdateFillableUpdater;
use App\Models\TestModel;
use org\bovigo\vfs\vfsStream;


beforeEach(function () {
    uses( DatabaseMigrations::class);
});

it('should return all columns for table', function () {

    $table = (new TestModel())->getTable();
    $updater = new LaravelUpdateFillableUpdater();

    $expectedColumns = ['id', 'name', 'description', 'created_at', 'updated_at', 'password'];

    $actualColumns = $updater->getColumnsForTable($table);

    expect(sort($actualColumns))->toEqual(sort($expectedColumns));
});

it('should return column data type for table', function () {

    $table = (new TestModel())->getTable();
    $column = 'created_at';
    $updater = new LaravelUpdateFillableUpdater();

    $expectedDataType = 'datetime';

    $actualDataType = $updater->getColumnDataType($table, $column);

    expect($actualDataType)->toBe($expectedDataType);
});

it('should return fillable columns for model', function () {
    $updater = new LaravelUpdateFillableUpdater();

    $expectedColumns = ['name', 'description'];
    $actualColumns = $updater->getFillableColumns(TestModel::class, 'test_table', ['id']);

    expect(sort($actualColumns))->toBe(sort($expectedColumns));
});

it('should return all models in the given directories', function () {
    $root = vfsStream::setup();
    $appDir = vfsStream::newDirectory('app')->at($root);
    $otherDir = vfsStream::newDirectory('other')->at($root);

    $model1 = vfsStream::newFile('TestModel1.php')->at($appDir);
    $model1->setContent('<?php namespace App\\Models; use Illuminate\\Database\\Eloquent\\Model; class TestModel1 extends Model {}');

    $model2 = vfsStream::newFile('TestModel2.php')->at($appDir);
    $model2->setContent('<?php namespace App\\Models; use Illuminate\\Database\\Eloquent\\Model; class TestModel2 extends Model {}');

    $model3 = vfsStream::newFile('TestModel3.php')->at($otherDir);
    $model3->setContent('<?php namespace Other\\Models; use Illuminate\\Database\\Eloquent\\Model; class TestModel3 extends Model {}');

    $updater = new LaravelUpdateFillableUpdater();
    $models = $updater->getAllModels($root->url(), ['app', 'other']);

    $modelsArray = [
        'App\\Models\\TestModel1',
        'App\\Models\\TestModel2',
        'Other\\Models\\TestModel3',
    ];
    expect(sort($models))->toEqual(sort($modelsArray));
});

it('should return the correct model file path', function () {
    $updater = new LaravelUpdateFillableUpdater();

    $modelPath = $updater->getModelFilePath(TestModel::class);

    expect($modelPath)->toBeString();
    expect($modelPath)->toEndWith('app/Models/TestModel.php');
});



it('reads the code of a model file', function () {
    // Create a virtual model file
    $modelCode = <<<EOT
<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected \$fillable = ['name', 'email'];
}
EOT;

    $root = vfsStream::setup();
    $file = vfsStream::newFile('TestModel1.php')->at($root);
    $file->setContent($modelCode);

    // Test the readModelCode() method
    $updater = new LaravelUpdateFillableUpdater();
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('readModelCode');
    $method->setAccessible(true);

    $result = $method->invokeArgs($updater, [$file->url()]);

    expect($result)->toBe($modelCode);
});

it('gets all date, datetime and timestamp columns of a table', function () {
    // Create a virtual database table
    $root = vfsStream::setup();
    Schema::create('test_table_1', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->date('birthday');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();
    });
    $databasePath = vfsStream::url('root/database.sqlite');
    $database = new \Illuminate\Database\Capsule\Manager();
    $database->addConnection(['driver' => 'sqlite', 'database' => $databasePath]);
    $database->setAsGlobal();
    $database->bootEloquent();

    // Test the getDatesColumns() method
    // Test the getDatesColumns() method
    $updater = new LaravelUpdateFillableUpdater();
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('getDatesColumns');
    $method->setAccessible(true);

    $result = $method->invokeArgs($updater, ['test_table_1']);

    expect($result)->toEqual(['birthday']);
});

it('gets the current fillable code', function () {
    // Create a virtual model file
    $modelCode = <<<EOT
    <?php


    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class DefaultTestModel extends Model
    {
        protected \$fillable = ['name', 'email'];
        protected \$dates = ['date_column', 'timestamp_column'];
    }
    EOT;

    $root = vfsStream::setup();
    $file = vfsStream::newFile('DefaultTestModel.php')->at($root);
    $file->setContent($modelCode);

    // Create a mock for LaravelUpdateFillableUpdater
    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
        ->onlyMethods(['getModelFilePath'])
        ->getMock();

    $updater->method('getModelFilePath')->willReturn($file->url());

    // Test the getCurrentFillableCode() method
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('getCurrentFillableCode');
    $method->setAccessible(true);

    $result = $method->invokeArgs($updater, [$file->url()]);

   expect($result)->toContain("protected \$fillable = ['name', 'email'];");
});


it('updates the dates property of a model', function () {
    // Create a virtual model file
    $modelCode = <<<EOT
    <?php


    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class DefaultTestModel extends Model
    {
        protected \$fillable = ['name', 'email'];
    }
    EOT;

    $root = vfsStream::setup();
    $root = vfsStream::newDirectory('app')->at($root);
    $appDir = vfsStream::newDirectory('Models')->at($root);
    $file = vfsStream::newFile('DefaultTestModel.php')->at($appDir);
    $file->setContent($modelCode);


    // Create a mock for LaravelUpdateFillableUpdater
    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
        ->onlyMethods(['getModelFilePath'])
        ->getMock();

    $updater->method('getModelFilePath')->willReturn($file->url());

    // Test the updateDates() method
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('updateDates');
    $method->setAccessible(true);

    $method->invokeArgs($updater, ['DefaultTestModel', ['date_column', 'timestamp_column']]);

    $updatedModelCode = file_get_contents($file->url());

    expect($updatedModelCode)->toContain("protected \$fillable = ['name', 'email'];\n");
    expect($updatedModelCode)->toContain("protected \$dates = ['date_column', 'timestamp_column'];\n");
});

it('generates the fillable code for a model based on table columns', function () {
    $root = vfsStream::setup();
    $modelCode = <<<EOT
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class TestModel extends Model
    {
        protected \$table = 'test_table';
    }
    EOT;

    $tableColumns = ['id', 'name', 'email', 'password', 'created_at', 'updated_at'];

    $updater = new LaravelUpdateFillableUpdater();
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('generateFillableCode');
    $method->setAccessible(true);

    $result = $method->invokeArgs($updater, [$tableColumns]);

    expect($result)->toContain(...$tableColumns);
    expect($result)->toContain('protected $fillable');
});


it('returns the current fillable code', function () {
    // Cria um arquivo virtual de modelo
    $modelCode = <<<EOT
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class TestModel extends Model
    {
        protected \$fillable = ['name', 'email'];
    }
    EOT;

    $root = vfsStream::setup();
    $file = vfsStream::newFile('TestModel.php')->at($root);
    $file->setContent($modelCode);

    // Create a mock for LaravelUpdateFillableUpdater
    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
        ->onlyMethods(['getModelFilePath'])
        ->getMock();

    $updater->method('getModelFilePath')->willReturn($file->url());

    // Testa o método getCurrentFillableCode()
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('getCurrentFillableCode');
    $method->setAccessible(true);

    $result = $method->invokeArgs($updater, [$file->url()]);

    expect($result)->toBe("protected \$fillable = ['name', 'email'];");
});


//it('generates the difference between the current and generated fillable code', function () {
//    // Create a virtual model file
//    $table = 'test_table';
//    $fillableColumns = ['name', 'email', 'password'];
//    $modelCode = generateModelCode($table, $fillableColumns);
//
//    $root = vfsStream::setup();
//    $file = vfsStream::newFile('TestModel.php')->at($root);
//    $file->setContent($modelCode);
//
//    // Test the generateFillableDiff() method
//    $updater = new LaravelUpdateFillableUpdater();
//    $reflection = new ReflectionClass($updater);
//    $method = $reflection->getMethod('generateFillableDiff');
//    $method->setAccessible(true);
//
//    // Create a mock for LaravelUpdateFillableUpdater
//    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
//        ->onlyMethods(['getModelFilePath'])
//        ->getMock();
//
//    $updater->method('getModelFilePath')->willReturn($file->url());
//
//    $methodGetCurrentFillableCode = $reflection->getMethod('getCurrentFillableCode');
//    $currentFillableCode = $methodGetCurrentFillableCode->invokeArgs($updater, [$file->url()]);
//
//    $methodGenerateFillableCode = $reflection->getMethod('getCurrentFillableCode');
//    $generatedFillableCode = $methodGenerateFillableCode->invokeArgs($updater,[$table]);
//
//    $result = $method->invokeArgs($updater, [$currentFillableCode, $generatedFillableCode]);
//
//    $expectedResult = [
//        'added' => [],
//        'removed' => [],
//        'changed' => []
//    ];
//    foreach ($fillableColumns as $column) {
//        if (!str_contains($currentFillableCode, $column) && str_contains($generatedFillableCode, $column)) {
//            $expectedResult['added'][] = $column;
//        } elseif (str_contains($currentFillableCode, $column) && !str_contains($generatedFillableCode, $column)) {
//            $expectedResult['removed'][] = $column;
//        } elseif (str_contains($currentFillableCode, $column) && str_contains($generatedFillableCode, $column)
//            && strpos($currentFillableCode, $column) !== strpos($generatedFillableCode, $column)) {
//            $expectedResult['changed'][] = $column;
//        }
//    }
//    dd($expectedResult);
//
//    expect($result)->toBe($expectedResult);
//});

it('generates the difference between the current and generated fillable code', function () {
    $table = 'test_table';
    $fillableColumns = ['name', 'email', 'password'];

    // create a temporary file for the model class
    $root = vfsStream::setup();
    $file = vfsStream::newFile('TestModel.php')->at($root);

    // generate model code with fillable columns
    $modelCode = '<?php' . "\n\n";
    $modelCode .= 'namespace App\Models;' . "\n\n";
    $modelCode .= 'use Illuminate\Database\Eloquent\Model;' . "\n\n";
    $modelCode .= 'class TestModel extends Model' . "\n";
    $modelCode .= '{' . "\n";
    $modelCode .= '    protected $fillable = [\'name\', \'email\', \'password\'];' . "\n";
    $modelCode .= '}' . "\n";

    $file->setContent($modelCode);

    $updater = new LaravelUpdateFillableUpdater();

    $reflection = new ReflectionClass($updater);

    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
        ->onlyMethods(['getModelFilePath'])
        ->getMock();

    $updater->method('getModelFilePath')->willReturn($file->url());

    $method = $reflection->getMethod('getCurrentFillableCode');
    $method->setAccessible(true);
    $currentFillableCode = $method->invokeArgs($updater, [$file->url()]);

    $method = $reflection->getMethod('generateFillableCode');
    $method->setAccessible(true);
    $generatedFillableCode = $method->invokeArgs($updater, [$fillableColumns]);

    $method = $reflection->getMethod('generateFillableDiff');
    $method->setAccessible(true);
    $result = $method->invokeArgs($updater, [$currentFillableCode, $generatedFillableCode]);

    $expectedResult = <<<OEL
    - protected \$fillable = ['name', 'email', 'password'];
    +     protected \$fillable = [
    +                 'name',
    +         'email',
    +         'password',
    +     ];

    OEL;

    expect($result)->toBe($expectedResult);
});

it('writes fillable code to model', function () {
    $fillableColumns = ['name', 'email', 'password'];
    $table = 'test_table';
    $modelCode = generateFillableCode($table, $fillableColumns);

    $root = vfsStream::setup();
    $modelFile = vfsStream::newFile('TestModel.php')->at($root);
    $modelFile->setContent($modelCode);

    $updater = new LaravelUpdateFillableUpdater();

    $reflection = new ReflectionClass($updater);
    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
        ->onlyMethods(['getModelFilePath'])
        ->getMock();

    $updater->method('getModelFilePath')->willReturn($modelFile->url());

    $readModelCodeMethod = $reflection->getMethod('readModelCode');
    $readModelCodeMethod->setAccessible(true);
    $readModelCodeMethod->invokeArgs($updater, [$modelFile->url()]);

    $newFillableCode = generateFillableCode($table, $fillableColumns);

    $writeFillableCodeToModelMethod = $reflection->getMethod('writeFillableCodeToModel');
    $writeFillableCodeToModelMethod->setAccessible(true);
    $writeFillableCodeToModelMethod->invokeArgs($updater, [$modelFile->url(), $newFillableCode]);

    $updatedModelCode = $modelFile->getContent();

    expect($updatedModelCode)->toContain($newFillableCode);
});

it('updates the fillable property of the specified model', function () {
    //Create a virtual model file
    $table = 'test_table';
    $fillableColumns = ['name', 'email', 'phone'];
    $modelCode = generateFillableCode($table, $fillableColumns);

    $root = vfsStream::setup();
    $file = vfsStream::newFile('TestModel.php')->at($root);
    $file->setContent($modelCode);

    // Test the updateFillable() method
    $updater = new LaravelUpdateFillableUpdater();
    $reflection = new ReflectionClass($updater);
    $updater = $this->getMockBuilder(LaravelUpdateFillableUpdater::class)
        ->onlyMethods(['getModelFilePath'])
        ->getMock();

    $updater->method('getModelFilePath')->willReturn($file->url());
    $getCurrentFillableCodeMethod = $reflection->getMethod('getCurrentFillableCode');
    $getCurrentFillableCodeMethod->setAccessible(true);
    $generateFillableCodeMethod = $reflection->getMethod('generateFillableCode');
    $generateFillableCodeMethod->setAccessible(true);
    $writeFillableCodeToModelMethod = $reflection->getMethod('writeFillableCodeToModel');
    $writeFillableCodeToModelMethod->setAccessible(true);

    $modelFilePath = $file->url();

    $newFillableColumns = ['name', 'email', 'phone', 'password'];
    $generateFillableCodeMethod->invokeArgs($updater, [$newFillableColumns]);
    $updater->updateFillable($modelFilePath, $newFillableColumns);
    $updatedFillableCode = $getCurrentFillableCodeMethod->invokeArgs($updater, [$modelFilePath]);

    expect($updatedFillableCode)->toContain(...$newFillableColumns);
});
