<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use RuntimeException;

class CloudinaryService
{
    /**
     * The client is optional so a deployment without Cloudinary credentials
     * can still boot and answer with a clear 503 on the endpoints that need
     * it, instead of throwing while the container is being resolved.
     */
    public function __construct(private readonly ?Cloudinary $cloudinary = null) {}

    public function isConfigured(): bool
    {
        return $this->cloudinary !== null;
    }

    /**
     * Delete a single asset from Cloudinary by public id.
     *
     * Cloudinary reports a missing asset as "not found" with a successful
     * response. Treat that as success: the desired end state (the asset is
     * gone) is already reached, and the database row can be removed instead
     * of being stuck behind a permanent 502.
     */
    public function destroy(string $publicId): bool
    {
        $result = $this->client()->uploadApi()->destroy($publicId);

        return in_array($result['result'] ?? null, ['ok', 'not found'], true);
    }

    /**
     * List every asset inside a folder, walking pagination.
     *
     * Each item contains the public id and Cloudinary's creation timestamp,
     * which the orphan purge uses to avoid deleting very recent uploads that
     * have not been persisted to the database yet.
     *
     * @return array<int, array{public_id: string, created_at: string|null}>
     */
    public function listAssets(string $folder): array
    {
        $assets = [];
        $nextCursor = null;

        do {
            $response = $this->client()->adminApi()->assets([
                'type' => 'upload',
                'prefix' => $folder,
                'max_results' => 500,
                'next_cursor' => $nextCursor,
            ]);

            foreach ($response['resources'] ?? [] as $resource) {
                if (! isset($resource['public_id'])) {
                    continue;
                }

                $assets[] = [
                    'public_id' => (string) $resource['public_id'],
                    'created_at' => $resource['created_at'] ?? null,
                ];
            }

            $nextCursor = $response['next_cursor'] ?? null;
        } while ($nextCursor !== null);

        return $assets;
    }

    private function client(): Cloudinary
    {
        if ($this->cloudinary === null) {
            throw new RuntimeException('Cloudinary is not configured.');
        }

        return $this->cloudinary;
    }
}
