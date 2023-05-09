<?php

use Illuminate\Support\Str;
use Jrbarros\LaravelUpdateFillable\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function generateFillableCode(string $table, array $fillableColumns): string
{
    $modelName = Str::studly(Str::singular($table));

    $fillableCode = '';
    if (! empty($fillableColumns)) {
        $fillableCode = "     protected \$fillable = ['".implode("', '", $fillableColumns)."'];\n\n";
    }

    $code = "<?php\n\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\n\nclass $modelName extends Model\n{\n$fillableCode}";

    return $code;
}
