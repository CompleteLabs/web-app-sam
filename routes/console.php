<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register custom commands
Artisan::command('media:compress {directory?} {--max-image-size=200} {--max-video-size=5} {--image-quality=85} {--video-quality=medium} {--images-only} {--videos-only} {--dry-run}', function () {
    $command = new \App\Console\Commands\CompressMedia();
    $command->setLaravel($this->laravel);
    $command->setInput($this->input);
    $command->setOutput($this->output);
    return $command->handle();
})->purpose('Compress existing images and videos in storage that are over specified size');

// Legacy command alias for backward compatibility
Artisan::command('images:compress {directory?} {--max-size=200} {--quality=85} {--dry-run}', function () {
    $this->call('media:compress', [
        'directory' => $this->argument('directory'),
        '--max-image-size' => $this->option('max-size'),
        '--image-quality' => $this->option('quality'),
        '--images-only' => true,
        '--dry-run' => $this->option('dry-run')
    ]);
})->purpose('Compress existing images in storage (legacy command)');
