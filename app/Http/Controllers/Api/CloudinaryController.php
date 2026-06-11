<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudinaryController extends Controller
{
    /**
     * Step 1 — Next.js requests a signed upload signature.
     * This is short-lived (valid for 1 hour by default).
     */
    public function signature(Request $request): JsonResponse
    {
        $request->validate([
            'folder' => ['required', 'string'],  // e.g. "jijel/destinations"
            'public_id' => ['nullable', 'string'],
        ]);

        $timestamp = time();
        $folder = $request->input('folder');
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
        ]);
    }

    /**
     * Step 3 — Next.js sends Cloudinary's response to Laravel
     * after a successful upload, so we can store the media record.
     */
    public function store(Request $request): JsonResponse
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

        $media = $model->attachMedia(
            cloudinaryResponse: $data,
            collection: $data['collection'],
            isCover: $data['is_cover'] ?? false,
        );

        return response()->json($media, 201);
    }

    /**
 * Delete a media record from both Cloudinary and the database.
 */
public function delete(Request $request): JsonResponse
{
    $request->validate([
        'media_id' => ['required', 'integer'],
    ]);

    $media = Media::findOrFail($request->input('media_id'));
    $publicId = $media->cloudinary_public_id;

    // Build the signed deletion request to Cloudinary
    $timestamp  = time();
    $apiKey     = config('cloudinary.api_key');
    $apiSecret  = config('cloudinary.api_secret');
    $cloudName  = config('cloudinary.cloud_name');

    $params = [
        'public_id' => $publicId,
        'timestamp' => $timestamp,
    ];

    ksort($params);

    $paramString = collect($params)
        ->map(fn ($v, $k) => "{$k}={$v}")
        ->implode('&');

    $signature = hash('sha256', $paramString . $apiSecret);

    // Call Cloudinary's destroy endpoint
    $response = \Illuminate\Support\Facades\Http::asForm()->post(
        "https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy",
        [
            'public_id' => $publicId,
            'signature' => $signature,
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
        ]
    );

    if ($response->failed() || ($response->json('result') !== 'ok')) {
        return response()->json([
            'message' => 'Failed to delete from Cloudinary.',
            'cloudinary' => $response->json(),
        ], 502);
    }

    $media->delete();

    return response()->json(['message' => 'Media deleted successfully.']);
}
}
