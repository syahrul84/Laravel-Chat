<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domains\Chat\Events\MessageSent;
use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    // ─── Send ────────────────────────────────────────────────────────────────

    public function test_member_can_send_a_message(): void
    {
        Event::fake();

        $user    = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'Hello world!',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'sender_id'  => $user->id,
            'channel_id' => $channel->id,
            'content'    => 'Hello world!',
        ]);
    }

    public function test_message_is_broadcast_when_sent(): void
    {
        Event::fake();

        $user    = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'Hello world!',
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_non_member_cannot_send_a_message(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'Hello world!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_cannot_be_empty(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => '',
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertDatabaseCount('messages', 0);
    }

    // ─── List ────────────────────────────────────────────────────────────────

    public function test_member_can_load_messages_for_channel(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        Message::factory()->count(5)->create([
            'channel_id' => $channel->id,
            'sender_id'  => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/channels/{$channel->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }
}
