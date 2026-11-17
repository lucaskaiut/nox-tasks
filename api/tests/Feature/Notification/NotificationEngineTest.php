<?php

namespace Tests\Feature\Notification;

use App\Modules\Notification\Channels\MailChannel;
use App\Modules\Notification\Contracts\NotificationChannel;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Jobs\DeliverNotification;
use App\Modules\Notification\Mail\NotificationMail;
use App\Modules\Notification\Services\ChannelRegistry;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\InteractsWithWorkspace;
use Tests\TestCase;

class NotificationEngineTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Sanctum::actingAs($this->professional);
        RecordingChannel::$sent = [];
    }

    private function useChannels(array $channels): void
    {
        config(['notifications.channels' => $channels]);
        $this->app->forgetInstance(ChannelRegistry::class);
        $this->app->forgetInstance(NotificationDispatcher::class);
    }

    public function test_task_assignment_is_delivered_in_app_and_by_email(): void
    {
        Mail::fake();
        config(['notifications.frontend_url' => 'https://tasks.example.com']);
        $assignee = $this->createProfessional($this->tenant);

        $task = $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", [
            'title' => 'Criar landing page',
            'assignee_id' => $assignee->uuid,
        ])->assertCreated()->json('data.id');

        $this->assertSame('task_assigned', $assignee->notifications()->first()->data['kind']);

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($assignee, $task) {
            return $mail->hasTo($assignee->email)
                && $mail->notification->kind === 'task_assigned'
                && $mail->notification->actionUrl === "https://tasks.example.com/w/tasks/{$task}";
        });

        // Quem executa a ação não notifica a si mesmo.
        Mail::assertNotSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->professional->email));
    }

    public function test_email_is_rendered_with_title_body_and_link(): void
    {
        $mail = new NotificationMail(new NotificationMessage(
            kind: 'task_assigned',
            title: 'Nova tarefa atribuída',
            body: 'Você é o responsável pela tarefa "Criar landing page".',
            actionUrl: 'https://tasks.example.com/w/tasks/123',
            actionLabel: 'Abrir tarefa',
        ), 'Maria');

        $mail->assertHasSubject('Nova tarefa atribuída');
        $mail->assertSeeInHtml('Olá, Maria.');
        $mail->assertSeeInHtml('https://tasks.example.com/w/tasks/123');
        $mail->assertSeeInHtml('Abrir tarefa');
    }

    public function test_internal_content_is_never_emailed_to_client_users(): void
    {
        Mail::fake();

        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/comments", ['body' => 'nota interna', 'visibility' => 'internal'])->assertCreated();
        Mail::assertNotSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->clientUserA->email));

        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/comments", ['body' => 'Olá cliente', 'visibility' => 'shared'])->assertCreated();
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->clientUserA->email));
        Mail::assertNotSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->clientUserB->email));
    }

    public function test_queued_channels_are_dispatched_after_commit_and_sync_channels_run_inline(): void
    {
        Queue::fake();
        $assignee = $this->createProfessional($this->tenant);

        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", ['title' => 'Tarefa', 'assignee_id' => $assignee->uuid])->assertCreated();

        $this->assertSame(1, $assignee->notifications()->count());
        Queue::assertPushed(DeliverNotification::class, fn (DeliverNotification $job) => $job->channel === 'mail' && $job->userId === $assignee->getKey());
        Queue::assertNotPushed(DeliverNotification::class, fn (DeliverNotification $job) => $job->channel === 'database');
    }

    public function test_new_channels_plug_in_through_configuration_only(): void
    {
        $this->useChannels([
            'recording' => ['driver' => RecordingChannel::class, 'enabled' => true, 'queue' => true, 'kinds' => ['task_assigned']],
            'disabled' => ['driver' => RecordingChannel::class, 'enabled' => false, 'queue' => false, 'kinds' => null],
        ]);
        $assignee = $this->createProfessional($this->tenant);

        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", ['title' => 'Tarefa', 'assignee_id' => $assignee->uuid])->assertCreated();
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/comments", ['body' => 'oi', 'visibility' => 'shared'])->assertCreated();

        // Só o canal habilitado, só para o tipo configurado.
        $this->assertSame([[$assignee->getKey(), 'task_assigned']], RecordingChannel::$sent);
        $this->assertSame(0, $assignee->notifications()->count());
    }

    public function test_failure_in_one_channel_does_not_break_the_others_or_the_request(): void
    {
        Mail::fake();
        $this->useChannels([
            'broken' => ['driver' => BrokenChannel::class, 'enabled' => true, 'queue' => false, 'kinds' => null],
            'mail' => ['driver' => MailChannel::class, 'enabled' => true, 'queue' => true, 'kinds' => null],
        ]);
        $assignee = $this->createProfessional($this->tenant);

        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", ['title' => 'Tarefa', 'assignee_id' => $assignee->uuid])->assertCreated();

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($assignee->email));
    }

    public function test_channel_can_skip_recipients_it_cannot_reach(): void
    {
        Mail::fake();
        $assignee = $this->createProfessional($this->tenant);
        User::withoutTenancy()->whereKey($assignee->getKey())->update(['email' => 'invalido']);

        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", ['title' => 'Tarefa', 'assignee_id' => $assignee->uuid])->assertCreated();

        Mail::assertNothingSent();
        $this->assertSame(1, $assignee->notifications()->count());
    }
}

class RecordingChannel implements NotificationChannel
{
    /** @var list<array{0: int, 1: string}> */
    public static array $sent = [];

    public function shouldSend(User $recipient, NotificationMessage $message): bool
    {
        return true;
    }

    public function send(User $recipient, NotificationMessage $message): void
    {
        self::$sent[] = [$recipient->getKey(), $message->kind];
    }
}

class BrokenChannel implements NotificationChannel
{
    public function shouldSend(User $recipient, NotificationMessage $message): bool
    {
        return true;
    }

    public function send(User $recipient, NotificationMessage $message): void
    {
        throw new RuntimeException('canal fora do ar');
    }
}
