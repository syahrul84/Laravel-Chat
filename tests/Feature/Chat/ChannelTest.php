<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domains\Chat\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    // ─── List ────────────────────────────────────────────────────────────────

    public function test_guest_cannot_list_channels(): void
    {
        $this->get('/channels')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_list_public_channels(): void
    {
        $user = User::factory()->create();
        Channel::factory()->count(3)->create();

        $response = $this->actingAs($user)->get('/channels');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->has('channels.data', 3)
        );
    }

    // ─── Create ──────────────────────────────────────────────────────────────

    public function test_guest_cannot_create_a_channel(): void
    {
        $this->post('/channels', ['name' => 'general'])->assertRedirect('/login');
    }

    public function test_user_can_create_a_public_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/channels', [
            'name' => 'general',
            'type' => 'public',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('channels', ['name' => 'general', 'type' => 'public']);

        // Creator is automatically a member
        $channel = Channel::where('name', 'general')->first();
        $this->assertTrue($channel->hasMember($user));
    }

    public function test_channel_creation_fails_without_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/channels', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_channel_creation_fails_with_duplicate_name(): void
    {
        $user = User::factory()->create();
        Channel::factory()->create(['name' => 'general']);

        $response = $this->actingAs($user)->post('/channels', ['name' => 'general']);

        $response->assertSessionHasErrors('name');
    }

    // ─── Join ────────────────────────────────────────────────────────────────

    public function test_user_can_join_a_public_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['type' => 'public']);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/join");

        $response->assertRedirect();
        $this->assertTrue($channel->fresh()->hasMember($user));
    }

    public function test_user_cannot_join_a_private_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->private()->create();

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/join");

        $response->assertForbidden();
        $this->assertFalse($channel->fresh()->hasMember($user));
    }

    // ─── Show ────────────────────────────────────────────────────────────────

    public function test_member_can_view_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $response = $this->actingAs($user)->get("/channels/{$channel->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Channel')
            ->has('channel')
        );
    }

    public function test_non_member_cannot_view_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)->get("/channels/{$channel->id}");

        $response->assertForbidden();
    }
}
