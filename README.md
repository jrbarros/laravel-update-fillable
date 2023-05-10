# Laravel Update Fillable
Laravel Update Fillable is a command-line tool that updates the $fillable property of Eloquent models based on the current database schema.

## Installation
#### Install the package via Composer:

```bash
composer require jrbarros/laravel-update-fillable --dev
```

## Usage
To update the $fillable property of all Eloquent models in your project:

```bash
php artisan update:fillable
```

To update the $fillable property of a specific Eloquent model:

```bash
php artisan update:fillable ModelName
```

To update the $fillable property of all Eloquent models in a specific directory (e.g. app/Models):

```bash
php artisan update:fillable --directories=app/Models
```
To exclude certain columns from the $fillable property:

```bash
php artisan update:fillable --exclude=id,created_at,updated_at
```

To write the changes to the model files:

```bash
php artisan update:fillable --write
```
To specify the path to your project:

```bash
php artisan update:fillable --path=/path/to/project
```
# Options
### The following options are available:

* `--write`: Write changes to the model files (default: false)
* `--exclude`: Comma-separated list of column names to exclude from the fillable property (default: "id")
* `--path`: The path to the project (default: base_path())
* `--directories`: Comma-separated list of directories where the models are located (default: "app")
model: The name of a specific model to update (optional)

# Customization
You can customize the behavior of the package by creating a nonFillable property in your model classes. This property should be an array of column names that should be excluded from the fillable property:

```php
class User extends Model
{
    protected $nonFillable = ['password'];
    protected $fillable = ['name', 'email'];
}
```

# TODO

- [ ] Remove migration, model e config and use what you need to test us, creating less useless code
- [ ] Clean existing tests and create more
- [ ] Refactor any function and check compatibility with older versions of laravel

## License
Laravel Update Fillable is open-sourced software licensed under the MIT license.

