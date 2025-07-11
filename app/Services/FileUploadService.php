<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FileUploadService
{
    /**
     * Upload a photo file
     */
    public static function uploadPhoto(UploadedFile $file, string $prefix = '', ?string $username = null): string
    {
        $username = $username ?? (Auth::user()->username ?? 'anonymous');
        $fileName = self::generateFileName($file, $prefix, $username);

        $file->move(storage_path('app/'.config('business.upload.path')), $fileName);

        return $fileName;
    }

    /**
     * Upload a video file
     */
    public static function uploadVideo(UploadedFile $file, string $prefix = '', ?string $username = null): string
    {
        $username = $username ?? (Auth::user()->username ?? 'anonymous');
        $fileName = self::generateFileName($file, $prefix, $username);

        $file->move(storage_path('app/'.config('business.upload.path')), $fileName);

        return $fileName;
    }

    /**
     * Generate unique filename
     */
    private static function generateFileName(UploadedFile $file, string $prefix, string $username): string
    {
        $date = date('Y-m-d');
        $time = date('His');
        $random = substr(bin2hex(random_bytes(4)), 0, 6);
        $extension = $file->getClientOriginalExtension();

        $parts = [];
        if ($prefix) $parts[] = $prefix;
        $parts[] = $username;
        $parts[] = $date;
        $parts[] = $time;
        $parts[] = $random;
        $filename = implode('_', $parts) . '.' . $extension;
        return $filename;
    }

    /**
     * Upload multiple photos for outlet
     *
     * @param  array  $files  Array of UploadedFile objects keyed by field name
     */
    public static function uploadOutletPhotos(array $files, string $prefix = 'outlet'): array
    {
        $uploadedFiles = [];

        foreach ($files as $fieldName => $file) {
            if ($file instanceof UploadedFile) {
                $uploadedFiles[$fieldName] = self::uploadPhoto($file, "{$prefix}-{$fieldName}");
            }
        }

        return $uploadedFiles;
    }

    /**
     * Validate file type for photos
     */
    public static function isValidPhoto(UploadedFile $file): bool
    {
        $allowedExtensions = config('business.upload.allowed_photo_extensions');

        return in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions);
    }

    /**
     * Validate file type for videos
     */
    public static function isValidVideo(UploadedFile $file): bool
    {
        $allowedExtensions = config('business.upload.allowed_video_extensions');

        return in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions);
    }

    /**
     * Check file size for photos
     */
    public static function isValidPhotoSize(UploadedFile $file): bool
    {
        $maxSize = config('business.upload.max_photo_size') * 1024; // Convert KB to bytes

        return $file->getSize() <= $maxSize;
    }

    /**
     * Check file size for videos
     */
    public static function isValidVideoSize(UploadedFile $file): bool
    {
        $maxSize = config('business.upload.max_video_size') * 1024; // Convert KB to bytes

        return $file->getSize() <= $maxSize;
    }

    /**
     * Delete uploaded file
     */
    public static function deleteFile(string $fileName): bool
    {
        $filePath = storage_path('app/'.config('business.upload.path').'/'.$fileName);

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * ========================================
     * ASYNC UPLOAD METHODS (FOR HIGH PERFORMANCE)
     * ========================================
     */

    /**
     * Save file to temp location and dispatch job for async processing
     * Returns temp file info for tracking
     */
    public static function uploadPhotoAsync(UploadedFile $file, string $prefix = '', ?string $username = null): array
    {
        $username = $username ?? (Auth::user()->username ?? 'anonymous');
        $finalFileName = self::generateFileName($file, $prefix, $username);

        // Get file info BEFORE moving (important!)
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();

        // Save to temp location (fast operation)
        $tempFileName = 'temp_' . time() . '_' . $finalFileName;
        $tempPath = storage_path('app/temp/' . $tempFileName);

        // Create temp directory if not exists
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Move to temp (blocking but fast since it's local)
        $file->move(storage_path('app/temp'), $tempFileName);

        return [
            'temp_file' => $tempFileName,
            'temp_path' => $tempPath,
            'final_name' => $finalFileName,
            'field_name' => $prefix,
            'size' => $fileSize,
            'mime_type' => $mimeType,
            'original_name' => $originalName,
        ];
    }

    /**
     * Save video to temp location and dispatch job for async processing
     */
    public static function uploadVideoAsync(UploadedFile $file, string $prefix = '', ?string $username = null): array
    {
        $username = $username ?? (Auth::user()->username ?? 'anonymous');
        $finalFileName = self::generateFileName($file, $prefix, $username);

        // Get file info BEFORE moving (important!)
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();

        // Save to temp location (fast operation)
        $tempFileName = 'temp_' . time() . '_' . $finalFileName;
        $tempPath = storage_path('app/temp/' . $tempFileName);

        // Create temp directory if not exists
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Move to temp (blocking but fast since it's local)
        $file->move(storage_path('app/temp'), $tempFileName);

        return [
            'temp_file' => $tempFileName,
            'temp_path' => $tempPath,
            'final_name' => $finalFileName,
            'field_name' => $prefix,
            'size' => $fileSize,
            'mime_type' => $mimeType,
            'original_name' => $originalName,
        ];
    }

    /**
     * Handle multiple file uploads asynchronously
     * Returns array of temp file info for job processing
     */
    public static function uploadOutletPhotosAsync(array $files, string $prefix = 'outlet'): array
    {
        $tempFiles = [];

        foreach ($files as $fieldName => $file) {
            if ($file instanceof UploadedFile) {
                $tempFiles[$fieldName] = self::uploadPhotoAsync($file, "{$prefix}-{$fieldName}");
            }
        }

        return $tempFiles;
    }

    /**
     * Move file from temp to permanent storage (used by Jobs)
     * This is the heavy operation that runs in background
     */
    public static function moveFromTempToPermanent(string $tempPath, string $finalFileName): bool
    {
        try {
            $finalPath = storage_path('app/' . config('business.upload.path') . '/' . $finalFileName);

            // Create final directory if not exists
            $finalDir = dirname($finalPath);
            if (!is_dir($finalDir)) {
                mkdir($finalDir, 0755, true);
            }

            // Move file from temp to permanent location
            if (file_exists($tempPath)) {
                $result = rename($tempPath, $finalPath);                if ($result) {
                    // Check file type and compress if needed
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
                    $extension = strtolower(pathinfo($finalFileName, PATHINFO_EXTENSION));

                    Log::info('File moved successfully, checking for compression', [
                        'final_path' => $finalPath,
                        'extension' => $extension,
                        'is_image' => in_array($extension, $imageExtensions),
                        'is_video' => in_array($extension, $videoExtensions)
                    ]);

                    if (in_array($extension, $imageExtensions)) {
                        // Compress image if it's over 200KB - target 200KB max
                        Log::info('Attempting image compression', [
                            'final_path' => $finalPath,
                            'file_size_kb' => file_exists($finalPath) ? round(filesize($finalPath) / 1024, 2) : 0
                        ]);

                        self::compressImageIfNeeded($finalPath, 200, 85);
                    } elseif (in_array($extension, $videoExtensions)) {
                        // Compress video if it's over 1MB - target 1MB max
                        Log::info('Attempting video compression', [
                            'final_path' => $finalPath,
                            'file_size_mb' => file_exists($finalPath) ? round(filesize($finalPath) / (1024 * 1024), 2) : 0
                        ]);

                        self::compressVideoIfNeeded($finalPath, 1, 'medium');
                    }
                }

                return $result;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error moving file from temp to permanent: ' . $e->getMessage(), [
                'temp_path' => $tempPath,
                'final_name' => $finalFileName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clean up temp files older than specified hours
     */
    public static function cleanupTempFiles(int $hoursOld = 24): int
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            return 0;
        }

        $deletedCount = 0;
        $cutoffTime = time() - ($hoursOld * 3600);

        $files = glob($tempDir . '/temp_*');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Compress image dynamically until it reaches target size
     *
     * @param string $filePath Path to the image file
     * @param int $maxSizeKB Maximum size in KB (default: 200)
     * @param int $initialQuality Initial JPEG quality (default: 85)
     * @return bool Success status
     */
    public static function compressImageIfNeeded(string $filePath, int $maxSizeKB = 200, int $initialQuality = 85): bool
    {
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                Log::warning('File not found for compression', ['path' => $filePath]);
                return false;
            }

            // Get file size in KB
            $fileSizeKB = filesize($filePath) / 1024;

            if ($fileSizeKB <= $maxSizeKB) {
                Log::info('Image compression skipped - file size acceptable', [
                    'path' => $filePath,
                    'size_kb' => round($fileSizeKB, 2),
                    'max_size_kb' => $maxSizeKB
                ]);
                return true; // No compression needed
            }

            // Get image info
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                Log::error('Unable to get image info for compression', ['path' => $filePath]);
                return false;
            }

            $mimeType = $imageInfo['mime'];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];

            // Create image resource based on type
            $image = null;
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($filePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($filePath);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($filePath);
                    break;
                default:
                    Log::error('Unsupported image type for compression', [
                        'path' => $filePath,
                        'mime_type' => $mimeType
                    ]);
                    return false;
            }

            if (!$image) {
                Log::error('Failed to create image resource for compression', ['path' => $filePath]);
                return false;
            }

            // Dynamic compression strategy
            $compressionStrategies = [
                // Strategy 1: Original size with high quality
                ['width' => $originalWidth, 'height' => $originalHeight, 'quality' => $initialQuality],
                // Strategy 2: Original size with medium quality
                ['width' => $originalWidth, 'height' => $originalHeight, 'quality' => 75],
                // Strategy 3: Resize to 80% with medium quality
                ['width' => intval($originalWidth * 0.8), 'height' => intval($originalHeight * 0.8), 'quality' => 75],
                // Strategy 4: Original size with low quality
                ['width' => $originalWidth, 'height' => $originalHeight, 'quality' => 60],
                // Strategy 5: Resize to 70% with low quality
                ['width' => intval($originalWidth * 0.7), 'height' => intval($originalHeight * 0.7), 'quality' => 60],
                // Strategy 6: Resize to max dimensions with medium quality
                ['width' => min($originalWidth, 1920), 'height' => min($originalHeight, 1080), 'quality' => 70],
                // Strategy 7: Resize to max dimensions with low quality
                ['width' => min($originalWidth, 1920), 'height' => min($originalHeight, 1080), 'quality' => 50],
                // Strategy 8: Resize to 50% with very low quality
                ['width' => intval($originalWidth * 0.5), 'height' => intval($originalHeight * 0.5), 'quality' => 40],
                // Strategy 9: Last resort - very small with minimum quality
                ['width' => intval($originalWidth * 0.4), 'height' => intval($originalHeight * 0.4), 'quality' => 30],
            ];

            $success = false;
            $finalQuality = $initialQuality;
            $finalWidth = $originalWidth;
            $finalHeight = $originalHeight;

            foreach ($compressionStrategies as $strategy) {
                $tempImage = $image;
                $needsResize = ($strategy['width'] != $originalWidth || $strategy['height'] != $originalHeight);

                // Create resized image if needed
                if ($needsResize) {
                    $tempImage = imagecreatetruecolor($strategy['width'], $strategy['height']);

                    // Preserve transparency for PNG
                    if ($mimeType === 'image/png') {
                        imagealphablending($tempImage, false);
                        imagesavealpha($tempImage, true);
                        $transparent = imagecolorallocatealpha($tempImage, 255, 255, 255, 127);
                        imagefilledrectangle($tempImage, 0, 0, $strategy['width'], $strategy['height'], $transparent);
                    }

                    imagecopyresampled($tempImage, $image, 0, 0, 0, 0, $strategy['width'], $strategy['height'], $originalWidth, $originalHeight);
                }

                // Create temporary file for testing
                $tempFile = $filePath . '.tmp';
                $compressionSuccess = false;

                switch ($mimeType) {
                    case 'image/jpeg':
                        $compressionSuccess = imagejpeg($tempImage, $tempFile, $strategy['quality']);
                        break;
                    case 'image/png':
                        $pngQuality = intval((100 - $strategy['quality']) / 10);
                        $compressionSuccess = imagepng($tempImage, $tempFile, $pngQuality);
                        break;
                    case 'image/gif':
                        $compressionSuccess = imagegif($tempImage, $tempFile);
                        break;
                }

                if ($needsResize && $tempImage !== $image) {
                    imagedestroy($tempImage);
                }

                if ($compressionSuccess && file_exists($tempFile)) {
                    $tempFileSize = filesize($tempFile) / 1024;

                    if ($tempFileSize <= $maxSizeKB) {
                        // Success! Use this compressed version
                        rename($tempFile, $filePath);
                        $success = true;
                        $finalQuality = $strategy['quality'];
                        $finalWidth = $strategy['width'];
                        $finalHeight = $strategy['height'];
                        break;
                    } else {
                        // Clean up temp file and try next strategy
                        unlink($tempFile);
                    }
                }
            }

            imagedestroy($image);

            if ($success) {
                $newFileSizeKB = filesize($filePath) / 1024;
                Log::info('Image compressed successfully with dynamic strategy', [
                    'path' => $filePath,
                    'original_size_kb' => round($fileSizeKB, 2),
                    'compressed_size_kb' => round($newFileSizeKB, 2),
                    'compression_ratio' => round(($fileSizeKB - $newFileSizeKB) / $fileSizeKB * 100, 2) . '%',
                    'dimensions' => "{$originalWidth}x{$originalHeight}" . ($finalWidth != $originalWidth || $finalHeight != $originalHeight ? " → {$finalWidth}x{$finalHeight}" : ""),
                    'final_quality' => $finalQuality,
                    'target_size_kb' => $maxSizeKB
                ]);
                return true;
            } else {
                Log::warning('Unable to compress image to target size', [
                    'path' => $filePath,
                    'original_size_kb' => round($fileSizeKB, 2),
                    'target_size_kb' => $maxSizeKB
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error compressing image: ' . $e->getMessage(), [
                'path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Compress video dynamically until it reaches target size
     *
     * @param string $filePath Path to the video file
     * @param int $maxSizeMB Maximum size in MB (default: 1)
     * @param string $initialQuality Initial video quality preset (default: 'medium')
     * @return bool Success status
     */
    public static function compressVideoIfNeeded(string $filePath, int $maxSizeMB = 1, string $initialQuality = 'medium'): bool
    {
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                Log::warning('Video file not found for compression', ['path' => $filePath]);
                return false;
            }

            // Get file size in MB
            $fileSizeMB = filesize($filePath) / (1024 * 1024);

            if ($fileSizeMB <= $maxSizeMB) {
                Log::info('Video compression skipped - file size acceptable', [
                    'path' => $filePath,
                    'size_mb' => round($fileSizeMB, 2),
                    'max_size_mb' => $maxSizeMB
                ]);
                return true; // No compression needed
            }

            // Check if FFmpeg is available
            if (!self::isFFmpegAvailable()) {
                Log::warning('FFmpeg not available for video compression', ['path' => $filePath]);
                return false;
            }

            // Dynamic compression strategies - progressively more aggressive
            $compressionStrategies = [
                // Strategy 1: High quality with controlled bitrate
                ['crf' => 20, 'preset' => 'medium', 'scale' => '', 'maxrate' => '1500k', 'bufsize' => '3000k'],
                // Strategy 2: Medium quality with lower bitrate
                ['crf' => 25, 'preset' => 'medium', 'scale' => '', 'maxrate' => '1000k', 'bufsize' => '2000k'],
                // Strategy 3: Lower quality with restricted bitrate
                ['crf' => 28, 'preset' => 'fast', 'scale' => '', 'maxrate' => '800k', 'bufsize' => '1600k'],
                // Strategy 4: Scale down to 80% with medium quality
                ['crf' => 25, 'preset' => 'fast', 'scale' => 'scale=iw*0.8:ih*0.8', 'maxrate' => '700k', 'bufsize' => '1400k'],
                // Strategy 5: Scale down to 70% with lower quality
                ['crf' => 30, 'preset' => 'fast', 'scale' => 'scale=iw*0.7:ih*0.7', 'maxrate' => '600k', 'bufsize' => '1200k'],
                // Strategy 6: Scale down to 60% with low quality
                ['crf' => 32, 'preset' => 'fast', 'scale' => 'scale=iw*0.6:ih*0.6', 'maxrate' => '500k', 'bufsize' => '1000k'],
                // Strategy 7: Scale down to 50% with very low quality
                ['crf' => 35, 'preset' => 'veryfast', 'scale' => 'scale=iw*0.5:ih*0.5', 'maxrate' => '400k', 'bufsize' => '800k'],
                // Strategy 8: Last resort - very small and low quality
                ['crf' => 40, 'preset' => 'veryfast', 'scale' => 'scale=iw*0.4:ih*0.4', 'maxrate' => '300k', 'bufsize' => '600k'],
            ];

            $pathInfo = pathinfo($filePath);
            $ffmpegPath = self::getFFmpegPath();
            $success = false;
            $finalStrategy = null;

            foreach ($compressionStrategies as $index => $strategy) {
                $tempOutputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . "_temp_{$index}." . $pathInfo['extension'];

                // Build FFmpeg command with current strategy
                $filterComplex = '';
                if (!empty($strategy['scale'])) {
                    $filterComplex = sprintf(' -vf "%s"', $strategy['scale']);
                }

                $command = sprintf(
                    '%s -i %s -c:v libx264 -crf %d -preset %s%s -c:a aac -b:a 96k -maxrate %s -bufsize %s -movflags +faststart %s 2>&1',
                    escapeshellarg($ffmpegPath),
                    escapeshellarg($filePath),
                    $strategy['crf'],
                    $strategy['preset'],
                    $filterComplex,
                    $strategy['maxrate'],
                    $strategy['bufsize'],
                    escapeshellarg($tempOutputPath)
                );

                Log::info('Trying video compression strategy', [
                    'path' => $filePath,
                    'strategy' => $index + 1,
                    'crf' => $strategy['crf'],
                    'preset' => $strategy['preset'],
                    'scale' => $strategy['scale'] ?: 'original',
                    'maxrate' => $strategy['maxrate'],
                    'target_size_mb' => $maxSizeMB
                ]);

                // Execute FFmpeg command
                $output = [];
                $returnCode = 0;
                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($tempOutputPath)) {
                    $compressedSizeMB = filesize($tempOutputPath) / (1024 * 1024);

                    if ($compressedSizeMB <= $maxSizeMB) {
                        // Success! Use this compressed version
                        if (rename($tempOutputPath, $filePath)) {
                            $success = true;
                            $finalStrategy = $strategy;
                            break;
                        } else {
                            Log::error('Failed to replace original video with compressed version', [
                                'path' => $filePath,
                                'strategy' => $index + 1
                            ]);
                        }
                    } else {
                        Log::info('Strategy did not achieve target size, trying next', [
                            'path' => $filePath,
                            'strategy' => $index + 1,
                            'achieved_size_mb' => round($compressedSizeMB, 2),
                            'target_size_mb' => $maxSizeMB
                        ]);
                    }

                    // Clean up temp file if not successful
                    if (!$success && file_exists($tempOutputPath)) {
                        unlink($tempOutputPath);
                    }
                } else {
                    Log::warning('FFmpeg command failed for strategy', [
                        'path' => $filePath,
                        'strategy' => $index + 1,
                        'return_code' => $returnCode,
                        'output' => implode("\n", array_slice($output, -5)) // Last 5 lines
                    ]);

                    // Clean up temp file
                    if (file_exists($tempOutputPath)) {
                        unlink($tempOutputPath);
                    }
                }
            }

            if ($success) {
                $newFileSizeMB = filesize($filePath) / (1024 * 1024);
                Log::info('Video compressed successfully with dynamic strategy', [
                    'path' => $filePath,
                    'original_size_mb' => round($fileSizeMB, 2),
                    'compressed_size_mb' => round($newFileSizeMB, 2),
                    'compression_ratio' => round(($fileSizeMB - $newFileSizeMB) / $fileSizeMB * 100, 2) . '%',
                    'final_strategy' => $finalStrategy,
                    'target_size_mb' => $maxSizeMB
                ]);
                return true;
            } else {
                Log::warning('Unable to compress video to target size with any strategy', [
                    'path' => $filePath,
                    'original_size_mb' => round($fileSizeMB, 2),
                    'target_size_mb' => $maxSizeMB
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error compressing video: ' . $e->getMessage(), [
                'path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if FFmpeg is available
     */
    private static function isFFmpegAvailable(): bool
    {
        $ffmpegPath = self::getFFmpegPath();
        if (!$ffmpegPath) {
            return false;
        }

        // Test FFmpeg with version command
        $command = sprintf('%s -version 2>&1', escapeshellarg($ffmpegPath));
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Get FFmpeg path
     */
    private static function getFFmpegPath(): ?string
    {
        // Try common paths
        $paths = [
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
            '/usr/bin/ffmpeg',
            'ffmpeg' // System PATH
        ];

        foreach ($paths as $path) {
            if ($path === 'ffmpeg') {
                // Test if ffmpeg is in PATH
                $command = 'which ffmpeg 2>/dev/null';
                $output = shell_exec($command);
                if (!empty($output)) {
                    return trim($output);
                }
            } else {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Compress existing images in storage (for maintenance)
     *
     * @param string $directory Directory to scan for images
     * @param int $maxSizeKB Maximum size in KB
     * @param int $quality JPEG quality
     * @return array Statistics of compression
     */
    public static function compressExistingImages(string $directory = '', int $maxSizeKB = 200, int $quality = 85): array
    {
        $uploadPath = storage_path('app/' . config('business.upload.path'));
        $scanPath = $directory ? $uploadPath . '/' . $directory : $uploadPath;

        if (!is_dir($scanPath)) {
            return ['error' => 'Directory not found: ' . $scanPath];
        }

        $stats = [
            'scanned' => 0,
            'compressed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_size_saved_kb' => 0
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());

                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $stats['scanned']++;
                    $filePath = $file->getPathname();
                    $originalSizeKB = filesize($filePath) / 1024;

                    if ($originalSizeKB > $maxSizeKB) {
                        $success = self::compressImageIfNeeded($filePath, $maxSizeKB, $quality);

                        if ($success) {
                            $newSizeKB = filesize($filePath) / 1024;
                            $stats['compressed']++;
                            $stats['total_size_saved_kb'] += ($originalSizeKB - $newSizeKB);
                        } else {
                            $stats['errors']++;
                        }
                    } else {
                        $stats['skipped']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Compress existing videos in storage (for maintenance)
     *
     * @param string $directory Directory to scan for videos
     * @param int $maxSizeMB Maximum size in MB
     * @param string $quality Video quality preset
     * @return array Statistics of compression
     */
    public static function compressExistingVideos(string $directory = '', int $maxSizeMB = 5, string $quality = 'medium'): array
    {
        $uploadPath = storage_path('app/' . config('business.upload.path'));
        $scanPath = $directory ? $uploadPath . '/' . $directory : $uploadPath;

        if (!is_dir($scanPath)) {
            return ['error' => 'Directory not found: ' . $scanPath];
        }

        $stats = [
            'scanned' => 0,
            'compressed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_size_saved_mb' => 0
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());

                if (in_array($extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
                    $stats['scanned']++;
                    $filePath = $file->getPathname();
                    $originalSizeMB = filesize($filePath) / (1024 * 1024);

                    if ($originalSizeMB > $maxSizeMB) {
                        $success = self::compressVideoIfNeeded($filePath, $maxSizeMB, $quality);

                        if ($success) {
                            $newSizeMB = filesize($filePath) / (1024 * 1024);
                            $stats['compressed']++;
                            $stats['total_size_saved_mb'] += ($originalSizeMB - $newSizeMB);
                        } else {
                            $stats['errors']++;
                        }
                    } else {
                        $stats['skipped']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Compress existing media files (images and videos) in storage
     *
     * @param string $directory Directory to scan
     * @param int $maxImageSizeKB Maximum image size in KB
     * @param int $maxVideoSizeMB Maximum video size in MB
     * @param int $imageQuality Initial image quality
     * @param string $videoQuality Initial video quality preset
     * @return array Combined statistics
     */
    public static function compressExistingMedia(
        string $directory = '',
        int $maxImageSizeKB = 200,
        int $maxVideoSizeMB = 1,
        int $imageQuality = 85,
        string $videoQuality = 'medium'
    ): array {
        $imageStats = self::compressExistingImages($directory, $maxImageSizeKB, $imageQuality);
        $videoStats = self::compressExistingVideos($directory, $maxVideoSizeMB, $videoQuality);

        if (isset($imageStats['error']) || isset($videoStats['error'])) {
            return ['error' => $imageStats['error'] ?? $videoStats['error']];
        }

        return [
            'images' => $imageStats,
            'videos' => $videoStats,
            'total_files_scanned' => $imageStats['scanned'] + $videoStats['scanned'],
            'total_files_compressed' => $imageStats['compressed'] + $videoStats['compressed'],
            'total_size_saved_mb' => round($imageStats['total_size_saved_kb'] / 1024, 2) + $videoStats['total_size_saved_mb']
        ];
    }
}
