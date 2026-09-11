<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBusinessVisibilityBoostRequest extends FormRequest
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
        return [
            'trusted_partner_points' => ['required', 'integer', 'between:0,50'],
            'community_favourite_points' => ['required', 'integer', 'between:0,50'],
        ];
    }

    /**
     * A higher tier should never carry a smaller boost than a lower one —
     * that would invert the "status is worth more the higher you go" promise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $trusted = (int) $this->input('trusted_partner_points');
            $favourite = (int) $this->input('community_favourite_points');

            if ($favourite < $trusted) {
                $validator->errors()->add(
                    'community_favourite_points',
                    'Community Favourite boost must be at least as large as the Trusted Partner boost.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trusted_partner_points.between' => 'Trusted Partner boost must be between 0 and 50 points.',
            'community_favourite_points.between' => 'Community Favourite boost must be between 0 and 50 points.',
        ];
    }
}
