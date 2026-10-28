<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\NatureOfCost;
use App\Models\Vessel;
use App\Models\VesselType;
use App\Policies\MasterDataPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Master-data maintenance (Admin only) shares a single policy.
        foreach ([Company::class, Department::class, VesselType::class, Vessel::class, NatureOfCost::class, DocumentType::class] as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }
    }
}
