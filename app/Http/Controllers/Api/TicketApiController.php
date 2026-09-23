<?php

namespace App\Http\Controllers\Api;

use App\Enums\Resolution;
use App\Exceptions\TicketException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PanelCall;
use App\Models\Priority;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitService;
use App\Services\QueueService;
use App\Services\TicketService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketApiController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function status(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'time' => now()->toIso8601String(), 'version' => 'v1']);
    }

    public function units(Request $request): JsonResponse
    {
        return response()->json($request->user()->availableUnits()->map->only(['id', 'name', 'description', 'timezone']));
    }

    public function unitServices(Request $request, Unit $unit): JsonResponse
    {
        $this->authorizeUnit($request, $unit);

        return response()->json(UnitService::with('service')->where('unit_id', $unit->id)->active()->get()->map(fn ($us) => [
            'service_id' => $us->service_id,
            'name' => $us->service->name,
            'prefix' => $us->prefix,
            'type' => $us->type->value,
            'next_number' => $us->next_number,
        ])->sortBy('name')->values());
    }

    public function priorities(): JsonResponse
    {
        return response()->json(Priority::active()->orderBy('weight')->get(['id', 'name', 'description', 'weight', 'color']));
    }

    public function queue(Request $request, Unit $unit, QueueService $queue): JsonResponse
    {
        $this->authorizeUnit($request, $unit);

        return response()->json($queue->unitQueue($unit, $request->integer('service_id') ?: null)->map->toApiArray());
    }

    public function panel(Request $request, Unit $unit): JsonResponse
    {
        $services = array_filter(array_map('intval', explode(',', (string) $request->query('services'))));

        return response()->json(PanelCall::with('service')
            ->where('unit_id', $unit->id)
            ->when($services, fn ($q) => $q->whereIn('service_id', $services))
            ->latest('id')->limit(10)->get()->map->toPanelArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'service_id' => ['required', 'integer'],
            'priority_id' => ['required', 'exists:priorities,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'customer.name' => ['nullable', 'string', 'max:60'],
            'customer.document' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email'],
            'customer.phone' => ['nullable', 'string', 'max:25'],
            'meta' => ['nullable', 'array'],
            'notify_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $this->authorizeUnit($request, $unit);

        return $this->run(fn () => $this->tickets->issue(
            $unit,
            (int) $data['service_id'],
            Priority::findOrFail($data['priority_id']),
            $request->user(),
            $data['customer'] ?? null,
            isset($data['appointment_id']) ? Appointment::find($data['appointment_id']) : null,
            $data['meta'] ?? null,
            notifyPhone: $data['notify_phone'] ?? null,
        ), 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeUnit($request, $ticket->unit);

        return response()->json($ticket->toApiArray());
    }

    public function action(Request $request, Ticket $ticket, string $action): JsonResponse
    {
        $this->authorizeUnit($request, $ticket->unit);
        $user = $request->user();

        return match ($action) {
            'call' => $this->run(fn () => $this->tickets->callTicket($ticket, $user)),
            'recall' => $this->run(fn () => $this->tickets->recall($ticket, $user)),
            'start' => $this->run(fn () => $this->tickets->start($ticket, $user)),
            'no-show' => $this->run(fn () => $this->tickets->noShow($ticket, $user)),
            'cancel' => $this->run(fn () => $this->tickets->cancel($ticket)),
            'reactivate' => $this->run(fn () => $this->tickets->reactivate($ticket)),
            'finish' => $this->finish($request, $ticket),
            'redirect' => $this->redirect($request, $ticket),
            'transfer' => $this->transfer($request, $ticket),
            default => abort(404),
        };
    }

    public function callNext(Request $request, Unit $unit): JsonResponse
    {
        $this->authorizeUnit($request, $unit);

        return $this->run(fn () => $this->tickets->callNext($unit, $request->user(), $request->integer('service_id') ?: null));
    }

    private function finish(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer'],
            'resolution' => ['nullable', Rule::enum(Resolution::class)],
            'notes' => ['nullable', 'string'],
            'redirect_service_id' => ['nullable', 'integer'],
            'redirect_user_id' => ['nullable', 'exists:users,id'],
        ]);

        return $this->run(fn () => $this->tickets->finish(
            $ticket, $request->user(), $data['services'],
            isset($data['resolution']) ? Resolution::from($data['resolution']) : null,
            $data['notes'] ?? null,
            $data['redirect_service_id'] ?? null,
            $data['redirect_user_id'] ?? null,
        ));
    }

    private function redirect(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        return $this->run(fn () => $this->tickets->redirect($ticket, $request->user(), $data['service_id'], $data['user_id'] ?? null), 201);
    }

    private function transfer(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'priority_id' => ['required', 'exists:priorities,id'],
        ]);

        return $this->run(fn () => $this->tickets->transfer($ticket, $data['service_id'], Priority::findOrFail($data['priority_id'])));
    }

    private function run(Closure $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action()->fresh()->toApiArray(), $status);
        } catch (TicketException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function authorizeUnit(Request $request, Unit $unit): void
    {
        abort_unless($request->user()->availableUnits()->contains('id', $unit->id), 403, 'Sem acesso a esta unidade.');
    }
}
