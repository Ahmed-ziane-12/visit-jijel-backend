<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Toggle like on a post or comment.
     */
    public function toggleLike(Request $request, string $likeableType, int $likeableId): JsonResponse
    {
        $model = $this->resolveLikeable($likeableType, $likeableId);

        if (! $model) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $existing = Like::where('user_id', $request->user()->id)
            ->where('likeable_type', get_class($model))
            ->where('likeable_id', $model->id)
            ->first();

        if ($existing) {
            if ($existing->type === 'like') {
                $existing->delete();

                return response()->json(['message' => 'Like removed.', 'liked' => false]);
            }

            $existing->update(['type' => 'like']);

            return response()->json(['message' => 'Liked.', 'liked' => true]);
        }

        Like::create([
            'user_id' => $request->user()->id,
            'likeable_type' => get_class($model),
            'likeable_id' => $model->id,
            'type' => 'like',
        ]);

        return response()->json(['message' => 'Liked.', 'liked' => true], 201);
    }

    /**
     * Toggle dislike on a post or comment.
     */
    public function toggleDislike(Request $request, string $likeableType, int $likeableId): JsonResponse
    {
        $model = $this->resolveLikeable($likeableType, $likeableId);

        if (! $model) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $existing = Like::where('user_id', $request->user()->id)
            ->where('likeable_type', get_class($model))
            ->where('likeable_id', $model->id)
            ->first();

        if ($existing) {
            if ($existing->type === 'dislike') {
                $existing->delete();

                return response()->json(['message' => 'Dislike removed.', 'disliked' => false]);
            }

            $existing->update(['type' => 'dislike']);

            return response()->json(['message' => 'Disliked.', 'disliked' => true]);
        }

        Like::create([
            'user_id' => $request->user()->id,
            'likeable_type' => get_class($model),
            'likeable_id' => $model->id,
            'type' => 'dislike',
        ]);

        return response()->json(['message' => 'Disliked.', 'disliked' => true], 201);
    }

    /**
     * Resolve the likeable model from the type and ID.
     */
    private function resolveLikeable(string $type, int $id): ?Model
    {
        $model = match ($type) {
            'posts' => Post::find($id),
            'comments' => Comment::find($id),
            default => null,
        };

        return $model;
    }
}
