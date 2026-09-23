<?php

namespace Database\Seeders;

use App\Enums\Module;
use App\Enums\QueueType;
use App\Models\Allocation;
use App\Models\Location;
use App\Models\Panel;
use App\Models\PauseReason;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceUser;
use App\Models\Unit;
use App\Models\UnitService;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $unit = Unit::create([
            'name' => 'Unidade Central',
            'description' => 'Unidade principal',
            'timezone' => 'America/Sao_Paulo',
            'print_header' => 'Bem-vindo!',
            'print_footer' => 'Aguarde ser chamado no painel',
        ]);

        Priority::create(['name' => 'Normal', 'description' => 'Sem prioridade', 'weight' => 0, 'color' => '#0284c7']);
        Priority::create(['name' => 'Prioridade', 'description' => 'Atendimento prioritário (Lei 10.048)', 'weight' => 1, 'color' => '#dc2626']);
        Priority::create(['name' => 'Idoso 80+', 'description' => 'Prioridade especial (Lei 13.466)', 'weight' => 2, 'color' => '#9333ea']);

        $guiche = Location::create(['name' => 'Guichê']);
        Location::create(['name' => 'Mesa']);
        Location::create(['name' => 'Sala']);

        $manager = Role::create([
            'name' => 'Gerente',
            'description' => 'Acesso a todos os módulos da unidade',
            'modules' => array_column(Module::cases(), 'value'),
        ]);
        Role::create([
            'name' => 'Atendente',
            'description' => 'Triagem, atendimento e monitor',
            'modules' => [Module::Triage->value, Module::Attendance->value, Module::Monitor->value],
        ]);
        Role::create([
            'name' => 'Recepção',
            'description' => 'Emissão de senhas e agendamentos',
            'modules' => [Module::Triage->value, Module::Scheduling->value, Module::Customers->value],
        ]);

        $services = [
            ['Atendimento Geral', 'Informações e serviços gerais', ['Informações', 'Segunda via de documentos']],
            ['Financeiro', 'Pagamentos e negociações', ['Pagamento', 'Negociação de débitos']],
            ['Cadastro', 'Cadastro e atualização de dados', ['Novo cadastro', 'Atualização cadastral']],
        ];

        $admin = User::forceCreate([
            'login' => 'admin',
            'name' => 'Administrador',
            'last_name' => '',
            'email' => 'admin@sga.local',
            'password' => '123456',
            'is_admin' => true,
            'current_unit_id' => $unit->id,
            'location_id' => $guiche->id,
            'location_number' => 1,
            'queue_type' => QueueType::All,
        ]);
        Allocation::create(['user_id' => $admin->id, 'unit_id' => $unit->id, 'role_id' => $manager->id]);

        foreach ($services as $i => [$name, $description, $children]) {
            $service = Service::create(['name' => $name, 'description' => $description]);
            foreach ($children as $child) {
                Service::create(['name' => $child, 'description' => '', 'parent_id' => $service->id]);
            }

            UnitService::create([
                'unit_id' => $unit->id,
                'service_id' => $service->id,
                'prefix' => Service::prefixFor($i + 1),
                'active' => true,
            ]);

            ServiceUser::create(['user_id' => $admin->id, 'unit_id' => $unit->id, 'service_id' => $service->id]);
        }

        foreach ([['Almoço', 60], ['Intervalo', 15], ['Banheiro', 10], ['Reunião', null], ['Treinamento', null], ['Suporte técnico', 30]] as [$name, $max]) {
            PauseReason::create(['name' => $name, 'max_minutes' => $max]);
        }

        $panel = Panel::create(['unit_id' => $unit->id, 'name' => 'Painel da recepção']);
        $panel->services()->sync(Service::main()->pluck('id'));
    }
}
