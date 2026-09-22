<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The admin panel's community onboarding, validated by the app's own rules.
 *
 * Same contract as {@see AdminBusinessOnboardingRequest}: extend, never restate. The
 * fields quick-add could not reach are the ones that decide how a community is found
 * — `community_type` (required, from the admin-managed `community_types` table) and
 * `community_size` — so a quick-added community was listed but effectively unsearchable.
 *
 * `verification_channels` is inherited and stays optional. A maintainer onboarding
 * from outreach has no proof links yet; the community supplies them itself later,
 * through the verification flow that exists for exactly that.
 */
final class AdminCommunityOnboardingRequest extends CommunityOnboardingRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('profiles', 'email')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * The parent answers a 422 JSON body, which on an HTML form renders as a blank
     * page with the maintainer's typing gone. Back to Laravel's default — but still
     * `never`, because the parent's signature cannot be widened.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }
}
