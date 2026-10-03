<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight signal fired when an existing message changes (reaction added
 * or removed, read receipt, deletion). Clients refetch the affected thread
 * instead of polling on a timer.
 */
class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $fromUserId,
        public int $toUserId,
        public ?int $messageId,
        public string $action,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->fromUserId),
            new PrivateChannel('App.Models.User.'.$this->toUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'action' => $this->action,
        ];
    }
}
