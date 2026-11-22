<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Services\CloudinaryService;
use Cloudinary\Api\ApiUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CloudinaryController extends Controller
{
    /**
     * Collections the frontend is allowed to write to. Keeping this explicit
     * stops arbitrary values from ending up in the media table and in cover
     * lookups.
     */
    private const ALLOWED_COLLECTIONS = [
        'default',
        'cover',
        'covers',
        'gallery',
        'avatar',
        'profiles',
        'posts',
    ];

    /**
     * Model type => owning class. Also defines the accepted model_type values.
     */
    private const MODEL_MAP = [
        'destination' => Destination::class,
        'business' => Business::class,
        'listing' => Listing::class,
        'event' => Event::class,
        'profile' => Profile::class,
        'post' => Post::class,
    ];

    /**
     * Step 1 — Next.js requests a signed upload signature.
     * This is short-lived (valid for 1 hour by default).
     */
    public function signature(Request $request): JsonResponse
    {
        $folders = array_values(config('cloudinary.folders', []));

        $data = $request->validate([
            'folder' => ['required', 'string', Rule::in($folders)],
            'public_id' => ['nullable', 'string', 'max:255', 'regex:~^[A-Za-z0-9_./-]+$~'],
            'transformation' => ['nullable', 'string', 'max:255', 'regex:~^[A-Za-z0-9_,./-]+$~'],
        ]);

        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (blank($cloudName) || blank($apiKey) || blank($apiSecret)) {
            return response()->json([
                'message' => 'Media uploads are not configured.',
            ], 503);
        }

        $timestamp = time();
        $folder = $data['folder'];

        if (isset($data['public_id']) && ! $this->publicIdBelongsToFolder($data['public_id'], $folder)) {
            return response()->json([
                'message' => 'The public id must live inside the selected folder.',
            ], 422);
        }

        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        if ($request->filled('public_id')) {
            $params['public_id'] = $data['public_id'];
        }

        if ($request->filled('transformation')) {
            $params['transformation'] = $data['transformation'];
        }

        $algorithm = config('cloudinary.signature_algorithm');

        if (! in_array($algorithm, ['sha1', 'sha256'], true)) {
            $algorithm = 'sha1';
        }

        // Let the SDK build the signature with the account's algorithm and
        // the URL-safe version of the parameter string.
        $signature = ApiUtils::signParameters($params, $apiSecret, $algorithm, 2);

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
            'model_type' => ['required', 'string', Rule::in(array_keys(self::MODEL_MAP))],
            'model_id' => ['required', 'integer'],
            'collection' => ['required', 'string', Rule::in(self::ALLOWED_COLLECTIONS)],
            'is_cover' => ['sometimes', 'boolean'],
            'cloudinary_public_id' => ['required', 'string', 'max:255', 'regex:~^[A-Za-z0-9_./-]+$~'],
            'url' => ['required', 'string', 'max:2048', $this->cloudinaryUrlRule()],
            'secure_url' => ['required', 'string', 'max:2048', $this->cloudinaryUrlRule()],
            'format' => ['nullable', 'string', 'max:20', 'regex:~^[A-Za-z0-9]+$~'],
            'resource_type' => ['nullable', 'string', Rule::in(['image', 'video', 'raw'])],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
            'bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = config('cloudinary.folders.'.$data['model_type']);

        if (! is_string($folder) || ! $this->publicIdBelongsToFolder($data['cloudinary_public_id'], $folder)) {
            return response()->json([
                'message' => 'The public id does not match the selected model type.',
            ], 422);
        }

        $modelClass = self::MODEL_MAP[$data['model_type']];
        $model = $modelClass::findOrFail($data['model_id']);

        if (! $this->canAttachMedia($request->user(), $data['model_type'], $model)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // For client profiles, remove old media in the same collection
        // so only the latest profile/cover image is kept.
        if ($data['model_type'] === 'profile') {
            if (! $cloudinary->isConfigured()) {
                return response()->json([
                    'message' => 'Media uploads are not configured.',
                ], 503);
            }

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

        if (! $cloudinary->isConfigured()) {
            return response()->json([
                'message' => 'Media uploads are not configured.',
            ], 503);
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
     * Ensure a public id lives inside the configured folder and cannot
     * traverse out of it.
     */
    private function publicIdBelongsToFolder(string $publicId, string $folder): bool
    {
        $publicId = ltrim($publicId, '/');

        if (str_contains($publicId, '..')) {
            return false;
        }

        return str_starts_with($publicId, $folder.'/');
    }

    /**
     * Only accept https URLs served by the configured Cloudinary cloud.
     *
     * @return \Closure(string, mixed, \Closure(string): void): void
     */
    private function cloudinaryUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || ! $this->isCloudinaryUrl($value)) {
                $fail('The :attribute must be a Cloudinary delivery URL.');
            }
        };
    }

    private function isCloudinaryUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        if (strtolower($parts['host'] ?? '') !== 'res.cloudinary.com') {
            return false;
        }

        $cloudName = config('cloudinary.cloud_name');
        $path = $parts['path'] ?? '';

        if (! blank($cloudName) && ! str_starts_with($path, '/'.$cloudName.'/')) {
            return false;
        }

        return true;
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
            'post' => $user->id === $model->user_id,
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

        if ($model instanceof Post) {
            return $user->id === $model->user_id;
        }

        return false;
    }
}
