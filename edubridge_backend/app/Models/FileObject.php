<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'public_id',
    'owner_central_user_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'bytes',
    'checksum_sha256',
    'scan_status',
    'scanned_at',
])]
class FileObject extends Model
{
    public const SCAN_PENDING = 'pending';

    public const SCAN_CLEAN = 'clean';

    public const SCAN_INFECTED = 'infected';

    public const SCAN_FAILED = 'failed';

    protected $connection = 'tenant';

    protected $table = 'files';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_central_user_id' => 'integer',
            'bytes' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }
}
