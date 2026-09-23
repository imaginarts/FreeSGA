<?php

namespace Tests\Feature;

use App\Livewire\Admin\Privacy as PrivacyScreen;
use App\Livewire\Customers;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PanelCall;
use App\Models\Priority;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\PrivacyService;
use App\Services\TicketService;
use App\Support\Privacy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrivacyAndAuditTest extends TestCase
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
        AuditLog::query()->delete(); // ignora os registros do seed
    }

    private function issue(?array $customer = null): Ticket
    {
        return $this->tickets->issue($this->unit, Service::where('name', 'Atendimento Geral')->value('id'), Priority::where('weight', 0)->first(), $this->admin, $customer);
    }

    // ------------------------------------------------------------ auditoria

    public function test_sensitive_ticket_actions_are_audited_with_actor(): void
    {
        $this->actingAs($this->admin);
        $ticket = $this->issue();
        $this->tickets->cancel($ticket);

        $this->assertFalse(AuditLog::where('action', 'ticket.issued')->exists()); // fluxo completo desligado
        $log = AuditLog::where('action', 'ticket.cancelled')->first();
        $this->assertSame('Senha A001 cancelada', $log->description);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->unit->id, $log->unit_id);
        $this->assertSame('Ticket', $log->subject_type);
    }

    public function test_full_ticket_flow_when_enabled(): void
    {
        Setting::put('audit', [...Setting::get('audit'), 'log_ticket_flow' => true]);
        $this->issue();

        $this->assertTrue(AuditLog::where('action', 'ticket.issued')->exists());
    }

    public function test_model_changes_record_before_and_after_without_secrets(): void
    {
        $this->actingAs($this->admin);
        $service = Service::first();
        $service->update(['name' => 'Novo nome']);

        $log = AuditLog::where('action', 'model.updated')->where('subject_type', 'Service')->first();
        $this->assertSame(['Atendimento Geral', 'Novo nome'], $log->changes['name']);

        $this->admin->update(['password' => 'nova-senha-123']);
        $log = AuditLog::where('subject_type', 'User')->latest('id')->first();
        $this->assertSame(['alterado', 'alterado'], $log->changes['password']);
        $this->assertStringNotContainsString('nova-senha', json_encode($log->changes));
    }

    public function test_setting_secrets_are_scrubbed(): void
    {
        Setting::put('messaging', [...Setting::get('messaging'), 'whatsapp_token' => 'segredo-1']);
        Setting::put('messaging', [...Setting::get('messaging'), 'whatsapp_token' => 'segredo-2', 'provider' => 'log']);

        $raw = AuditLog::where('subject_type', 'Setting')->pluck('changes')->toJson();
        $this->assertStringNotContainsString('segredo', $raw);
    }

    public function test_customer_changes_do_not_store_personal_values(): void
    {
        $customer = Customer::create(['name' => 'Maria Silva', 'document' => '52998224725']);
        $customer->update(['name' => 'Maria Souza']);

        $log = AuditLog::where('subject_type', 'Customer')->where('action', 'model.updated')->first();
        $this->assertSame(['alterado', 'alterado'], $log->changes['name']);
        $this->assertStringNotContainsString('Souza', json_encode($log->changes));
    }

    public function test_noise_fields_are_ignored(): void
    {
        $this->issue(); // incrementa next_number do serviço na unidade

        $this->assertFalse(AuditLog::where('subject_type', 'UnitService')->where('action', 'model.updated')->exists());
    }

    public function test_logins_are_audited(): void
    {
        $this->post(route('login'), ['login' => 'admin', 'password' => 'errada']);
        $this->post(route('login'), ['login' => 'admin', 'password' => '123456']);

        $this->assertTrue(AuditLog::where('action', 'auth.failed')->exists());
        $this->assertSame($this->admin->id, AuditLog::where('action', 'auth.login')->value('user_id'));
    }

    public function test_audit_can_be_disabled(): void
    {
        Setting::put('audit', [...Setting::get('audit'), 'enabled' => false]);
        AuditLog::query()->delete();

        $this->tickets->cancel($this->issue());
        Service::first()->update(['name' => 'X']);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_screen_filters_and_exports_csv(): void
    {
        $this->actingAs($this->admin);
        $this->tickets->cancel($this->issue());
        Service::first()->update(['name' => 'Renomeado']);

        Livewire::test(PrivacyScreen::class)
            ->assertSee('Senha A001 cancelada')
            ->set('action', 'ticket.*')
            ->assertSee('Senha A001 cancelada')
            ->assertDontSee('Renomeado')
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    // ------------------------------------------------------------ LGPD

    public function test_document_masking_follows_setting(): void
    {
        $this->assertSame('***.982.247-**', Privacy::document('529.982.247-25'));

        Setting::put('privacy', [...Setting::get('privacy'), 'mask_document' => false]);
        $this->assertSame('529.982.247-25', Privacy::document('529.982.247-25'));
    }

    public function test_panel_shows_name_according_to_policy_and_never_document(): void
    {
        $this->issue(['name' => 'Maria da Silva', 'document' => '52998224725']);
        $this->tickets->callNext($this->unit, $this->admin);

        $call = PanelCall::first();
        $this->assertSame('Maria', $call->customer_name);
        $this->assertNull($call->customer_document);

        Setting::put('privacy', [...Setting::get('privacy'), 'panel_customer_name' => 'hidden']);
        $this->tickets->recall($this->tickets->currentTicket($this->unit, $this->admin), $this->admin);
        $this->assertNull(PanelCall::latest('id')->first()->customer_name);
    }

    public function test_export_and_anonymize_customer(): void
    {
        $this->actingAs($this->admin);
        $ticket = $this->issue(['name' => 'Maria da Silva', 'document' => '52998224725', 'phone' => '11999998888']);
        $customer = $ticket->customer;

        $data = app(PrivacyService::class)->export($customer);
        $this->assertSame('Maria da Silva', $data['cliente']['name']);
        $this->assertCount(1, $data['atendimentos']);

        Livewire::test(Customers::class)->call('exportData', $customer->id)->assertFileDownloaded("dados-cliente-{$customer->id}.json");
        Livewire::test(Customers::class)->call('anonymize', $customer->id);

        $customer->refresh();
        $this->assertSame('Cliente anonimizado', $customer->name);
        $this->assertSame('ANON-'.$customer->id, $customer->document);
        $this->assertNull($customer->phone);
        $this->assertTrue(Ticket::whereKey($ticket->id)->exists()); // estatística mantida
        $this->assertTrue(AuditLog::where('action', 'privacy.anonymized')->exists());
        $this->assertTrue(AuditLog::where('action', 'privacy.export')->exists());
    }

    public function test_retention_cleanup_anonymizes_inactive_customers_only_when_enabled(): void
    {
        $old = Customer::create(['name' => 'Antigo', 'document' => '111']);
        $old->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();
        $recent = Customer::create(['name' => 'Recente', 'document' => '222']);

        app(PrivacyService::class)->cleanup();
        $this->assertSame('Antigo', $old->fresh()->name); // desligado por padrão

        Setting::put('privacy', [...Setting::get('privacy'), 'retention_enabled' => true, 'retention_months' => 24]);
        $result = app(PrivacyService::class)->cleanup();

        $this->assertSame(1, $result['customers']);
        $this->assertSame('Cliente anonimizado', $old->fresh()->name);
        $this->assertSame('Recente', $recent->fresh()->name);
    }

    public function test_old_audit_logs_are_purged(): void
    {
        AuditLog::create(['action' => 'auth.login', 'description' => 'antigo', 'created_at' => now()->subDays(400)]);
        AuditLog::create(['action' => 'auth.login', 'description' => 'novo']);

        app(PrivacyService::class)->cleanup();

        $this->assertFalse(AuditLog::where('description', 'antigo')->exists());
        $this->assertTrue(AuditLog::where('description', 'novo')->exists());
    }

    public function test_privacy_settings_screen_saves(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PrivacyScreen::class)
            ->set('tab', 'privacy')
            ->set('privacy.panel_customer_name', 'hidden')
            ->call('savePrivacy')
            ->assertHasNoErrors()
            ->set('audit.retention_days', 10)
            ->call('saveAudit')
            ->assertHasErrors('audit.retention_days');

        $this->assertSame('hidden', Setting::get('privacy')['panel_customer_name']);
    }
}
