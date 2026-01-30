<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileUploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadGalleryPhotoRequest;
use App\Http\Resources\Api\V1\GalleryPhotoResource;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    private const int MAX_GALLERY_PHOTOS = 10;

    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * List gallery photos for the authenticated user.
     *
     * GET /api/v1/me/gallery
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $photos = $profile->galleryPhotos()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => GalleryPhotoResource::collection($photos),
        ]);
    }

    /**
     * Upload a gallery photo.
     *
     * POST /api/v1/me/gallery
     */
    public function store(UploadGalleryPhotoRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $currentCount = $profile->galleryPhotos()->count();

        if ($currentCount >= self::MAX_GALLERY_PHOTOS) {
            return response()->json([
                'success' => false,
                'message' => __('You can upload a maximum of :max gallery photos.', ['max' => self::MAX_GALLERY_PHOTOS]),
            ], 422);
        }

        $url = $this->fileUploadService->uploadFromFile(
            $request->file('photo'),
            FileUploadType::GalleryPhoto,
            $profile->id
        );

        $photo = ProfileGalleryPhoto::query()->create([
            'profile_id' => $profile->id,
            'url' => $url,
            'caption' => $request->validated('caption'),
            'sort_order' => $currentCount,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Photo uploaded successfully.'),
            'data' => new GalleryPhotoResource($photo),
        ], 201);
    }

    /**
     * Delete a gallery photo.
     *
     * DELETE /api/v1/me/gallery/{photo}
     */
    public function destroy(Request $request, ProfileGalleryPhoto $photo): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($photo->profile_id !== $profile->id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this photo.'),
            ], 403);
        }

        $this->fileUploadService->delete($photo->url);
        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => __('Photo deleted successfully.'),
        ]);
    }

    /**
     * View gallery photos for a specific profile.
     *
     * GET /api/v1/profiles/{profile}/gallery
     */
    public function show(Profile $profile): JsonResponse
    {
        $photos = $profile->galleryPhotos()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => GalleryPhotoResource::collection($photos),
        ]);
    }
}
