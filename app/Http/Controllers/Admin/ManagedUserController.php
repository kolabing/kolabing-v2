<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Enums\VenueType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBusinessOnboardingRequest;
use App\Http\Requests\Admin\AdminCommunityOnboardingRequest;
use App\Http\Requests\Admin\BulkProfileActiveRequest;
use App\Http\Requests\Admin\GrantSubscriptionRequest;
use App\Http\Requests\Admin\PreviewWelcomeEmailRequest;
use App\Http\Requests\Admin\QuickAddProfileRequest;
use App\Http\Requests\Admin\SendWelcomeEmailRequest;
use App\Http\Requests\Admin\StoreManagedUserRequest;
use App\Http\Requests\Admin\UpdateManagedUserRequest;
use App\Models\AdminWelcomeEmailTemplate;
use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\CommunityType;
use App\Models\Kolab;
use App\Models\OfferOption;
use App\Models\Profile;
use App\Models\Scopes\ActiveProfileScope;
use App\Services\Admin\ManagedProfileService;
use App\Services\OrganizerEntitlementService;
use App\Support\OfferOptionValues;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagedUserController extends Controller
{
    public function __construct(
        private readonly ManagedProfileService $managedProfileService,
        private readonly OrganizerEntitlementService $organizerEntitlementService,
    ) {}

    /**
     * Daniel 2026-09-14: the plain unfiltered list was "not UI/UX friendly ... a lot
     * of stale tests", buried among real listings with no way to search, filter by
     * city, or tell them apart. Adds: name/email search, user_type + city filters, a
     * "hide likely test rows" toggle (on by default -- both the real `is_test_user`
     * flag and an email/name pattern catch, since plenty of QA rows predate that
     * column), and a city map (same Leaflet-by-count pattern as admin.crm.index,
     * click a marker to filter) so a city with a listed business is visible at a
     * glance instead of scrolling a flat 20-per-page table.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $userType = $request->query('user_type');
        $cityId = $request->query('city_id');
        $hideTest = ! $request->boolean('show_test');

        // Deliberately unfiltered on is_active: an admin that cannot see a switched-off
        // account cannot switch it back on. The sub-profile relations carry
        // ActiveProfileScope, so they are loaded without it or the name/city columns
        // would go blank (#254).
        $query = Profile::query()
            ->with([
                'businessProfile' => fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->with('city'),
                'communityProfile' => fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->with('city'),
                'attendeeProfile' => fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class),
                'subscription',
            ]);

        if ($q !== '') {
            $needle = '%'.mb_strtolower($q).'%';
            $query->where(function ($outer) use ($needle) {
                $outer->whereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereHas('businessProfile', fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('communityProfile', fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        if (in_array($userType, UserType::values(), true)) {
            $query->where('user_type', $userType);
        }

        if ($cityId) {
            $query->where(function ($outer) use ($cityId) {
                $outer->whereHas('businessProfile', fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->where('city_id', $cityId))
                    ->orWhereHas('communityProfile', fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->where('city_id', $cityId));
            });
        }

        if ($hideTest) {
            // LOWER()+LIKE, not `ilike`: `ilike` is Postgres-only and this repo's test
            // suite runs on SQLite, which has no `ilike` operator at all (SQLSTATE
            // "near ilike: syntax error"). LOWER() LIKE is portable to both.
            $query->where('is_test_user', false)
                ->whereRaw('LOWER(email) NOT LIKE ?', ['%test%'])
                ->whereRaw('LOWER(email) NOT LIKE ?', ['%example.com'])
                ->whereDoesntHave('businessProfile', fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->whereRaw('LOWER(name) LIKE ?', ['%test%']))
                ->whereDoesntHave('communityProfile', fn ($sub) => $sub->withoutGlobalScope(ActiveProfileScope::class)->whereRaw('LOWER(name) LIKE ?', ['%test%']));
        }

        $profiles = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'profiles' => $profiles,
            'userTypes' => UserType::cases(),
            'cities' => City::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'cityCounts' => $this->cityCounts(),
            'filters' => [
                'q' => $q,
                'user_type' => $userType,
                'city_id' => $cityId,
                'show_test' => ! $hideTest,
            ],
        ]);
    }

    /**
     * Listed businesses + communities per city, for the index map. A plain
     * count()->groupBy('city_id') per table, merged in PHP -- no cross-dialect SQL
     * (no HAVING-on-a-computed-alias, which SQLite and Postgres disagree on), and this
     * repo's tests run on SQLite while prod is Postgres.
     *
     * Keyed by id (not name): a city with listings isn't guaranteed to be in the
     * *active* city list the create/edit dropdowns use (a market can go inactive
     * without its historical listings disappearing), so the view must not have to
     * re-resolve a name back to an id through that separate, possibly-missing list.
     *
     * @return \Illuminate\Support\Collection<string, array{id: string, name: string, n: int}>
     */
    private function cityCounts(): \Illuminate\Support\Collection
    {
        $businessCounts = BusinessProfile::query()->withoutGlobalScope(ActiveProfileScope::class)
            ->whereNotNull('city_id')->selectRaw('city_id, count(*) as n')->groupBy('city_id')->pluck('n', 'city_id');
        $communityCounts = CommunityProfile::query()->withoutGlobalScope(ActiveProfileScope::class)
            ->whereNotNull('city_id')->selectRaw('city_id, count(*) as n')->groupBy('city_id')->pluck('n', 'city_id');

        $cityIds = $businessCounts->keys()->merge($communityCounts->keys())->unique();

        return City::query()->whereIn('id', $cityIds)->get(['id', 'name'])
            ->mapWithKeys(fn (City $city): array => [
                $city->id => ['id' => $city->id, 'name' => $city->name, 'n' => ($businessCounts[$city->id] ?? 0) + ($communityCounts[$city->id] ?? 0)],
            ])
            ->sortByDesc('n');
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'userTypes' => UserType::cases(),
            'cities' => City::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'businessTypes' => BusinessType::query()->active()->ordered()->get(),
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $profile = $this->managedProfileService->create($request->validated());

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('User created successfully.'));
    }

    /**
     * The listing-first quick add: list a business/community sourced from outreach
     * before its owner has ever touched the app, then email them a create-password
     * link + what-happens-next explainer.
     */
    public function quickAddForm(): View
    {
        return view('admin.users.quick-add', [
            'userTypes' => [UserType::Business, UserType::Community],
            'cities' => City::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'businessTypes' => BusinessType::query()->active()->ordered()->get(),
        ]);
    }

    public function quickAddStore(QuickAddProfileRequest $request): RedirectResponse
    {
        $profile = $this->managedProfileService->quickAdd($request->validated());

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('Listing created. Send the welcome email below once you know the language.'));
    }

    /**
     * The full onboarding form (BE-NF-64) — every step the mobile wizard asks for.
     *
     * Business and community are separate forms rather than one form with a type
     * switch, because past the first question they share almost nothing: a business
     * is asked about a venue or a product it wants promoted, a community about what
     * kind of community it is and how big. Splitting them is also what lets each POST
     * carry its own request class, so the type is decided by the route and cannot be
     * changed by editing the payload.
     */
    public function onboardForm(Request $request): View
    {
        $type = $request->query('type') === UserType::Community->value
            ? UserType::Community
            : UserType::Business;

        $cities = City::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.users.onboard', [
            'userType' => $type,
            'cities' => $cities,
            'businessTypes' => BusinessType::query()->active()->ordered()->get(),
            'communityTypes' => CommunityType::query()->active()->ordered()->get(),
            'venueTypes' => VenueType::values(),
            'productTypes' => OfferOptionValues::for(OfferOption::KIND_PRODUCT_TYPE),
        ]);
    }

    public function onboardBusiness(AdminBusinessOnboardingRequest $request): RedirectResponse
    {
        // The route decides the type, not the payload.
        $profile = $this->managedProfileService->onboard([
            ...$request->validated(),
            'user_type' => UserType::Business->value,
        ]);

        /*
         * Don't claim the Kolab is live without checking. provisionBusinessAutoOffer()
         * returns early (logging, not throwing) when there is no primary venue or no
         * resolvable city, and catches Throwable around create+publish — so the
         * happy-path wording would tell a maintainer a listing exists when nothing in
         * the panel would contradict it.
         */
        $hasKolab = Kolab::query()->where('creator_profile_id', $profile->id)->exists();

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', $hasKolab
                ? __('Business onboarded. Its first Kolab is live; send the welcome email below.')
                : __('Business onboarded, but no first Kolab could be composed from these details — create one manually. Send the welcome email below.'));
    }

    public function onboardCommunity(AdminCommunityOnboardingRequest $request): RedirectResponse
    {
        $profile = $this->managedProfileService->onboard([
            ...$request->validated(),
            'user_type' => UserType::Community->value,
        ]);

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('Community onboarded. Send the welcome email below once you know the language.'));
    }

    public function edit(Profile $profile): View
    {
        $profile->loadMissing([
            'businessProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            'communityProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            'attendeeProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            'subscription',
        ]);

        return view('admin.users.edit', [
            'profile' => $profile,
            'cities' => City::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'businessTypes' => BusinessType::query()->active()->ordered()->get(),
            'welcomeEmailLocales' => AdminWelcomeEmailTemplate::query()
                ->where('is_active', true)
                ->orderBy('label')
                ->get(['locale', 'label']),
        ]);
    }

    /**
     * Read-only preview a maintainer must see before the send route below is reachable
     * in the UI — Daniel 2026-09-14: "i don't want them to get an unapproved email".
     * Nothing is queued here.
     */
    public function previewWelcomeEmail(PreviewWelcomeEmailRequest $request, Profile $profile): View
    {
        $locale = $request->validated()['locale'];

        return view('admin.users.welcome-email-preview', [
            'profile' => $profile,
            'locale' => $locale,
            'html' => $this->managedProfileService->previewWelcomeEmail($profile, $locale),
        ]);
    }

    /**
     * Manual follow-up to quick-add, reached only from the preview screen above —
     * Daniel 2026-09-14: outreach happens in different languages, so sending is a
     * deliberate, reviewed step, not an automatic side effect of creating the listing.
     */
    public function sendWelcomeEmail(SendWelcomeEmailRequest $request, Profile $profile): RedirectResponse
    {
        $this->managedProfileService->sendWelcomeEmail($profile, $request->validated()['locale']);

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('Welcome email sent.'));
    }

    public function update(UpdateManagedUserRequest $request, Profile $profile): RedirectResponse
    {
        $profile = $this->managedProfileService->update($profile, $request->validated());

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('User updated successfully.'));
    }

    public function destroy(Profile $profile): RedirectResponse
    {
        $this->managedProfileService->delete($profile);

        return redirect()->route('admin.users.index')
            ->with('status', __('User deleted.'));
    }

    /**
     * The global active/passive switch (#254). Not a delete: reversible, and the
     * account's data is untouched. Deactivating also revokes its tokens, so a
     * signed-in phone stops working immediately rather than at token expiry.
     */
    public function deactivate(Profile $profile): RedirectResponse
    {
        $this->managedProfileService->deactivate($profile);

        return redirect()->back()
            ->with('status', __('Account deactivated. It is now hidden from the app and cannot sign in.'));
    }

    public function activate(Profile $profile): RedirectResponse
    {
        $this->managedProfileService->activate($profile);

        return redirect()->back()
            ->with('status', __('Account activated.'));
    }

    /**
     * Switch a selection off in one action (#256). The message reports how many
     * rows actually changed, not how many were ticked — an admin who re-selects
     * accounts that were already passive should see that nothing happened.
     */
    public function bulkDeactivate(BulkProfileActiveRequest $request): RedirectResponse
    {
        $changed = $this->managedProfileService->deactivateMany($request->profileIds());

        return redirect()->back()->with('status', trans_choice(
            '{0}No accounts changed — they were already deactivated.|{1}1 account deactivated. It is now hidden from the app and cannot sign in.|[2,*]:count accounts deactivated. They are now hidden from the app and cannot sign in.',
            $changed,
            ['count' => $changed],
        ));
    }

    public function bulkActivate(BulkProfileActiveRequest $request): RedirectResponse
    {
        $changed = $this->managedProfileService->activateMany($request->profileIds());

        return redirect()->back()->with('status', trans_choice(
            '{0}No accounts changed — they were already active.|{1}1 account activated.|[2,*]:count accounts activated.',
            $changed,
            ['count' => $changed],
        ));
    }

    public function grantSubscription(GrantSubscriptionRequest $request, Profile $profile): RedirectResponse
    {
        abort_unless($profile->isBusiness(), 422, 'Only business users can receive a subscription.');

        $plan = $request->plan();
        $this->managedProfileService->grantSubscription($profile, 12, $plan);

        return redirect()->back()
            ->with('status', __(':plan granted for 12 months.', ['plan' => $plan->label()]));
    }

    public function revokeSubscription(Profile $profile): RedirectResponse
    {
        abort_unless($profile->isBusiness(), 422, 'Only business users have a subscription.');

        $this->managedProfileService->revokeSubscription($profile);

        return redirect()->back()
            ->with('status', __('Subscription revoked.'));
    }

    /**
     * Grant the Multi-Kolab Event Creator capability. Unlike the subscription
     * grant above, both Business and Community profiles are eligible — this
     * is an independent capability, not the business paywall.
     */
    public function grantEventCreatorEntitlement(Profile $profile): RedirectResponse
    {
        $this->organizerEntitlementService->grant($profile);

        return redirect()->back()
            ->with('status', __('Event Creator access granted for 12 months.'));
    }

    public function revokeEventCreatorEntitlement(Profile $profile): RedirectResponse
    {
        $this->organizerEntitlementService->revoke($profile);

        return redirect()->back()
            ->with('status', __('Event Creator access revoked.'));
    }
}
