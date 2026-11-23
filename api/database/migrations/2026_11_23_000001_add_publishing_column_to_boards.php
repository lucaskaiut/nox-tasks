<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adiciona a coluna "Aguardando publicação" (kind = publishing) aos quadros
 * existentes, logo antes da primeira coluna encerrada (concluída/cancelada).
 * Quadros que já tenham uma coluna desse tipo ou com esse nome são mantidos.
 */
return new class extends Migration
{
    private const NAME = 'Aguardando publicação';

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('boards')->get(['id', 'tenant_id']) as $board) {
                $this->addTo($board);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $columns = DB::table('board_columns')->where('kind', 'publishing')->get(['id', 'board_id']);

            foreach ($columns as $column) {
                $target = DB::table('board_columns')
                    ->where('board_id', $column->board_id)
                    ->where('id', '!=', $column->id)
                    ->whereNotIn('kind', ['done', 'cancelled'])
                    ->orderBy('position')
                    ->orderBy('id')
                    ->value('id');

                if ($target === null) {
                    // Sem destino possível: mantém a coluna como "em aberto".
                    DB::table('board_columns')->where('id', $column->id)->update(['kind' => 'open']);

                    continue;
                }

                $offset = (int) DB::table('tasks')->where('board_column_id', $target)->max('position') + 1;

                DB::table('tasks')
                    ->where('board_column_id', $column->id)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(fn (int $id, int $index) => DB::table('tasks')->where('id', $id)->update([
                        'board_column_id' => $target,
                        'position' => $offset + $index,
                    ]));

                DB::table('board_columns')->where('id', $column->id)->delete();
                $this->normalize((int) $column->board_id);
            }
        });
    }

    private function addTo(object $board): void
    {
        $columns = DB::table('board_columns')->where('board_id', $board->id)->orderBy('position')->orderBy('id')->get();

        $exists = $columns->contains(
            fn ($c) => $c->kind === 'publishing' || mb_strtolower($c->name) === mb_strtolower(self::NAME),
        );

        if ($exists) {
            return;
        }

        $closedIndex = $columns->search(fn ($c) => in_array($c->kind, ['done', 'cancelled'], true));
        $position = $closedIndex === false ? $columns->count() : $closedIndex;

        DB::table('board_columns')
            ->where('board_id', $board->id)
            ->where('position', '>=', $position)
            ->increment('position');

        DB::table('board_columns')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $board->tenant_id,
            'board_id' => $board->id,
            'name' => self::NAME,
            'color' => '#7c3aed',
            'kind' => 'publishing',
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->normalize((int) $board->id);
    }

    private function normalize(int $boardId): void
    {
        DB::table('board_columns')
            ->where('board_id', $boardId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id, int $index) => DB::table('board_columns')->where('id', $id)->update(['position' => $index]));
    }
};
