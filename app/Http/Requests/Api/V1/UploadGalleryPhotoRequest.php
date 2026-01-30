<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadGalleryPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'A photo is required.',
            'photo.image' => 'The file must be an image.',
            'photo.mimes' => 'The photo must be a JPEG, PNG, GIF, or WebP image.',
            'photo.max' => 'The photo must not exceed 5MB.',
            'caption.max' => 'The caption must not exceed 500 characters.',
        ];
    }
}
