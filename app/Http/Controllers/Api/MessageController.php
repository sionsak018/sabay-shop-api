<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\CloudinaryService;

class MessageController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Broadcast an event without letting a transport failure break the
     * request that triggered it (e.g. Pusher being unreachable).
     */
    private function broadcastSafely(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        // Get all conversations for the logged-in user
        $messages = Message::where('from_user_id', $request->user()->id)
                    ->orWhere('to_user_id', $request->user()->id)
                    ->with([
                        'fromUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
                        'toUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
                        'product',
                        'reactions',
                        'replyTo.fromUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
                    ])
                    ->orderBy('created_at', 'desc')
                    ->get();
        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'message' => 'nullable|string',
            'type' => 'nullable|string|in:text,image,audio,file',
            'file' => 'nullable|file|max:10240', // 10MB limit
            'product_id' => 'nullable|exists:products,id',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ]);

        if ((int) $request->to_user_id === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot message yourself.'], 422);
        }

        $type = $request->input('type', 'text');
        $filePath = null;

        if ($request->hasFile('file')) {
            $folder = $type === 'image' ? 'sabay-shop/messages/images' : ($type === 'audio' ? 'sabay-shop/messages/voice' : 'sabay-shop/messages/files');
            $url = $this->cloudinaryService->upload($request->file('file'), $folder);
            if ($url) {
                $filePath = $url;
            }
        }

        $message = Message::create([
            'from_user_id' => $request->user()->id,
            'to_user_id' => $request->to_user_id,
            'product_id' => $request->product_id,
            'message' => $request->message ?? '',
            'type' => $type,
            'file_path' => $filePath,
            'reply_to_id' => $request->reply_to_id,
        ]);

        $message->load([
            'fromUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
            'toUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
            'reactions',
            'replyTo.fromUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
        ]);

        $this->broadcastSafely(fn () => MessageSent::dispatch($message));

        return response()->json($message, 201);
    }

    public function react(Request $request, $id)
    {
        $request->validate([
            'emoji' => 'required|string'
        ]);

        $message = Message::findOrFail($id);

        $existing = MessageReaction::where('message_id', $id)
            ->where('user_id', $request->user()->id)
            ->where('emoji', $request->emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->broadcastSafely(fn () => MessageUpdated::dispatch(
                (int) $message->from_user_id,
                (int) $message->to_user_id,
                (int) $id,
                'reaction',
            ));
            return response()->json(['message' => 'Reaction removed', 'status' => 'removed']);
        }

        if ($message->from_user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot react to your own message.'], 403);
        }

        $reaction = MessageReaction::updateOrCreate(
            ['message_id' => $id, 'user_id' => $request->user()->id],
            ['emoji' => $request->emoji]
        );

        $this->broadcastSafely(fn () => MessageUpdated::dispatch(
            (int) $message->from_user_id,
            (int) $message->to_user_id,
            (int) $id,
            'reaction',
        ));

        return response()->json($reaction);
    }

    public function destroy($id, Request $request)
    {
        $message = Message::where('from_user_id', $request->user()->id)->findOrFail($id);

        // Delete file from Cloudinary if exists
        if ($message->file_path) {
            $this->cloudinaryService->delete($message->file_path);
        }

        $fromUserId = (int) $message->from_user_id;
        $toUserId = (int) $message->to_user_id;

        $message->delete();

        $this->broadcastSafely(fn () => MessageUpdated::dispatch($fromUserId, $toUserId, (int) $id, 'deleted'));

        return response()->json(['message' => 'Message deleted']);
    }

    public function markAsRead(int $id, Request $request)
    {
        $message = Message::where('to_user_id', $request->user()->id)->findOrFail($id);
        $message->update(['is_read' => true]);

        $this->broadcastSafely(fn () => MessageUpdated::dispatch(
            (int) $message->from_user_id,
            (int) $message->to_user_id,
            (int) $id,
            'read',
        ));

        return response()->json(['message' => 'Marked as read']);
    }
}
