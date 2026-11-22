<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\CloudinaryService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PurgeOrphanedCloudinaryAssets extends Command
{
    protected $signature = 'cloudinary:purge-orphans
        {--folder= : Only scan this folder (defaults to every application folder)}
        {--min-age=60 : Skip assets created less than this many minutes ago}
        {--run : Actually delete orphaned assets (default is a dry run)}';

    protected $description = 'Delete Cloudinary assets that have no matching media record';

    public function handle(CloudinaryService $cloudinary): int
    {
        if (! $cloudinary->isConfigured()) {
            $this->error('Cloudinary is not configured.');

            return self::FAILURE;
        }

        $configuredFolders = array_values(config('cloudinary.folders', []));
        $requestedFolder = $this->option('folder');

        if ($requestedFolder !== null && ! in_array($requestedFolder, $configuredFolders, true)) {
            $this->error(sprintf('"%s" is not an application media folder.', $requestedFolder));

            return self::FAILURE;
        }

        $folders = $requestedFolder !== null ? [$requestedFolder] : $configuredFolders;
        $minimumAge = max(0, (int) $this->option('min-age'));
        $cutoff = CarbonImmutable::now()->subMinutes($minimumAge);

        $totalOrphans = 0;
        $deleted = 0;
        $failed = 0;

        foreach ($folders as $folder) {
            $assets = $cloudinary->listAssets($folder);

            $referenced = array_flip(
                Media::query()
                    ->where('cloudinary_public_id', 'like', $folder.'/%')
                    ->pluck('cloudinary_public_id')
                    ->all()
            );

            $orphans = [];
            $recent = 0;

            foreach ($assets as $asset) {
                if (isset($referenced[$asset['public_id']])) {
                    continue;
                }

                $createdAt = $asset['created_at'] !== null
                    ? CarbonImmutable::parse($asset['created_at'])
                    : null;

                // A very recent upload may still be waiting for the browser
                // to POST its result back to /media/store. Never delete it.
                if ($createdAt !== null && $createdAt->greaterThan($cutoff)) {
                    $recent++;

                    continue;
                }

                $orphans[] = $asset['public_id'];
            }

            $totalOrphans += count($orphans);

            if ($orphans === []) {
                $message = sprintf('No orphaned assets found in "%s".', $folder);

                if ($recent > 0) {
                    $message .= sprintf(' Skipped %d recent asset(s).', $recent);
                }

                $this->info($message);

                continue;
            }

            $this->info(sprintf('Found %d orphaned asset(s) in "%s".', count($orphans), $folder));

            if ($recent > 0) {
                $this->line(sprintf('  Skipped %d recent asset(s).', $recent));
            }

            foreach ($orphans as $publicId) {
                $this->line('  - '.$publicId);
            }

            if (! $this->option('run')) {
                continue;
            }

            foreach ($orphans as $publicId) {
                if ($cloudinary->destroy($publicId)) {
                    $deleted++;
                } else {
                    $failed++;
                    $this->warn('  Failed: '.$publicId);
                }
            }
        }

        if ($totalOrphans === 0) {
            return self::SUCCESS;
        }

        if (! $this->option('run')) {
            $this->warn('Dry run — nothing was deleted. Re-run with --run to delete.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Deleted %d asset(s), %d failed.', $deleted, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
