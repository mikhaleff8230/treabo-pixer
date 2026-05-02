<?php

namespace Marvel\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Marvel\Database\Models\Conversation;
use Marvel\Database\Models\Message;
use Marvel\Database\Models\Review;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public $conversation;

    public $type;

    /**
     * Create a new event instance.
     *
     * @param Message $message
     * @param Conversation $conversation
     * @param $type
     *
     */
    public function __construct(Message $message, Conversation $conversation, $type)
    {
        $this->message = $message->load(['user', 'chatAttachments']);
        $this->conversation = $conversation;
        $this->type = $type;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('conversation.' . $this->conversation->id);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'user_id' => $this->message->user_id,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'email' => $this->message->user->email,
            ],
            'body' => $this->message->body,
            'read_at' => $this->message->read_at,
            'attachments' => $this->message->chatAttachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                    'file_name' => $attachment->file_name,
                    'file_size' => $attachment->file_size,
                ];
            }),
            'created_at' => $this->message->created_at,
            'updated_at' => $this->message->updated_at,
        ];
    }
}
