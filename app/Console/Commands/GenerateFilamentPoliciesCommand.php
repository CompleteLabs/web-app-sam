<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateFilamentPoliciesCommand extends Command
{
    protected $signature = 'filament:policies {model?} {--all : Generate for all models}';

    protected $description = 'Generate Filament-style policy classes for model(s)';

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
    }

    private function generateForModel($modelName)
    {
        $policyName = $modelName.'Policy';
        $policyPath = app_path("Policies/{$policyName}.php");

        if (File::exists($policyPath)) {
            $this->line("- Policy exists: {$policyName}");
        }

        // Always replace the file to ensure the latest stub is used
        $stub = $this->getFilamentPolicyStub($modelName);

        File::ensureDirectoryExists(dirname($policyPath));
        File::put($policyPath, $stub);

        $this->info("✓ Created or updated: {$policyName}");
    }

    private function hasExporter($modelName)
    {
        $exporterPath = app_path("Filament/Exports/{$modelName}Exporter.php");

        return File::exists($exporterPath);
    }

    private function hasImporter($modelName)
    {
        $importerPath = app_path("Filament/Imports/{$modelName}Importer.php");

        return File::exists($importerPath);
    }

    private function getFilamentPolicyStub($modelName)
    {
        $modelSnake = $this->getModelSnakeName($modelName);
        $modelVariable = Str::camel($modelName);

        // Handle case where model name conflicts with parameter name
        if ($modelVariable === 'user') {
            $modelVariable = 'model';
        }

        $usesSoftDeletes = $this->modelUsesSoftDeletes($modelName);
        $hasExporter = $this->hasExporter($modelName);
        $hasImporter = $this->hasImporter($modelName);

        $softDeleteMethods = '';
        if ($usesSoftDeletes) {
            $softDeleteMethods = <<<EOM

    public function restoreAny(User \$user): bool
    {
        return \$user->hasPermission('restore_any_{$modelSnake}');
    }

    public function restore(User \$user, {$modelName} \${$modelVariable}): bool
    {
        return \$user->hasPermission('restore_{$modelSnake}');
    }

    public function forceDeleteAny(User \$user): bool
    {
        return \$user->hasPermission('force_delete_any_{$modelSnake}');
    }

    public function forceDelete(User \$user, {$modelName} \${$modelVariable}): bool
    {
        return \$user->hasPermission('force_delete_{$modelSnake}');
    }
EOM;
        }

        $importExportMethods = '';
        if ($hasExporter) {
            $importExportMethods .= <<<EOM

    public function export(User \$user): bool
    {
        return \$user->hasPermission('export_{$modelSnake}');
    }
EOM;
        }
        if ($hasImporter) {
            $importExportMethods .= <<<EOM

    public function import(User \$user): bool
    {
        return \$user->hasPermission('import_{$modelSnake}');
    }
EOM;
        }

        $useStatements = $modelName === 'User'
            ? 'use App\\Models\\User;'
            : "use App\\Models\\{$modelName};\nuse App\\Models\\User;";

        // Use HEREDOC for the stub, and move $importExportMethods and $softDeleteMethods into the HEREDOC as variables
        $stub = <<<EOT
<?php

namespace App\\Policies;

{$useStatements}
use Illuminate\\Auth\\Access\\HandlesAuthorization;

class {$modelName}Policy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User \$user): bool
    {
        return \$user->hasPermission('view_any_{$modelSnake}');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User \$user, {$modelName} \${$modelVariable}): bool
    {
        return \$user->hasPermission('view_{$modelSnake}');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User \$user): bool
    {
        return \$user->hasPermission('create_{$modelSnake}');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User \$user, {$modelName} \${$modelVariable}): bool
    {
        return \$user->hasPermission('update_{$modelSnake}');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User \$user): bool
    {
        return \$user->hasPermission('delete_any_{$modelSnake}');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User \$user, {$modelName} \${$modelVariable}): bool
    {
        return \$user->hasPermission('delete_{$modelSnake}');
    }
{$softDeleteMethods}
{$importExportMethods}
}
EOT;

        return $stub;
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
}
