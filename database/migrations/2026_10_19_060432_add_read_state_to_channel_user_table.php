<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_user', function (Blueprint $table) {
            // Message ids rather than timestamps: exact even for messages sent in the same second
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            // Messages up to this id are hidden for this member ("X" on a direct message)
            $table->unsignedBigInteger('cleared_message_id')->nullable();
            // Set when the member closes a direct message; cleared when a new message arrives
            $table->timestamp('hidden_at')->nullable();
        });

        // Existing members start with their channels' history already read
        DB::table('channel_user')->update([
            'last_read_message_id' => DB::raw('(SELECT MAX(messages.id) FROM messages WHERE messages.channel_id = channel_user.channel_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('channel_user', function (Blueprint $table) {
            $table->dropColumn(['last_read_message_id', 'cleared_message_id', 'hidden_at']);
        });
    }
};
