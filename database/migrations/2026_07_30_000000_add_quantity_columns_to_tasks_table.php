<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedInteger('planned_quantity')->nullable()->after('progress');
            $table->unsignedInteger('actual_quantity')->nullable()->after('planned_quantity');
            $table->string('quantity_unit', 30)->nullable()->after('actual_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['planned_quantity', 'actual_quantity', 'quantity_unit']);
        });
    }
};
