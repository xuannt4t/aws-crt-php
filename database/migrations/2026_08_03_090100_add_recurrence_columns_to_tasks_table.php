<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_recurrence_id')
                ->nullable()
                ->after('project_id')
                ->constrained('task_recurrences')
                ->nullOnDelete();

            $table->date('recurrence_date')->nullable()->after('task_recurrence_id');

            $table->unique(['task_recurrence_id', 'recurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['task_recurrence_id', 'recurrence_date']);
            $table->dropConstrainedForeignId('task_recurrence_id');
            $table->dropColumn('recurrence_date');
        });
    }
};
