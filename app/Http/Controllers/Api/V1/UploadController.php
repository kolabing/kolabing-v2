<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Upload a file to storage.
     *
     * POST /api/v1/uploads
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,webp'],
            'folder' => ['required', 'string', 'in:kolabs,events,profiles'],
        ]);

        /** @var Profile $profile */
        $profile = $request->user();

        $path = $request->file('file')->store(
            $request->input('folder').'/'.$profile->id,
            'cloud'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'url' => Storage::disk('cloud')->url($path),
            ],
        ], 201);
    }
}
