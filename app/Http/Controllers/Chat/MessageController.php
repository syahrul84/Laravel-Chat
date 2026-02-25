<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\MessageRepositoryInterface;
use App\Domains\Chat\Services\MessageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly MessageRepositoryInterface $messageRepository,
    ) {}

    public function index(Channel $channel): JsonResponse
    {
        $messages = $this->messageRepository->forChannel($channel);

        return response()->json($messages);
    }

    public function store(SendMessageRequest $request, Channel $channel): JsonResponse
    {
        try {
            $message = $this->messageService->send(
                channel: $channel,
                sender:  $request->user(),
                content: $request->string('content')->toString(),
            );
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return response()->json([
            'id'         => $message->id,
            'content'    => $message->content,
            'created_at' => $message->created_at->toISOString(),
            'sender'     => [
                'id'   => $message->sender->id,
                'name' => $message->sender->name,
            ],
        ]);
    }
}
