<?php

namespace App\Modules\Mcp\Tools\Attachments;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskAttachmentResource;
use App\Modules\Workspace\Models\TaskAttachment;
use App\Modules\Workspace\Support\AttachmentReader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Name('read_task_attachment')]
#[Description('Lê o conteúdo de um anexo de tarefa ou de comentário (ids em list_task_attachments, get_task ou list_task_comments). Imagens são devolvidas como imagem; PDF, Word (.docx), Excel (.xlsx), PowerPoint (.pptx), txt, csv, md e json como texto extraído; .zip como lista de arquivos. Textos longos vêm em partes: repita a chamada com offset = next_offset enquanto has_more for true.')]
#[IsReadOnly]
class ReadTaskAttachment extends WorkspaceTool
{
    /** Caracteres de texto devolvidos por chamada. */
    public const CHUNK = 50_000;

    public function schema(JsonSchema $schema): array
    {
        return [
            'attachment_id' => $schema->string()->format('uuid')->required(),
            'offset' => $schema->integer()->min(0)->description('Posição (em caracteres) a partir da qual ler textos longos. Padrão 0.'),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::TASK_READ);
    }

    protected function run(Request $request, User $user): array|Response|ResponseFactory
    {
        $data = $request->validate(['offset' => ['nullable', 'integer', 'min:0']]);

        $attachment = $this->findOrFail(TaskAttachment::class, $request->get('attachment_id'));
        $this->authorizeFor($user, 'view', $attachment);
        $attachment->load(['uploader:id,uuid,name', 'comment:id,uuid']);

        $meta = $this->item(TaskAttachmentResource::class, $attachment);
        $meta['task_id'] = $attachment->task->uuid;
        $reader = app(AttachmentReader::class);

        try {
            if ($attachment->isImage()) {
                $image = $reader->image($attachment);

                return Response::make([
                    Response::text(json_encode(['attachment' => $meta], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    Response::image($image['data'], $image['mime_type']),
                ]);
            }

            $text = $reader->text($attachment);
        } catch (RuntimeException $e) {
            return ['attachment' => $meta, 'content' => null, 'error' => $e->getMessage()];
        }

        if ($text === null) {
            return [
                'attachment' => $meta,
                'content' => null,
                'error' => 'Formato não suportado para leitura pelo MCP. Formatos legíveis: imagens, pdf, docx, xlsx, pptx, txt, csv, md, json e zip (listagem).',
            ];
        }

        $total = mb_strlen($text);
        $offset = min((int) ($data['offset'] ?? 0), $total);
        $chunk = mb_substr($text, $offset, self::CHUNK);
        $next = $offset + mb_strlen($chunk);

        return [
            'attachment' => $meta,
            'content' => $chunk,
            'offset' => $offset,
            'next_offset' => $next < $total ? $next : null,
            'total_chars' => $total,
            'has_more' => $next < $total,
            ...($total === 0 ? ['notice' => 'Nenhum texto extraído (ex.: PDF digitalizado sem camada de texto).'] : []),
        ];
    }
}
