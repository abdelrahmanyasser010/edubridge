<?php

namespace App\Support\Files;

use App\Models\FileObject;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PrivateFileStorage
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'text/plain',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @throws ValidationException
     */
    public function storeUploadedFile(UploadedFile $upload, User $owner, string $disk = 'private'): FileObject
    {
        $this->requireActiveTenant();

        $contents = file_get_contents($upload->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file could not be read.'],
            ]);
        }

        $mimeType = $upload->getMimeType() ?: 'application/octet-stream';
        $bytes = strlen($contents);

        $this->validateFile($mimeType, $bytes);

        $publicId = (string) Str::ulid();
        $path = $this->tenantPath($publicId, $upload->getClientOriginalName());

        $this->disk($disk)->put($path, $contents, [
            'visibility' => 'private',
        ]);

        return FileObject::query()->create([
            'public_id' => $publicId,
            'owner_central_user_id' => $owner->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->safeOriginalName($upload->getClientOriginalName()),
            'mime_type' => $mimeType,
            'bytes' => $bytes,
            'checksum_sha256' => hash('sha256', $contents),
            'scan_status' => FileObject::SCAN_PENDING,
        ]);
    }

    public function markScanStatus(FileObject $file, string $status): FileObject
    {
        if (! in_array($status, $this->scanStatuses(), true)) {
            throw ValidationException::withMessages([
                'scan_status' => ['The selected scan status is invalid.'],
            ]);
        }

        $file->forceFill([
            'scan_status' => $status,
            'scanned_at' => $status === FileObject::SCAN_PENDING ? null : now(),
        ])->save();

        return $file->refresh();
    }

    public function findByPublicId(string $publicId): FileObject
    {
        $this->requireActiveTenant();

        return FileObject::query()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    public function temporaryDownloadUrl(FileObject $file, User $user, int $minutes = 5): string
    {
        Gate::forUser($user)->authorize('download', $file);
        $this->ensureDownloadable($file);

        return URL::temporarySignedRoute('api.files.download', now()->addMinutes($minutes), [
            'publicId' => $file->public_id,
        ]);
    }

    public function downloadResponse(FileObject $file, User $user): StreamedResponse
    {
        Gate::forUser($user)->authorize('download', $file);
        $this->ensureDownloadable($file);

        if (! $this->disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        return response()->streamDownload(function () use ($file): void {
            echo $this->disk($file->disk)->get($file->path);
        }, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<string>
     */
    private function scanStatuses(): array
    {
        return [
            FileObject::SCAN_PENDING,
            FileObject::SCAN_CLEAN,
            FileObject::SCAN_INFECTED,
            FileObject::SCAN_FAILED,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function validateFile(string $mimeType, int $bytes): void
    {
        $errors = [];

        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            $errors['file'][] = 'The uploaded file size is not allowed.';
        }

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $errors['file'][] = 'The uploaded file type is not allowed.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureDownloadable(FileObject $file): void
    {
        if ($file->scan_status !== FileObject::SCAN_CLEAN) {
            throw new ConflictHttpException('File is not available for download.');
        }
    }

    private function tenantPath(string $publicId, string $originalName): string
    {
        $extension = pathinfo($this->safeOriginalName($originalName), PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '' : '.'.Str::lower($extension);

        return sprintf('tenants/%d/%s%s', $this->tenantContext->schoolId(), $publicId, $suffix);
    }

    private function safeOriginalName(string $name): string
    {
        $safe = trim(str_replace(['/', '\\'], '-', $name));

        return $safe === '' ? 'file' : Str::limit($safe, 180, '');
    }

    private function disk(string $disk): Filesystem
    {
        return Storage::disk($disk);
    }

    private function requireActiveTenant(): void
    {
        $this->tenantContext->tenant();
    }
}
