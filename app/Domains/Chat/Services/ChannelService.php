<?php

declare(strict_types=1);

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;

class ChannelService
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
    ) {}

    public function create(User $creator, string $name, string $type = 'public', ?string $description = null): Channel
    {
        $channel = $this->channels->create([
            'name'        => $name,
            'description' => $description,
            'type'        => $type,
            'created_by'  => $creator->id,
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
}
