<?php

declare(strict_types=1);

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ChannelService
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
    ) {}

    public function create(User $creator, string $name, string $type = 'public', ?string $description = null): Channel
    {
        $channel = $this->channels->create([
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'created_by' => $creator->id,
        ]);

        // Creator automatically joins their own channel
        $this->channels->addMember($channel, $creator);

        return $channel;
    }

    public function listPublic(int $perPage = 20): LengthAwarePaginator
    {
        return $this->channels->listPublic($perPage);
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->channels->listForUser($user, $perPage);
    }

    /**
     * @throws AuthorizationException
     */
    public function join(Channel $channel, User $user): void
    {
        if (! $channel->isPublic()) {
            throw new AuthorizationException('Cannot join a private channel without an invitation.');
        }

        $this->channels->addMember($channel, $user);
    }

    public function leave(Channel $channel, User $user): void
    {
        $this->channels->removeMember($channel, $user);
    }

    /**
     * The sidebar "X": a direct message is hidden and its history cleared for this user only
     * (it reopens when a new message arrives); a channel is left.
     */
    public function close(Channel $channel, User $user): void
    {
        if ($channel->isDirect()) {
            $this->channels->hideAndClear($channel, $user);

            return;
        }

        $this->channels->removeMember($channel, $user);
    }

    /**
     * @return Collection<int, User>
     */
    public function members(Channel $channel): Collection
    {
        return $this->channels->members($channel);
    }

    /**
     * @throws AuthorizationException
     */
    public function invite(Channel $channel, User $inviter, User $invitee): void
    {
        if ($channel->isDirect()) {
            throw new AuthorizationException('Direct messages are between two people only.');
        }

        if (! $this->channels->isMember($channel, $inviter)) {
            throw new AuthorizationException('Only channel members can invite others.');
        }

        $this->channels->addMember($channel, $invitee);
    }
}
