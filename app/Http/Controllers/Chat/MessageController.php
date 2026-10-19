<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Domains\Chat\Services\MessageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {}

    public function index(Request $request, Channel $channel): JsonResponse
    {
        if (! $this->channelRepository->isMember($channel, $request->user())) {
            abort(403);
        }

        return response()->json($this->messageService->getMessages($channel, $request->user()));
    }

    // Called by the open chat when a live message arrives, so it doesn't count as unread
    public function markRead(Request $request, Channel $channel): Response
    {
        if (! $this->channelRepository->isMember($channel, $request->user())) {
            abort(403);
        }

        $this->messageService->markRead($channel, $request->user());

        return response()->noContent();
    }

    public function store(SendMessageRequest $request, Channel $channel): JsonResponse
    {
        try {
            $message = $this->messageService->send(
                channel: $channel,
                sender: $request->user(),
                content: $request->string('content')->toString(),
            );
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return response()->json([
            'id' => $message->id,
            'content' => $message->content,
            'created_at' => $message->created_at->toISOString(),
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ],
        ]);
    }
}
