<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passa de um quadro por projeto para um único quadro geral por tenant.
 *
 * Para cada tenant, o quadro mais antigo vira o quadro geral. Tarefas de
 * colunas de outros quadros são realocadas na coluna equivalente do quadro
 * geral (mesmo nome, senão mesmo tipo, senão a primeira coluna). Em seguida
 * os demais quadros são removidos, projects.board_id deixa de existir e a
 * ordem das tarefas passa a ser única por coluna (não mais por projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $tenantIds = DB::table('boards')->distinct()->pluck('tenant_id');

            foreach ($tenantIds as $tenantId) {
                $this->consolidate((int) $tenantId);
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_id');
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'name']);
            $table->unique('tenant_id');
        });

        $this->normalizeTaskPositions();
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropUnique(['tenant_id']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable()->after('client_id')->constrained('boards')->restrictOnDelete();
        });

        DB::table('projects')->update([
            'board_id' => DB::raw('(SELECT boards.id FROM boards WHERE boards.tenant_id = projects.tenant_id)'),
        ]);
    }

    private function consolidate(int $tenantId): void
    {
        $boards = DB::table('boards')->where('tenant_id', $tenantId)->orderBy('id')->pluck('id');
        $mainId = (int) $boards->shift();

        if ($boards->isEmpty()) {
            return;
        }

        $mainColumns = DB::table('board_columns')->where('board_id', $mainId)->orderBy('position')->orderBy('id')->get();
        $fallback = $mainColumns->first();

        $otherColumns = DB::table('board_columns')->whereIn('board_id', $boards)->get();

        foreach ($otherColumns as $column) {
            $target = $mainColumns->first(fn ($c) => mb_strtolower($c->name) === mb_strtolower($column->name))
                ?? $mainColumns->first(fn ($c) => $c->kind === $column->kind)
                ?? $fallback;

            DB::table('tasks')->where('board_column_id', $column->id)->update([
                'board_column_id' => $target->id,
                'completed_at' => $target->kind === 'done' ? DB::raw('COALESCE(completed_at, CURRENT_TIMESTAMP)') : null,
            ]);
        }

        DB::table('projects')->whereIn('board_id', $boards)->update(['board_id' => $mainId]);
        DB::table('board_columns')->whereIn('board_id', $boards)->delete();
        DB::table('boards')->whereIn('id', $boards)->delete();
    }

    private function normalizeTaskPositions(): void
    {
        DB::table('board_columns')->orderBy('id')->pluck('id')->each(function (int $columnId): void {
            DB::table('tasks')
                ->where('board_column_id', $columnId)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->each(fn (int $id, int $index) => DB::table('tasks')->whereKey($id)->update(['position' => $index]));
        });
    }
};
