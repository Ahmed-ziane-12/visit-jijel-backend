<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'post_id' => $this->post_id,
            'parent_comment_id' => $this->parent_comment_id,
            'body' => $this->body,
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
            'post' => $this->whenLoaded('post'),
            'parent_comment' => $this->whenLoaded('parentComment', fn () => new self($this->parentComment)),
            'replies' => $this->whenLoaded('replies', fn () => self::collection($this->replies)),
            'likes' => $this->whenLoaded('likes'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
