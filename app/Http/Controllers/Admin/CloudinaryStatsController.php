<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;

class CloudinaryStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
        ]);

        $usage = $cloudinary->adminApi()->usage();

        $storage = $usage['storage'];
        $bandwidth = $usage['bandwidth'];

        return response()->json([
            'storage' => [
                'usage' => $storage['usage'] ?? 0,
                'limit' => $storage['limit'] ?? 0,
                'used_percent' => $storage['used_percent'] ?? 0,
            ],
            'bandwidth' => [
                'usage' => $bandwidth['usage'] ?? 0,
                'limit' => $bandwidth['limit'] ?? 0,
                'used_percent' => $bandwidth['used_percent'] ?? 0,
            ],
            'media_limits' => $usage['media_limits'] ?? ['image' => 0, 'video' => 0, 'total' => 0],
            'images' => $usage['resources']['image']['usage'] ?? 0,
            'videos' => $usage['resources']['video']['usage'] ?? 0,
            'used_percent' => ($storage['usage'] ?? 0) / max($storage['limit'] ?? 1, 1) * 100,
        ]);
    }
}
