<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

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
            ->with('creator')
            ->withCount('members')
            ->latest()
            ->paginate($perPage);
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->channels()
            ->with('creator')
            ->withCount('members')
            ->latest()
            ->paginate($perPage);
    }

    public function addMember(Channel $channel, User $user): void
    {
        $channel->members()->syncWithoutDetaching([$user->id => ['joined_at' => now()]]);
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
