<?php

namespace Tests\Feature;

use App\Exceptions\TicketException;
use App\Livewire\Attendance;
use App\Livewire\Monitor;
use App\Livewire\UnitSettings;
use App\Models\AttendantPause;
use App\Models\PauseReason;
use App\Models\Priority;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitService;
use App\Models\User;
use App\Services\PauseService;
use App\Services\SlaService;
use App\Services\TicketService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SlaAndPausesTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $admin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->unit = Unit::first();
        $this->admin = User::where('login', 'admin')->first();
        $this->service = Service::where('name', 'Atendimento Geral')->first();
    }

    private function issue(int $minutesAgo = 0): Ticket
    {
        $t = app(TicketService::class)->issue($this->unit, $this->service->id, Priority::where('weight', 0)->first(), $this->admin);
        $t->update(['arrived_at' => now()->subMinutes($minutesAgo)]);

        return $t->fresh();
    }

    private function configure(array $settings): void
    {
        $this->unit->update(['settings' => array_replace($this->unit->settings ?? [], $settings)]);
        $this->unit->refresh();
    }

    // ------------------------------------------------------------ metas

    public function test_sla_states_follow_default_and_service_target(): void
    {
        $this->configure(['sla_default_target' => 10, 'sla_warning_percent' => 80]);
        $sla = new SlaService;

        $this->assertSame(SlaService::OK, $sla->state($this->issue(2), $this->unit));
        $this->assertSame(SlaService::WARNING, $sla->state($this->issue(9), $this->unit));
        $this->assertSame(SlaService::BREACH, $sla->state($this->issue(11), $this->unit));

        UnitService::where('service_id', $this->service->id)->update(['wait_target' => 30]);
        $this->assertSame(SlaService::OK, (new SlaService)->state($this->issue(11), $this->unit));
    }

    public function test_sla_disabled_returns_no_state(): void
    {
        $this->configure(['sla_enabled' => false]);

        $this->assertNull((new SlaService)->state($this->issue(60), $this->unit));
        $this->assertFalse($this->unit->feature('sla_monitor_sound'));
    }

    public function test_monitor_shows_breach_summary(): void
    {
        $this->configure(['sla_default_target' => 5]);
        $this->issue(20);
        $this->issue(1);

        $this->actingAs($this->admin);
        $component = Livewire::test(Monitor::class)->assertSee('Acima da meta');

        $this->assertSame(1, $component->instance()->slaSummary['breach']);
    }

    // ------------------------------------------------------------ pausas

    public function test_pause_blocks_calls_until_resumed(): void
    {
        $this->issue();
        $pauses = app(PauseService::class);
        $pause = $pauses->start($this->unit, $this->admin, PauseReason::where('name', 'Almoço')->value('id'));

        try {
            app(TicketService::class)->callNext($this->unit, $this->admin);
            $this->fail('Deveria bloquear a chamada durante a pausa.');
        } catch (TicketException $e) {
            $this->assertStringContainsString('pausa', $e->getMessage());
        }

        $pauses->end($pause);
        $this->assertNotNull($pause->fresh()->duration);
        $this->assertSame('A001', app(TicketService::class)->callNext($this->unit, $this->admin)->code());
    }

    public function test_pause_requires_reason_when_configured(): void
    {
        $this->expectException(TicketException::class);
        app(PauseService::class)->start($this->unit, $this->admin);
    }

    public function test_pause_without_reason_when_allowed(): void
    {
        $this->configure(['pause_require_reason' => false]);

        $this->assertNull(app(PauseService::class)->start($this->unit, $this->admin)->reason);
    }

    public function test_cannot_pause_during_attendance(): void
    {
        $this->issue();
        app(TicketService::class)->callNext($this->unit, $this->admin);

        $this->expectExceptionMessage('Finalize o atendimento');
        app(PauseService::class)->start($this->unit, $this->admin, PauseReason::value('id'));
    }

    public function test_disabled_pauses_are_rejected(): void
    {
        $this->configure(['pauses_enabled' => false]);

        $this->expectException(TicketException::class);
        app(PauseService::class)->start($this->unit, $this->admin, PauseReason::value('id'));
    }

    public function test_attendance_screen_pause_flow_and_monitor(): void
    {
        $this->actingAs($this->admin);
        $reason = PauseReason::where('name', 'Intervalo')->first();

        Livewire::test(Attendance::class)
            ->set('pauseReasonId', $reason->id)
            ->call('startPause')
            ->assertSee('Em pausa')
            ->assertSee('Retomar atendimento');

        $pause = AttendantPause::open()->first();
        $pause->update(['started_at' => now()->subMinutes(20)]); // acima dos 15 min do Intervalo

        $monitor = Livewire::test(Monitor::class)->assertSee('acima do limite');
        $this->assertSame('paused', $monitor->instance()->attendants->first()['status']);

        Livewire::test(Attendance::class)->call('endPause')->assertDontSee('Retomar atendimento');
        $this->assertNull(AttendantPause::open()->first());
    }

    public function test_stale_pauses_are_closed(): void
    {
        $this->configure(['pause_require_reason' => false]);
        $pause = app(PauseService::class)->start($this->unit, $this->admin);
        $pause->update(['started_at' => now()->subHours(20)]);

        $this->assertSame(1, app(PauseService::class)->closeStale(now()->subHours(12)));
        $this->assertNotNull($pause->fresh()->ended_at);
    }

    // ------------------------------------------------------------ configuração e relatórios

    public function test_settings_screen_saves_sla_and_pause_options(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UnitSettings::class)
            ->set('tab', 'sla')
            ->set('features.sla_default_target', 20)
            ->set('features.pauses_enabled', false)
            ->call('saveFeatures')
            ->assertHasNoErrors();

        $unit = $this->unit->fresh();
        $this->assertSame(20, $unit->setting('sla_default_target'));
        $this->assertFalse($unit->feature('pauses_enabled'));
        $this->assertFalse($unit->feature('pause_require_reason'));
    }

    public function test_sla_and_pause_reports(): void
    {
        $this->configure(['sla_default_target' => 10]);
        $t = $this->issue();
        $t->update(['wait_time' => 300]);
        app(PauseService::class)->start($this->unit, $this->admin, PauseReason::value('id'));

        $this->actingAs($this->admin)->get(route('reports.show', 'sla'))->assertOk()->assertSee('100,0%');
        $this->actingAs($this->admin)->get(route('reports.show', 'pauses'))->assertOk()->assertSee('Administrador');
    }
}
