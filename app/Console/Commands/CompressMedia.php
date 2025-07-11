<?php

namespace App\Console\Commands;

use App\Services\FileUploadService;
use Illuminate\Console\Command;

class CompressMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:compress
                          {directory? : Specific directory to compress (optional)}
                          {--max-image-size=200 : Maximum image size in KB before compression}
                          {--max-video-size=5 : Maximum video size in MB before compression}
                          {--image-quality=85 : Image quality (1-100)}
                          {--video-quality=medium : Video quality preset (low, medium, high)}
                          {--images-only : Compress only images}
                          {--videos-only : Compress only videos}
                          {--dry-run : Show what would be compressed without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress existing images and videos in storage that are over specified size';    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = $this->argument('directory') ?? '';
        $maxImageSize = (int) $this->option('max-image-size');
        $maxVideoSize = (int) $this->option('max-video-size');
        $imageQuality = (int) $this->option('image-quality');
        $videoQuality = $this->option('video-quality');
        $imagesOnly = $this->option('images-only');
        $videosOnly = $this->option('videos-only');
        $dryRun = $this->option('dry-run');

        $this->info('Starting media compression process...');
        $this->info('Directory: ' . ($directory ?: 'All directories'));
        
        if (!$videosOnly) {
            $this->info('Max image size: ' . $maxImageSize . 'KB');
            $this->info('Image quality: ' . $imageQuality . '%');
        }
        
        if (!$imagesOnly) {
            $this->info('Max video size: ' . $maxVideoSize . 'MB');
            $this->info('Video quality: ' . $videoQuality);
        }
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be modified');
        }

        $this->newLine();

        if ($dryRun) {
            $this->simulateCompression($directory, $maxImageSize, $maxVideoSize, $imagesOnly, $videosOnly);
        } else {
            if ($imagesOnly) {
                $stats = FileUploadService::compressExistingImages($directory, $maxImageSize, $imageQuality);
                $this->displayImageStats($stats);
            } elseif ($videosOnly) {
                $stats = FileUploadService::compressExistingVideos($directory, $maxVideoSize, $videoQuality);
                $this->displayVideoStats($stats);
            } else {
                $stats = FileUploadService::compressExistingMedia($directory, $maxImageSize, $maxVideoSize, $imageQuality, $videoQuality);
                $this->displayMediaStats($stats);
            }
        }

        $this->newLine();
        $this->info('Media compression process completed!');
    }    /**
     * Simulate compression for dry run
     */
    private function simulateCompression(string $directory, int $maxImageSizeKB, int $maxVideoSizeMB, bool $imagesOnly, bool $videosOnly): void
    {
        $uploadPath = storage_path('app/' . config('business.upload.path'));
        $scanPath = $directory ? $uploadPath . '/' . $directory : $uploadPath;
        
        if (!is_dir($scanPath)) {
            $this->error('Directory not found: ' . $scanPath);
            return;
        }

        $imageStats = [
            'scanned' => 0,
            'would_compress' => 0,
            'would_skip' => 0,
            'total_size_to_process_kb' => 0
        ];

        $videoStats = [
            'scanned' => 0,
            'would_compress' => 0,
            'would_skip' => 0,
            'total_size_to_process_mb' => 0
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                $filePath = $file->getPathname();
                $relativePath = str_replace($uploadPath . '/', '', $filePath);
                
                if (!$videosOnly && in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $imageStats['scanned']++;
                    $fileSizeKB = filesize($filePath) / 1024;
                    
                    if ($fileSizeKB > $maxImageSizeKB) {
                        $imageStats['would_compress']++;
                        $imageStats['total_size_to_process_kb'] += $fileSizeKB;
                        
                        $this->line(sprintf(
                            'Would compress IMAGE: %s (%.2f KB)',
                            $relativePath,
                            $fileSizeKB
                        ));
                    } else {
                        $imageStats['would_skip']++;
                    }
                }
                
                if (!$imagesOnly && in_array($extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
                    $videoStats['scanned']++;
                    $fileSizeMB = filesize($filePath) / (1024 * 1024);
                    
                    if ($fileSizeMB > $maxVideoSizeMB) {
                        $videoStats['would_compress']++;
                        $videoStats['total_size_to_process_mb'] += $fileSizeMB;
                        
                        $this->line(sprintf(
                            'Would compress VIDEO: %s (%.2f MB)',
                            $relativePath,
                            $fileSizeMB
                        ));
                    } else {
                        $videoStats['would_skip']++;
                    }
                }
            }
        }

        $this->newLine();
        $this->info('DRY RUN RESULTS:');
        
        if (!$videosOnly) {
            $this->line('<comment>IMAGES:</comment>');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Images scanned', $imageStats['scanned']],
                    ['Would compress', $imageStats['would_compress']],
                    ['Would skip', $imageStats['would_skip']],
                    ['Total size to process', round($imageStats['total_size_to_process_kb'] / 1024, 2) . ' MB'],
                ]
            );
        }
        
        if (!$imagesOnly) {
            $this->line('<comment>VIDEOS:</comment>');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Videos scanned', $videoStats['scanned']],
                    ['Would compress', $videoStats['would_compress']],
                    ['Would skip', $videoStats['would_skip']],
                    ['Total size to process', round($videoStats['total_size_to_process_mb'], 2) . ' MB'],
                ]
            );
        }
    }

    /**
     * Display media compression statistics
     */
    private function displayMediaStats(array $stats): void
    {
        if (isset($stats['error'])) {
            $this->error($stats['error']);
            return;
        }

        $this->info('MEDIA COMPRESSION RESULTS:');
        
        $this->line('<comment>IMAGES:</comment>');
        $this->displayImageStats($stats['images']);
        
        $this->newLine();
        $this->line('<comment>VIDEOS:</comment>');
        $this->displayVideoStats($stats['videos']);
        
        $this->newLine();
        $this->line('<comment>TOTAL SUMMARY:</comment>');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total files scanned', $stats['total_files_scanned']],
                ['Total files compressed', $stats['total_files_compressed']],
                ['Total size saved', round($stats['total_size_saved_mb'], 2) . ' MB'],
            ]
        );
    }

    /**
     * Display image compression statistics
     */
    private function displayImageStats(array $stats): void
    {
        if (isset($stats['error'])) {
            $this->error($stats['error']);
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Images scanned', $stats['scanned']],
                ['Images compressed', $stats['compressed']],
                ['Images skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
                ['Total size saved', round($stats['total_size_saved_kb'] / 1024, 2) . ' MB'],
            ]
        );

        if ($stats['compressed'] > 0) {
            $avgSavingKB = $stats['total_size_saved_kb'] / $stats['compressed'];
            $this->info('Average saving per compressed image: ' . round($avgSavingKB, 2) . ' KB');
        }

        if ($stats['errors'] > 0) {
            $this->warn('Some images could not be compressed. Check the log for details.');
        }
    }

    /**
     * Display video compression statistics
     */
    private function displayVideoStats(array $stats): void
    {
        if (isset($stats['error'])) {
            $this->error($stats['error']);
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Videos scanned', $stats['scanned']],
                ['Videos compressed', $stats['compressed']],
                ['Videos skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
                ['Total size saved', round($stats['total_size_saved_mb'], 2) . ' MB'],
            ]
        );

        if ($stats['compressed'] > 0) {
            $avgSavingMB = $stats['total_size_saved_mb'] / $stats['compressed'];
            $this->info('Average saving per compressed video: ' . round($avgSavingMB, 2) . ' MB');
        }

        if ($stats['errors'] > 0) {
            $this->warn('Some videos could not be compressed. Check the log for details.');
        }
    }
}
