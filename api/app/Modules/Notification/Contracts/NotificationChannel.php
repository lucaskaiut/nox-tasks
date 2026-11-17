<?php

namespace App\Modules\Notification\Contracts;

use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\User\Models\User;

/**
 * Canal de entrega de notificações (in-app, e-mail, WhatsApp, push...).
 *
 * Para criar um canal novo basta implementar esta interface e registrá-lo
 * em config/notifications.php — nenhum ponto de disparo precisa mudar.
 */
interface NotificationChannel
{
    /**
     * Se o canal consegue/deve entregar esta mensagem a este destinatário
     * (ex.: e-mail exige endereço; WhatsApp exigiria telefone).
     */
    public function shouldSend(User $recipient, NotificationMessage $message): bool;

    /**
     * Entrega a mensagem. Exceções fazem a entrega ser tentada novamente
     * quando o canal roda em fila.
     */
    public function send(User $recipient, NotificationMessage $message): void;
}
