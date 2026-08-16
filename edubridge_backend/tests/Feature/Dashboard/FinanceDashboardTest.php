<?php

namespace Tests\Feature\Dashboard;

use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $financeUser;

    private User $noRoleUser;

    private User $teacherUser;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralDatabase = $this->sqliteDatabasePath('dashboard-finance-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('dashboard-finance-tenant');
        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

        $this->seedIdentity();
        $this->assignRole($this->financeUser, 'finance_officer');
        $this->assignRole($this->teacherUser, 'teacher');
        $this->seedStudent();
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

    public function test_finance_dashboard_invoice_payment_discount_reports_and_audit_flow(): void
    {
        $token = $this->loginAndReturnToken($this->financeUser, 'dashboard-finance-device');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.total_due', 0)
            ->assertJsonPath('data.currency', 'SAR');

        $invoiceId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/finance/invoices', [
                'student_id' => $this->studentId,
                'issue_date' => '2026-07-01',
                'due_date' => '2026-08-01',
                'currency' => 'SAR',
                'discount' => 500,
                'tax' => 0,
                'lines' => [
                    ['title' => 'Tuition term 1', 'amount' => 3000],
                    ['title' => 'Books', 'amount' => 2000],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.total', 4500)
            ->assertJsonPath('data.remaining', 4500)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.parent_name', 'Finance Parent')
            ->json('data.id');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/finance/invoices?student_id='.$this->studentId.'&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', (string) $invoiceId);

        $paymentId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/finance/payments', [
                'invoice_id' => $invoiceId,
                'amount' => 2000,
                'method' => 'cash',
                'paid_at' => '2026-07-22T10:30:00Z',
                'reference' => 'manual-receipt-1001',
                'notes' => 'Paid at school office',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invoice_id', (string) $invoiceId)
            ->assertJsonPath('data.amount', 2000)
            ->json('data.id');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/finance/invoices/'.$invoiceId)
            ->assertOk()
            ->assertJsonPath('data.paid', 2000)
            ->assertJsonPath('data.remaining', 2500)
            ->assertJsonPath('data.status', 'partial');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/finance/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('data.reference', 'manual-receipt-1001');

        $discountId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/finance/discounts', [
                'student_id' => $this->studentId,
                'title' => 'Sibling discount',
                'amount' => 250,
                'type' => 'fixed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->withBearerToken($token)
            ->patchJson('/api/v1/dashboard/finance/discounts/'.$discountId, ['amount' => 300])
            ->assertOk()
            ->assertJsonPath('data.amount', 300);

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/dashboard/finance/discounts/'.$discountId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/finance/reports/collections')
            ->assertOk()
            ->assertJsonPath('data.0.total', 2000);

        $this->withBearerToken($token)
            ->getJson('/api/v1/dashboard/finance/reports/student-statement/'.$this->studentId)
            ->assertOk()
            ->assertJsonPath('data.summary.remaining', 2500);

        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.invoice.created'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.payment.recorded'], 'tenant');
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.discount.updated'], 'tenant');
    }

    public function test_finance_dashboard_requires_dashboard_token_and_finance_permission(): void
    {
        $this->getJson('/api/v1/dashboard/finance/summary')->assertUnauthorized();

        $noRoleToken = $this->loginAndReturnToken($this->noRoleUser, 'dashboard-no-role-device');
        $this->withBearerToken($noRoleToken)
            ->getJson('/api/v1/dashboard/finance/summary')
            ->assertForbidden();

        $teacherToken = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $this->teacherUser->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => 'teacher-device',
            'device_name' => 'Teacher Phone',
        ])->assertOk()->json('data.token');

        $this->withBearerToken((string) $teacherToken)
            ->getJson('/api/v1/dashboard/finance/summary')
            ->assertForbidden();
    }

    public function test_finance_dashboard_validates_invoice_and_rejects_overpayment(): void
    {
        $token = $this->loginAndReturnToken($this->financeUser, 'dashboard-validation-device');

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/finance/invoices', [
                'student_id' => $this->studentId,
                'issue_date' => '2026-07-01',
                'due_date' => '2026-06-01',
                'lines' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date', 'lines']);

        $invoiceId = $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/finance/invoices', [
                'student_id' => $this->studentId,
                'issue_date' => '2026-07-01',
                'due_date' => '2026-08-01',
                'lines' => [['title' => 'Fee', 'amount' => 100]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withBearerToken($token)
            ->postJson('/api/v1/dashboard/finance/payments', [
                'invoice_id' => $invoiceId,
                'amount' => 150,
                'method' => 'cash',
                'paid_at' => '2026-07-22T10:30:00Z',
            ])
            ->assertConflict();
    }

    private function assignRole(User $user, string $role): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $role)->value('id');

        DB::connection('tenant')->table('user_roles')->insert([
            'central_user_id' => $user->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function loginAndReturnToken(User $user, string $deviceId): string
    {
        $token = $this->postJson('/api/v1/dashboard/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'EduBridge Dashboard Test',
        ])->assertOk()->json('data.token');

        $this->assertIsString($token);

        return $token;
    }

    private function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
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
        Config::set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), [
            'database' => $database,
        ]));
        DB::purge($connection);
    }

    private function seedIdentity(): void
    {
        $this->financeUser = User::query()->create([
            'name' => 'Finance Officer',
            'email' => 'finance@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->noRoleUser = User::query()->create([
            'name' => 'Dashboard Without Tenant Role',
            'email' => 'finance-no-role@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->teacherUser = User::query()->create([
            'name' => 'Teacher App User',
            'email' => 'finance-teacher@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([[$this->financeUser, 'finance_officer'], [$this->noRoleUser, 'school_admin'], [$this->teacherUser, 'teacher']] as [$user, $role]) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role_key' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $school->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabase,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedStudent(): void
    {
        $gradeLevelId = (int) DB::connection('tenant')->table('grade_levels')->insertGetId([
            'name' => 'Grade 1',
            'code' => 'G01',
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionId = (int) DB::connection('tenant')->table('sections')->insertGetId([
            'grade_level_id' => $gradeLevelId,
            'name' => 'A',
            'code' => 'A',
            'capacity' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentId = (int) DB::connection('tenant')->table('parents')->insertGetId([
            'full_name' => 'Finance Parent',
            'phone' => '+201001112223',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = (int) DB::connection('tenant')->table('students')->insertGetId([
            'admission_number' => 'FIN-S-001',
            'full_name' => 'Finance Student',
            'grade_level_id' => $gradeLevelId,
            'section_id' => $sectionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('student_parent')->insert([
            'student_id' => $this->studentId,
            'parent_id' => $parentId,
            'relationship' => 'father',
            'is_primary' => true,
            'can_pickup' => true,
            'valid_from' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
