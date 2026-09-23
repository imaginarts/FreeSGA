<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Exceptions\TicketException;
use App\Livewire\Devices;
use App\Livewire\KioskScreen;
use App\Livewire\Triage;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Kiosk;
use App\Models\Printer;
use App\Models\Priority;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\TicketPrinter;
use App\Services\TicketService;
use App\Support\Cpf;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\CapturingPrintConnector;
use Tests\TestCase;

class KioskAndPrinterTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $admin;

    private Service $service;

    /** @var CapturingPrintConnector[] */
    private array $printed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->unit = Unit::first();
        $this->admin = User::where('login', 'admin')->first();
        $this->service = Service::where('name', 'Atendimento Geral')->first();

        // captura os bytes ESC/POS em memória em vez de abrir conexão com a impressora
        app(TicketPrinter::class)->useConnector(function () {
            return $this->printed[] = new CapturingPrintConnector;
        });
    }

    private function printer(array $attributes = []): Printer
    {
        return Printer::create(['unit_id' => $this->unit->id, 'name' => 'Recepção', 'host' => '192.168.0.50', ...$attributes]);
    }

    private function kiosk(array $attributes = [], array $settings = []): Kiosk
    {
        return Kiosk::create(['unit_id' => $this->unit->id, 'name' => 'Totem 1', 'settings' => $settings, ...$attributes]);
    }

    private function issue(): Ticket
    {
        return app(TicketService::class)->issue($this->unit, $this->service->id, Priority::where('weight', 0)->first(), $this->admin);
    }

    // ------------------------------------------------------------ impressora

    public function test_prints_ticket_in_escpos_with_qr_and_cut(): void
    {
        $ticket = $this->issue();
        app(TicketPrinter::class)->print($ticket, $printer = $this->printer());

        $data = $this->printed[0]->getData();
        $this->assertStringContainsString('A001', $data);
        $this->assertStringContainsString('Atendimento Geral', $data);
        $this->assertStringContainsString($ticket->trackingUrl(), $data); // QR nativo leva a URL
        $this->assertStringContainsString("\x1dV", $data); // comando de corte
        $this->assertNotNull($printer->fresh()->last_used_at);
    }

    public function test_printer_options_are_respected(): void
    {
        $this->unit->update(['name' => 'Unidade São João']);
        $ticket = $this->issue();
        app(TicketPrinter::class)->print($ticket, $this->printer(['strip_accents' => true, 'print_qr' => false, 'cut' => false]));

        $data = $this->printed[0]->getData();
        $this->assertStringContainsString('UNIDADE SAO JOAO', $data);
        $this->assertStringNotContainsString($ticket->trackingUrl(), $data);
        $this->assertStringNotContainsString("\x1dV", $data);
    }

    public function test_printer_failure_is_reported(): void
    {
        app(TicketPrinter::class)->useConnector(fn () => throw new RuntimeException('Connection refused'));
        $printer = $this->printer();

        try {
            app(TicketPrinter::class)->testPage($printer);
            $this->fail('Deveria lançar exceção');
        } catch (TicketException $e) {
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }

        $this->assertStringContainsString('Connection refused', $printer->fresh()->last_error);
    }

    public function test_triage_prints_directly_on_selected_printer(): void
    {
        $printer = $this->printer();
        $ticket = $this->issue();

        Livewire::actingAs($this->admin->fresh())->test(Triage::class)->call('printDirect', $ticket->id, $printer->id);
        $this->assertCount(1, $this->printed);

        $this->unit->update(['settings' => ['direct_print_enabled' => false]]);
        Livewire::actingAs($this->admin->fresh())->test(Triage::class)->call('printDirect', $ticket->id, $printer->id)->assertDispatched('toast');
        $this->assertCount(1, $this->printed);
    }

    public function test_devices_screen_manages_printers_and_kiosks(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Devices::class)
            ->call('createPrinter')
            ->set('printer.name', 'Balcão')
            ->set('printer.host', '10.0.0.9')
            ->call('savePrinter')
            ->assertHasNoErrors();
        $printer = Printer::first();
        $this->assertSame('10.0.0.9:9100', $printer->address());

        Livewire::test(Devices::class)->call('testPrinter', $printer->id)->assertDispatched('toast');
        $this->assertCount(1, $this->printed);

        Livewire::test(Devices::class)
            ->call('createKiosk')
            ->set('kiosk.name', 'Entrada')
            ->set('kiosk.print_mode', 'printer')
            ->call('saveKiosk')
            ->assertHasErrors('kiosk.printer_id')
            ->set('kiosk.printer_id', $printer->id)
            ->call('saveKiosk')
            ->assertHasNoErrors();

        $this->assertSame($printer->id, Kiosk::first()->printer_id);
    }

    // ------------------------------------------------------------ totem

    public function test_kiosk_issues_normal_ticket_and_prints_in_browser(): void
    {
        $kiosk = $this->kiosk();

        $this->get(route('kiosk.show', $kiosk))->assertOk()->assertSee('Bem-vindo');

        Livewire::test(KioskScreen::class, ['kiosk' => $kiosk])
            ->call('begin')
            ->assertSee('Escolha o serviço')
            ->call('chooseService', $this->service->id)
            ->assertSet('step', 'type')
            ->call('chooseNormal')
            ->assertSet('step', 'done')
            ->assertSee('A001')
            ->assertDispatched('kiosk-print');

        $ticket = Ticket::first();
        $this->assertSame($kiosk->id, $ticket->kiosk_id);
        $this->assertNull($ticket->triage_user_id);
    }

    public function test_kiosk_with_thermal_printer_prints_on_server(): void
    {
        $kiosk = $this->kiosk(['print_mode' => 'printer', 'printer_id' => $this->printer()->id]);

        Livewire::test(KioskScreen::class, ['kiosk' => $kiosk])
            ->call('begin')
            ->call('chooseService', $this->service->id)
            ->call('choosePreferential')
            ->assertSet('step', 'priority') // duas prioridades no seed
            ->call('choosePriority', Priority::where('weight', 1)->value('id'))
            ->assertSet('step', 'done')
            ->assertNotDispatched('kiosk-print');

        $this->assertCount(1, $this->printed);
        $this->assertSame(1, Ticket::first()->priority->weight);
    }

    public function test_kiosk_document_step_validates_cpf(): void
    {
        $kiosk = $this->kiosk([], ['ask_document' => true, 'require_document' => true, 'show_priority' => false]);

        $component = Livewire::test(KioskScreen::class, ['kiosk' => $kiosk])
            ->call('begin')
            ->call('chooseService', $this->service->id)
            ->assertSet('step', 'document')
            ->call('submitDocument', '')
            ->assertSee('Informe o CPF')
            ->call('submitDocument', '12345678900')
            ->assertSee('CPF inválido')
            ->call('submitDocument', '52998224725')
            ->assertSet('step', 'done');

        $this->assertSame('52998224725', Ticket::first()->meta['document']);
    }

    public function test_kiosk_appointment_check_in_by_cpf(): void
    {
        $customer = Customer::create(['name' => 'Maria', 'document' => '529.982.247-25']);
        $local = now($this->unit->timezone());
        $appointment = Appointment::create([
            'customer_id' => $customer->id, 'unit_id' => $this->unit->id, 'service_id' => $this->service->id,
            'date' => $local->toDateString(), 'time' => $local->format('H:i:00'),
        ]);

        Livewire::test(KioskScreen::class, ['kiosk' => $this->kiosk()])
            ->call('startAppointment')
            ->call('findAppointments', '11111111111')
            ->assertSee('CPF inválido')
            ->call('findAppointments', '52998224725')
            ->assertSee('Confirmar chegada')
            ->call('confirmAppointment', $appointment->id)
            ->assertSet('step', 'done');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame($customer->id, Ticket::first()->customer_id);
    }

    public function test_kiosk_disabled_blocks_issuing(): void
    {
        $kiosk = $this->kiosk();
        $this->unit->update(['settings' => ['kiosk_enabled' => false]]);

        Livewire::test(KioskScreen::class, ['kiosk' => $kiosk])->assertSee('Totem indisponível');

        $this->expectException(TicketException::class);
        app(TicketService::class)->issue($this->unit, $this->service->id, Priority::first(), null, kiosk: $kiosk->fresh());
    }

    public function test_kiosk_only_shows_selected_services(): void
    {
        $kiosk = $this->kiosk(['services' => [$this->service->id]]);

        Livewire::test(KioskScreen::class, ['kiosk' => $kiosk])
            ->call('begin')
            ->assertSee('Atendimento Geral')
            ->assertDontSee('Financeiro');
    }

    public function test_cpf_validation(): void
    {
        $this->assertTrue(Cpf::valid('529.982.247-25'));
        $this->assertFalse(Cpf::valid('529.982.247-24'));
        $this->assertFalse(Cpf::valid('000.000.000-00'));
        $this->assertSame('529.982.247-25', Cpf::format('52998224725'));
    }
}
