<?php

namespace App\Support;

use App\Support\Exceptions\AppAccessDeniedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($exception instanceof AppAccessDeniedException) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                code: 'APP_ACCESS_DENIED',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                code: 'VALIDATION_FAILED',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: $exception->errors(),
            );
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::error(
                message: 'Unauthenticated.',
                code: 'UNAUTHENTICATED',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($exception instanceof AuthorizationException) {
            return ApiResponse::error(
                message: $exception->getMessage() ?: 'This action is unauthorized.',
                code: 'FORBIDDEN',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($exception instanceof NotFoundHttpException) {
            return ApiResponse::error(
                message: 'The requested resource was not found.',
                code: 'NOT_FOUND',
                status: Response::HTTP_NOT_FOUND,
                headers: $exception->getHeaders(),
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return ApiResponse::error(
                message: Response::$statusTexts[$status] ?? 'HTTP error.',
                code: self::codeForStatus($status),
                status: $status,
                headers: $exception->getHeaders(),
            );
        }

        return ApiResponse::error(
            message: 'Server Error',
            code: 'SERVER_ERROR',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'BAD_REQUEST',
            Response::HTTP_UNAUTHORIZED => 'UNAUTHENTICATED',
            Response::HTTP_FORBIDDEN => 'FORBIDDEN',
            Response::HTTP_NOT_FOUND => 'NOT_FOUND',
            Response::HTTP_METHOD_NOT_ALLOWED => 'METHOD_NOT_ALLOWED',
            Response::HTTP_CONFLICT => 'CONFLICT',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'VALIDATION_FAILED',
            Response::HTTP_TOO_MANY_REQUESTS => 'RATE_LIMITED',
            default => 'HTTP_ERROR',
        };
    }
}
