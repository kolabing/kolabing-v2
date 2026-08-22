<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileUploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReorderGalleryRequest;
use App\Http\Requests\Api\V1\UpdateGalleryPhotoRequest;
use App\Http\Requests\Api\V1\UploadGalleryPhotoRequest;
use App\Http\Resources\Api\V1\GalleryPhotoResource;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use App\Services\FileUploadService;
use App\Services\PhotoOrderingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    private const int MAX_GALLERY_PHOTOS = 20;

    private const int MAX_PER_REQUEST = 5;

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

        // Accept either the legacy single `photo` or a `photos[]` batch (up to 5).
        $batch = $request->file('photos');
        $files = is_array($batch) ? array_values($batch) : [$request->file('photo')];
        $isBatch = is_array($batch);

        $currentCount = $profile->galleryPhotos()->count();

        if ($currentCount + count($files) > self::MAX_GALLERY_PHOTOS) {
            return response()->json([
                'success' => false,
                'message' => __('You can upload a maximum of :max gallery photos.', ['max' => self::MAX_GALLERY_PHOTOS]),
            ], 422);
        }

        // caption only applies to the single-photo legacy shape.
        $caption = $isBatch ? null : $request->validated('caption');

        $created = [];
        foreach ($files as $i => $file) {
            $url = $this->fileUploadService->uploadFromFile(
                $file,
                FileUploadType::GalleryPhoto,
                $profile->id
            );

            $created[] = ProfileGalleryPhoto::query()->create([
                'profile_id' => $profile->id,
                'url' => $url,
                'caption' => $caption,
                'sort_order' => $currentCount + $i,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Photo uploaded successfully.'),
            // Single-photo requests keep the legacy object shape; batch returns a list.
            'data' => $isBatch
                ? GalleryPhotoResource::collection($created)
                : new GalleryPhotoResource($created[0]),
        ], 201);
    }

    /**
     * PATCH /api/v1/me/gallery/{photo} — edit a caption.
     */
    public function update(UpdateGalleryPhotoRequest $request, ProfileGalleryPhoto $photo): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($photo->profile_id !== $profile->id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to edit this photo.'),
            ], 403);
        }

        $photo->update(['caption' => $request->validated('caption')]);

        return response()->json([
            'success' => true,
            'data' => new GalleryPhotoResource($photo->fresh()),
        ]);
    }

    /**
     * PUT /api/v1/me/gallery/order — set the display order.
     *
     * Returns the caller's FULL ordered gallery so the client never has to infer
     * where the omitted photos landed.
     */
    public function reorder(ReorderGalleryRequest $request, PhotoOrderingService $ordering): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $owned = $profile->galleryPhotos()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->pluck('id')
            ->all();

        $ordered = $ordering->resolve($request->validated('ids'), $owned);

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $id) {
                ProfileGalleryPhoto::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => GalleryPhotoResource::collection(
                $profile->galleryPhotos()->orderBy('sort_order')->orderByDesc('created_at')->get()
            ),
        ]);
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
