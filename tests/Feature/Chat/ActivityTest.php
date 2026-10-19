<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Domains\Chat\Events\ChannelActivity;
use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Services\ChannelService;
use App\Domains\Chat\Services\DirectMessageService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    private function channelWith(User ...$members): Channel
    {
        $channel = Channel::factory()->create();
        foreach ($members as $member) {
            app(ChannelService::class)->join($channel, $member);
        }

        return $channel;
    }

    private function send(User $sender, Channel $channel, string $content = 'hello'): void
    {
        $this->actingAs($sender)->postJson("/channels/{$channel->id}/messages", ['content' => $content])->assertOk();
    }

    private function sidebarChannel(User $user, int $channelId): ?array
    {
        $page = $this->actingAs($user)->get('/chat')->viewData('page');

        return collect($page['props']['sidebar']['channels'])->firstWhere('id', $channelId);
    }

    // ─── Live alerts ─────────────────────────────────────────────────────────

    public function test_new_message_alerts_every_other_member(): void
    {
        Event::fake([ChannelActivity::class]);
        [$alice, $bob, $carol] = User::factory()->count(3)->create();
        $channel = $this->channelWith($alice, $bob, $carol);

        $this->send($alice, $channel, 'hello team');

        Event::assertDispatched(ChannelActivity::class, function (ChannelActivity $event) use ($bob, $carol) {
            $channels = collect($event->broadcastOn())->map->name->sort()->values()->all();

            return $channels === collect(["private-App.Models.User.{$bob->id}", "private-App.Models.User.{$carol->id}"])->sort()->values()->all()
                && $event->broadcastWith()['preview'] === 'hello team';
        });
    }

    // ─── Unread ──────────────────────────────────────────────────────────────

    public function test_sidebar_counts_unread_messages_from_others(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $channel = $this->channelWith($alice, $bob);

        $this->send($alice, $channel);
        $this->send($alice, $channel);

        $this->assertSame(2, $this->sidebarChannel($bob, $channel->id)['unread']);
        $this->assertSame(0, $this->sidebarChannel($alice, $channel->id)['unread']);
    }

    public function test_opening_a_channel_marks_it_read(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $channel = $this->channelWith($alice, $bob);
        $this->send($alice, $channel);

        $this->actingAs($bob)->get("/channels/{$channel->id}")->assertOk();

        $this->assertSame(0, $this->sidebarChannel($bob, $channel->id)['unread']);
    }

    public function test_mark_read_endpoint_clears_unread(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $channel = $this->channelWith($alice, $bob);
        $this->send($alice, $channel);

        $this->actingAs($bob)->post("/channels/{$channel->id}/read")->assertNoContent();

        $this->assertSame(0, $this->sidebarChannel($bob, $channel->id)['unread']);
    }

    public function test_non_member_cannot_mark_read(): void
    {
        $outsider = User::factory()->create();
        $channel = Channel::factory()->create();

        $this->actingAs($outsider)->post("/channels/{$channel->id}/read")->assertForbidden();
    }

    public function test_history_before_joining_is_not_unread(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $channel = $this->channelWith($alice);
        $this->send($alice, $channel);

        app(ChannelService::class)->join($channel, $bob);

        $this->assertSame(0, $this->sidebarChannel($bob, $channel->id)['unread']);
    }

    // ─── Close ("X") ─────────────────────────────────────────────────────────

    public function test_closing_a_direct_message_hides_it_and_clears_history_for_me_only(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $dm = app(DirectMessageService::class)->start($alice, $bob);
        $this->send($alice, $dm, 'old message');

        $this->actingAs($bob)->post("/channels/{$dm->id}/close")->assertRedirect(route('chat.index'));

        $this->actingAs($bob)->get('/chat')->assertInertia(fn ($page) => $page->has('sidebar.directMessages', 0));
        $this->actingAs($bob)->getJson("/channels/{$dm->id}/messages")->assertJsonCount(0, 'data');
        $this->actingAs($alice)->getJson("/channels/{$dm->id}/messages")->assertJsonCount(1, 'data');
        $this->assertSame(1, Message::count());
    }

    public function test_a_new_message_reopens_a_closed_direct_message_with_only_new_history(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $dm = app(DirectMessageService::class)->start($alice, $bob);
        $this->send($alice, $dm, 'old message');
        $this->actingAs($bob)->post("/channels/{$dm->id}/close");

        $this->send($alice, $dm, 'new message');

        $this->actingAs($bob)->get('/chat')->assertInertia(fn ($page) => $page
            ->has('sidebar.directMessages', 1)
            ->where('sidebar.directMessages.0.unread', 1)
        );
        $this->actingAs($bob)->getJson("/channels/{$dm->id}/messages")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'new message');
    }

    public function test_closing_a_channel_leaves_it(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $channel = $this->channelWith($alice, $bob);

        $this->actingAs($bob)->post("/channels/{$channel->id}/close")->assertRedirect(route('chat.index'));

        $this->assertFalse($channel->fresh()->hasMember($bob));
        $this->assertTrue($channel->fresh()->hasMember($alice));
    }

    // ─── Members & home ──────────────────────────────────────────────────────

    public function test_channel_page_lists_member_names(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $channel = $this->channelWith($bob, $alice);

        $this->actingAs($alice)->get("/channels/{$channel->id}")->assertInertia(fn ($page) => $page
            ->where('members', [['id' => $alice->id, 'name' => 'Alice'], ['id' => $bob->id, 'name' => 'Bob']])
        );
    }

    public function test_home_page_is_shown_after_login_even_with_no_conversations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/chat')->assertInertia(fn ($page) => $page
            ->component('Chat/Home')
            ->has('sidebar.channels', 0)
            ->has('sidebar.directMessages', 0)
        );
    }
}
