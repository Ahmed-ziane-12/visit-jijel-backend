<?php

namespace App\Events;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The latest Like is nullable: removing the last reaction on a post
     * must still broadcast the new (zero) counts without blowing up.
     */
    public function __construct(
        public Post $post,
        public ?Like $like,
        public int $likesCount,
        public int $dislikesCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('post.'.$this->post->id.'.likes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.liked';
    }

    public function broadcastWith(): array
    {
        return [
            'likeable_type' => $this->like
                ? class_basename($this->like->likeable_type)
                : class_basename($this->post),
            'likeable_id' => $this->post->id,
            'user_id' => $this->like?->user_id,
            'type' => $this->like?->type,
            'likes_count' => $this->likesCount,
            'dislikes_count' => $this->dislikesCount,
        ];
    }
}
