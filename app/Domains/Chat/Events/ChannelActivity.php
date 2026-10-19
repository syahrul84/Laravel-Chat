<?php

declare(strict_types=1);

namespace App\Domains\Chat\Events;

use App\Domains\Chat\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Tells each other member of a channel, on their personal channel, that a message arrived —
 * drives the sidebar's unread dots and the pop-up alert for direct messages, wherever they are.
 */
class ChannelActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public readonly Message $message,
        public readonly array $recipientIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.'.$id),
            $this->recipientIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'channel.activity';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->message->channel_id,
            'channel_type' => $this->message->channel->type,
            'channel_name' => $this->message->channel->name,
            'message_id' => $this->message->id,
            'preview' => Str::limit($this->message->content, 100),
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ],
        ];
    }
}
