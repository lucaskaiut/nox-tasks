<?php

namespace App\Modules\Workspace\Http\Requests;

use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Autorização é feita no controller via Policy; aqui apenas validação.
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return WorkspaceRules::task();
    }
}
