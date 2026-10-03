<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    /**
     * Broadcast to both participants so the recipient gets it instantly and
     * the sender's other open tabs stay in sync.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->message->from_user_id),
            new PrivateChannel('App.Models.User.'.$this->message->to_user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->load([
            'fromUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
            'toUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
            'reactions',
            'replyTo.fromUser' => fn ($q) => $q->select(User::PUBLIC_COLUMNS),
        ]);

        return ['message' => $this->message->toArray()];
    }
}
