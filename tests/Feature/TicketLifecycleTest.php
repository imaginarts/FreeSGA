<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Exceptions\TicketException;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PanelCall;
use App\Models\Priority;
use App\Models\Service;
use App\Models\ServiceUser;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitService;
use App\Models\User;
use App\Services\QueueService;
use App\Services\TicketService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $tickets;

    private Unit $unit;

    private User $admin;

    private Priority $normal;

    private Priority $priority;

    private Service $general;

    private Service $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->tickets = app(TicketService::class);
        $this->unit = Unit::first();
        $this->admin = User::where('login', 'admin')->first();
        $this->normal = Priority::where('weight', 0)->first();
        $this->priority = Priority::where('weight', 1)->first();
        $this->general = Service::where('name', 'Atendimento Geral')->first();
        $this->finance = Service::where('name', 'Financeiro')->first();
    }

    private function issue(?Service $service = null, ?Priority $priority = null): Ticket
    {
        return $this->tickets->issue($this->unit, ($service ?? $this->general)->id, $priority ?? $this->normal, $this->admin);
    }

    public function test_issues_sequential_numbers_per_service(): void
    {
        $a1 = $this->issue();
        $a2 = $this->issue();
        $b1 = $this->issue($this->finance);

        $this->assertSame('A001', $a1->code());
        $this->assertSame('A002', $a2->code());
        $this->assertSame('B001', $b1->code());
        $this->assertSame(TicketStatus::Issued, $a1->status);
    }

    public function test_counter_wraps_at_end_number(): void
    {
        UnitService::where('service_id', $this->general->id)->update(['start_number' => 10, 'end_number' => 11, 'next_number' => 10]);

        $this->assertSame(10, $this->issue()->number);
        $this->assertSame(11, $this->issue()->number);
        $this->assertSame(10, $this->issue()->number);
    }

    public function test_rejects_priority_not_accepted_by_service(): void
    {
        UnitService::where('service_id', $this->general->id)->update(['type' => ServiceType::NormalOnly]);

        $this->expectException(TicketException::class);
        $this->issue(priority: $this->priority);
    }

    public function test_rejects_when_max_tickets_reached(): void
    {
        UnitService::where('service_id', $this->general->id)->update(['max_tickets' => 1]);
        $this->issue();

        $this->expectException(TicketException::class);
        $this->issue();
    }

    public function test_priority_ticket_is_called_first(): void
    {
        $normal = $this->issue();
        $priority = $this->issue(priority: $this->priority);

        $called = $this->tickets->callNext($this->unit, $this->admin);

        $this->assertSame($priority->id, $called->id);
        $this->assertSame(TicketStatus::Called, $called->status);
        $this->assertNotNull($called->wait_time);
        $this->assertSame(1, PanelCall::count());
        $this->assertNotSame($normal->id, $called->id);
    }

    public function test_priority_swap_alternates_after_count(): void
    {
        Setting::put('behavior', ['priority_swap' => true, 'priority_swap_count' => 1]);

        $normal = $this->issue();
        $p1 = $this->issue(priority: $this->priority);
        $this->issue(priority: $this->priority);

        $first = $this->tickets->callNext($this->unit, $this->admin);
        $this->assertSame($p1->id, $first->id);
        $this->finishTicket($first);

        // após 1 prioridade, a próxima chamada ignora o peso e segue a chegada
        $second = $this->tickets->callNext($this->unit, $this->admin);
        $this->assertSame($normal->id, $second->id);
    }

    public function test_cannot_call_while_attending(): void
    {
        $this->issue();
        $this->issue();
        $this->tickets->callNext($this->unit, $this->admin);

        $this->expectException(TicketException::class);
        $this->tickets->callNext($this->unit, $this->admin);
    }

    public function test_requires_location_to_call(): void
    {
        $this->issue();
        $this->admin->update(['location_id' => null]);

        $this->expectExceptionMessage('local de atendimento');
        $this->tickets->callNext($this->unit, $this->admin);
    }

    public function test_full_attendance_flow_records_times_and_services(): void
    {
        $ticket = $this->issue();
        $ticket = $this->tickets->callNext($this->unit, $this->admin);
        $this->tickets->recall($ticket, $this->admin);
        $this->assertSame(2, PanelCall::count());

        $this->tickets->start($ticket, $this->admin);
        $child = Service::where('parent_id', $this->general->id)->first();
        $this->tickets->finish($ticket, $this->admin, [$this->general->id, $child->id], notes: 'ok');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Finished, $ticket->status);
        $this->assertNotNull($ticket->service_time);
        $this->assertNotNull($ticket->total_time);
        $this->assertCount(2, $ticket->performedServices);
        $this->assertNull($this->tickets->currentTicket($this->unit, $this->admin));
    }

    public function test_finish_requires_performed_service(): void
    {
        $this->issue();
        $ticket = $this->tickets->callNext($this->unit, $this->admin);
        $this->tickets->start($ticket, $this->admin);

        $this->expectException(TicketException::class);
        $this->tickets->finish($ticket, $this->admin, []);
    }

    public function test_redirect_creates_child_with_same_number_for_target_user(): void
    {
        $other = User::factory()->create(['location_id' => Location::first()->id, 'location_number' => 2]);
        ServiceUser::create(['user_id' => $other->id, 'unit_id' => $this->unit->id, 'service_id' => $this->finance->id]);

        $this->issue();
        $ticket = $this->tickets->callNext($this->unit, $this->admin);
        $this->tickets->start($ticket, $this->admin);
        $child = $this->tickets->redirect($ticket, $this->admin, $this->finance->id, $other->id);

        $this->assertSame(TicketStatus::Redirected, $ticket->fresh()->status);
        $this->assertSame($ticket->code(), $child->code());
        $this->assertSame($ticket->id, $child->parent_id);

        $queue = app(QueueService::class);
        $this->assertTrue($queue->userQueue($this->unit, $other)->contains('id', $child->id));
        $this->assertFalse($queue->userQueue($this->unit, $this->admin)->contains('id', $child->id));
    }

    public function test_no_show_cancel_reactivate_transfer(): void
    {
        $this->issue();
        $ticket = $this->tickets->callNext($this->unit, $this->admin);
        $this->tickets->noShow($ticket, $this->admin);
        $this->assertSame(TicketStatus::NoShow, $ticket->fresh()->status);

        $this->tickets->reactivate($ticket->fresh());
        $ticket->refresh();
        $this->assertSame(TicketStatus::Issued, $ticket->status);
        $this->assertNull($ticket->user_id);

        $this->tickets->transfer($ticket, $this->finance->id, $this->priority);
        $this->assertSame($this->finance->id, $ticket->fresh()->service_id);

        $this->tickets->cancel($ticket->fresh());
        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);
    }

    public function test_appointment_confirmation_creates_scheduled_ticket(): void
    {
        $customer = Customer::create(['name' => 'Maria', 'document' => '123']);
        $local = now($this->unit->timezone());
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'unit_id' => $this->unit->id,
            'service_id' => $this->general->id,
            'date' => $local->toDateString(),
            'time' => $local->format('H:i:00'),
        ]);

        $ticket = $this->tickets->issue($this->unit, $this->general->id, $this->normal, $this->admin, appointment: $appointment);

        $this->assertNotNull($ticket->scheduled_at);
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_archive_hides_tickets_and_resets_counters(): void
    {
        $this->issue();
        $this->issue();

        $this->assertSame(2, $this->tickets->archive($this->unit));
        $this->assertSame(0, Ticket::current()->count());
        $this->assertSame('A001', $this->issue()->code());
    }

    private function finishTicket(Ticket $ticket): void
    {
        $this->tickets->start($ticket, $this->admin);
        $this->tickets->finish($ticket, $this->admin, [$ticket->service_id]);
    }
}
