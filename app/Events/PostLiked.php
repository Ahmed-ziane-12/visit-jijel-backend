<?php

namespace App\Events;

use App\Models\Like;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Like $like,
        public int $likesCount,
        public int $dislikesCount,
    ) {}

    public function broadcastOn(): array
    {
        $postId = $this->like->likeable_id;

        return [
            new Channel('post.'.$postId.'.likes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.liked';
    }

    public function broadcastWith(): array
    {
        return [
            'likeable_type' => class_basename($this->like->likeable_type),
            'likeable_id' => $this->like->likeable_id,
            'user_id' => $this->like->user_id,
            'type' => $this->like->type,
            'likes_count' => $this->likesCount,
            'dislikes_count' => $this->dislikesCount,
        ];
    }
}
