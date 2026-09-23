<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\Kiosk;
use App\Models\Printer;
use App\Models\UnitService;
use App\Services\TicketPrinter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Impressoras térmicas e totens da unidade (aba de Configurações da unidade). */
class Devices extends Component
{
    use InteractsWithUnit;

    public bool $showPrinter = false;

    public ?int $printerId = null;

    public array $printer = [];

    public bool $showKiosk = false;

    public ?int $kioskId = null;

    public array $kiosk = [];

    // ------------------------------------------------------------ impressoras

    public function createPrinter(): void
    {
        $this->resetErrorBag();
        $this->printerId = null;
        $this->printer = ['name' => '', 'connection_type' => 'network', 'host' => '', 'port' => 9100, 'paper_width' => 80, 'cut' => true, 'print_qr' => true, 'strip_accents' => false, 'active' => true];
        $this->showPrinter = true;
    }

    public function editPrinter(int $id): void
    {
        $p = $this->findPrinter($id);
        $this->resetErrorBag();
        $this->printerId = $p->id;
        $this->printer = $p->only(['name', 'connection_type', 'host', 'port', 'paper_width', 'cut', 'print_qr', 'strip_accents', 'active']);
        $this->showPrinter = true;
    }

    public function savePrinter(): void
    {
        $data = $this->validate([
            'printer.name' => ['required', 'string', 'max:60'],
            'printer.connection_type' => ['required', Rule::in(array_keys(Printer::CONNECTIONS))],
            'printer.host' => ['required', 'string', 'max:150', $this->printer['connection_type'] === 'network'
                ? 'regex:/^[a-zA-Z0-9.\-]+$/'
                : 'regex:/^(smb:\/\/[^\s]+|[\w\- ]+)$/'],
            'printer.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'printer.paper_width' => ['required', Rule::in([58, 80])],
            'printer.cut' => ['boolean'],
            'printer.print_qr' => ['boolean'],
            'printer.strip_accents' => ['boolean'],
            'printer.active' => ['boolean'],
        ], ['printer.host.regex' => 'Endereço inválido.'], [
            'printer.name' => 'nome', 'printer.host' => 'endereço', 'printer.port' => 'porta',
        ])['printer'];

        $this->printerId
            ? $this->findPrinter($this->printerId)->update($data)
            : Printer::create([...$data, 'unit_id' => $this->unit()->id]);

        $this->showPrinter = false;
        $this->toast('Impressora salva.');
    }

    public function testPrinter(int $id): void
    {
        $this->attempt(fn () => app(TicketPrinter::class)->testPage($this->findPrinter($id)), 'Página de teste enviada.');
    }

    public function deletePrinter(int $id): void
    {
        $this->findPrinter($id)->delete();
        $this->toast('Impressora removida.');
    }

    private function findPrinter(int $id): Printer
    {
        return Printer::where('unit_id', $this->unit()->id)->findOrFail($id);
    }

    // ------------------------------------------------------------ totens

    public function createKiosk(): void
    {
        $this->resetErrorBag();
        $this->kioskId = null;
        $this->kiosk = ['name' => '', 'print_mode' => 'browser', 'printer_id' => null, 'services' => [], 'active' => true, 'settings' => Kiosk::DEFAULT_SETTINGS];
        $this->showKiosk = true;
    }

    public function editKiosk(int $id): void
    {
        $k = $this->findKiosk($id);
        $this->resetErrorBag();
        $this->kioskId = $k->id;
        $this->kiosk = [
            ...$k->only(['name', 'print_mode', 'printer_id', 'active']),
            'services' => array_map('strval', $k->services ?? []),
            'settings' => array_replace(Kiosk::DEFAULT_SETTINGS, $k->settings ?? []),
        ];
        $this->showKiosk = true;
    }

    public function saveKiosk(): void
    {
        $unit = $this->unit();
        $printers = Printer::where('unit_id', $unit->id)->pluck('id');

        $data = $this->validate([
            'kiosk.name' => ['required', 'string', 'max:60'],
            'kiosk.print_mode' => ['required', Rule::in(array_keys(Kiosk::PRINT_MODES))],
            'kiosk.printer_id' => [$this->kiosk['print_mode'] === 'printer' ? 'required' : 'nullable', Rule::in($printers)],
            'kiosk.services' => ['array'],
            'kiosk.active' => ['boolean'],
            'kiosk.settings.welcome_title' => ['nullable', 'string', 'max:60'],
            'kiosk.settings.welcome_text' => ['nullable', 'string', 'max:120'],
            'kiosk.settings.ask_document' => ['boolean'],
            'kiosk.settings.require_document' => ['boolean'],
            'kiosk.settings.show_priority' => ['boolean'],
            'kiosk.settings.ask_phone' => ['boolean'],
            'kiosk.settings.appointments' => ['boolean'],
            'kiosk.settings.show_eta' => ['boolean'],
            'kiosk.settings.show_qr' => ['boolean'],
            'kiosk.settings.reset_seconds' => ['required', 'integer', 'min:3', 'max:120'],
            'kiosk.settings.idle_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'kiosk.settings.high_contrast' => ['boolean'],
            'kiosk.settings.primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['kiosk.printer_id.required' => 'Escolha a impressora do totem.'], ['kiosk.name' => 'nome'])['kiosk'];

        $valid = UnitService::where('unit_id', $unit->id)->pluck('service_id');
        $data['services'] = $valid->intersect(array_map('intval', $data['services'] ?? []))->values()->all() ?: null;
        $data['printer_id'] = $data['print_mode'] === 'printer' ? $data['printer_id'] : null;

        $this->kioskId
            ? $this->findKiosk($this->kioskId)->update($data)
            : Kiosk::create([...$data, 'unit_id' => $unit->id]);

        $this->showKiosk = false;
        $this->toast('Totem salvo.');
    }

    public function deleteKiosk(int $id): void
    {
        $this->findKiosk($id)->delete();
        $this->toast('Totem removido.');
    }

    private function findKiosk(int $id): Kiosk
    {
        return Kiosk::where('unit_id', $this->unit()->id)->findOrFail($id);
    }

    public function getListeners(): array
    {
        return ['features-saved' => '$refresh'];
    }

    public function render()
    {
        $unit = $this->unit();

        return view('livewire.devices', [
            'unit' => $unit,
            'printers' => Printer::where('unit_id', $unit->id)->orderBy('name')->get(),
            'kiosks' => Kiosk::with('printer')->where('unit_id', $unit->id)->orderBy('name')->get(),
            'unitServices' => UnitService::with('service')->where('unit_id', $unit->id)->where('active', true)->get()->sortBy('service.name'),
            'connections' => Printer::CONNECTIONS,
            'printModes' => Kiosk::PRINT_MODES,
        ]);
    }
}
