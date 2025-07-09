<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateFilamentPermissionsCommand extends Command
{
    protected $signature = 'filament:permissions {model?} {--all : Generate for all models} {--custom= : Add custom actions (comma separated)}';

    protected $description = 'Generate Filament-style permissions for model(s)';

    public function handle()
    {
        if ($this->option('all')) {
            $this->generateForAllModels();
        } elseif ($this->argument('model')) {
            $this->generateForModel($this->argument('model'));
        } else {
            $this->error('Please specify a model or use --all flag');

            return 1;
        }

        return 0;
    }

    private function generateForAllModels()
    {
        $modelPath = app_path('Models');
        $models = collect(File::files($modelPath))
            ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->filter(fn ($model) => ! in_array($model, ['Permission', 'TableView', 'TableViewFavorite', 'UserScope']));

        foreach ($models as $model) {
            $this->generateForModel($model);
        }

        // Assign all permissions to Super Admin
        $this->assignToSuperAdmin();
    }

    private function generateForModel($modelName)
    {
        $modelSnake = $this->getModelSnakeName($modelName);

        // Standard Filament permissions
        $actions = [
            'view_any' => "View any {$modelName}",
            'view' => "View {$modelName}",
            'create' => "Create {$modelName}",
            'update' => "Update {$modelName}",
            'delete_any' => "Delete any {$modelName}",
            'delete' => "Delete {$modelName}",
        ];

        // Add soft delete permissions if model uses SoftDeletes
        if ($this->modelUsesSoftDeletes($modelName)) {
            $actions = array_merge($actions, [
                'restore_any' => "Restore any {$modelName}",
                'restore' => "Restore {$modelName}",
                'force_delete_any' => "Force delete any {$modelName}",
                'force_delete' => "Force delete {$modelName}",
            ]);
        }

        // Add export permission
        $actions['export'] = "Export {$modelName}";

        // Add custom actions if specified
        if ($customActions = $this->option('custom')) {
            $customList = explode(',', $customActions);
            foreach ($customList as $action) {
                $action = trim($action);
                $actions[$action] = ucfirst(str_replace('_', ' ', $action))." {$modelName}";
            }
        }

        $this->info("Generating Filament permissions for {$modelName}...");

        foreach ($actions as $action => $description) {
            $permissionName = "{$action}_{$modelSnake}";

            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => $description]
            );

            if ($permission->wasRecentlyCreated) {
                $this->line("✓ Created: {$permissionName}");
            } else {
                $this->line("- Exists: {$permissionName}");
            }
        }

        $this->info("Completed permissions for {$modelName}\n");
    }

    private function getModelSnakeName($modelName)
    {
        // Handle special cases like "PlanVisit" -> "plan::visit"
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

    private function assignToSuperAdmin()
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'SUPER ADMIN', 'can_access_web' => true, 'can_access_mobile' => true]);
        $allPermissions = Permission::pluck('id')->toArray();

        $superAdminRole->permissions()->syncWithoutDetaching($allPermissions);

        $this->info('✓ All permissions assigned to SUPER ADMIN role');
    }
}
