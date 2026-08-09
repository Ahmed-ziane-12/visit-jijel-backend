<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\CloudinaryService;
use Illuminate\Console\Command;

class PurgeOrphanedCloudinaryAssets extends Command
{
    protected $signature = 'cloudinary:purge-orphans
        {--folder=jijel/destinations : Folder to scan for orphaned assets}
        {--run : Actually delete orphaned assets (default is a dry run)}';

    protected $description = 'Delete Cloudinary assets that have no matching media record';

    public function handle(CloudinaryService $cloudinary): int
    {
        $folder = $this->option('folder');
        $assetIds = $cloudinary->listAssetIds($folder);

        $referenced = array_flip(
            Media::query()
                ->where('cloudinary_public_id', 'like', $folder.'/%')
                ->pluck('cloudinary_public_id')
                ->all()
        );

        $orphans = array_values(array_filter(
            $assetIds,
            fn (string $publicId): bool => ! isset($referenced[$publicId])
        ));

        if ($orphans === []) {
            $this->info(sprintf('No orphaned assets found in "%s".', $folder));

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d orphaned asset(s) in "%s".', count($orphans), $folder));

        foreach ($orphans as $publicId) {
            $this->line('  - '.$publicId);
        }

        if (! $this->option('run')) {
            $this->warn('Dry run — nothing was deleted. Re-run with --run to delete.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($orphans as $publicId) {
            if ($cloudinary->destroy($publicId)) {
                $deleted++;
            } else {
                $failed++;
                $this->warn('  Failed: '.$publicId);
            }
        }

        $this->info(sprintf('Deleted %d asset(s), %d failed.', $deleted, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
