<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('description', 250)->default('');
            $table->boolean('active')->default(true);
            $table->string('timezone', 50)->nullable();
            $table->string('print_header', 150)->default('');
            $table->string('print_footer', 150)->default('');
            $table->boolean('print_show_date')->default(true);
            $table->boolean('print_show_priority')->default(true);
            $table->boolean('print_show_unit_name')->default(true);
            $table->boolean('print_show_service_name')->default(true);
            $table->boolean('print_show_service_message')->default(true);
            $table->unsignedInteger('priority_swap_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('description', 250)->default('');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Tipo de ponto de atendimento: "Guichê", "Mesa", "Sala"...
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->timestamps();
        });

        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('description', 100)->default('');
            $table->unsignedSmallInteger('weight')->default(0); // 0 = normal, > 0 = prioridade
            $table->string('color', 20)->default('#0091da');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('services');
            $table->string('name', 50);
            $table->string('description', 250)->default('');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // Configuração do serviço na unidade + contador de senhas
        Schema::create('unit_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prefix', 3)->default('');
            $table->boolean('active')->default(false);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->unsignedTinyInteger('type')->default(1); // 1 todos, 2 só normal, 3 só prioridade
            $table->unsignedInteger('increment')->default(1);
            $table->unsignedInteger('start_number')->default(1);
            $table->unsignedInteger('end_number')->nullable();
            $table->unsignedInteger('max_tickets')->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->string('message')->default('');
            $table->timestamps();
            $table->unique(['unit_id', 'service_id']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('description', 150)->default('');
            $table->json('modules');
            $table->timestamps();
        });

        // Lotação: usuário x unidade x perfil
        Schema::create('unit_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->timestamps();
            $table->unique(['user_id', 'unit_id']);
        });

        // Serviços que o atendente atende em cada unidade
        Schema::create('service_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'unit_id', 'service_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_unit_id')->nullable()->after('is_admin')->constrained('units')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('current_unit_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('location_number')->nullable()->after('location_id');
            $table->string('queue_type', 20)->default('all')->after('location_number');
            $table->json('behavior')->nullable()->after('queue_type');
            $table->unsignedInteger('priority_swap_count')->default(0)->after('behavior');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('document', 30)->unique();
            $table->string('email', 80)->nullable();
            $table->string('phone', 25)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 1)->nullable();
            $table->text('notes')->nullable();
            $table->string('address_country', 2)->nullable();
            $table->string('address_zip', 25)->nullable();
            $table->string('address_state', 3)->nullable();
            $table->string('address_city', 30)->nullable();
            $table->string('address_street', 60)->nullable();
            $table->string('address_number', 10)->nullable();
            $table->string('address_complement', 15)->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->date('date');
            $table->time('time');
            $table->string('status', 20)->default('scheduled'); // scheduled, confirmed, no_show
            $table->timestamp('confirmed_at')->nullable();
            $table->string('external_id', 64)->nullable()->index();
            $table->timestamps();
            $table->index(['unit_id', 'date']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('priority_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained(); // atendente
            $table->foreignId('triage_user_id')->nullable()->constrained('users');
            $table->foreignId('parent_id')->nullable()->constrained('tickets');
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->unsignedSmallInteger('location_number')->nullable();
            $table->string('prefix', 3);
            $table->unsignedInteger('number');
            $table->string('status', 25)->default('issued');
            $table->string('resolution', 25)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('arrived_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('wait_time')->nullable();     // segundos
            $table->unsignedInteger('travel_time')->nullable();
            $table->unsignedInteger('service_time')->nullable();
            $table->unsignedInteger('total_time')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'status', 'archived_at']);
            $table->index('arrived_at');
        });

        // Serviços efetivamente realizados no atendimento (codificação)
        Schema::create('ticket_services', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->primary(['ticket_id', 'service_id']);
        });

        Schema::create('panels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->uuid('public_id')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('panel_service', function (Blueprint $table) {
            $table->foreignId('panel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['panel_id', 'service_id']);
        });

        // Chamadas exibidas nos painéis (uma linha por chamada/rechamada)
        Schema::create('panel_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prefix', 3);
            $table->unsignedInteger('number');
            $table->string('message')->default('');
            $table->string('location', 20);
            $table->unsignedSmallInteger('location_number');
            $table->unsignedSmallInteger('priority_weight')->default(0);
            $table->string('priority_name', 100)->nullable();
            $table->string('priority_color', 20)->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_document', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['unit_id', 'id']);
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('url');
            $table->json('headers');
            $table->json('events');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_unit_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['location_number', 'queue_type', 'behavior', 'priority_swap_count']);
        });

        foreach (['settings', 'webhooks', 'panel_calls', 'panel_service', 'panels', 'ticket_services', 'tickets',
            'appointments', 'customers', 'service_user', 'unit_user', 'roles', 'unit_services', 'services',
            'priorities', 'locations', 'departments', 'units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
