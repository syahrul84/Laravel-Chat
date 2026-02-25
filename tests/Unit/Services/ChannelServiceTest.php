<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Services\ChannelService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChannelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ChannelService::class);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function test_it_creates_channel_and_adds_creator_as_member(): void
    {
        $user = User::factory()->create();

        $channel = $this->service->create($user, 'engineering', 'public', 'Engineering chat');

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertTrue($channel->exists);
        $this->assertDatabaseHas('channels', [
            'id'          => $channel->id,
            'name'        => 'engineering',
            'type'        => 'public',
            'description' => 'Engineering chat',
            'created_by'  => $user->id,
        ]);

        // Creator should automatically be a member
        $this->assertDatabaseHas('channel_user', [
            'channel_id' => $channel->id,
            'user_id'    => $user->id,
        ]);
    }

    // ─── List Public ─────────────────────────────────────────────────────────

    public function test_it_can_list_public_channels(): void
    {
        Channel::factory()->count(3)->create(['type' => 'public']);
        Channel::factory()->count(2)->create(['type' => 'private']);

        $result = $this->service->listPublic(perPage: 10);

        $this->assertCount(3, $result->items());
        $this->assertEquals(3, $result->total());

        foreach ($result->items() as $channel) {
            $this->assertEquals('public', $channel->type);
        }
    }

    // ─── Join ─────────────────────────────────────────────────────────────────

    public function test_it_allows_user_to_join_public_channel(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create(['type' => 'public']);

        $this->service->join($channel, $user);

        $this->assertDatabaseHas('channel_user', [
            'channel_id' => $channel->id,
            'user_id'    => $user->id,
        ]);
    }

    public function test_it_prevents_joining_private_channel(): void
    {
        $user    = User::factory()->create();
        $channel = Channel::factory()->create(['type' => 'private']);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Cannot join a private channel without an invitation.');

        $this->service->join($channel, $user);
    }
}
