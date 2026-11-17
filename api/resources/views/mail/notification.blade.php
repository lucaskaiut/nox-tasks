<x-mail::message>
# {{ $notification->title }}

Olá, {{ $recipientName }}.

{{ $notification->body }}

@if ($notification->actionUrl)
<x-mail::button :url="$notification->actionUrl">
{{ $notification->actionLabel ?? 'Abrir no '.$appName }}
</x-mail::button>
@endif

<x-mail::subcopy>
Você recebeu este e-mail porque participa deste trabalho no {{ $appName }}.
</x-mail::subcopy>
</x-mail::message>
