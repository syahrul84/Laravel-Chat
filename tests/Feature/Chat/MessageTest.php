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

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'Hello world!',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $user->id,
            'channel_id' => $channel->id,
            'content' => 'Hello world!',
        ]);
    }

    public function test_message_is_broadcast_when_sent(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'Hello world!',
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_non_member_cannot_send_a_message(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/messages", [
            'content' => 'Hello world!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_cannot_be_empty(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        Message::factory()->count(5)->create([
            'channel_id' => $channel->id,
            'sender_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/channels/{$channel->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_non_member_cannot_load_messages_for_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->private()->create();
        Message::factory()->create(['channel_id' => $channel->id]);

        $response = $this->actingAs($user)->getJson("/channels/{$channel->id}/messages");

        $response->assertForbidden();
    }

    public function test_loaded_messages_do_not_expose_sender_email(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);
        Message::factory()->create(['channel_id' => $channel->id, 'sender_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/channels/{$channel->id}/messages");

        $response->assertOk();
        $this->assertSame(['id', 'name'], array_keys($response->json('data.0.sender')));
        $this->assertStringNotContainsString($user->email, $response->getContent());
    }

    public function test_channel_page_shows_the_newest_messages_first_page(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        foreach (range(1, 60) as $i) {
            Message::factory()->create([
                'channel_id' => $channel->id,
                'sender_id' => $user->id,
                'content' => "message {$i}",
                'created_at' => now()->subMinutes(60 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get("/channels/{$channel->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Channel')
            ->has('messages.data', 50)
            ->where('messages.data.0.content', 'message 11')
            ->where('messages.data.49.content', 'message 60')
            ->where('messages.data.49.sender', ['id' => $user->id, 'name' => $user->name])
            ->where('messages.last_page', 2)
        );
    }

    public function test_member_can_load_older_messages_page(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        foreach (range(1, 60) as $i) {
            Message::factory()->create([
                'channel_id' => $channel->id,
                'sender_id' => $user->id,
                'content' => "message {$i}",
                'created_at' => now()->subMinutes(60 - $i),
            ]);
        }

        $response = $this->actingAs($user)->getJson("/channels/{$channel->id}/messages?page=2");

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('data.0.content', 'message 1');
        $response->assertJsonPath('data.9.content', 'message 10');
    }
}
