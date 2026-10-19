<?php

declare(strict_types=1);

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class DirectMessageService
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
    ) {}

    /**
     * Returns the one conversation between two users, creating it on first use.
     * Both users are (re-)added as members, so a conversation someone left comes back.
     */
    public function start(User $from, User $to): Channel
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('You cannot start a conversation with yourself.');
        }

        $key = Channel::directKeyFor($from, $to);
        $channel = $this->channels->findByDirectKey($key);

        if (! $channel) {
            try {
                $channel = $this->channels->create([
                    'name' => null,
                    'type' => 'direct',
                    'direct_key' => $key,
                    'created_by' => $from->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The other person started it at the same moment
                $channel = $this->channels->findByDirectKey($key);
            }
        }

        $this->channels->addMember($channel, $from);
        $this->channels->addMember($channel, $to);
        // Re-opening a conversation you closed brings it back to your sidebar
        $this->channels->unhide($channel, $from);

        return $channel;
    }

    /**
     * @return list<array{id: int, partner: array{id: int, name: string}|null}>
     */
    public function listForUser(User $user): array
    {
        return $this->channels->listDirectForUser($user)
            ->map(fn (Channel $channel) => [
                'id' => $channel->id,
                'partner' => $this->partnerOf($channel, $user),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function partnerOf(Channel $channel, User $user): ?array
    {
        $members = $channel->relationLoaded('members')
            ? $channel->members
            : $channel->members()->select(['users.id', 'users.name'])->get();

        $partner = $members->first(fn (User $member) => ! $member->is($user));

        return $partner ? ['id' => $partner->id, 'name' => $partner->name] : null;
    }
}
