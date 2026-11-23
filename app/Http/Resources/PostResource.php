<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'shareable_type' => $this->shareable_type,
            'shareable_id' => $this->shareable_id,
            'parent_post_id' => $this->parent_post_id,
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
            'media' => $this->whenLoaded('media', fn () => MediaResource::collection($this->media)),
            'likes' => $this->whenLoaded('likes'),
            'comments' => $this->whenLoaded('comments', fn () => CommentResource::collection($this->comments)),
            'parent_post' => $this->whenLoaded('parentPost', fn () => new self($this->parentPost)),
            'child_posts' => $this->whenLoaded('childPosts', fn () => self::collection($this->childPosts)),
            'shareable' => $this->whenLoaded('shareable'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
