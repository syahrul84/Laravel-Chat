<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Infrastructure\Repositories\ChannelRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ChannelRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ChannelRepository;
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function test_it_can_create_a_channel(): void
    {
        $user = User::factory()->create();

        $channel = $this->repository->create([
            'name' => 'general',
            'description' => 'General discussion',
            'type' => 'public',
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertTrue($channel->exists);
        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'name' => 'general',
            'slug' => 'general',
            'description' => 'General discussion',
            'type' => 'public',
            'created_by' => $user->id,
        ]);
    }

    // ─── Find by ID ──────────────────────────────────────────────────────────

    public function test_it_can_find_channel_by_id(): void
    {
        $channel = Channel::factory()->create();

        $found = $this->repository->findById($channel->id);

        $this->assertNotNull($found);
        $this->assertEquals($channel->id, $found->id);
        $this->assertEquals($channel->name, $found->name);
    }

    public function test_it_returns_null_for_nonexistent_channel(): void
    {
        $found = $this->repository->findById(99999);

        $this->assertNull($found);
    }

    // ─── List Public ─────────────────────────────────────────────────────────

    public function test_it_can_list_public_channels(): void
    {
        Channel::factory()->count(3)->create(['type' => 'public']);

        $result = $this->repository->listPublic(perPage: 10);

        $this->assertCount(3, $result->items());
        $this->assertEquals(3, $result->total());
    }

    public function test_it_does_not_list_private_channels_in_public_list(): void
    {
        Channel::factory()->count(2)->create(['type' => 'public']);
        Channel::factory()->count(3)->create(['type' => 'private']);

        $result = $this->repository->listPublic(perPage: 10);

        $this->assertCount(2, $result->items());
        $this->assertEquals(2, $result->total());

        foreach ($result->items() as $channel) {
            $this->assertEquals('public', $channel->type);
        }
    }

    // ─── Membership ──────────────────────────────────────────────────────────

    public function test_it_can_add_member_to_channel(): void
    {
        $channel = Channel::factory()->create();
        $user = User::factory()->create();

        $this->repository->addMember($channel, $user);

        $this->assertDatabaseHas('channel_user', [
            'channel_id' => $channel->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_it_can_check_membership(): void
    {
        $channel = Channel::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();

        $this->repository->addMember($channel, $member);

        $this->assertTrue($this->repository->isMember($channel, $member));
        $this->assertFalse($this->repository->isMember($channel, $stranger));
    }

    public function test_it_can_list_channels_for_user(): void
    {
        $user = User::factory()->create();

        $joined = Channel::factory()->count(2)->create();
        foreach ($joined as $channel) {
            $this->repository->addMember($channel, $user);
        }

        // Create channels the user has NOT joined
        Channel::factory()->count(3)->create();

        $result = $this->repository->listForUser($user, perPage: 10);

        $this->assertCount(2, $result->items());
        $this->assertEquals(2, $result->total());
    }
}
