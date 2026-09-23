<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Meta de espera (minutos) do serviço na unidade; nulo usa a meta padrão da unidade
        Schema::table('unit_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('wait_target')->nullable()->after('max_tickets');
        });

        Schema::create('pause_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedSmallInteger('max_minutes')->nullable(); // tempo máximo antes de alertar
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendant_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pause_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 50)->nullable(); // snapshot do motivo
            $table->unsignedSmallInteger('max_minutes')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration')->nullable(); // segundos
            $table->timestamps();
            $table->index(['unit_id', 'ended_at']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendant_pauses');
        Schema::dropIfExists('pause_reasons');
        Schema::table('unit_services', function (Blueprint $table) {
            $table->dropColumn('wait_target');
        });
    }
};
