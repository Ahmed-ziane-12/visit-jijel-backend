<?php

namespace App\Services;

use Cloudinary\Cloudinary;

class CloudinaryService
{
    public function __construct(private readonly Cloudinary $cloudinary) {}

    /**
     * Delete a single asset from Cloudinary by public id.
     */
    public function destroy(string $publicId): bool
    {
        $result = $this->cloudinary->uploadApi()->destroy($publicId);

        return ($result['result'] ?? null) === 'ok';
    }

    /**
     * List every uploaded public id inside a folder, walking pagination.
     *
     * @return string[]
     */
    public function listAssetIds(string $folder): array
    {
        $publicIds = [];
        $nextCursor = null;

        do {
            $response = $this->cloudinary->adminApi()->assets([
                'type' => 'upload',
                'prefix' => $folder,
                'max_results' => 500,
                'next_cursor' => $nextCursor,
            ]);

            foreach ($response['resources'] ?? [] as $resource) {
                $publicIds[] = $resource['public_id'];
            }

            $nextCursor = $response['next_cursor'] ?? null;
        } while ($nextCursor !== null);

        return $publicIds;
    }
}
