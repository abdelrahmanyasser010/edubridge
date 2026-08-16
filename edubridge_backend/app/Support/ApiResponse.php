<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;

final class ApiResponse
{
    public const REQUEST_ID_ATTRIBUTE = 'request_id';

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function data(mixed $data, int $status = 200, array $meta = [], array $headers = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge(['request_id' => self::requestId()], $meta),
        ], $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $headers
     */
    public static function error(string $message, string $code, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => $errors === [] ? new stdClass : $errors,
            'meta' => ['request_id' => self::requestId()],
        ], $status, $headers);
    }

    public static function requestId(?Request $request = null): string
    {
        $request ??= request();
        $requestId = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $requestId = (string) Str::ulid();
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);

        return $requestId;
    }
}
