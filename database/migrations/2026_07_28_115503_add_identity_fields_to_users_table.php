<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_unit_id')->nullable()->after('id')->constrained('organization_units')->nullOnDelete();
            $table->boolean('is_system_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_system_admin');
            $table->string('employee_code')->nullable()->unique()->after('is_active');
            $table->string('phone')->nullable()->after('employee_code');
            $table->string('job_title')->nullable()->after('phone');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_unit_id']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'organization_unit_id',
                'is_system_admin',
                'is_active',
                'employee_code',
                'phone',
                'job_title',
            ]);
        });
    }
};
