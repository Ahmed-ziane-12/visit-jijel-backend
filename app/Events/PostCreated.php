<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Post $post,
    ) {
        $this->post->load(['user.profile.media', 'media', 'shareable', 'parentPost.user.profile.media']);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('profile.'.$this->post->user_id.'.posts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->post->id,
            'user_id' => $this->post->user_id,
            'body' => $this->post->body,
            'shareable_type' => $this->post->shareable_type,
            'shareable_id' => $this->post->shareable_id,
            'parent_post_id' => $this->post->parent_post_id,
            'created_at' => $this->post->created_at,
            'user' => [
                'id' => $this->post->user->id,
                'name' => $this->post->user->name,
                'profile' => $this->post->user->profile,
            ],
            'media' => $this->post->media,
            'shareable' => $this->post->shareable,
            'parentPost' => $this->post->parentPost,
            'likes' => [],
            'comments' => [],
        ];
    }
}
