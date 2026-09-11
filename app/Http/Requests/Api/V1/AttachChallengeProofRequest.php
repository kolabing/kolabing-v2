<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\FileUploadType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The photo two people took while completing a challenge (kolabing-v2#216).
 *
 * Authorization is NOT here: whether the caller is one of the two participants
 * is the service's decision, so the same rule holds for every future caller of
 * attachProofPhoto() and not just this route.
 */
class AttachChallengeProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = FileUploadType::ChallengeProof;

        return [
            'photo' => [
                'required',
                'file',
                'image',
                'mimetypes:'.implode(',', $type->getAllowedMimeTypes()),
                // Laravel's `max` is in kilobytes; the enum is the one place the
                // limit lives, so derive it rather than repeating a number.
                'max:'.(int) ($type->getMaxFileSize() / 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'A photo is required.',
            'photo.image' => 'That file is not an image.',
            'photo.mimetypes' => 'Use a JPEG, PNG, GIF or WebP image.',
            'photo.max' => 'Photos must be 5 MB or smaller.',
        ];
    }
}
