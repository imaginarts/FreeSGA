<?php

namespace Tests\Feature;

use App\Livewire\Admin\Messaging;
use App\Livewire\KioskScreen;
use App\Models\Kiosk;
use App\Models\Priority;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\Unit;
use App\Models\User;
use App\Services\MessagingService;
use App\Services\TicketService;
use App\Support\Phone;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsAndSurveyTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $admin;

    private Service $service;

    private TicketService $tickets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->unit = Unit::first();
        $this->admin = User::where('login', 'admin')->first();
        $this->service = Service::where('name', 'Atendimento Geral')->first();
        $this->tickets = app(TicketService::class);

        Http::fake([
            'api.exemplo.com/*' => Http::response(['ok' => true]),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]]),
            'falha.exemplo.com/*' => Http::response('erro', 500),
        ]);
        Setting::put('messaging', [...Setting::get('messaging'), 'provider' => 'http', 'http_url' => 'https://api.exemplo.com/send']);
        $this->configure(['notify_enabled' => true, 'survey_enabled' => true]);
    }

    private function configure(array $settings): void
    {
        $this->unit->update(['settings' => array_replace($this->unit->settings ?? [], $settings)]);
        $this->unit->refresh();
    }

    private function issue(?string $phone = '(11) 98765-4321'): Ticket
    {
        return $this->tickets->issue($this->unit->fresh(), $this->service->id, Priority::where('weight', 0)->first(), $this->admin, notifyPhone: $phone);
    }

    private function sentMessages(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0]->data()['message'] ?? null)->filter()->values()->all();
    }

    // ------------------------------------------------------------ avisos

    public function test_issue_sends_confirmation_with_tracking_link(): void
    {
        $ticket = $this->issue();

        $this->assertSame('5511987654321', $ticket->notify_phone);
        Http::assertSent(fn (Request $r) => $r['phone'] === '5511987654321'
            && str_contains($r['message'], 'A001')
            && str_contains($r['message'], $ticket->trackingUrl()));
        $this->assertSame(1, TicketNotification::where('type', 'issued')->where('status', 'sent')->count());
    }

    public function test_called_and_near_notifications(): void
    {
        $this->configure(['notify_near_threshold' => 2]);
        $this->issue();
        $second = $this->issue();
        $third = $this->issue();
        $fourth = $this->issue();

        $this->tickets->callNext($this->unit->fresh(), $this->admin);

        $messages = $this->sentMessages();
        $this->assertTrue(collect($messages)->contains(fn ($m) => str_contains($m, 'A001 foi chamada') && str_contains($m, 'Guichê 01')));
        $this->assertSame(
            [$second->id, $third->id],
            TicketNotification::where('type', 'near')->orderBy('ticket_id')->pluck('ticket_id')->all(),
        );
        $this->assertFalse(TicketNotification::where('type', 'near')->where('ticket_id', $fourth->id)->exists());

        // nova chamada não repete o aviso para quem já foi avisado
        $current = $this->tickets->currentTicket($this->unit, $this->admin);
        $this->tickets->noShow($current, $this->admin);
        $this->tickets->callNext($this->unit->fresh(), $this->admin);
        $this->assertSame(1, TicketNotification::where('type', 'near')->where('ticket_id', $second->id)->count());
        $this->assertTrue(TicketNotification::where('type', 'near')->where('ticket_id', $fourth->id)->exists());
    }

    public function test_disabled_options_send_nothing(): void
    {
        $this->configure(['notify_on_issue' => false]);
        $this->issue();
        Http::assertNothingSent();

        $this->configure(['notify_on_issue' => true, 'notify_enabled' => false]);
        $this->issue();
        Http::assertNothingSent();
    }

    public function test_tickets_without_phone_send_nothing(): void
    {
        $this->issue(null);
        Http::assertNothingSent();
    }

    public function test_failed_delivery_is_recorded(): void
    {
        Setting::put('messaging', [...Setting::get('messaging'), 'http_url' => 'https://falha.exemplo.com/send']);
        $this->issue();

        $notification = TicketNotification::first();
        $this->assertSame('failed', $notification->status);
        $this->assertStringContainsString('500', $notification->error);
    }

    public function test_whatsapp_cloud_uses_approved_template(): void
    {
        Setting::put('messaging', [
            ...Setting::get('messaging'),
            'provider' => 'whatsapp_cloud',
            'whatsapp_phone_number_id' => '123456',
            'whatsapp_token' => MessagingService::encrypt('segredo'),
            'whatsapp_templates' => ['issued' => 'sga_senha', 'near' => '', 'called' => '', 'survey' => ''],
        ]);

        $this->issue();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com/v21.0/123456/messages')
            && $r->hasHeader('Authorization', 'Bearer segredo')
            && $r['template']['name'] === 'sga_senha'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'A001');
    }

    public function test_archive_erases_phone_numbers(): void
    {
        $ticket = $this->issue();
        $this->tickets->archive($this->unit);

        $this->assertNull($ticket->fresh()->notify_phone);
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('5511987654321', Phone::normalize('(11) 98765-4321'));
        $this->assertSame('5511987654321', Phone::normalize('+55 11 98765-4321'));
        $this->assertNull(Phone::normalize('1234'));
        $this->assertSame('(11) 9****-4321', Phone::masked('5511987654321'));
    }

    public function test_kiosk_asks_phone_when_enabled(): void
    {
        $kiosk = Kiosk::create(['unit_id' => $this->unit->id, 'name' => 'Totem', 'print_mode' => 'none', 'settings' => ['ask_phone' => true, 'show_priority' => false]]);

        Livewire::test(KioskScreen::class, ['kiosk' => $kiosk])
            ->call('begin')
            ->call('chooseService', $this->service->id)
            ->assertSet('step', 'phone')
            ->call('submitPhone', '123')
            ->assertSee('Número inválido')
            ->call('submitPhone', '11987654321')
            ->assertSet('step', 'done');

        $this->assertSame('5511987654321', Ticket::first()->notify_phone);
    }

    // ------------------------------------------------------------ pesquisa

    public function test_finish_sends_survey_and_customer_answers(): void
    {
        $ticket = $this->issue();
        $this->tickets->callNext($this->unit->fresh(), $this->admin);
        $this->tickets->start($ticket->fresh(), $this->admin);
        $this->tickets->finish($ticket->fresh(), $this->admin, [$this->service->id]);

        $this->assertTrue(collect($this->sentMessages())->contains(fn ($m) => str_contains($m, $ticket->surveyUrl())));

        $this->get($ticket->surveyUrl())->assertOk()->assertSee('recomendaria');
        $this->post(route('survey.store', [$ticket, $ticket->surveyToken()]), ['score' => 11])->assertSessionHasErrors('score');
        $this->post(route('survey.store', [$ticket, $ticket->surveyToken()]), ['score' => 9, 'comment' => 'Ótimo!'])->assertRedirect();

        $response = SurveyResponse::first();
        $this->assertSame(9, $response->score);
        $this->assertSame($this->admin->id, $response->user_id);
        $this->get($ticket->surveyUrl())->assertSee('Obrigado pela avaliação');

        // não aceita segunda resposta
        $this->post(route('survey.store', [$ticket, $ticket->surveyToken()]), ['score' => 1])->assertStatus(422);
    }

    public function test_survey_link_on_tracking_page_and_rules(): void
    {
        $this->configure(['notify_enabled' => false]);
        $ticket = $this->issue(null);

        $this->get($ticket->surveyUrl())->assertOk()->assertSee('assim que seu atendimento for encerrado');
        $this->get(route('survey.show', [$ticket, 'invalido']))->assertNotFound();

        $this->tickets->callNext($this->unit->fresh(), $this->admin);
        $this->tickets->start($ticket->fresh(), $this->admin);
        $this->tickets->finish($ticket->fresh(), $this->admin, [$this->service->id]);

        $this->getJson(route('ticket.track.data', [$ticket, $ticket->trackingToken()]))->assertJsonPath('survey_url', $ticket->surveyUrl());

        $this->configure(['survey_on_tracking' => false]);
        $this->getJson(route('ticket.track.data', [$ticket, $ticket->trackingToken()]))->assertJsonPath('survey_url', null);

        $this->configure(['survey_enabled' => false]);
        $this->get($ticket->surveyUrl())->assertNotFound();
    }

    public function test_csat_scale_and_satisfaction_report(): void
    {
        $this->configure(['survey_scale' => 'csat']);
        $ticket = $this->issue(null);
        $this->tickets->callNext($this->unit->fresh(), $this->admin);
        $this->tickets->start($ticket->fresh(), $this->admin);
        $this->tickets->finish($ticket->fresh(), $this->admin, [$this->service->id]);

        $this->post(route('survey.store', [$ticket, $ticket->surveyToken()]), ['score' => 7])->assertSessionHasErrors('score');
        $this->post(route('survey.store', [$ticket, $ticket->surveyToken()]), ['score' => 5, 'comment' => 'Muito bom'])->assertRedirect();

        $this->actingAs($this->admin)->get(route('reports.show', 'satisfaction'))
            ->assertOk()->assertSee('100%')->assertSee('Muito bom');
    }

    // ------------------------------------------------------------ administração

    public function test_admin_messaging_screen_encrypts_token_and_sends_test(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Messaging::class)
            ->set('config.provider', 'whatsapp_cloud')
            ->set('config.whatsapp_phone_number_id', '999')
            ->call('save')
            ->assertHasErrors('whatsappToken')
            ->set('whatsappToken', 'meu-token')
            ->call('save')
            ->assertHasNoErrors();

        $stored = Setting::get('messaging')['whatsapp_token'];
        $this->assertNotSame('meu-token', $stored);
        $this->assertSame('meu-token', decrypt($stored, false));

        Livewire::test(Messaging::class)
            ->set('testPhone', '11987654321')
            ->call('sendTest')
            ->assertDispatched('toast');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/999/messages'));
    }
}
