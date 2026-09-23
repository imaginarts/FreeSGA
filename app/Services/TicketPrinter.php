<?php

namespace App\Services;

use App\Exceptions\TicketException;
use App\Models\Printer as PrinterModel;
use App\Models\Ticket;
use App\Models\UnitService;
use Closure;
use Illuminate\Support\Str;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

/** Impressão direta em impressoras térmicas ESC/POS, sem diálogo do navegador. */
class TicketPrinter
{
    /** Permite trocar a conexão (ex.: testes). */
    private ?Closure $connectorFactory = null;

    public function useConnector(Closure $factory): void
    {
        $this->connectorFactory = $factory;
    }

    public function print(Ticket $ticket, PrinterModel $printer): void
    {
        $ticket->loadMissing(['unit', 'service', 'priority']);
        $unit = $ticket->unit;
        $message = UnitService::where('unit_id', $ticket->unit_id)->where('service_id', $ticket->service_id)->value('message');

        $this->send($printer, function (Printer $p) use ($ticket, $unit, $message, $printer) {
            $p->setJustification(Printer::JUSTIFY_CENTER);

            if ($unit->print_show_unit_name) {
                $p->setEmphasis(true);
                $this->line($p, $printer, mb_strtoupper($unit->name));
                $p->setEmphasis(false);
            }
            if ($unit->print_header) {
                $this->line($p, $printer, $unit->print_header);
            }
            $this->separator($p, $printer);

            if ($unit->print_show_priority) {
                $p->setEmphasis(true);
                $this->line($p, $printer, mb_strtoupper($ticket->priority->name));
                $p->setEmphasis(false);
            }

            $p->setTextSize(4, 4);
            $this->line($p, $printer, $ticket->code());
            $p->setTextSize(1, 1);

            if ($unit->print_show_service_name) {
                $p->setTextSize(1, 2);
                $this->line($p, $printer, $ticket->service->name);
                $p->setTextSize(1, 1);
            }
            if ($unit->print_show_service_message && $message) {
                $this->line($p, $printer, $message);
            }

            if ($printer->print_qr && $unit->feature('mobile_ticket')) {
                $p->feed();
                $p->qrCode($ticket->trackingUrl(), Printer::QR_ECLEVEL_M, $printer->paper_width <= 58 ? 5 : 6);
                $this->line($p, $printer, 'Acompanhe sua senha pelo celular');
            }

            $this->separator($p, $printer);
            if ($unit->print_show_date) {
                $this->line($p, $printer, $ticket->localTime($ticket->arrived_at, 'd/m/Y H:i'));
            }
            if ($unit->print_footer) {
                $this->line($p, $printer, $unit->print_footer);
            }
        });
    }

    public function testPage(PrinterModel $printer): void
    {
        $this->send($printer, function (Printer $p) use ($printer) {
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->setTextSize(2, 2);
            $this->line($p, $printer, 'SGA');
            $p->setTextSize(1, 1);
            $this->line($p, $printer, 'Página de teste');
            $this->separator($p, $printer);
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $this->line($p, $printer, 'Impressora: '.$printer->name);
            $this->line($p, $printer, 'Conexão: '.$printer->address());
            $this->line($p, $printer, "Papel: {$printer->paper_width} mm ({$printer->columns()} colunas)");
            $this->line($p, $printer, 'Acentuação: ÁÉÍÓÚ ÂÊÔ ÃÕ Ç');
            $this->line($p, $printer, 'Data: '.now($printer->unit->timezone())->format('d/m/Y H:i:s'));
            $this->line($p, $printer, str_repeat('-', $printer->columns()));
            $p->setJustification(Printer::JUSTIFY_CENTER);
            if ($printer->print_qr) {
                $p->qrCode(url('/'), Printer::QR_ECLEVEL_M, 5);
            }
            $this->line($p, $printer, 'Impressão OK');
        });
    }

    private function send(PrinterModel $printer, Closure $build): void
    {
        $escpos = null;

        try {
            $escpos = new Printer($this->connect($printer), CapabilityProfile::load('default'));
            $build($escpos);
            $escpos->feed(3);
            if ($printer->cut) {
                $escpos->cut();
            }
            $printer->forceFill(['last_used_at' => now(), 'last_error' => null])->saveQuietly();
        } catch (Throwable $e) {
            $printer->forceFill(['last_error' => Str::limit($e->getMessage(), 250)])->saveQuietly();

            throw new TicketException("Falha ao imprimir em \"{$printer->name}\": ".$e->getMessage(), previous: $e);
        } finally {
            try {
                $escpos?->close();
            } catch (Throwable) {
                // conexão já encerrada
            }
        }
    }

    private function connect(PrinterModel $printer): PrintConnector
    {
        if ($this->connectorFactory) {
            return ($this->connectorFactory)($printer);
        }

        return match ($printer->connection_type) {
            'windows' => new WindowsPrintConnector($printer->host),
            default => new NetworkPrintConnector($printer->host, $printer->port, 3),
        };
    }

    private function line(Printer $p, PrinterModel $printer, string $text): void
    {
        $p->text(($printer->strip_accents ? Str::ascii($text) : $text)."\n");
    }

    private function separator(Printer $p, PrinterModel $printer): void
    {
        $p->text(str_repeat('-', $printer->columns())."\n");
    }
}
