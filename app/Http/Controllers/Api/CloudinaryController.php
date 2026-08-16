<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Profile;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CloudinaryController extends Controller
{
    private const ALLOWED_FOLDERS = [
        'jijel/businesses',
        'jijel/listings',
        'jijel/events',
        'jijel/destinations',
        'jijel/profiles',
    ];

    /**
     * Step 1 — Next.js requests a signed upload signature.
     * This is short-lived (valid for 1 hour by default).
     */
    public function signature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required', 'string', Rule::in(self::ALLOWED_FOLDERS)],
            'public_id' => ['nullable', 'string'],
            'transformation' => ['nullable', 'string', 'max:255'],
        ]);

        $timestamp = time();
        $folder = $data['folder'];
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        if ($request->filled('public_id')) {
            $params['public_id'] = $request->input('public_id');
        }

        if ($request->filled('transformation')) {
            $params['transformation'] = $request->input('transformation');
        }

        // Sort params alphabetically — required by Cloudinary
        ksort($params);

        $paramString = collect($params)
            ->map(fn ($v, $k) => "{$k}={$v}")
            ->implode('&');

        $signature = hash('sha256', $paramString.$apiSecret);

        return response()->json([
            'signature' => $signature,
            'timestamp' => $timestamp,
            'api_key' => $apiKey,
            'cloud_name' => $cloudName,
            'folder' => $folder,
            'transformation' => $data['transformation'] ?? null,
        ]);
    }

    /**
     * Step 3 — Next.js sends Cloudinary's response to Laravel
     * after a successful upload, so we can store the media record.
     */
    public function store(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $data = $request->validate([
            'model_type' => ['required', 'string'],  // e.g. "destination"
            'model_id' => ['required', 'integer'],
            'collection' => ['required', 'string'],
            'is_cover' => ['sometimes', 'boolean'],
            'cloudinary_public_id' => ['required', 'string'],
            'url' => ['required', 'url'],
            'secure_url' => ['required', 'url'],
            'format' => ['nullable', 'string'],
            'resource_type' => ['nullable', 'string'],
            'width' => ['nullable', 'integer'],
            'height' => ['nullable', 'integer'],
            'bytes' => ['nullable', 'integer'],
        ]);

        // Resolve the model class from a safe map — never trust raw user input
        $modelMap = [
            'destination' => Destination::class,
            'listing' => Listing::class,
            'business' => Business::class,
            'event' => Event::class,
            'profile' => Profile::class,
        ];

        if (! isset($modelMap[$data['model_type']])) {
            return response()->json(['message' => 'Invalid model type.'], 422);
        }

        $modelClass = $modelMap[$data['model_type']];
        $model = $modelClass::findOrFail($data['model_id']);

        if (! $this->canAttachMedia($request->user(), $data['model_type'], $model)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // For client profiles, remove old media in the same collection
        // so only the latest profile/cover image is kept.
        if ($data['model_type'] === 'profile') {
            $this->clearExistingMedia($model, $data['collection'], $cloudinary);
        }

        $media = $model->attachMedia(
            cloudinaryResponse: $data,
            collection: $data['collection'],
            isCover: $data['is_cover'] ?? false,
        );

        return response()->json($media, 201);
    }

    /**
     * Set an existing media as the cover and move it to the front
     * of the collection's sort order.
     */
    public function setCover(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
        ]);

        $media = Media::findOrFail($request->input('media_id'));

        if (! $this->canDeleteMedia($request->user(), $media)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $media->model->setCover($media);

        return response()->json($media->refresh());
    }

    /**
     * Delete a media record from both Cloudinary and the database.
     * If the deleted media was the cover, the first remaining media
     * in the same collection becomes the new cover.
     */
    public function delete(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
        ]);

        $media = Media::findOrFail($request->input('media_id'));

        if (! $this->canDeleteMedia($request->user(), $media)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $cloudinary->destroy($media->cloudinary_public_id)) {
            return response()->json([
                'message' => 'Failed to delete from Cloudinary.',
            ], 502);
        }

        $wasCover = $media->is_cover;

        $media->delete();

        if ($wasCover) {
            $this->promoteFirstMediaToCover($media);
        }

        return response()->json(['message' => 'Media deleted successfully.']);
    }

    /**
     * Promote the first remaining media in the same collection to cover,
     * ordered by sort_order then id to match display order.
     */
    private function promoteFirstMediaToCover(Media $deleted): void
    {
        $replacement = Media::query()
            ->where('model_type', $deleted->model_type)
            ->where('model_id', $deleted->model_id)
            ->where('collection', $deleted->collection)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($replacement !== null) {
            $replacement->update(['is_cover' => true]);
        }
    }

    /**
     * Delete all existing media in a collection from both
     * Cloudinary and the database.
     */
    private function clearExistingMedia(Model $model, string $collection, CloudinaryService $cloudinary): void
    {
        $existingMedia = $model->getMediaInCollection($collection)->get();

        foreach ($existingMedia as $media) {
            $cloudinary->destroy($media->cloudinary_public_id);
            $media->delete();
        }
    }

    /**
     * Verify the authenticated user owns the model they are attaching media to.
     * Destinations are platform-managed (admins only).
     */
    private function canAttachMedia(User $user, string $modelType, Model $model): bool
    {
        if ($modelType === 'destination') {
            return $user->isAdmin();
        }

        return match ($modelType) {
            'business' => $user->id === $model->owner_id,
            'listing' => $user->id === $model->business?->owner_id,
            'event' => $user->id === $model->created_by,
            'profile' => $user->id === $model->user_id,
            default => false,
        };
    }

    /**
     * Verify the authenticated user owns the media's parent model.
     */
    private function canDeleteMedia(User $user, Media $media): bool
    {
        $model = $media->model;

        if ($model instanceof Destination) {
            return $user->isAdmin();
        }

        if ($model instanceof Business) {
            return $user->id === $model->owner_id;
        }

        if ($model instanceof Listing) {
            return $user->id === $model->business?->owner_id;
        }

        if ($model instanceof Event) {
            return $user->id === $model->created_by;
        }

        if ($model instanceof Profile) {
            return $user->id === $model->user_id;
        }

        return false;
    }
}
