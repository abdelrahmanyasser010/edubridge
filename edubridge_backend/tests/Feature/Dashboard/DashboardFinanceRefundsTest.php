<?php

namespace Tests\Feature\Dashboard;

use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardFinanceRefundsTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $financeUser;

    private User $academicUser;

    private int $invoiceId;

    private int $paymentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-finance-refunds-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-finance-refunds-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->financeUser, 'finance_officer');
        $this->assignRole($this->academicUser, 'academic_admin');
        $this->seedFinancePayment();
    }

    protected function tearDown(): void
    {
        app(TenantConnectionManager::class)->disconnect();
        DB::disconnect('central');
        DB::purge('central');
        gc_collect_cycles();

        foreach ([$this->centralDatabase, $this->tenantDatabase] as $database) {
            if (is_file($database)) {
                unlink($database);
            }
        }

        parent::tearDown();
    }

    public function test_dashboard_finance_can_create_and_list_refunds(): void
    {
        $this->postJson('/api/v1/dashboard/finance/payments/'.$this->paymentId.'/refunds', [])->assertUnauthorized();

        $academicToken = $this->loginAndReturnToken($this->academicUser, 'dashboard-finance-refunds-academic');
        $this->withBearerToken($academicToken)
            ->postJson('/api/v1/dashboard/finance/payments/'.$this->paymentId.'/refunds', [
                'amount' => 250,
                'reason' => 'No permission',
            ])
            ->assertForbidden();

        $financeToken = $this->loginAndReturnToken($this->financeUser, 'dashboard-finance-refunds-finance');
        $this->withBearerToken($financeToken)
            ->postJson('/api/v1/dashboard/finance/payments/'.$this->paymentId.'/refunds', [
                'amount' => 0,
                'reason' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'reason']);

        $refundId = $this->withBearerToken($financeToken)
            ->postJson('/api/v1/dashboard/finance/payments/'.$this->paymentId.'/refunds', [
                'amount' => 250,
                'reason' => 'Parent refund request',
                'reference' => 'refund-reference-1001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_id', (string) $this->paymentId)
            ->assertJsonPath('data.invoice_id', (string) $this->invoiceId)
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.status', 'completed')
            ->json('data.id');

        $this->assertIsString($refundId);
        $this->assertDatabaseHas('finance_invoices', ['id' => $this->invoiceId, 'paid_total' => 750, 'status' => 'partial'], 'tenant');

        $this->withBearerToken($financeToken)
            ->postJson('/api/v1/dashboard/finance/payments/'.$this->paymentId.'/refunds', [
                'amount' => 250,
                'reason' => 'Duplicate reference should replay',
                'reference' => 'refund-reference-1001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $refundId);

        $this->assertDatabaseHas('finance_invoices', ['id' => $this->invoiceId, 'paid_total' => 750], 'tenant');

        $this->withBearerToken($financeToken)
            ->postJson('/api/v1/dashboard/finance/payments/'.$this->paymentId.'/refunds', [
                'amount' => 800,
                'reason' => 'Too much',
            ])
            ->assertConflict();

        $this->withBearerToken($financeToken)
            ->getJson('/api/v1/dashboard/finance/refunds?payment_id='.$this->paymentId)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $refundId)
            ->assertJsonPath('data.0.reference', 'refund-reference-1001');

        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.refund.created'], 'tenant');
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');
        DB::connection('tenant')->table('user_roles')->insert(['central_user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loginAndReturnToken(User $user, string $deviceId): string
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Dashboard',
        ])->assertOk()->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->withServerVariables(['HTTP_AUTHORIZATION' => 'Bearer '.$token])->withHeader('Authorization', 'Bearer '.$token);
    }

    private function sqliteDatabasePath(string $name): string
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.$name.'-'.Str::ulid().'.sqlite';
        touch($path);

        return $path;
    }

    private function configureSqliteConnection(string $connection, string $database): void
    {
        Config::set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), ['database' => $database]));
        DB::purge($connection);
    }

    private function seedIdentity(): void
    {
        $this->financeUser = User::query()->create(['name' => 'Finance', 'email' => 'dashboard-finance-refunds@example.test', 'password' => 'secret-password', 'status' => 'active']);
        $this->academicUser = User::query()->create(['name' => 'Academic', 'email' => 'dashboard-finance-refunds-academic@example.test', 'password' => 'secret-password', 'status' => 'active']);

        $school = School::query()->create(['public_id' => (string) Str::ulid(), 'code' => 'alpha', 'name' => 'Alpha School', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'SAR', 'status' => 'active']);
        foreach ([[$this->financeUser, 'finance_officer'], [$this->academicUser, 'academic_admin']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert(['school_id' => $school->id, 'user_id' => $user->id, 'role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::connection('central')->table('tenant_connections')->insert(['school_id' => $school->id, 'driver' => 'sqlite', 'database' => $this->tenantDatabase, 'status' => 'active', 'migrated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedFinancePayment(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId(['name' => 'Grade 1', 'code' => 'G01', 'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId(['grade_level_id' => $gradeLevelId, 'name' => 'A', 'code' => 'A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $studentId = (int) DB::connection('tenant')->table('students')->insertGetId(['admission_number' => 'S-FIN-REF-001', 'full_name' => 'Finance Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->invoiceId = (int) DB::connection('tenant')->table('finance_invoices')->insertGetId([
            'invoice_number' => 'INV-REF-001',
            'student_id' => $studentId,
            'issue_date' => '2026-07-01',
            'due_date' => '2026-08-01',
            'subtotal' => 1000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 1000,
            'paid_total' => 1000,
            'status' => 'paid',
            'currency' => 'SAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->paymentId = (int) DB::connection('tenant')->table('finance_payments')->insertGetId([
            'finance_invoice_id' => $this->invoiceId,
            'amount' => 1000,
            'method' => 'cash',
            'paid_at' => now(),
            'reference' => 'payment-reference-1001',
            'recorded_by_central_user_id' => $this->financeUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
