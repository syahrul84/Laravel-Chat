<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChannelRepository implements ChannelRepositoryInterface
{
    public function create(array $data): Channel
    {
        return Channel::create($data);
    }

    public function findById(int $id): ?Channel
    {
        return Channel::find($id);
    }

    public function findBySlug(string $slug): ?Channel
    {
        return Channel::where('slug', $slug)->first();
    }

    public function listPublic(int $perPage = 20): LengthAwarePaginator
    {
        return Channel::where('type', 'public')
            ->with('creator:id,name')
            ->withCount('members')
            ->latest()
            ->paginate($perPage);
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->channels()
            ->where('channels.type', '!=', 'direct')
            ->with('creator:id,name')
            ->withCount('members')
            ->latest()
            ->paginate($perPage, pageName: 'my_page');
    }

    public function findByDirectKey(string $key): ?Channel
    {
        return Channel::where('direct_key', $key)->first();
    }

    public function listDirectForUser(User $user): Collection
    {
        return $user->channels()
            ->where('channels.type', 'direct')
            ->wherePivotNull('hidden_at')
            ->with('members:id,name')
            ->latest('channels.updated_at')
            ->get();
    }

    public function listSidebarChannels(User $user): Collection
    {
        return $user->channels()
            ->where('channels.type', '!=', 'direct')
            ->select(['channels.id', 'channels.name', 'channels.type'])
            ->orderBy('channels.name')
            ->get();
    }

    public function addMember(Channel $channel, User $user): void
    {
        if ($this->isMember($channel, $user)) {
            return;
        }

        // Existing history counts as read for a new member; only later messages are "new"
        $channel->members()->attach($user->id, [
            'joined_at' => now(),
            'last_read_message_id' => $channel->messages()->max('id'),
        ]);
    }

    public function members(Channel $channel): Collection
    {
        return $channel->members()
            ->select(['users.id', 'users.name'])
            ->orderBy('users.name')
            ->get()
            ->each(fn (User $user) => $user->setHidden(['pivot']));
    }

    public function memberIds(Channel $channel): array
    {
        return $channel->members()->pluck('users.id')->all();
    }

    public function markRead(Channel $channel, User $user): void
    {
        $channel->members()->updateExistingPivot($user->id, [
            'last_read_message_id' => $channel->messages()->max('id'),
        ]);
    }

    public function unreadCounts(User $user): array
    {
        return DB::table('channel_user')
            ->join('messages', 'messages.channel_id', '=', 'channel_user.channel_id')
            ->where('channel_user.user_id', $user->id)
            ->whereNull('channel_user.hidden_at')
            ->where('messages.sender_id', '!=', $user->id)
            ->whereRaw('messages.id > COALESCE(channel_user.last_read_message_id, 0)')
            ->whereRaw('messages.id > COALESCE(channel_user.cleared_message_id, 0)')
            ->groupBy('channel_user.channel_id')
            ->selectRaw('channel_user.channel_id as channel_id, COUNT(messages.id) as unread')
            ->pluck('unread', 'channel_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function clearedMessageId(Channel $channel, User $user): ?int
    {
        $id = $channel->members()->where('users.id', $user->id)->value('channel_user.cleared_message_id');

        return $id === null ? null : (int) $id;
    }

    public function hideAndClear(Channel $channel, User $user): void
    {
        $lastId = $channel->messages()->max('id');

        $channel->members()->updateExistingPivot($user->id, [
            'hidden_at' => now(),
            'cleared_message_id' => $lastId,
            'last_read_message_id' => $lastId,
        ]);
    }

    public function unhide(Channel $channel, ?User $user = null): void
    {
        DB::table('channel_user')
            ->where('channel_id', $channel->id)
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->update(['hidden_at' => null]);
    }

    public function removeMember(Channel $channel, User $user): void
    {
        $channel->members()->detach($user->id);
    }

    public function isMember(Channel $channel, User $user): bool
    {
        return $channel->members()->where('user_id', $user->id)->exists();
    }
}
