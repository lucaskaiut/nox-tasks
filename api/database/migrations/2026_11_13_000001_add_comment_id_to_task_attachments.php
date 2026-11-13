<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos podem pertencer a um comentário (além da tarefa). Anexos sem
 * comentário são os enviados diretamente na tarefa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('task_attachments', 'comment_id')) {
            return;
        }

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->foreignId('comment_id')->nullable()->after('task_id')->constrained('task_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comment_id');
        });
    }
};
