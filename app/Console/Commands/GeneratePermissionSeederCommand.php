<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GeneratePermissionSeederCommand extends Command
{
    protected $signature = 'filament:permission-seeder {--models=* : Specific models to include}';

    protected $description = 'Generate a permission seeder based on existing models';

    public function handle()
    {
        $models = $this->option('models');

        if (empty($models)) {
            $models = $this->getAllModels();
        }

        $this->generateSeeder($models);

        $this->info('Permission seeder generated successfully!');

        return 0;
    }

    private function getAllModels()
    {
        $modelPath = app_path('Models');

        return collect(File::files($modelPath))
            ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->filter(fn ($model) => ! in_array($model, ['Permission', 'TableView', 'TableViewFavorite', 'UserScope']))
            ->toArray();
    }

    private function getModelSnakeName($modelName)
    {
        $snake = Str::snake($modelName);

        // Convert camelCase compound words to double colon format
        if (preg_match('/^([a-z_]+)_([a-z_]+)$/', $snake, $matches)) {
            return $matches[1].'::'.$matches[2];
        }

        return $snake;
    }

    private function modelUsesSoftDeletes($modelName)
    {
        $modelClass = "App\\Models\\{$modelName}";

        if (! class_exists($modelClass)) {
            return false;
        }

        $reflection = new \ReflectionClass($modelClass);
        $traits = $reflection->getTraitNames();

        return in_array('Illuminate\Database\Eloquent\SoftDeletes', $traits);
    }

    private function hasExporter($modelName)
    {
        $exporterPath = app_path("Filament/Exports/{$modelName}Exporter.php");

        return file_exists($exporterPath);
    }

    private function hasImporter($modelName)
    {
        $importerPath = app_path("Filament/Imports/{$modelName}Importer.php");

        return file_exists($importerPath);
    }

    private function generateSeeder($models)
    {
        $permissions = [];

        foreach ($models as $model) {
            $modelSnake = $this->getModelSnakeName($model);
            $usesSoftDeletes = $this->modelUsesSoftDeletes($model);
            $hasExporter = $this->hasExporter($model);
            $hasImporter = $this->hasImporter($model);

            // Standard permissions
            $permissions[] = "view_any_{$modelSnake}";
            $permissions[] = "view_{$modelSnake}";
            $permissions[] = "create_{$modelSnake}";
            $permissions[] = "update_{$modelSnake}";
            $permissions[] = "delete_any_{$modelSnake}";
            $permissions[] = "delete_{$modelSnake}";

            // Soft delete permissions
            if ($usesSoftDeletes) {
                $permissions[] = "restore_any_{$modelSnake}";
                $permissions[] = "restore_{$modelSnake}";
                $permissions[] = "force_delete_any_{$modelSnake}";
                $permissions[] = "force_delete_{$modelSnake}";
            }

            // Export permission
            if ($hasExporter) {
                $permissions[] = "export_{$modelSnake}";
            }
            // Import permission
            if ($hasImporter) {
                $permissions[] = "import_{$modelSnake}";
            }

            // Add custom permissions based on model
            $customPermissions = $this->getCustomPermissionsForModel($model);
            $permissions = array_merge($permissions, $customPermissions);
        }

        $seederContent = $this->generateSeederContent($permissions);
        $seederPath = database_path('seeders/DynamicPermissionSeeder.php');

        \Illuminate\Support\Facades\File::put($seederPath, $seederContent);

        $this->info("Generated seeder at: {$seederPath}");
        $this->line('Total permissions: '.count($permissions));
    }

    private function getCustomPermissionsForModel($model)
    {
        $modelSnake = $this->getModelSnakeName($model);
        $customPermissions = [];

        // Add model-specific custom permissions here
        switch (strtolower($model)) {
            case 'noo':
                $customPermissions = [
                    "confirm_{$modelSnake}",
                    "approve_{$modelSnake}",
                    "reject_{$modelSnake}",
                ];
                break;
            case 'outlet':
                $customPermissions = [
                    "reset_any_{$modelSnake}",
                ];
                break;
                // Add more custom permissions as needed
        }

        return $customPermissions;
    }

    private function generateSeederContent($permissions)
    {
        $permissionsArray = collect($permissions)
            ->map(fn ($permission) => "            '{$permission}',")
            ->implode("\n");

        return "<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DynamicPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \$permissions = [
{$permissionsArray}
        ];

        \$superAdminRole = Role::firstOrCreate(['name' => 'SUPER ADMIN', 'can_access_web' => true, 'can_access_mobile' => true]);

        foreach (\$permissions as \$permission) {
            \$perm = Permission::updateOrCreate(
                ['name' => \$permission],
                ['description' => ucwords(str_replace(['_', '::'], [' ', ' '], \$permission))]
            );

            \$superAdminRole->permissions()->syncWithoutDetaching([\$perm->id]);
        }

        \$this->command->info('✓ Permissions created and assigned to SUPER ADMIN role');
    }
}";
    }
}
