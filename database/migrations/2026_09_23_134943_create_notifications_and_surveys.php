<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telefone informado pelo cliente para receber avisos desta senha
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('notify_phone', 20)->nullable()->after('customer_id');
        });

        // Histórico de mensagens enviadas (evita duplicidade e permite auditoria)
        Schema::create('ticket_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);      // issued | near | called | survey
            $table->string('provider', 30);
            $table->string('to', 20);
            $table->string('status', 10);    // sent | failed
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'type']);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // atendente
            $table->string('scale', 10);      // nps (0-10) | csat (1-5)
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('ticket_notifications');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('notify_phone');
        });
    }
};
