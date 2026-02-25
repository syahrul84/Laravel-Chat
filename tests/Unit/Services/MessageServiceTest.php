<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domains\Chat\Events\MessageSent;
use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Services\MessageService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageService::class);
    }

    // ─── Send Message ────────────────────────────────────────────────────────

    public function test_it_creates_message_and_broadcasts_event(): void
    {
        Event::fake();

        $user    = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $message = $this->service->send($channel, $user, 'Hello from service test!');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertDatabaseHas('messages', [
            'id'         => $message->id,
            'sender_id'  => $user->id,
            'channel_id' => $channel->id,
            'content'    => 'Hello from service test!',
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($message) {
            return $event->message->id === $message->id;
        });
    }

    public function test_it_throws_authorization_exception_for_non_member(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create();

        // User is NOT a member of the channel
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You must be a member of this channel to send messages.');

        $this->service->send($channel, $user, 'This should fail');
    }

    public function test_it_loads_sender_relationship(): void
    {
        Event::fake();

        $user    = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $message = $this->service->send($channel, $user, 'Check relationships');

        $this->assertTrue($message->relationLoaded('sender'));
        $this->assertEquals($user->id, $message->sender->id);
        $this->assertEquals($user->name, $message->sender->name);
    }
}
