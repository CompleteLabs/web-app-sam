<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

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
}
