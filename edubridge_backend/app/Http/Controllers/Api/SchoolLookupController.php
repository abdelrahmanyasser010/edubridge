<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SchoolLookupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = $request->input('token');

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
            if (! is_array($payload) || ! isset($payload['school_id'])) {
                return ApiResponse::error('Invalid invitation token.', 'INVALID_TOKEN', 422);
            }

            if (isset($payload['expires_at']) && now()->timestamp > $payload['expires_at']) {
                return ApiResponse::error('Expired invitation token.', 'EXPIRED_TOKEN', 422);
            }

            $schoolId = $payload['school_id'];
        } catch (DecryptException $e) {
            return ApiResponse::error('Invalid invitation token.', 'INVALID_TOKEN', 422);
        }

        $school = School::query()->where('status', 'active')->find($schoolId);
        if (! $school) {
            return ApiResponse::error('School not found or inactive.', 'SCHOOL_NOT_FOUND', 404);
        }

        // Resolve api_base_url from school_domains table
        $domain = DB::connection('central')
            ->table('school_domains')
            ->where('school_id', $school->id)
            ->orderByDesc('is_primary')
            ->first();

        $host = $domain ? $domain->host : ($school->code.'.edubridge.com');
        $scheme = $request->secure() ? 'https://' : 'http://';
        $apiBaseUrl = $scheme.$host;

        return ApiResponse::data([
            'school_name' => $school->name,
            'school_code' => $school->code,
            'api_base_url' => $apiBaseUrl,
        ]);
    }
}
