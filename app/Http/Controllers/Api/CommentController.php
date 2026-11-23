<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Add a comment to a post.
     */
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $comment->load('user.profile.media', 'likes');

        broadcast(new CommentCreated($comment));

        return response()->json(new CommentResource($comment), 201);
    }

    /**
     * Reply to an existing comment.
     */
    public function reply(StoreCommentRequest $request, Comment $comment): JsonResponse
    {
        $reply = $comment->replies()->create([
            'user_id' => $request->user()->id,
            'post_id' => $comment->post_id,
            'body' => $request->validated('body'),
        ]);

        $reply->load('user.profile.media', 'likes');

        broadcast(new CommentCreated($reply));

        return response()->json(new CommentResource($reply), 201);
    }

    /**
     * Delete a comment (owner only).
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully.']);
    }
}
