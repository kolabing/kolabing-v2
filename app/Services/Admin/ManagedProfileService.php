<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Mail\AdminProfileWelcomeMail;
use App\Models\AdminWelcomeEmailTemplate;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

class ManagedProfileService
{
    /**
     * Listing-first quick add (#kolabing quick-add): a maintainer lists a business/community
     * sourced from outreach (e.g. an Instagram reply) before its owner has ever touched the
     * app. Creates a real Profile with a random, never-shown password.
     *
     * Does NOT send the welcome email — Daniel 2026-09-14: outreach happens across languages
     * (English/Spanish/Catalan/etc.), so sending is a deliberate follow-up step where a
     * maintainer picks the right language, not an automatic side effect of creating the
     * listing. See sendWelcomeEmail().
     *
     * @param  array<string, mixed>  $data
     */
    public function quickAdd(array $data): Profile
    {
        return DB::transaction(function () use ($data): Profile {
            $userType = UserType::from((string) $data['user_type']);

            $profile = Profile::query()->create([
                'email' => $data['email'],
                'password' => Str::random(32),
                'phone_number' => ($data['phone_number'] ?? null) ?: null,
                'user_type' => $userType,
                'email_verified_at' => now(),
            ]);

            $this->upsertDetailProfile($profile, $data);
            $this->applyCityId($profile, $data);

            if ($profile->isBusiness()) {
                BusinessSubscription::query()->firstOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'source' => SubscriptionSource::AppleIap,
                        'status' => SubscriptionStatus::Inactive,
                    ]
                );
            }

            return $profile->fresh(['businessProfile', 'communityProfile']);
        });
    }

    /**
     * Read-only render of exactly what sendWelcomeEmail() would send — Daniel 2026-09-14:
     * "i don't want them to get an unapproved email". Nothing is queued, no token is
     * minted (a placeholder stands in for the real link so a maintainer isn't tempted to
     * treat the preview link as a working one). A maintainer must see this before the
     * "Send" route (ManagedUserController::sendWelcomeEmail) is reachable in the UI.
     */
    public function previewWelcomeEmail(Profile $profile, string $locale): string
    {
        $template = $this->resolveActiveTemplate($locale);

        return (new AdminProfileWelcomeMail($profile, 'preview-only-not-a-real-link', $template))
            ->locale($locale)
            ->render();
    }

    /**
     * Manual follow-up to quickAdd(), reached only after previewWelcomeEmail() has been
     * shown and explicitly confirmed — a maintainer picks the language once they know how
     * the outreach conversation was conducted. Mints a fresh reset token every call (safe
     * to send more than once; the previous token, if unused, still works too —
     * Password::broker() doesn't invalidate prior tokens on a new one).
     */
    public function sendWelcomeEmail(Profile $profile, string $locale): void
    {
        $template = $this->resolveActiveTemplate($locale);

        $token = Password::broker()->createToken($profile);

        // ->locale() matters here: the two fixed action-button labels are translated via
        // lang/{locale}/admin_mail.php (see the Blade view), not stored on the template row.
        // Without this, they'd always render in whatever the app's current default locale
        // is, regardless of which language the maintainer picked.
        Mail::to($profile->email)
            ->locale($locale)
            ->queue(new AdminProfileWelcomeMail($profile, $token, $template));
    }

    private function resolveActiveTemplate(string $locale): AdminWelcomeEmailTemplate
    {
        $template = AdminWelcomeEmailTemplate::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw new InvalidArgumentException("No active welcome email template for locale [{$locale}].");
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Profile
    {
        return DB::transaction(function () use ($data): Profile {
            $userType = UserType::from((string) $data['user_type']);

            $profile = Profile::query()->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'phone_number' => ($data['phone_number'] ?? null) ?: null,
                'user_type' => $userType,
                'email_verified_at' => $this->resolveVerifiedAt($data),
            ]);

            $this->upsertDetailProfile($profile, $data);
            $this->applyCityId($profile, $data);

            if ($profile->isBusiness()) {
                BusinessSubscription::query()->firstOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'source' => SubscriptionSource::AppleIap,
                        'status' => SubscriptionStatus::Inactive,
                    ]
                );
            }

            return $profile->fresh([
                'businessProfile',
                'communityProfile',
                'attendeeProfile',
                'subscription',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Profile $profile, array $data): Profile
    {
        return DB::transaction(function () use ($profile, $data): Profile {
            $attributes = [
                'email' => $data['email'],
                'phone_number' => ($data['phone_number'] ?? null) ?: null,
                'email_verified_at' => $this->resolveVerifiedAt($data),
            ];

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $profile->update($attributes);

            $this->upsertDetailProfile($profile, $data);
            $this->applyCityId($profile, $data);

            if ($profile->isBusiness()) {
                BusinessSubscription::query()->firstOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'source' => SubscriptionSource::AppleIap,
                        'status' => SubscriptionStatus::Inactive,
                    ]
                );
            }

            return $profile->fresh([
                'businessProfile',
                'communityProfile',
                'attendeeProfile',
                'subscription',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertDetailProfile(Profile $profile, array $data): void
    {
        if ($profile->isBusiness()) {
            BusinessProfile::query()->updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'name' => ($data['name'] ?? null) ?: null,
                    'about' => ($data['about'] ?? null) ?: null,
                    'instagram' => ($data['instagram'] ?? null) ?: null,
                    'website' => ($data['website'] ?? null) ?: null,
                    // Populated by the shared admin.users._places-import Google Maps import
                    // card, present on quick-add, create, and edit alike (merged 2026-09-14
                    // so photos/logo/venue data can be added or refreshed after creation, not
                    // only at quick-add time).
                    'profile_photo' => ($data['profile_photo'] ?? null) ?: null,
                    'offer_photos' => ($data['offer_photos'] ?? null) ?: null,
                    'primary_venue' => ($data['primary_venue'] ?? null) ?: null,
                    // Same Maps-import card, mapped from Google's place `types` by
                    // GooglePlacesService::mapBusinessCategories() -- was returned by the
                    // API on every import but never actually captured by the form, so
                    // every imported listing fell back to the generic "Business" label
                    // on the public page instead of a real category. Caught 2026-09-15
                    // benchmarking against Yelp/Google Business Profile/TripAdvisor.
                    'business_type' => ($data['business_type'] ?? null) ?: null,
                ]
            );

            return;
        }

        if ($profile->isCommunity()) {
            CommunityProfile::query()->updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'name' => ($data['name'] ?? null) ?: null,
                    'about' => ($data['about'] ?? null) ?: null,
                    'instagram' => ($data['instagram'] ?? null) ?: null,
                    'tiktok' => ($data['tiktok'] ?? null) ?: null,
                    'website' => ($data['website'] ?? null) ?: null,
                ]
            );

            return;
        }

        AttendeeProfile::query()->firstOrCreate([
            'profile_id' => $profile->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCityId(Profile $profile, array $data): void
    {
        $cityId = $data['city_id'] ?? null;
        if ($cityId === null) {
            return;
        }

        if ($profile->isBusiness()) {
            $profile->businessProfile()->update(['city_id' => $cityId]);
        } elseif ($profile->isCommunity()) {
            $profile->communityProfile()->update(['city_id' => $cityId]);
        }
    }

    public function delete(Profile $profile): void
    {
        $profile->delete();
    }

    /**
     * Grant a maintainer-issued subscription that unblocks publish.
     * Defaults to 12 months from today.
     */
    /**
     * Switch an account off (#254).
     *
     * Reversible and lossless — the opposite of delete(). Revoking the tokens is
     * the half that makes it immediate: without it a signed-in phone keeps working
     * until its token happens to expire.
     */
    public function deactivate(Profile $profile): Profile
    {
        return DB::transaction(function () use ($profile): Profile {
            $profile->forceFill(['is_active' => false])->save();

            $profile->tokens()->delete();

            return $profile->refresh();
        });
    }

    /**
     * Switch an account back on (#254). The user signs in again as normal;
     * nothing else needs restoring, because nothing was destroyed.
     */
    public function activate(Profile $profile): Profile
    {
        return DB::transaction(function () use ($profile): Profile {
            $profile->forceFill(['is_active' => true])->save();

            return $profile->refresh();
        });
    }

    /**
     * Switch a batch off in two statements (#256).
     *
     * Not a loop over deactivate(): that would issue one UPDATE and one DELETE
     * per account, which is the whole reason bulk exists. Atomic, so an admin
     * who selects twenty either changes twenty or changes none.
     *
     * @param  list<string>  $profileIds
     * @return int how many rows actually changed
     */
    public function deactivateMany(array $profileIds): int
    {
        if ($profileIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($profileIds): int {
            $changed = Profile::query()
                ->whereIn('id', $profileIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // One statement for the whole batch, whatever its size. Without this
            // a signed-in phone keeps working until its token happens to expire.
            PersonalAccessToken::query()
                ->where('tokenable_type', Profile::class)
                ->whereIn('tokenable_id', $profileIds)
                ->delete();

            return $changed;
        });
    }

    /**
     * Switch a batch back on (#256). Nothing to restore — nothing was destroyed.
     *
     * @param  list<string>  $profileIds
     * @return int how many rows actually changed
     */
    public function activateMany(array $profileIds): int
    {
        if ($profileIds === []) {
            return 0;
        }

        return DB::transaction(fn (): int => Profile::query()
            ->whereIn('id', $profileIds)
            ->where('is_active', false)
            ->update(['is_active' => true]));
    }

    public function grantSubscription(Profile $profile, int $months = 12): BusinessSubscription
    {
        return DB::transaction(function () use ($profile, $months): BusinessSubscription {
            $subscription = BusinessSubscription::query()->firstOrNew(
                ['profile_id' => $profile->id],
            );

            $subscription->source = SubscriptionSource::Maintainer;
            $subscription->status = SubscriptionStatus::Active;
            $subscription->current_period_start = now();
            $subscription->current_period_end = now()->addMonths($months);
            $subscription->cancel_at_period_end = false;
            $subscription->save();

            return $subscription;
        });
    }

    public function revokeSubscription(Profile $profile): ?BusinessSubscription
    {
        return DB::transaction(function () use ($profile): ?BusinessSubscription {
            $subscription = BusinessSubscription::query()
                ->where('profile_id', $profile->id)
                ->first();

            if ($subscription === null) {
                return null;
            }

            $subscription->status = SubscriptionStatus::Inactive;
            $subscription->cancel_at_period_end = true;
            $subscription->save();

            return $subscription;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveVerifiedAt(array $data): ?\Illuminate\Support\Carbon
    {
        return filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOL)
            ? now()
            : null;
    }
}
