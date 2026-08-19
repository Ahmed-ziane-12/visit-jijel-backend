<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Comment $comment,
    ) {
        $this->comment->load(['user.profile.media']);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('profile.'.$this->comment->post->user_id.'.posts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'post_id' => $this->comment->post_id,
            'parent_comment_id' => $this->comment->parent_comment_id,
            'body' => $this->comment->body,
            'created_at' => $this->comment->created_at,
            'user' => [
                'id' => $this->comment->user->id,
                'name' => $this->comment->user->name,
                'profile' => $this->comment->user->profile,
            ],
            'likes' => [],
            'replies' => [],
        ];
    }
}
