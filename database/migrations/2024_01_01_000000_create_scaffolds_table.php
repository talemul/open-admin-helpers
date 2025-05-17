<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScaffoldsTable extends Migration
{
    public function up()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        Schema::connection($connection)->create(config('admin.extensions.helpers.scaffolds', 'helper_scaffolds'), function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('model_name')->nullable();
            $table->string('controller_name')->nullable();
            $table->json('create_options')->nullable(); // stores selected checkboxes as JSON
            $table->string('primary_key')->default('id');
            $table->boolean('timestamps')->default(true);
            $table->boolean('soft_deletes')->default(false);
            $table->timestamps();
        });

        Schema::connection($connection)->create(config('admin.extensions.helpers.scaffold_details', 'scaffold_details'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('scaffold_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->boolean('nullable')->default(false);
            $table->string('key')->nullable();
            $table->string('default')->nullable();
            $table->string('comment')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        Schema::connection($connection)->dropIfExists(config('admin.extensions.helpers.scaffolds', 'helper_scaffolds'));
        Schema::connection($connection)->dropIfExists(config('admin.extensions.helpers.scaffold_details', 'scaffold_details'));
    }
}

