<?php

namespace Tests\Feature;

use App\Enums\Module;
use App\Enums\TicketStatus;
use App\Livewire\Attendance;
use App\Livewire\Monitor;
use App\Livewire\Triage;
use App\Models\Allocation;
use App\Models\Panel;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TicketService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('login', 'admin')->first();
    }

    public static function pages(): array
    {
        return array_map(fn ($r) => [$r], [
            'home', 'profile', 'modules.triage', 'modules.attendance', 'modules.monitor', 'modules.panel',
            'modules.reports', 'modules.scheduling', 'modules.customers', 'modules.users', 'modules.settings',
            'admin.index', 'admin.units', 'admin.services', 'admin.priorities', 'admin.locations',
            'admin.departments', 'admin.roles', 'admin.pause-reasons', 'admin.webhooks', 'admin.api', 'admin.messaging', 'admin.privacy',
        ]);
    }

    #[DataProvider('pages')]
    public function test_admin_can_open_page(string $route): void
    {
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }

    public function test_login_page_and_authentication(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
        $this->post(route('login'), ['login' => 'admin', 'password' => 'errada'])->assertSessionHasErrors('login');
        $this->post(route('login'), ['login' => 'admin', 'password' => '123456'])->assertRedirect(route('home'));
        $this->assertNotNull($this->admin->fresh()->last_login_at);
    }

    public function test_module_access_follows_role(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Só triagem', 'modules' => [Module::Triage->value]]);
        Allocation::create(['user_id' => $user->id, 'unit_id' => Unit::first()->id, 'role_id' => $role->id]);

        $this->actingAs($user)->get(route('modules.triage'))->assertOk();
        $this->actingAs($user)->get(route('modules.attendance'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
    }

    public function test_reports_render(): void
    {
        $this->issueTicket();
        foreach (array_keys(ReportService::REPORTS) as $report) {
            $this->actingAs($this->admin)->get(route('reports.show', $report))->assertOk();
        }
    }

    public function test_public_panel_and_print(): void
    {
        $ticket = $this->issueTicket();
        $panel = Panel::first();

        $this->get(route('panel.display', $panel))->assertOk()->assertSee($panel->name);

        app(TicketService::class)->callNext(Unit::first(), $this->admin);
        $this->getJson(route('panel.data', $panel))->assertOk()->assertJsonPath('calls.0.code', 'A001');

        $this->get(route('ticket.print.public', [$ticket, 'hash' => 'x']))->assertForbidden();
        $this->get(route('ticket.print.public', [$ticket, 'hash' => $ticket->hash()]))->assertOk()->assertSee('A001');
    }

    public function test_triage_and_attendance_components(): void
    {
        $this->actingAs($this->admin);
        $service = Service::where('name', 'Atendimento Geral')->first();

        Livewire::test(Triage::class)
            ->call('choose', $service->id, false)
            ->assertDispatched('ticket-issued');

        $this->assertSame(1, Ticket::count());

        Livewire::test(Attendance::class)
            ->call('callNext')
            ->assertSee('A001')
            ->call('start')
            ->call('openFinish')
            ->set('performed', [(string) $service->id])
            ->call('finish')
            ->assertHasNoErrors();

        $this->assertSame(TicketStatus::Finished, Ticket::first()->status);
    }

    public function test_monitor_cancel_and_reactivate(): void
    {
        $ticket = $this->issueTicket();
        $this->actingAs($this->admin);

        Livewire::test(Monitor::class)->call('open', $ticket->id)->call('cancel');
        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);

        Livewire::test(Monitor::class)->call('open', $ticket->id)->call('reactivate');
        $this->assertSame(TicketStatus::Issued, $ticket->fresh()->status);
    }

    public function test_api_issue_and_call(): void
    {
        Sanctum::actingAs($this->admin);
        $unit = Unit::first();

        $response = $this->postJson('/api/v1/tickets', [
            'unit_id' => $unit->id,
            'service_id' => Service::where('name', 'Financeiro')->value('id'),
            'priority_id' => Priority::where('weight', 1)->value('id'),
            'customer' => ['name' => 'João', 'document' => '999'],
        ])->assertCreated()->assertJsonPath('code', 'B001')->assertJsonPath('customer.name', 'João');

        $this->getJson("/api/v1/units/{$unit->id}/queue")->assertOk()->assertJsonCount(1);
        $this->postJson("/api/v1/units/{$unit->id}/call-next")->assertOk()->assertJsonPath('status', 'called');
        $this->postJson('/api/v1/tickets/'.$response->json('id').'/finish', ['services' => []])->assertUnprocessable();
    }

    private function issueTicket(): Ticket
    {
        return app(TicketService::class)->issue(
            Unit::first(),
            Service::where('name', 'Atendimento Geral')->value('id'),
            Priority::where('weight', 0)->first(),
            $this->admin,
        );
    }
}
