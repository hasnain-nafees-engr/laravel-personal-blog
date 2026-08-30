<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // why: a plain string column (not a native PostgreSQL enum type)
            // because altering a PG enum later needs a migration dance, while
            // a string + PHP enum cast gives the same safety in application
            // code and stays easy to extend.
            $table->string('role', 20)->default(UserRole::Author->value)->after('password');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
