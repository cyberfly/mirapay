<?php

namespace App\Providers;

use App\Models\User;
use App\Payments\ManualPaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StripeClient::class, fn (): StripeClient => new StripeClient((string) config('services.stripe.secret')));

        $this->app->bind(PaymentGateway::class, fn ($app): PaymentGateway => config('services.payment_gateway') === 'stripe'
            ? $app->make(StripePaymentGateway::class)
            : new ManualPaymentGateway);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
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

        Password::defaults(fn (): ?Password => app()->isProduction()
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
