<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Notification $notification) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('App.User.'.$this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->notification->id,
            'type'         => $this->notification->type,
            'title'        => $this->notification->title,
            'body'         => $this->notification->body,
            'related_type' => $this->notification->related_type,
            'related_id'   => $this->notification->related_id,
            'read_at'      => $this->notification->read_at,
            'created_at'   => $this->notification->created_at,
        ];
    }
}
