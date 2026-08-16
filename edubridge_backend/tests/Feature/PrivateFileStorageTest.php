<?php

namespace Tests\Feature;

use App\Models\FileObject;
use App\Models\School;
use App\Models\User;
use App\Support\Files\PrivateFileStorage;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantConnectionResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PrivateFileStorageTest extends TestCase
{
    private string $centralDatabase;

    private string $tenantDatabase;

    private User $owner;

    private User $otherUser;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->centralDatabase = $this->sqliteDatabasePath('files-central');
        $this->tenantDatabase = $this->sqliteDatabasePath('files-tenant');

        $this->configureSqliteConnection('central', $this->centralDatabase);
        $this->configureSqliteConnection('tenant', $this->tenantDatabase);

        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);

        $this->seedIdentity();
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

    public function test_uploaded_file_is_private_tenant_metadata_with_pending_scan_state(): void
    {
        $this->activateTenant();

        $file = app(PrivateFileStorage::class)->storeUploadedFile(
            UploadedFile::fake()->createWithContent('medical-note.txt', 'hello private file'),
            $this->owner,
        );

        $this->assertSame($this->owner->id, $file->owner_central_user_id);
        $this->assertSame('private', $file->disk);
        $this->assertSame('medical-note.txt', $file->original_name);
        $this->assertSame('text/plain', $file->mime_type);
        $this->assertSame(strlen('hello private file'), $file->bytes);
        $this->assertSame(hash('sha256', 'hello private file'), $file->checksum_sha256);
        $this->assertSame(FileObject::SCAN_PENDING, $file->scan_status);
        $this->assertStringStartsWith('tenants/'.$this->school->id.'/', $file->path);

        Storage::disk('private')->assertExists($file->path);
    }

    public function test_download_url_requires_clean_scan_state(): void
    {
        $this->activateTenant();
        $service = app(PrivateFileStorage::class);
        $file = $service->storeUploadedFile(
            UploadedFile::fake()->createWithContent('note.txt', 'pending scan'),
            $this->owner,
        );

        $this->expectException(ConflictHttpException::class);

        $service->temporaryDownloadUrl($file, $this->owner);
    }

    public function test_owner_can_download_clean_file_from_signed_url(): void
    {
        [$url] = $this->cleanFileDownloadUrl('clean file body', $this->owner);
        $token = $this->loginAndReturnToken($this->owner, 'owner-device');

        $response = $this->withBearerToken($token)
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $this->assertSame('clean file body', $response->streamedContent());
    }

    public function test_signed_download_requires_authentication(): void
    {
        [$url] = $this->cleanFileDownloadUrl('auth required body', $this->owner);

        $this->getJson($url)
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_signed_download_forbids_other_school_member(): void
    {
        [$url] = $this->cleanFileDownloadUrl('not yours', $this->owner);
        $token = $this->loginAndReturnToken($this->otherUser, 'other-device');

        $this->withBearerToken($token)
            ->getJson($url)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    /**
     * @return array{0: string, 1: FileObject}
     */
    private function cleanFileDownloadUrl(string $contents, User $owner): array
    {
        $this->activateTenant();
        $service = app(PrivateFileStorage::class);
        $file = $service->storeUploadedFile(
            UploadedFile::fake()->createWithContent('download.txt', $contents),
            $owner,
        );
        $file = $service->markScanStatus($file, FileObject::SCAN_CLEAN);
        $url = $service->temporaryDownloadUrl($file, $owner);
        app(TenantConnectionManager::class)->disconnect();

        return [$url, $file];
    }

    private function activateTenant(): void
    {
        app(TenantConnectionManager::class)->activate(
            app(TenantConnectionResolver::class)->resolveBySchoolId($this->school->id),
        );
    }

    private function loginAndReturnToken(User $user, string $deviceId): string
    {
        $token = $this->postJson('/api/v1/teacher/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'school_code' => 'alpha',
            'device_id' => $deviceId,
            'device_name' => 'Test Phone',
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
        $this->owner = User::query()->create([
            'name' => 'Owner Teacher',
            'email' => 'owner@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->otherUser = User::query()->create([
            'name' => 'Other Teacher',
            'email' => 'other@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->school = School::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'alpha',
            'name' => 'Alpha School',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        foreach ([$this->owner, $this->otherUser] as $user) {
            DB::connection('central')->table('school_user')->insert([
                'school_id' => $this->school->id,
                'user_id' => $user->id,
                'role_key' => 'teacher',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('tenant_connections')->insert([
            'school_id' => $this->school->id,
            'driver' => 'sqlite',
            'database' => $this->tenantDatabase,
            'status' => 'active',
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
