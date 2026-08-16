<?php

use App\Actions\Rbac\TenantUserRoleSynchronizer;
use App\Models\School;
use App\Models\User;
use App\Tenancy\Tenant;
use App\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantRbacSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('edubridge:demo-school {--migrate : Run central and tenant migrations before seeding} {--school-code=alpha} {--tenant-database=}', function (): int {
    if (app()->environment('production')) {
        $this->error('Refusing to run demo-school in production.');

        return 1;
    }
    $schoolCode = (string) $this->option('school-code');
    $tenantDatabase = (string) ($this->option('tenant-database') ?: config('database.connections.tenant.database'));

    if ($tenantDatabase === '') {
        $this->error('Missing tenant database. Pass --tenant-database=... or configure database.connections.tenant.database.');

        return 1;
    }

    Config::set('database.connections.tenant.database', $tenantDatabase);
    DB::purge('tenant');

    if ($this->option('migrate')) {
        $this->call('migrate', ['--database' => 'central', '--force' => true]);
        $this->call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
    }

    $this->call('db:seed', ['--database' => 'tenant', '--class' => TenantRbacSeeder::class, '--force' => true]);

    $password = 'password';
    $users = [
        'admin' => User::query()->firstOrCreate(['email' => 'demo-admin@example.test'], ['name' => 'Demo Admin', 'password' => $password, 'status' => 'active']),
        'teacher' => User::query()->firstOrCreate(['email' => 'demo-teacher@example.test'], ['name' => 'Demo Teacher', 'password' => $password, 'status' => 'active']),
        'parent' => User::query()->firstOrCreate(['email' => 'demo-parent@example.test'], ['name' => 'Demo Parent', 'password' => $password, 'status' => 'active']),
        'student' => User::query()->firstOrCreate(['email' => 'demo-student@example.test'], ['name' => 'Demo Student', 'password' => $password, 'status' => 'active']),
        'transport' => User::query()->firstOrCreate(['email' => 'demo-transport@example.test'], ['name' => 'Demo Transport', 'password' => $password, 'status' => 'active']),
    ];

    $school = School::query()->firstOrCreate(
        ['code' => $schoolCode],
        [
            'public_id' => (string) Str::ulid(),
            'name' => 'Alpha Demo School',
            'timezone' => 'Africa/Cairo',
            'locale' => 'ar',
            'currency' => 'SAR',
            'status' => 'active',
        ],
    );

    DB::connection('central')->table('tenant_connections')->updateOrInsert(
        ['school_id' => $school->id],
        [
            'driver' => config('database.connections.tenant.driver', 'mysql'),
            'database' => $tenantDatabase,
            'host' => config('database.connections.tenant.host'),
            'port' => config('database.connections.tenant.port'),
            'username' => config('database.connections.tenant.username'),
            'secret_ref' => null,
            'options' => null,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    foreach ([
        'admin' => 'school_admin',
        'teacher' => 'teacher',
        'parent' => 'parent',
        'student' => 'student',
        'transport' => 'transport_supervisor',
    ] as $key => $role) {
        DB::connection('central')->table('school_user')->updateOrInsert(
            ['school_id' => $school->id, 'user_id' => $users[$key]->id],
            ['role_key' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );
        app(TenantUserRoleSynchronizer::class)->syncUser((int) $school->id, (int) $users[$key]->id);
    }

    $yearId = upsertTenantRow('academic_years', 'name', '2026-2027', ['starts_on' => '2026-08-03', 'ends_on' => '2027-06-30', 'status' => 'active']);
    $termId = upsertTenantRow('academic_terms', 'name', 'Term 1', ['academic_year_id' => $yearId, 'starts_on' => '2026-08-03', 'ends_on' => '2026-12-20', 'status' => 'active']);
    $gradeLevelId = upsertTenantRow('grade_levels', 'code', 'G01', ['name' => 'Grade 1', 'sort_order' => 1, 'status' => 'active']);
    $sectionId = upsertTenantRow('sections', 'code', 'A', ['grade_level_id' => $gradeLevelId, 'name' => 'A', 'capacity' => 30, 'status' => 'active']);
    $subjectId = upsertTenantRow('subjects', 'code', 'MATH', ['name' => 'Math', 'status' => 'active']);

    DB::connection('tenant')->table('grade_level_subject')->updateOrInsert(
        ['grade_level_id' => $gradeLevelId, 'subject_id' => $subjectId],
        ['created_at' => now(), 'updated_at' => now()],
    );

    $teacherId = upsertTenantRow('teachers', 'employee_number', 'T-DEMO-001', ['central_user_id' => $users['teacher']->id, 'full_name' => 'Demo Teacher', 'email' => $users['teacher']->email, 'status' => 'active']);
    $parentId = upsertTenantRow('parents', 'email', $users['parent']->email, ['central_user_id' => $users['parent']->id, 'full_name' => 'Demo Parent', 'phone' => '+201000000000', 'status' => 'active']);
    $studentId = upsertTenantRow('students', 'admission_number', 'S-DEMO-001', ['central_user_id' => $users['student']->id, 'full_name' => 'Demo Student', 'grade_level_id' => $gradeLevelId, 'section_id' => $sectionId, 'status' => 'active']);

    DB::connection('tenant')->table('student_parent')->updateOrInsert(
        ['student_id' => $studentId, 'parent_id' => $parentId],
        ['relationship' => 'father', 'is_primary' => true, 'can_pickup' => true, 'valid_from' => '2026-08-03', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
    );

    DB::connection('tenant')->table('teacher_section_subject')->updateOrInsert(
        ['academic_term_id' => $termId, 'teacher_id' => $teacherId, 'section_id' => $sectionId, 'subject_id' => $subjectId],
        ['weekly_quota' => 5, 'is_homeroom' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
    );
    $allocationId = (int) DB::connection('tenant')->table('teacher_section_subject')
        ->where('academic_term_id', $termId)
        ->where('teacher_id', $teacherId)
        ->where('section_id', $sectionId)
        ->where('subject_id', $subjectId)
        ->value('id');

    $routeId = upsertTenantRow('bus_routes', 'code', 'DEMO-BUS', ['name' => 'Demo Bus Route', 'capacity' => 40, 'driver_name' => 'Demo Driver', 'status' => 'active']);

    DB::connection('tenant')->table('bus_route_assignments')->updateOrInsert(
        ['bus_route_id' => $routeId, 'student_id' => $studentId],
        ['valid_from' => '2026-08-03', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
    );

    DB::connection('tenant')->table('fees')->updateOrInsert(
        ['student_id' => $studentId, 'title' => 'Demo Meal Top-up'],
        ['amount' => 100, 'currency' => 'SAR', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
    );
    $feeId = (int) DB::connection('tenant')->table('fees')
        ->where('student_id', $studentId)
        ->where('title', 'Demo Meal Top-up')
        ->value('id');

    $this->info('Demo school is ready.');
    $this->line('School code: '.$schoolCode);
    $this->line('Password for all demo users: '.$password);
    $this->line('Admin: demo-admin@example.test');
    $this->line('Teacher: demo-teacher@example.test');
    $this->line('Parent: demo-parent@example.test');
    $this->line('Student: demo-student@example.test');
    $this->line('Transport: demo-transport@example.test');
    $this->line('Seeded IDs: student='.$studentId.', allocation='.$allocationId.', route='.$routeId.', fee='.$feeId);

    return 0;
})->purpose('Create a demo school with users and tenant sample data');

if (! function_exists('assignTenantDemoRole')) {
    function assignTenantDemoRole(int $centralUserId, string $roleKey): void
    {
        $roleId = DB::connection('tenant')->table('roles')->where('key', $roleKey)->value('id');

        if ($roleId === null) {
            return;
        }

        DB::connection('tenant')->table('user_roles')->updateOrInsert(
            ['central_user_id' => $centralUserId, 'role_id' => $roleId],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}

if (! function_exists('upsertTenantRow')) {
    /** @param array<string, mixed> $values */
    function upsertTenantRow(string $table, string $uniqueColumn, string|int $uniqueValue, array $values): int
    {
        DB::connection('tenant')->table($table)->updateOrInsert(
            [$uniqueColumn => $uniqueValue],
            [...$values, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::connection('tenant')->table($table)->where($uniqueColumn, $uniqueValue)->value('id');
    }
}

Artisan::command('edubridge:migrate-tenants', function (): int {
    $tenants = DB::connection('central')
        ->table('tenant_connections')
        ->where('status', 'active')
        ->orderBy('school_id')
        ->get();

    if ($tenants->isEmpty()) {
        $this->warn('No active tenant connections found.');

        return 0;
    }

    $manager = app(TenantConnectionManager::class);

    foreach ($tenants as $row) {
        if (! empty($row->secret_ref)) {
            $this->error(
                "Tenant school_id={$row->school_id} uses secret_ref, ".
                'but secret-based database credentials are not implemented yet.'
            );

            return 1;
        }

        $options = [];

        if (! empty($row->options)) {
            $decoded = json_decode((string) $row->options, true);

            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        $tenant = new Tenant(
            schoolId: (int) $row->school_id,
            driver: (string) $row->driver,
            database: (string) $row->database,
            host: $row->host ?: null,
            port: $row->port !== null ? (int) $row->port : null,
            username: $row->username ?: null,
            secretRef: $row->secret_ref ?: null,
            options: $options,
        );

        $this->info(
            "Migrating tenant school_id={$tenant->schoolId} ".
            "database={$tenant->database}"
        );

        try {
            $manager->run($tenant, function () use ($row): void {
                $migrationExit = $this->call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);

                if ($migrationExit !== 0) {
                    throw new RuntimeException(
                        "Tenant migration failed for school_id={$row->school_id}"
                    );
                }

                $seedExit = $this->call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => TenantRbacSeeder::class,
                    '--force' => true,
                ]);

                if ($seedExit !== 0) {
                    throw new RuntimeException(
                        "Tenant RBAC seed failed for school_id={$row->school_id}"
                    );
                }

                app(TenantUserRoleSynchronizer::class)->syncAllForSchool((int) $row->school_id);
            });

            DB::connection('central')
                ->table('tenant_connections')
                ->where('school_id', $tenant->schoolId)
                ->update([
                    'migrated_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->info("Tenant {$tenant->schoolId}: OK");
        } catch (Throwable $e) {
            $this->error(
                "Tenant {$tenant->schoolId}: FAILED - {$e->getMessage()}"
            );

            return 1;
        }
    }

    $this->info('All active tenants migrated successfully.');

    return 0;
})->purpose('Run production migrations and RBAC updates for all active school tenants');
