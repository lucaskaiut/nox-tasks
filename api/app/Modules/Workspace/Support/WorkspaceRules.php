<?php

namespace App\Modules\Workspace\Support;

use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Workspace\Enums\ClientStatus;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\ProjectStatus;
use App\Modules\Workspace\Enums\SubtaskStatus;
use App\Modules\Workspace\Enums\TaskPriority;
use App\Modules\Workspace\Enums\Visibility;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Regras de validação centralizadas — usadas pelos FormRequests (HTTP) e
 * pelas Tools do MCP, garantindo exatamente as mesmas validações.
 *
 * Toda referência por UUID é validada dentro do tenant corrente.
 */
final class WorkspaceRules
{
    public static function tenantExists(string $table, string $column = 'uuid'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', TenantContext::tenantId());
    }

    public static function professional(): Exists
    {
        return self::tenantExists('users')->whereNull('client_id')->whereNull('deleted_at');
    }

    public static function activeClient(): Exists
    {
        return self::tenantExists('clients')->where('status', ClientStatus::ACTIVE->value)->whereNull('deleted_at');
    }

    /**
     * @return array<string, mixed>
     */
    public static function client(bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:255'],
            'email' => [...$required, 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'document' => ['sometimes', 'nullable', 'string', new CpfOrCnpj],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(ClientStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientUser(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function project(bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'client_id' => [...$required, 'uuid', self::activeClient()],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'member_ids' => ['sometimes', 'array', 'max:50'],
            'member_ids.*' => ['uuid', 'distinct', self::professional()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function projectFilters(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'client' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'member' => ['nullable', 'uuid'],
            'archived' => ['nullable', Rule::in(['exclude', 'include', 'only'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function board(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function column(bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:60'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'kind' => ['sometimes', Rule::enum(ColumnKind::class)],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function task(bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999'],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            ...($partial ? [] : [
                'column_id' => ['sometimes', 'nullable', 'uuid'],
                'assignee_id' => ['sometimes', 'nullable', 'uuid', self::professional()],
                'participant_ids' => ['sometimes', 'array', 'max:20'],
                'participant_ids.*' => ['uuid', 'distinct', self::professional()],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskAssignment(): array
    {
        return [
            'assignee_id' => ['present', 'nullable', 'uuid', self::professional()],
            'participant_ids' => ['sometimes', 'array', 'max:20'],
            'participant_ids.*' => ['uuid', 'distinct', self::professional()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskMove(): array
    {
        return [
            'column_id' => ['required', 'uuid'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'after_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskFilters(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'project' => ['nullable', 'uuid'],
            'client' => ['nullable', 'uuid'],
            'assignee' => ['nullable', 'string', 'max:36'],
            'priority' => ['nullable'],
            'priority.*' => [Rule::enum(TaskPriority::class)],
            'column' => ['nullable', 'uuid'],
            'state' => ['nullable', Rule::in(['open', 'waiting', 'done', 'cancelled', 'closed'])],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'tag' => ['nullable', 'string', 'max:40'],
            'overdue' => ['nullable', 'boolean'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['due_at', 'priority', 'created_at', 'updated_at', 'title', 'position'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function subtask(bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(SubtaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'assignee_id' => ['sometimes', 'nullable', 'uuid', self::professional()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function comment(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['sometimes', 'nullable', Rule::enum(Visibility::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attachment(): array
    {
        return [
            'file' => [
                'required', 'file', 'max:20480',
                'mimes:pdf,png,jpg,jpeg,gif,webp,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip,md,json',
            ],
            'visibility' => ['sometimes', 'nullable', Rule::enum(Visibility::class)],
        ];
    }

    /**
     * Normaliza documento (somente dígitos) antes da validação.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeDocument(array $data): array
    {
        if (filled($data['document'] ?? null)) {
            $data['document'] = (string) preg_replace('/\D+/', '', (string) $data['document']);
        }

        return $data;
    }
}
