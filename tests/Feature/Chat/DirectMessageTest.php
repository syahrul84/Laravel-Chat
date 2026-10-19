<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domains\Chat\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    // ─── Start ───────────────────────────────────────────────────────────────

    public function test_guest_cannot_start_a_direct_message(): void
    {
        $other = User::factory()->create();

        $this->post('/direct-messages', ['user_id' => $other->id])->assertRedirect('/login');
    }

    public function test_user_can_start_a_direct_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $response = $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);

        $dm = Channel::where('type', 'direct')->firstOrFail();
        $response->assertRedirect(route('channels.show', $dm));
        $this->assertNull($dm->name);
        $this->assertTrue($dm->hasMember($alice));
        $this->assertTrue($dm->hasMember($bob));
        $this->assertSame(2, $dm->members()->count());
    }

    public function test_starting_again_from_either_side_reuses_the_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);
        $this->actingAs($bob)->post('/direct-messages', ['user_id' => $alice->id]);

        $this->assertSame(1, Channel::where('type', 'direct')->count());
    }

    public function test_starting_again_after_leaving_restores_membership(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);
        $dm = Channel::where('type', 'direct')->firstOrFail();
        $dm->members()->detach($bob->id);

        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);

        $this->assertTrue($dm->fresh()->hasMember($bob));
    }

    public function test_user_cannot_direct_message_themselves(): void
    {
        $alice = User::factory()->create();

        $response = $this->actingAs($alice)->post('/direct-messages', ['user_id' => $alice->id]);

        $response->assertSessionHasErrors('user_id');
        $this->assertSame(0, Channel::where('type', 'direct')->count());
    }

    public function test_user_cannot_direct_message_unknown_user(): void
    {
        $alice = User::factory()->create();

        $this->actingAs($alice)->post('/direct-messages', ['user_id' => 99999])
            ->assertSessionHasErrors('user_id');
    }

    // ─── Listing & viewing ───────────────────────────────────────────────────

    public function test_direct_messages_are_listed_separately_with_the_other_person(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);

        $this->actingAs($alice)->get('/channels')->assertInertia(fn ($page) => $page
            ->has('channels.data', 0)
            ->has('myChannels.data', 0)
            ->has('sidebar.directMessages', 1)
            ->where('sidebar.directMessages.0.partner', ['id' => $bob->id, 'name' => $bob->name])
        );

        $this->actingAs($bob)->get('/channels')->assertInertia(fn ($page) => $page
            ->where('sidebar.directMessages.0.partner', ['id' => $alice->id, 'name' => $alice->name])
        );
    }

    public function test_direct_message_page_shows_the_other_person(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);
        $dm = Channel::where('type', 'direct')->firstOrFail();

        $this->actingAs($alice)->get("/channels/{$dm->id}")->assertInertia(fn ($page) => $page
            ->component('Chat/Channel')
            ->where('channel.type', 'direct')
            ->where('partner', ['id' => $bob->id, 'name' => $bob->name])
            ->has('sidebar.directMessages', 1)
        );
    }

    public function test_outsider_cannot_view_or_read_a_direct_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $eve = User::factory()->create();
        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);
        $dm = Channel::where('type', 'direct')->firstOrFail();

        $this->actingAs($eve)->get("/channels/{$dm->id}")->assertForbidden();
        $this->actingAs($eve)->getJson("/channels/{$dm->id}/messages")->assertForbidden();
        $this->actingAs($eve)->post("/channels/{$dm->id}/join")->assertForbidden();
    }

    public function test_nobody_can_be_invited_into_a_direct_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $eve = User::factory()->create();
        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);
        $dm = Channel::where('type', 'direct')->firstOrFail();

        $this->actingAs($alice)->post("/channels/{$dm->id}/members", ['email' => $eve->email])
            ->assertForbidden();
        $this->assertFalse($dm->fresh()->hasMember($eve));
    }

    public function test_members_can_send_direct_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->post('/direct-messages', ['user_id' => $bob->id]);
        $dm = Channel::where('type', 'direct')->firstOrFail();

        $this->actingAs($alice)->postJson("/channels/{$dm->id}/messages", ['content' => 'hi bob'])->assertOk();

        $this->assertDatabaseHas('messages', ['channel_id' => $dm->id, 'sender_id' => $alice->id, 'content' => 'hi bob']);
    }

    // ─── People search ───────────────────────────────────────────────────────

    public function test_user_search_returns_names_only_and_excludes_self(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Tan']);
        $ali = User::factory()->create(['name' => 'Ali Baba']);
        User::factory()->create(['name' => 'Bob']);

        $response = $this->actingAs($alice)->getJson('/users/search?q=ali');

        $response->assertOk();
        $response->assertExactJson([['id' => $ali->id, 'name' => 'Ali Baba']]);
    }

    public function test_user_search_requires_a_query(): void
    {
        $alice = User::factory()->create();

        $this->actingAs($alice)->getJson('/users/search')->assertUnprocessable();
    }
}
