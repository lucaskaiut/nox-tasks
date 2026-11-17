<?php

use App\Modules\Notification\Channels\DatabaseChannel;
use App\Modules\Notification\Channels\MailChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Canais de notificação
    |--------------------------------------------------------------------------
    |
    | Cada canal implementa App\Modules\Notification\Contracts\NotificationChannel.
    | Para adicionar um canal novo, crie a classe e registre-a aqui.
    |
    | - enabled: liga/desliga o canal inteiro.
    | - queue:   entrega em fila (recomendado para canais externos/lentos).
    | - kinds:   tipos de notificação atendidos (null = todos).
    |
    */

    'channels' => [

        'database' => [
            'driver' => DatabaseChannel::class,
            'enabled' => true,
            'queue' => false,
            'kinds' => null,
        ],

        'mail' => [
            'driver' => MailChannel::class,
            'enabled' => (bool) env('NOTIFICATIONS_MAIL_ENABLED', true),
            'queue' => true,
            'kinds' => null,
        ],

    ],

    /*
    | Fila usada pelas entregas assíncronas e política de novas tentativas.
    */
    'queue' => env('NOTIFICATIONS_QUEUE', 'default'),

    'tries' => 3,

    'backoff' => [60, 300],

    /*
    | URL do front-end, usada para montar links nas notificações.
    */
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
