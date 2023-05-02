<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('test_table', function (Blueprint $table) {
            $table->id();
            $table->string('name',100);
            $table->string('password',200);
            $table->text('description');
            $table->timestamps();
        });
    }
};
