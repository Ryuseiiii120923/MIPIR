<?php

namespace App\Providers;

use App\Inspection\Repositories\LotConfirmationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Inspection\Events\XBarCycleFull;
use App\Inspection\Listeners\NotifyStaffXBarFull;
use Illuminate\Support\Facades\Event;

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
        $this->configureDefaults();

        Gate::define('confirm-lots', function ($user) {
            return app(LotConfirmationRepository::class)
                ->isGl((string) $user->{'社員CD'});
        });

         Event::listen(XBarCycleFull::class, NotifyStaffXBarFull::class);
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
