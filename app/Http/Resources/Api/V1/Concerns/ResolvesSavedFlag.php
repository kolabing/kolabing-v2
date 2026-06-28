<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Concerns;

use App\Models\Kolab;
use Illuminate\Http\Request;

/**
 * Resolves the viewer-scoped `is_saved` flag for a kolab resource. Prefers a
 * query-annotated `is_saved` attribute (set via withExists/loadExists in the
 * list and detail paths, so no N+1) and falls back to a single existence check
 * when the resource is rendered without that annotation (e.g. nested resources).
 */
trait ResolvesSavedFlag
{
    protected function resolveIsSaved(Request $request): bool
    {
        $viewer = $request->user();

        if ($viewer === null) {
            return false;
        }

        $model = $this->resource;

        if (array_key_exists('is_saved', $model->getAttributes())) {
            return (bool) $model->getAttribute('is_saved');
        }

        if (! $model instanceof Kolab) {
            return false;
        }

        return $model->savedByProfiles()
            ->whereKey($viewer->id)
            ->exists();
    }
}
