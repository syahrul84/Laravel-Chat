<?php

declare(strict_types=1);

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Events\ChannelActivity;
use App\Domains\Chat\Events\MessageSent;
use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Domains\Chat\Repositories\MessageRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;

class MessageService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messages,
        private readonly ChannelRepositoryInterface $channels,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function send(Channel $channel, User $sender, string $content): Message
    {
        if (! $this->channels->isMember($channel, $sender)) {
            throw new AuthorizationException('You must be a member of this channel to send messages.');
        }

        $message = $this->messages->create([
            'sender_id' => $sender->id,
            'channel_id' => $channel->id,
            'content' => $content,
        ]);

        $message->load('sender', 'channel');

        $this->channels->markRead($channel, $sender);

        // A closed direct message reopens for both people when a new message arrives
        if ($channel->isDirect()) {
            $this->channels->unhide($channel);
        }

        broadcast(new MessageSent($message))->toOthers();

        $recipients = array_values(array_diff($this->channels->memberIds($channel), [$sender->id]));
        if ($recipients !== []) {
            broadcast(new ChannelActivity($message, $recipients));
        }

        return $message;
    }

    /**
     * The member's view of the history: anything they cleared is left out.
     */
    public function getMessages(Channel $channel, User $member, int $perPage = 50): LengthAwarePaginator
    {
        return $this->messages->forChannel($channel, $perPage, $this->channels->clearedMessageId($channel, $member));
    }

    public function markRead(Channel $channel, User $member): void
    {
        $this->channels->markRead($channel, $member);
    }
}
