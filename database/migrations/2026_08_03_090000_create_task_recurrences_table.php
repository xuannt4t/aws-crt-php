<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 16);
            $table->unsignedInteger('planned_quantity')->nullable();
            $table->string('quantity_unit', 32)->nullable();
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('interval')->default(1);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('start_date');
            $table->time('due_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('last_generated_for')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'last_generated_for']);
            $table->index('organization_unit_id');
            $table->index('assignee_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_recurrences');
    }
};
