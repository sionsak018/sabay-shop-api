<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        // Get all conversations for the logged-in user
        $messages = Message::where('from_user_id', $request->user()->id)
                    ->orWhere('to_user_id', $request->user()->id)
                    ->with(['fromUser', 'toUser', 'product', 'reactions'])
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
        ]);

        $type = $request->input('type', 'text');
        $filePath = null;

        if ($request->hasFile('file')) {
            $folder = $type === 'image' ? 'messages/images' : ($type === 'audio' ? 'messages/voice' : 'messages/files');
            $filePath = $request->file('file')->store($folder, 'public');
        }

        $message = Message::create([
            'from_user_id' => $request->user()->id,
            'to_user_id' => $request->to_user_id,
            'product_id' => $request->product_id,
            'message' => $request->message ?? '',
            'type' => $type,
            'file_path' => $filePath,
        ]);

        return response()->json($message->load(['fromUser', 'toUser', 'reactions']), 201);
    }

    public function react(Request $request, $id)
    {
        $request->validate([
            'emoji' => 'required|string'
        ]);

        $existing = MessageReaction::where('message_id', $id)
            ->where('user_id', $request->user()->id)
            ->where('emoji', $request->emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['message' => 'Reaction removed', 'status' => 'removed']);
        }

        $reaction = MessageReaction::updateOrCreate(
            ['message_id' => $id, 'user_id' => $request->user()->id],
            ['emoji' => $request->emoji]
        );

        return response()->json($reaction);
    }

    public function destroy($id, Request $request)
    {
        $message = Message::where('from_user_id', $request->user()->id)->findOrFail($id);

        if ($message->file_path) {
            Storage::disk('public')->delete($message->file_path);
        }

        $message->delete();
        return response()->json(['message' => 'Message deleted']);
    }

public function markAsRead(int $id, Request $request)
{
    $message = Message::where('to_user_id', $request->user()->id)->findOrFail($id);
    $message->update(['is_read' => true]);
    return response()->json(['message' => 'Marked as read']);
}
}
