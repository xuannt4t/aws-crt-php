<?php

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectTaskVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            $table->string('task_visibility', 16)
                ->default(ProjectTaskVisibility::Own->value)
                ->after('role');
        });

        DB::table('project_members')
            ->where('role', ProjectMemberRole::Manager->value)
            ->update(['task_visibility' => ProjectTaskVisibility::All->value]);
    }

    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table): void {
            $table->dropColumn('task_visibility');
        });
    }
};
