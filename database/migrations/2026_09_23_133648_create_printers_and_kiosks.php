<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Impressoras térmicas ESC/POS acessadas diretamente pelo servidor
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('connection_type', 20)->default('network'); // network | windows
            $table->string('host', 150);                            // IP ou nome do compartilhamento
            $table->unsignedSmallInteger('port')->default(9100);
            $table->unsignedTinyInteger('paper_width')->default(80); // 58 ou 80 mm
            $table->boolean('cut')->default(true);
            $table->boolean('print_qr')->default(true);
            $table->boolean('strip_accents')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        // Totens de autoatendimento, acessados publicamente por public_id
        Schema::create('kiosks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            $table->uuid('public_id')->unique();
            $table->string('print_mode', 20)->default('browser'); // browser | printer | none
            $table->json('settings')->nullable();
            $table->json('services')->nullable(); // vazio = todos os serviços ativos
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('kiosk_id')->nullable()->after('triage_user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kiosk_id');
        });
        Schema::dropIfExists('kiosks');
        Schema::dropIfExists('printers');
    }
};
