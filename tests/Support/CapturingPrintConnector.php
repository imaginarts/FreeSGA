<?php

namespace Tests\Support;

use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;

/** Guarda os bytes ESC/POS enviados, mesmo depois de a conexão ser fechada. */
class CapturingPrintConnector extends MemoryPrintConnector
{
    public string $data = '';

    public function finalize(): void
    {
        $this->data = parent::getData();
        parent::finalize();
    }

    public function getData()
    {
        return $this->data;
    }
}
