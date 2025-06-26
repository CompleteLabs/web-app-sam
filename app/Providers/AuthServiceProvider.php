<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Policies will be auto-registered
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->autoRegisterPolicies();
    }

    /**
     * Auto-register policies based on model-policy naming convention
     */
    private function autoRegisterPolicies(): void
    {
        $policyPath = app_path('Policies');

        if (! File::exists($policyPath)) {
            return;
        }

        $policies = collect(File::files($policyPath))
            ->map(function ($file) {
                $policyName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $modelName = str_replace('Policy', '', $policyName);

                $modelClass = "App\\Models\\{$modelName}";
                $policyClass = "App\\Policies\\{$policyName}";

                if (class_exists($modelClass) && class_exists($policyClass)) {
                    return [$modelClass => $policyClass];
                }

                return null;
            })
            ->filter()
            ->collapse()
            ->toArray();

        $this->policies = array_merge($this->policies, $policies);

        // Re-register policies after auto-discovery
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
