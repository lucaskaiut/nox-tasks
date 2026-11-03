<?php

namespace App\Providers;

use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Policies\RolePolicy;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\ApiToken\Policies\ApiTokenPolicy;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Assistant\Policies\ConversationPolicy;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Billing\Policies\PlanPolicy;
use App\Modules\Billing\Policies\SubscriptionPolicy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Policies\TenantPolicy;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Policies\WebhookPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureUrls();
        $this->configureOAuth();
    }

    /**
     * Atrás de proxy/TLS terminado fora do PHP, garante URLs https geradas
     * (metadados OAuth/MCP precisam anunciar o endereço público correto).
     */
    private function configureUrls(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * OAuth 2.1 (Passport) para conectores MCP remotos.
     */
    private function configureOAuth(): void
    {
        Passport::authorizationView('auth.oauth.authorize');
        Passport::tokensExpireIn(now()->addDay());
        Passport::refreshTokensExpireIn(now()->addDays(60));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Conexões MCP: limite por usuário (agentes podem encadear muitas chamadas).
        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute((int) config('services.mcp.rate_limit', 120))
                ->by('mcp:'.($request->user()?->getKey() ?: $request->ip()));
        });

    }

    private function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(ApiToken::class, ApiTokenPolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
