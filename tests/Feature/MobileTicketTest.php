<?php

namespace Tests\Feature;

use App\Livewire\UnitSettings;
use App\Models\Panel;
use App\Models\Priority;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\TicketService;
use App\Services\WaitEstimator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MobileTicketTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $admin;

    private TicketService $tickets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->unit = Unit::first();
        $this->admin = User::where('login', 'admin')->first();
        $this->tickets = app(TicketService::class);
    }

    private function issue(): Ticket
    {
        return $this->tickets->issue($this->unit, Service::where('name', 'Atendimento Geral')->value('id'), Priority::where('weight', 0)->first(), $this->admin);
    }

    private function configure(array $settings): void
    {
        $this->unit->update(['settings' => array_replace($this->unit->settings ?? [], $settings)]);
    }

    public function test_tracking_page_shows_position_and_status(): void
    {
        $first = $this->issue();
        $second = $this->issue();

        $this->get($second->trackingUrl())->assertOk()->assertSee('A002');

        $this->getJson(route('ticket.track.data', [$second, $second->trackingToken()]))
            ->assertOk()
            ->assertJsonPath('status', 'issued')
            ->assertJsonPath('position', 2)
            ->assertJsonPath('near', true);

        $this->tickets->callNext($this->unit, $this->admin);
        $this->tickets->start($first->fresh(), $this->admin);
        $this->tickets->finish($first->fresh(), $this->admin, [$first->service_id]);
        $this->tickets->callNext($this->unit, $this->admin);

        $this->getJson(route('ticket.track.data', [$second, $second->trackingToken()]))
            ->assertJsonPath('status', 'called')
            ->assertJsonPath('location', 'Guichê 01')
            ->assertJsonPath('position', null);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $ticket = $this->issue();

        $this->get(route('ticket.track', [$ticket, 'errado']))->assertNotFound();
    }

    public function test_disabling_feature_blocks_page_and_hides_qr(): void
    {
        $ticket = $this->issue();
        $this->configure(['mobile_ticket' => false]);

        $this->get($ticket->trackingUrl())->assertNotFound();
        $this->actingAs($this->admin)->get(route('ticket.print', $ticket))->assertOk()->assertDontSee('Acompanhe sua senha');
        $this->assertNull($ticket->fresh()->toApiArray()['tracking_url']);
        $this->assertFalse($this->unit->fresh()->feature('triage_show_qr'));
    }

    public function test_print_includes_qr_when_enabled(): void
    {
        $ticket = $this->issue();

        $this->actingAs($this->admin)->get(route('ticket.print', $ticket))->assertOk()->assertSee('Acompanhe sua senha')->assertSee('<svg', false);
    }

    public function test_sub_options_can_be_disabled(): void
    {
        $ticket = $this->issue();
        $this->configure(['mobile_show_position' => false, 'mobile_show_eta' => false, 'mobile_near_alert' => false]);

        $this->getJson(route('ticket.track.data', [$ticket, $ticket->trackingToken()]))
            ->assertJsonPath('position', null)
            ->assertJsonPath('eta_text', null)
            ->assertJsonPath('near', false);
    }

    public function test_estimate_uses_call_rhythm(): void
    {
        $service = Service::where('name', 'Atendimento Geral')->first();
        // 4 chamadas nos últimos 20 minutos => uma a cada ~5 min
        foreach ([20, 15, 10, 5] as $minutes) {
            $t = $this->issue();
            $t->update(['status' => 'finished', 'called_at' => now()->subMinutes($minutes), 'finished_at' => now()]);
        }
        $waiting = $this->issue();

        $estimator = app(WaitEstimator::class);
        $this->assertEqualsWithDelta(300, $estimator->interval($this->unit, $service->id), 5);
        $this->assertSame(1, $estimator->position($waiting));
        $this->assertEqualsWithDelta(300, $estimator->forTicket($waiting), 5);
        $this->assertSame('~5 min', WaitEstimator::format($estimator->forTicket($waiting)));
    }

    public function test_estimate_is_null_without_history(): void
    {
        $ticket = $this->issue();

        $this->assertNull(app(WaitEstimator::class)->forTicket($ticket));
        $this->assertSame('calculando…', WaitEstimator::format(null));
    }

    public function test_panel_estimates_follow_panel_setting(): void
    {
        $panel = Panel::first();
        $this->issue();

        $this->getJson(route('panel.data', $panel))->assertJsonPath('estimates', []);

        $panel->update(['settings' => ['show_eta' => true]]);
        $this->getJson(route('panel.data', $panel))->assertJsonCount(3, 'estimates')->assertJsonFragment(['waiting' => 1]);
    }

    public function test_settings_screen_saves_features(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UnitSettings::class)
            ->set('tab', 'mobile')
            ->set('features.mobile_ticket', false)
            ->set('features.mobile_near_threshold', 5)
            ->call('saveFeatures')
            ->assertHasNoErrors();

        $unit = $this->unit->fresh();
        $this->assertFalse($unit->feature('mobile_ticket'));
        $this->assertSame(5, $unit->setting('mobile_near_threshold'));
    }
}
