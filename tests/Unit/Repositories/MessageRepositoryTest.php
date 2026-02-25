<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Repositories\MessageRepositoryInterface;
use App\Infrastructure\Repositories\MessageRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private MessageRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MessageRepository();
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function test_it_can_create_a_message(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create();

        $message = $this->repository->create([
            'sender_id'  => $user->id,
            'channel_id' => $channel->id,
            'content'    => 'Hello from the repository test!',
        ]);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertTrue($message->exists);
        $this->assertDatabaseHas('messages', [
            'id'         => $message->id,
            'sender_id'  => $user->id,
            'channel_id' => $channel->id,
            'content'    => 'Hello from the repository test!',
        ]);
    }

    // ─── Find by ID ──────────────────────────────────────────────────────────

    public function test_it_can_find_message_by_id(): void
    {
        $message = Message::factory()->create();

        $found = $this->repository->findById($message->id);

        $this->assertNotNull($found);
        $this->assertEquals($message->id, $found->id);
        $this->assertEquals($message->content, $found->content);
    }

    public function test_it_returns_null_for_nonexistent_message(): void
    {
        $found = $this->repository->findById(99999);

        $this->assertNull($found);
    }

    // ─── Paginated for Channel ───────────────────────────────────────────────

    public function test_it_can_get_paginated_messages_for_channel(): void
    {
        $channel = Channel::factory()->create();
        $user    = User::factory()->create();

        Message::factory()->count(5)->create([
            'channel_id' => $channel->id,
            'sender_id'  => $user->id,
        ]);

        // Create messages in a different channel to ensure isolation
        Message::factory()->count(3)->create();

        $result = $this->repository->forChannel($channel, perPage: 10);

        $this->assertCount(5, $result->items());
        $this->assertEquals(5, $result->total());
    }

    public function test_messages_are_ordered_oldest_first(): void
    {
        $channel = Channel::factory()->create();
        $user    = User::factory()->create();

        $oldest = Message::factory()->create([
            'channel_id' => $channel->id,
            'sender_id'  => $user->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $middle = Message::factory()->create([
            'channel_id' => $channel->id,
            'sender_id'  => $user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $newest = Message::factory()->create([
            'channel_id' => $channel->id,
            'sender_id'  => $user->id,
            'created_at' => now(),
        ]);

        $result = $this->repository->forChannel($channel);

        $items = $result->items();
        $this->assertEquals($oldest->id, $items[0]->id);
        $this->assertEquals($middle->id, $items[1]->id);
        $this->assertEquals($newest->id, $items[2]->id);
    }
}
