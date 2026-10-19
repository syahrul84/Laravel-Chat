<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // Direct messages have no name of their own; the UI shows the other person's name
            $table->string('name')->nullable()->change();
            $table->enum('type', ['public', 'private', 'direct'])->default('public')->change();

            // "{lowerUserId}:{higherUserId}" — one conversation per pair of users
            $table->string('direct_key')->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropUnique(['direct_key']);
            $table->dropColumn('direct_key');
        });

        // Direct channels cannot exist once the type is narrowed back
        DB::table('channels')->where('type', 'direct')->delete();

        Schema::table('channels', function (Blueprint $table) {
            $table->enum('type', ['public', 'private'])->default('public')->change();
            $table->string('name')->nullable(false)->change();
        });
    }
};
