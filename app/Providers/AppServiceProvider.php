<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface::class,
            \App\Inspection\Repositories\Dimensions\DimensionMasterRepository::class
        );
        $this->app->bind(
            \App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface::class,
            \App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            if (str_contains($query->sql, 'tblDimensionMeasure')) {
                logger('Dimension SQL', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                ]);
            }
        });
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null,
        );
    }
}
