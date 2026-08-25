<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Same rules as store; the slug-uniqueness rule ignores the bound post's id.
 */
class UpdateBlogPostRequest extends StoreBlogPostRequest {}
