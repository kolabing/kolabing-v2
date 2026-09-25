# CLAUDE.md reference (not auto-loaded)

Long examples moved out of `CLAUDE.md` to keep the auto-loaded file small. The rules themselves stay in `CLAUDE.md`.

## Authorization policy example

```php
// OpportunityPolicy
public function update(User $user, Opportunity $opportunity): bool
{
    return $user->profile->id === $opportunity->creator_profile_id;
}

public function publish(User $user, Opportunity $opportunity): bool
{
    return $this->update($user, $opportunity)
        && $user->profile->hasActiveSubscription();
}
```

## JSONB field shapes

```php
// business_offer
['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 20]]

// community_deliverables
['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 50]

// categories
['Food & Drink', 'Sports', 'Wellness']
```

## Example feature test pattern

```php
// Example test pattern
public function test_accepting_application_creates_collaboration(): void
{
    $creator = Profile::factory()->business()->withSubscription()->create();
    $opportunity = Opportunity::factory()->published()->for($creator)->create();
    $application = Application::factory()->for($opportunity)->create();

    $this->actingAs($creator->user)
        ->postJson("/api/v1/applications/{$application->id}/accept")
        ->assertOk();

    $this->assertDatabaseHas('collaborations', [
        'application_id' => $application->id,
        'status' => 'scheduled',
    ]);
}
```

## Enum definitions (full code)

```php
enum UserType: string {
    case Business = 'business';
    case Community = 'community';
}

enum OfferStatus: string {
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Completed = 'completed';
}

enum ApplicationStatus: string {
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}

enum CollaborationStatus: string {
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

enum SubscriptionStatus: string {
    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
    case Inactive = 'inactive';
}
```

## Service layer tree (original MVP listing)

```
app/Services/
├── GoogleAuthService.php      # Google token verification, user creation
├── ProfileService.php         # Profile CRUD operations
├── OpportunityService.php     # Opportunity lifecycle management
├── ApplicationService.php     # Application accept/decline/withdraw
├── CollaborationService.php   # Collaboration status transitions
└── SubscriptionService.php    # Stripe integration
```

## Laravel Boost: search-docs syntax examples

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

## Laravel Boost: PHP snippets

    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>
