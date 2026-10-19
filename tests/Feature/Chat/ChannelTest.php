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

    public function test_channel_pages_do_not_expose_creator_email(): void
    {
        $creator = User::factory()->create();
        $channel = Channel::factory()->create(['created_by' => $creator->id]);
        $channel->members()->attach($creator->id, ['joined_at' => now()]);

        $this->actingAs($creator)->get('/channels')
            ->assertInertia(fn ($page) => $page->where('channels.data.0.creator', ['id' => $creator->id, 'name' => $creator->name]));
        $this->actingAs($creator)->get("/channels/{$channel->id}")
            ->assertInertia(fn ($page) => $page->where('channel.creator', ['id' => $creator->id, 'name' => $creator->name]));
    }

    public function test_browsing_public_pages_does_not_page_my_channels(): void
    {
        $user = User::factory()->create();
        $mine = Channel::factory()->create();
        $mine->members()->attach($user->id, ['joined_at' => now()]);
        Channel::factory()->count(25)->create();

        $response = $this->actingAs($user)->get('/channels?page=2');

        $response->assertInertia(fn ($page) => $page
            ->where('channels.current_page', 2)
            ->has('myChannels.data', 1)
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

    // ─── Slugs ───────────────────────────────────────────────────────────────

    public function test_channels_whose_names_share_a_slug_can_both_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/channels', ['name' => 'Room'])->assertRedirect();
        $this->actingAs($user)->post('/channels', ['name' => 'Room!'])->assertRedirect();

        $this->assertDatabaseCount('channels', 2);
        $this->assertNotSame(
            Channel::where('name', 'Room')->value('slug'),
            Channel::where('name', 'Room!')->value('slug'),
        );
    }

    public function test_channels_named_only_with_symbols_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/channels', ['name' => '!!!'])->assertRedirect();
        $this->actingAs($user)->post('/channels', ['name' => '???'])->assertRedirect();

        $this->assertDatabaseCount('channels', 2);
        $this->assertNotContains('', Channel::pluck('slug')->all());
    }

    // ─── Leave ───────────────────────────────────────────────────────────────

    public function test_member_can_leave_a_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id, ['joined_at' => now()]);

        $response = $this->actingAs($user)->post("/channels/{$channel->id}/leave");

        $response->assertRedirect(route('channels.index'));
        $this->assertFalse($channel->fresh()->hasMember($user));
    }

    // ─── Invite ──────────────────────────────────────────────────────────────

    public function test_member_can_invite_a_user_to_a_private_channel(): void
    {
        $member = User::factory()->create();
        $invitee = User::factory()->create();
        $channel = Channel::factory()->private()->create();
        $channel->members()->attach($member->id, ['joined_at' => now()]);

        $response = $this->actingAs($member)->post("/channels/{$channel->id}/members", [
            'email' => $invitee->email,
        ]);

        $response->assertRedirect(route('channels.show', $channel));
        $this->assertTrue($channel->fresh()->hasMember($invitee));
        $this->actingAs($invitee)->get("/channels/{$channel->id}")->assertOk();
    }

    public function test_non_member_cannot_invite_to_a_channel(): void
    {
        $outsider = User::factory()->create();
        $invitee = User::factory()->create();
        $channel = Channel::factory()->private()->create();

        $response = $this->actingAs($outsider)->post("/channels/{$channel->id}/members", [
            'email' => $invitee->email,
        ]);

        $response->assertForbidden();
        $this->assertFalse($channel->fresh()->hasMember($invitee));
    }

    public function test_invite_requires_an_existing_user_email(): void
    {
        $member = User::factory()->create();
        $channel = Channel::factory()->private()->create();
        $channel->members()->attach($member->id, ['joined_at' => now()]);

        $response = $this->actingAs($member)->post("/channels/{$channel->id}/members", [
            'email' => 'nobody@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
