<?php

namespace App\Http\Controllers\Api;

use App\Events\PostCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePostRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use AuthorizesRequests;

    /**
     * List posts for a given user's profile.
     */
    public function userPosts(Request $request, User $user): JsonResponse
    {
        $posts = Post::where('user_id', $user->id)
            ->with([
                'user.profile.media',
                'media',
                'likes',
                'comments' => fn ($q) => $q->whereNull('parent_comment_id')->with('user.profile.media')->latest(),
                'comments.likes',
                'comments.replies' => fn ($q) => $q->with('user.profile.media')->latest(),
                'comments.replies.likes',
                'parentPost' => fn ($q) => $q->with([
                    'user.profile.media',
                    'media',
                    'shareable',
                ]),
                'shareable',
            ])
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    /**
     * Show a single post.
     */
    public function show(Post $post): JsonResponse
    {
        $post->load([
            'user.profile.media',
            'media',
            'likes',
            'comments' => fn ($q) => $q->with('user.profile.media')->latest(),
            'comments.likes',
            'comments.replies' => fn ($q) => $q->with('user.profile.media')->latest(),
            'comments.replies.likes',
            'parentPost' => fn ($q) => $q->with([
                'user.profile.media',
                'media',
                'shareable',
            ]),
            'shareable',
        ]);

        return response()->json($post);
    }

    /**
     * Create a new post.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $post = $request->user()->posts()->create(
            $request->only(['body', 'shareable_type', 'shareable_id', 'parent_post_id'])
        );

        $post->load(['user.profile.media', 'media', 'shareable', 'likes', 'comments.user.profile.media']);

        broadcast(new PostCreated($post));

        return response()->json($post, 201);
    }

    /**
     * Update a post (owner only).
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $post->update($validated);

        $post->load(['user.profile.media', 'media', 'shareable', 'likes', 'comments.user.profile.media']);

        return response()->json($post);
    }

    /**
     * Delete a post (owner only).
     */
    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully.']);
    }
}
