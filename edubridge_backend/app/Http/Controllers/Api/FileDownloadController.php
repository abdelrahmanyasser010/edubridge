<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Support\Files\PrivateFileStorage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileDownloadController
{
    public function __invoke(Request $request, string $publicId, PrivateFileStorage $files): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $files->downloadResponse(
            $files->findByPublicId($publicId),
            $user,
        );
    }
}
