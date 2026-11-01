<?php

namespace App\Modules\Workspace\Services;

use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\BoardColumn;
use App\Modules\Workspace\Models\Task;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoardService
{
    /**
     * Colunas iniciais do quadro geral — totalmente editáveis depois.
     *
     * @var list<array{name: string, color: string, kind: ColumnKind}>
     */
    private const DEFAULT_COLUMNS = [
        ['name' => 'A fazer', 'color' => '#71717a', 'kind' => ColumnKind::OPEN],
        ['name' => 'Em andamento', 'color' => '#2563eb', 'kind' => ColumnKind::OPEN],
        ['name' => 'Aguardando retorno', 'color' => '#d97706', 'kind' => ColumnKind::WAITING],
        ['name' => 'Concluído', 'color' => '#16a34a', 'kind' => ColumnKind::DONE],
    ];

    private const COLUMN_FIELDS = ['name', 'color', 'kind'];

    public function __construct(private readonly ActivityService $activity) {}

    /**
     * Quadro geral do tenant atual. É criado sob demanda, com as colunas
     * padrão, na primeira vez em que for necessário.
     */
    public function current(): Board
    {
        $board = Board::query()->first();

        if ($board !== null) {
            return $board;
        }

        try {
            return DB::transaction(function (): Board {
                $board = Board::query()->create(['name' => 'Quadro geral']);

                foreach (self::DEFAULT_COLUMNS as $position => $column) {
                    $board->columns()->create([...$column, 'position' => $position]);
                }

                return $board;
            });
        } catch (UniqueConstraintViolationException) {
            // Outra requisição criou o quadro ao mesmo tempo.
            return Board::query()->firstOrFail();
        }
    }

    public function load(Board $board): Board
    {
        return $board->load('columns');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Board $board, array $data): Board
    {
        $board->fill(Arr::only($data, ['name', 'description']));
        $changes = $this->activity->diff($board, ['name', 'description']);
        $board->save();

        if ($changes !== []) {
            $this->activity->record('board.updated', $board, Visibility::INTERNAL, changes: $changes);
        }

        return $this->load($board);
    }

    /**
     * @param  array{name: string, color?: string, kind?: string, position?: ?int}  $data
     */
    public function addColumn(Board $board, array $data): BoardColumn
    {
        return DB::transaction(function () use ($board, $data): BoardColumn {
            $column = $board->columns()->create([
                ...Arr::only($data, self::COLUMN_FIELDS),
                'position' => $board->columns()->count(),
            ]);

            if (isset($data['position'])) {
                $this->reorder($column, (int) $data['position']);
            }

            $this->activity->record('column.created', $column, Visibility::INTERNAL);

            return $column->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateColumn(BoardColumn $column, array $data): BoardColumn
    {
        return DB::transaction(function () use ($column, $data): BoardColumn {
            $column->fill(Arr::only($data, self::COLUMN_FIELDS));
            $changes = $this->activity->diff($column, self::COLUMN_FIELDS);
            $kindChanged = $column->isDirty('kind');
            $column->save();

            if ($kindChanged) {
                $this->syncCompletion($column);
            }

            if (array_key_exists('position', $data) && $data['position'] !== null) {
                $this->reorder($column, (int) $data['position']);
            }

            if ($changes !== []) {
                $this->activity->record('column.updated', $column, Visibility::INTERNAL, changes: $changes);
            }

            return $column->refresh();
        });
    }

    public function moveColumn(BoardColumn $column, int $position): BoardColumn
    {
        return DB::transaction(function () use ($column, $position): BoardColumn {
            $from = $column->position;
            $this->reorder($column, $position);
            $column->refresh();

            $this->activity->record('column.moved', $column, Visibility::INTERNAL, changes: [
                'position' => ['from' => $from, 'to' => $column->position],
            ]);

            return $column;
        });
    }

    /**
     * Exclui a coluna. Se houver tarefas (inclusive na lixeira), é obrigatório
     * indicar outra coluna de destino.
     *
     * @throws ValidationException
     */
    public function deleteColumn(BoardColumn $column, ?BoardColumn $moveTo = null): void
    {
        DB::transaction(function () use ($column, $moveTo): void {
            $board = $column->board;

            if ($board->columns()->count() <= 1) {
                throw ValidationException::withMessages(['column' => ['O quadro precisa ter ao menos uma coluna.']]);
            }

            $hasTasks = Task::query()->withTrashed()->where('board_column_id', $column->getKey())->exists();

            if ($hasTasks) {
                if ($moveTo === null) {
                    throw ValidationException::withMessages([
                        'move_tasks_to' => ['A coluna possui tarefas. Informe a coluna de destino.'],
                    ]);
                }

                if ((int) $moveTo->board_id !== (int) $column->board_id || $moveTo->is($column)) {
                    throw ValidationException::withMessages([
                        'move_tasks_to' => ['A coluna de destino deve ser outra coluna do quadro.'],
                    ]);
                }

                $offset = (int) Task::query()->withTrashed()->where('board_column_id', $moveTo->getKey())->max('position') + 1;

                Task::query()->withTrashed()
                    ->where('board_column_id', $column->getKey())
                    ->update([
                        'board_column_id' => $moveTo->getKey(),
                        'position' => DB::raw('position + '.$offset),
                    ]);

                $this->syncCompletion($moveTo);
            }

            $column->delete();
            $this->normalizePositions($board);

            $this->activity->record('column.deleted', $column, Visibility::INTERNAL, changes: [
                'name' => ['from' => $column->name, 'to' => null],
            ]);
        });
    }

    /**
     * Primeira coluna "em aberto" do quadro — destino padrão de tarefas novas.
     */
    public function defaultColumn(?Board $board = null): BoardColumn
    {
        $board ??= $this->current();

        return $board->columns()->where('kind', ColumnKind::OPEN->value)->first()
            ?? $board->columns()->whereNotIn('kind', ColumnKind::closedValues())->first()
            ?? $board->columns()->firstOrFail();
    }

    private function reorder(BoardColumn $column, int $position): void
    {
        $ids = BoardColumn::query()
            ->where('board_id', $column->board_id)
            ->whereKeyNot($column->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$column->getKey()]);

        foreach ($ids as $index => $id) {
            BoardColumn::query()->whereKey($id)->where('position', '!=', $index)->update(['position' => $index]);
        }
    }

    private function normalizePositions(Board $board): void
    {
        $board->columns()->pluck('id')->each(
            fn (int $id, int $index) => BoardColumn::query()->whereKey($id)->update(['position' => $index]),
        );
    }

    /**
     * Mantém completed_at coerente com a semântica da coluna.
     */
    private function syncCompletion(BoardColumn $column): void
    {
        $query = Task::query()->withTrashed()->where('board_column_id', $column->getKey());

        if ($column->kind === ColumnKind::DONE) {
            $query->whereNull('completed_at')->update(['completed_at' => now()]);
        } else {
            $query->whereNotNull('completed_at')->update(['completed_at' => null]);
        }
    }
}
