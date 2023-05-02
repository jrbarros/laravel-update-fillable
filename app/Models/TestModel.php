<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class TestModel extends Model
{
    protected $table = 'test_table';
    protected $fillable = ['name', 'description'];
    protected $dates = ['created_at', 'updated_at'];
    protected $hidden = ['password'];
    public static $nonFillable = ['status'];
}
