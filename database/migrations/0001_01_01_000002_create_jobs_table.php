<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The queue tables live on their own SQLite connection/file (sqlite_queue),
     * so the worker's constant polling doesn't contend for the single SQLite
     * writer with ordinary web requests on the app DB (which caused "database
     * is locked"). In tests the queue is forced synchronous, so these tables
     * aren't used there.
     */
    private const string CONNECTION = 'sqlite_queue';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(self::CONNECTION)->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::connection(self::CONNECTION)->create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::connection(self::CONNECTION)->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('jobs');
        Schema::connection(self::CONNECTION)->dropIfExists('job_batches');
        Schema::connection(self::CONNECTION)->dropIfExists('failed_jobs');
    }
};
