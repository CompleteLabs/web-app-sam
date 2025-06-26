<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FilamentSetupCommand extends Command
{
    protected $signature = 'setup:filament-permissions {model?} {--all : Setup for all models} {--custom= : Add custom actions (comma separated)}';

    protected $description = 'Complete Filament permissions setup - generates permissions, policies, and seeder';

    public function handle()
    {
        $this->info('🚀 Starting Filament Permissions Setup...');
        $this->newLine();

        // Step 1: Generate Permissions
        $this->info('📝 Step 1: Generating permissions...');
        $this->call('filament:permissions', [
            'model' => $this->argument('model'),
            '--all' => $this->option('all'),
            '--custom' => $this->option('custom'),
        ]);
        $this->newLine();

        // Step 2: Generate Policies
        $this->info('🛡️  Step 2: Generating policies...');
        $this->call('filament:policies', [
            'model' => $this->argument('model'),
            '--all' => $this->option('all'),
        ]);
        $this->newLine();

        // Step 3: Generate Seeder
        $this->info('🌱 Step 3: Generating permission seeder...');
        $models = $this->argument('model') ? [$this->argument('model')] : [];
        $this->call('filament:permission-seeder', [
            '--models' => $models,
        ]);
        $this->newLine();

        // Step 4: Run the seeder
        if ($this->confirm('Do you want to run the seeder now?', true)) {
            $this->info('⚡ Step 4: Running the seeder...');
            $this->call('db:seed', [
                '--class' => 'DynamicPermissionSeeder',
            ]);
            $this->newLine();
        }

        $this->info('✅ Filament Permissions Setup Complete!');
        $this->newLine();

        $this->line('Next steps:');
        $this->line('1. Check your Filament Resource classes');
        $this->line('2. Add authorization to your Resource pages if needed');
        $this->line('3. Test the permissions in your Filament admin panel');

        return 0;
    }
}
