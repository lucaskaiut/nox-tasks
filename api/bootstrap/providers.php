<?php

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Modules\Workspace\Providers\WorkspaceServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    TenantServiceProvider::class,
    WebhookServiceProvider::class,
    WorkspaceServiceProvider::class,
];
