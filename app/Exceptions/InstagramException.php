<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Every Instagram Connect failure the app has to tell apart (BE-NF-75). The
 * `code` is a stable wire contract for kolabing-app; `reason` is the short form
 * the OAuth callback puts on the redirect (`?status=error&reason=…`).
 */
class InstagramException extends RuntimeException
{
    public const UNAVAILABLE = 'INSTAGRAM_UNAVAILABLE';

    public const NOT_CONNECTED = 'INSTAGRAM_NOT_CONNECTED';

    public const TOKEN_EXPIRED = 'INSTAGRAM_TOKEN_EXPIRED';

    public const PERSONAL_ACCOUNT = 'INSTAGRAM_PERSONAL_ACCOUNT';

    public const PERMISSION_DENIED = 'INSTAGRAM_PERMISSION_DENIED';

    public const API_ERROR = 'INSTAGRAM_API_ERROR';

    public const GALLERY_FULL = 'INSTAGRAM_GALLERY_FULL';

    public const AVATAR_ALREADY_SET = 'INSTAGRAM_AVATAR_ALREADY_SET';

    private const STATUS = [
        self::UNAVAILABLE => 404,
        self::NOT_CONNECTED => 409,
        self::TOKEN_EXPIRED => 409,
        self::PERSONAL_ACCOUNT => 422,
        self::PERMISSION_DENIED => 403,
        self::API_ERROR => 502,
        self::GALLERY_FULL => 422,
        self::AVATAR_ALREADY_SET => 409,
    ];

    private const REASON = [
        self::TOKEN_EXPIRED => 'token_expired',
        self::PERSONAL_ACCOUNT => 'personal_account',
        self::PERMISSION_DENIED => 'permission_denied',
    ];

    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public function status(): int
    {
        return self::STATUS[$this->errorCode] ?? 500;
    }

    public function reason(): string
    {
        return self::REASON[$this->errorCode] ?? 'error';
    }

    public function render(Request $request): JsonResponse
    {
        $message = match ($this->errorCode) {
            self::UNAVAILABLE => __('Resource not found'),
            self::NOT_CONNECTED => __('Connect your Instagram account first.'),
            self::TOKEN_EXPIRED => __('Your Instagram connection expired. Please connect it again.'),
            self::PERSONAL_ACCOUNT => __('Only Instagram professional accounts (Business or Creator) can connect. In Instagram: Settings → Account type and tools → Switch to professional account.'),
            self::PERMISSION_DENIED => __('Instagram did not grant access to your profile and media.'),
            self::GALLERY_FULL => $this->getMessage(),
            self::AVATAR_ALREADY_SET => __('Your profile already has a photo.'),
            default => __('Instagram is not responding right now. Please try again later.'),
        };

        return response()->json([
            'success' => false,
            'code' => $this->errorCode,
            'message' => $message,
        ], $this->status());
    }
}
