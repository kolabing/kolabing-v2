<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileUploadType;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Upload a file to storage.
     *
     * POST /api/v1/uploads
     */
    public function store(Request $request): JsonResponse
    {
        $folder = (string) $request->input('folder');
        $isKolabUpload = $folder === 'kolabs';

        $request->validate([
            'file' => array_merge(
                ['required', 'file'],
                $isKolabUpload
                    ? ['max:51200', 'mimetypes:image/jpeg,image/jpg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm']
                    : ['max:5120', 'mimes:jpeg,jpg,png,gif,webp']
            ),
            'folder' => ['required', 'string', 'in:kolabs,events,profiles'],
        ]);

        /** @var Profile $profile */
        $profile = $request->user();

        $file = $request->file('file');
        $uploadType = match ($folder) {
            'kolabs' => FileUploadType::KolabMedia,
            'events' => FileUploadType::EventPhoto,
            'profiles' => FileUploadType::ProfilePhoto,
        };
        $url = $this->fileUploadService->uploadFromFile(
            $file,
            $uploadType,
            $profile->id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'type' => $this->fileUploadService->inferMediaType($file),
                'thumbnail_url' => null,
            ],
        ], 201);
    }
}
