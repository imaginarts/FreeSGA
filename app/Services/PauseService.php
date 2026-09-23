<?php

namespace App\Services;

use App\Events\QueueUpdated;
use App\Exceptions\TicketException;
use App\Models\AttendantPause;
use App\Models\PauseReason;
use App\Models\Unit;
use App\Models\User;
use App\Support\Realtime;

/** Pausas do atendente (almoço, intervalo...). */
class PauseService
{
    public function __construct(private TicketService $tickets) {}

    public function start(Unit $unit, User $user, ?int $reasonId = null, ?string $notes = null): AttendantPause
    {
        if (! $unit->feature('pauses_enabled')) {
            throw new TicketException('Pausas estão desabilitadas nesta unidade.');
        }
        if ($user->openPause($unit)) {
            throw new TicketException('Você já está em pausa.');
        }
        if ($this->tickets->currentTicket($unit, $user)) {
            throw new TicketException('Finalize o atendimento atual antes de pausar.');
        }

        $reason = $reasonId ? PauseReason::where('active', true)->find($reasonId) : null;
        if (! $reason && $unit->feature('pause_require_reason')) {
            throw new TicketException('Informe o motivo da pausa.');
        }

        $pause = AttendantPause::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'pause_reason_id' => $reason?->id,
            'reason' => $reason?->name,
            'max_minutes' => $reason?->max_minutes,
            'notes' => $notes ?: null,
            'started_at' => now(),
        ]);

        Realtime::broadcast(new QueueUpdated($unit->id));

        return $pause;
    }

    public function end(AttendantPause $pause): AttendantPause
    {
        if ($pause->ended_at) {
            return $pause;
        }

        $now = now();
        $pause->update([
            'ended_at' => $now,
            'duration' => (int) $pause->started_at->diffInSeconds($now, true),
        ]);

        Realtime::broadcast(new QueueUpdated($pause->unit_id));

        return $pause;
    }

    /** Encerra pausas esquecidas abertas antes do corte (rotina diária). */
    public function closeStale(\DateTimeInterface $before, ?Unit $unit = null): int
    {
        $pauses = AttendantPause::open()
            ->where('started_at', '<', $before)
            ->when($unit, fn ($q) => $q->where('unit_id', $unit->id))
            ->get();

        $pauses->each(fn ($p) => $this->end($p));

        return $pauses->count();
    }
}
