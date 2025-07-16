<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Services\ExternalVisitSyncService;
use Illuminate\Console\Command;

class TestExternalDataFormatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visit:test-data-format {visit_id? : Visit ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test external data format without sending to API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $visitId = $this->argument('visit_id');

        if (!$visitId) {
            $visits = Visit::latest()->limit(5)->get();

            if ($visits->isEmpty()) {
                $this->error('No visits found.');
                return 1;
            }

            $this->info('Recent visits:');
            $this->table(
                ['ID', 'Date', 'User', 'Outlet', 'Type', 'Transaction'],
                $visits->map(function ($visit) {
                    return [
                        $visit->id,
                        $visit->visit_date->format('Y-m-d'),
                        $visit->user->name ?? 'N/A',
                        $visit->outlet->name ?? 'N/A',
                        $visit->type,
                        $visit->transaction ?? 'N/A'
                    ];
                })
            );

            $visitId = $this->ask('Enter visit ID to test');
        }

        $visit = Visit::find($visitId);
        if (!$visit) {
            $this->error("Visit with ID {$visitId} not found.");
            return 1;
        }

        $this->info("Testing data format for Visit ID: {$visitId}");
        $this->line("Visit Date: {$visit->visit_date->format('Y-m-d')}");
        $this->line("Type: {$visit->type}");
        $this->line("Transaction: " . ($visit->transaction ?? 'N/A'));
        $this->line("Checkin Photo: " . ($visit->checkin_photo ?? 'N/A'));
        $this->line("Checkout Photo: " . ($visit->checkout_photo ?? 'N/A'));

        // Test data preparation
        $service = new ExternalVisitSyncService();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('prepareVisitData');
        $method->setAccessible(true);

        $data = $method->invoke($service, $visit);

        $this->info("\n📋 Data yang akan dikirim ke external API:");
        $this->line(json_encode($data, JSON_PRETTY_PRINT));

        // Test photo path resolution
        $this->info("\n📸 Photo path testing:");
        $photoMethod = $reflection->getMethod('getPhotoPath');
        $photoMethod->setAccessible(true);

        if ($visit->checkin_photo) {
            $checkinPath = $photoMethod->invoke($service, $visit->checkin_photo);
            $this->line("Checkin photo path: " . ($checkinPath ?? 'NOT FOUND'));
        }

        if ($visit->checkout_photo) {
            $checkoutPath = $photoMethod->invoke($service, $visit->checkout_photo);
            $this->line("Checkout photo path: " . ($checkoutPath ?? 'NOT FOUND'));
        }

        return 0;
    }
}
