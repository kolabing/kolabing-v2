# Gamification Wallet & Rewards Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a points wallet, ledger, badges, referral codes, and withdrawal system for Community + Business users in the collaboration flow.

**Architecture:** New parallel gamification system (separate from existing attendee/event gamification). Points are awarded server-side when collaborations complete, reviews are posted, UGC is submitted, or referrals convert. Wallet tracks balance; ledger is the audit trail. 5 new badges auto-evaluate after every point award. Withdrawal converts points to EUR at fixed rate (1 pt = EUR 0.20, threshold 375 pts = EUR 75).

**Tech Stack:** Laravel 12, PostgreSQL, Sanctum auth, UUID PKs, Service Layer pattern, TDD with LazilyRefreshDatabase

**Key Decisions:**
- Both collaboration parties (creator + applicant) get +1 point on completion
- No Stripe subscription differentiation for referrals — single `referral_conversion` event type (+50 pts)
- Review/UGC endpoints don't exist yet — service hooks ready, triggers deferred
- `earned_badges` table is separate from existing `badge_awards` (different system)
- Wallet auto-creates on first access

---

## Task 1: Create Enums

**Files:**
- Create: `app/Enums/PointEventType.php`
- Create: `app/Enums/WithdrawalStatus.php`
- Create: `app/Enums/GamificationBadgeSlug.php`

**Step 1: Create PointEventType enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PointEventType: string
{
    case CollaborationComplete = 'collaboration_complete';
    case ReviewPosted = 'review_posted';
    case UgcPosted = 'ugc_posted';
    case ReferralConversion = 'referral_conversion';
    case Withdrawal = 'withdrawal';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the default points for this event type.
     */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::CollaborationComplete => 1,
            self::ReviewPosted => 1,
            self::UgcPosted => 1,
            self::ReferralConversion => 50,
            self::Withdrawal => 0, // Withdrawal points are dynamic (negative)
        };
    }
}
```

**Step 2: Create WithdrawalStatus enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 3: Create GamificationBadgeSlug enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum GamificationBadgeSlug: string
{
    case FirstKolab = 'first_kolab';
    case ContentCreator = 'content_creator';
    case CommunityEarner = 'community_earner';
    case ReferralPioneer = 'referral_pioneer';
    case PowerPartner = 'power_partner';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function displayName(): string
    {
        return match ($this) {
            self::FirstKolab => 'First Kolab',
            self::ContentCreator => 'Content Creator',
            self::CommunityEarner => 'Community Earner',
            self::ReferralPioneer => 'Referral Pioneer',
            self::PowerPartner => 'Power Partner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FirstKolab => 'Completed your first collaboration',
            self::ContentCreator => 'Posted 3 reviews or pieces of content',
            self::CommunityEarner => 'Earned your first 100 points',
            self::ReferralPioneer => 'Referred a business that converted',
            self::PowerPartner => 'Completed 5 collaborations',
        };
    }
}
```

**Step 4: Commit**

```bash
git add app/Enums/PointEventType.php app/Enums/WithdrawalStatus.php app/Enums/GamificationBadgeSlug.php
git commit -m "feat: add PointEventType, WithdrawalStatus, GamificationBadgeSlug enums"
```

---

## Task 2: Create Migrations

**Files:**
- Create: `database/migrations/2026_03_30_000001_create_wallets_table.php`
- Create: `database/migrations/2026_03_30_000002_create_point_ledger_table.php`
- Create: `database/migrations/2026_03_30_000003_create_earned_badges_table.php`
- Create: `database/migrations/2026_03_30_000004_create_referral_codes_table.php`
- Create: `database/migrations/2026_03_30_000005_create_withdrawal_requests_table.php`

**Step 1: Create wallets migration**

Run: `php artisan make:migration create_wallets_table --no-interaction`

Edit the migration:

```php
public function up(): void
{
    Schema::create('wallets', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('profile_id')->unique();
        $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        $table->integer('points')->default(0);
        $table->integer('redeemed_points')->default(0);
        $table->boolean('pending_withdrawal')->default(false);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('wallets');
}
```

**Step 2: Create point_ledger migration**

Run: `php artisan make:migration create_point_ledger_table --no-interaction`

```php
public function up(): void
{
    Schema::create('point_ledger', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('profile_id');
        $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        $table->integer('points');
        $table->string('event_type', 50);
        $table->uuid('reference_id')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();

        $table->index('profile_id');
        $table->index(['profile_id', 'event_type']);
    });
}

public function down(): void
{
    Schema::dropIfExists('point_ledger');
}
```

**Step 3: Create earned_badges migration**

Run: `php artisan make:migration create_earned_badges_table --no-interaction`

```php
public function up(): void
{
    Schema::create('earned_badges', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('profile_id');
        $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        $table->string('badge_slug', 50);
        $table->timestamp('earned_at')->useCurrent();
        $table->timestamps();

        $table->unique(['profile_id', 'badge_slug']);
    });
}

public function down(): void
{
    Schema::dropIfExists('earned_badges');
}
```

**Step 4: Create referral_codes migration**

Run: `php artisan make:migration create_referral_codes_table --no-interaction`

```php
public function up(): void
{
    Schema::create('referral_codes', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('profile_id')->unique();
        $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        $table->string('code', 20)->unique();
        $table->integer('total_conversions')->default(0);
        $table->integer('total_points_earned')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('referral_codes');
}
```

**Step 5: Create withdrawal_requests migration**

Run: `php artisan make:migration create_withdrawal_requests_table --no-interaction`

```php
public function up(): void
{
    Schema::create('withdrawal_requests', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('profile_id');
        $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        $table->integer('points');
        $table->decimal('eur_amount', 10, 2);
        $table->string('iban', 50);
        $table->string('account_holder', 255);
        $table->string('status', 20)->default('pending');
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('withdrawal_requests');
}
```

**Step 6: Run migrations**

Run: `php artisan migrate --no-interaction`
Expected: All 5 tables created successfully.

**Step 7: Commit**

```bash
git add database/migrations/*_create_wallets_table.php database/migrations/*_create_point_ledger_table.php database/migrations/*_create_earned_badges_table.php database/migrations/*_create_referral_codes_table.php database/migrations/*_create_withdrawal_requests_table.php
git commit -m "feat: add migrations for wallets, point_ledger, earned_badges, referral_codes, withdrawal_requests"
```

---

## Task 3: Create Models + Factories

**Files:**
- Create: `app/Models/Wallet.php`
- Create: `app/Models/PointLedger.php`
- Create: `app/Models/EarnedBadge.php`
- Create: `app/Models/ReferralCode.php`
- Create: `app/Models/WithdrawalRequest.php`
- Create: `database/factories/WalletFactory.php`
- Create: `database/factories/PointLedgerFactory.php`
- Create: `database/factories/EarnedBadgeFactory.php`
- Create: `database/factories/ReferralCodeFactory.php`
- Create: `database/factories/WithdrawalRequestFactory.php`

**Step 1: Create Wallet model**

Run: `php artisan make:model Wallet -f --no-interaction`

Replace generated model with:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $points
 * @property int $redeemed_points
 * @property bool $pending_withdrawal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class Wallet extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'points',
        'redeemed_points',
        'pending_withdrawal',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'redeemed_points' => 'integer',
            'pending_withdrawal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the available points (earned minus redeemed).
     */
    public function getAvailablePoints(): int
    {
        return $this->points - $this->redeemed_points;
    }

    /**
     * Get the EUR value of available points (1 point = EUR 0.20).
     */
    public function getEurValue(): float
    {
        return round($this->getAvailablePoints() * 0.20, 2);
    }

    /**
     * Get progress toward withdrawal threshold (375 points).
     */
    public function getProgress(): float
    {
        $available = $this->getAvailablePoints();
        if ($available <= 0) {
            return 0.0;
        }

        return round(min($available / 375, 1.0), 4);
    }

    /**
     * Check if the wallet can make a withdrawal.
     */
    public function canWithdraw(): bool
    {
        return $this->getAvailablePoints() >= 375 && ! $this->pending_withdrawal;
    }
}
```

**Step 2: Create WalletFactory**

Replace generated factory with:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'points' => 0,
            'redeemed_points' => 0,
            'pending_withdrawal' => false,
        ];
    }

    public function withPoints(int $points): static
    {
        return $this->state(fn () => [
            'points' => $points,
        ]);
    }

    public function withdrawable(): static
    {
        return $this->state(fn () => [
            'points' => 375,
            'redeemed_points' => 0,
            'pending_withdrawal' => false,
        ]);
    }

    public function pendingWithdrawal(): static
    {
        return $this->state(fn () => [
            'pending_withdrawal' => true,
        ]);
    }
}
```

**Step 3: Create PointLedger model**

Run: `php artisan make:model PointLedger -f --no-interaction`

Replace generated model with:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $points
 * @property PointEventType $event_type
 * @property string|null $reference_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class PointLedger extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'point_ledger';

    protected $fillable = [
        'profile_id',
        'points',
        'event_type',
        'reference_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'event_type' => PointEventType::class,
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
```

**Step 4: Create PointLedgerFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PointEventType;
use App\Models\PointLedger;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointLedger>
 */
class PointLedgerFactory extends Factory
{
    protected $model = PointLedger::class;

    public function definition(): array
    {
        $eventType = fake()->randomElement([
            PointEventType::CollaborationComplete,
            PointEventType::ReviewPosted,
            PointEventType::UgcPosted,
        ]);

        return [
            'profile_id' => Profile::factory(),
            'points' => $eventType->defaultPoints(),
            'event_type' => $eventType,
            'reference_id' => null,
            'description' => fake()->sentence(),
        ];
    }

    public function collaborationComplete(): static
    {
        return $this->state(fn () => [
            'points' => 1,
            'event_type' => PointEventType::CollaborationComplete,
        ]);
    }

    public function reviewPosted(): static
    {
        return $this->state(fn () => [
            'points' => 1,
            'event_type' => PointEventType::ReviewPosted,
        ]);
    }

    public function ugcPosted(): static
    {
        return $this->state(fn () => [
            'points' => 1,
            'event_type' => PointEventType::UgcPosted,
        ]);
    }

    public function referralConversion(): static
    {
        return $this->state(fn () => [
            'points' => 50,
            'event_type' => PointEventType::ReferralConversion,
        ]);
    }

    public function withdrawal(int $points = 375): static
    {
        return $this->state(fn () => [
            'points' => -$points,
            'event_type' => PointEventType::Withdrawal,
        ]);
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }
}
```

**Step 5: Create EarnedBadge model**

Run: `php artisan make:model EarnedBadge -f --no-interaction`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GamificationBadgeSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property GamificationBadgeSlug $badge_slug
 * @property \Illuminate\Support\Carbon $earned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class EarnedBadge extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'badge_slug',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'badge_slug' => GamificationBadgeSlug::class,
            'earned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
```

**Step 6: Create EarnedBadgeFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GamificationBadgeSlug;
use App\Models\EarnedBadge;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EarnedBadge>
 */
class EarnedBadgeFactory extends Factory
{
    protected $model = EarnedBadge::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'badge_slug' => fake()->randomElement(GamificationBadgeSlug::cases()),
            'earned_at' => now(),
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }

    public function slug(GamificationBadgeSlug $slug): static
    {
        return $this->state(fn () => [
            'badge_slug' => $slug,
        ]);
    }
}
```

**Step 7: Create ReferralCode model**

Run: `php artisan make:model ReferralCode -f --no-interaction`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $code
 * @property int $total_conversions
 * @property int $total_points_earned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class ReferralCode extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'code',
        'total_conversions',
        'total_points_earned',
    ];

    protected function casts(): array
    {
        return [
            'total_conversions' => 'integer',
            'total_points_earned' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Generate a unique referral code in KOLAB-XXXX format.
     */
    public static function generateCode(): string
    {
        do {
            $code = 'KOLAB-' . strtoupper(Str::random(4));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
```

**Step 8: Create ReferralCodeFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCode>
 */
class ReferralCodeFactory extends Factory
{
    protected $model = ReferralCode::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'code' => 'KOLAB-' . strtoupper(Str::random(4)),
            'total_conversions' => 0,
            'total_points_earned' => 0,
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }

    public function withConversions(int $count, int $pointsEarned): static
    {
        return $this->state(fn () => [
            'total_conversions' => $count,
            'total_points_earned' => $pointsEarned,
        ]);
    }
}
```

**Step 9: Create WithdrawalRequest model**

Run: `php artisan make:model WithdrawalRequest -f --no-interaction`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $points
 * @property float $eur_amount
 * @property string $iban
 * @property string $account_holder
 * @property WithdrawalStatus $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class WithdrawalRequest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'points',
        'eur_amount',
        'iban',
        'account_holder',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'eur_amount' => 'decimal:2',
            'status' => WithdrawalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get IBAN with middle digits masked.
     */
    public function getMaskedIban(): string
    {
        $length = strlen($this->iban);
        if ($length <= 8) {
            return $this->iban;
        }

        return substr($this->iban, 0, 4) . str_repeat('*', $length - 8) . substr($this->iban, -4);
    }
}
```

**Step 10: Create WithdrawalRequestFactory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\Profile;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'points' => 375,
            'eur_amount' => 75.00,
            'iban' => 'ES7921000813610123456789',
            'account_holder' => fake()->company(),
            'status' => WithdrawalStatus::Pending,
            'notes' => null,
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalStatus::Pending,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalStatus::Completed,
        ]);
    }
}
```

**Step 11: Commit**

```bash
git add app/Models/Wallet.php app/Models/PointLedger.php app/Models/EarnedBadge.php app/Models/ReferralCode.php app/Models/WithdrawalRequest.php database/factories/WalletFactory.php database/factories/PointLedgerFactory.php database/factories/EarnedBadgeFactory.php database/factories/ReferralCodeFactory.php database/factories/WithdrawalRequestFactory.php
git commit -m "feat: add Wallet, PointLedger, EarnedBadge, ReferralCode, WithdrawalRequest models + factories"
```

---

## Task 4: Add Profile Relationships

**Files:**
- Modify: `app/Models/Profile.php`

**Step 1: Add relationships to Profile model**

Add these imports at top of `app/Models/Profile.php`:

```php
use App\Models\Wallet;
use App\Models\PointLedger;
use App\Models\EarnedBadge;
use App\Models\ReferralCode;
use App\Models\WithdrawalRequest;
```

Add these relationship methods to `Profile.php` (before `isBusiness()` method):

```php
/**
 * Get the gamification wallet for this profile.
 *
 * @return HasOne<Wallet, $this>
 */
public function wallet(): HasOne
{
    return $this->hasOne(Wallet::class);
}

/**
 * Get point ledger entries for this profile.
 *
 * @return HasMany<PointLedger, $this>
 */
public function pointLedger(): HasMany
{
    return $this->hasMany(PointLedger::class);
}

/**
 * Get earned gamification badges for this profile.
 *
 * @return HasMany<EarnedBadge, $this>
 */
public function earnedBadges(): HasMany
{
    return $this->hasMany(EarnedBadge::class);
}

/**
 * Get the referral code for this profile.
 *
 * @return HasOne<ReferralCode, $this>
 */
public function referralCode(): HasOne
{
    return $this->hasOne(ReferralCode::class);
}

/**
 * Get withdrawal requests for this profile.
 *
 * @return HasMany<WithdrawalRequest, $this>
 */
public function withdrawalRequests(): HasMany
{
    return $this->hasMany(WithdrawalRequest::class);
}
```

Also update the PHPDoc block at the top of Profile class to include:

```php
 * @property-read Wallet|null $wallet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PointLedger> $pointLedger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EarnedBadge> $earnedBadges
 * @property-read ReferralCode|null $referralCode
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WithdrawalRequest> $withdrawalRequests
```

**Step 2: Commit**

```bash
git add app/Models/Profile.php
git commit -m "feat: add wallet, pointLedger, earnedBadges, referralCode, withdrawalRequests relationships to Profile"
```

---

## Task 5: GamificationWalletService — awardPoints + evaluateBadges (TDD)

**Files:**
- Create: `app/Services/GamificationWalletService.php`
- Create: `tests/Unit/Services/GamificationWalletServiceTest.php`

**Step 1: Write failing tests for awardPoints()**

Create `tests/Unit/Services/GamificationWalletServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\GamificationBadgeSlug;
use App\Enums\PointEventType;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\Wallet;
use App\Services\GamificationWalletService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationWalletServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GamificationWalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GamificationWalletService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | awardPoints()
    |--------------------------------------------------------------------------
    */

    public function test_award_points_creates_wallet_if_not_exists(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'points' => 1,
        ]);
    }

    public function test_award_points_creates_ledger_entry(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'points' => 1,
            'event_type' => 'collaboration_complete',
            'reference_id' => 'collab-uuid',
            'description' => 'Collaboration completed',
        ]);
    }

    public function test_award_points_increments_existing_wallet(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 10,
        ]);

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'points' => 11,
        ]);
    }

    public function test_award_points_with_referral_conversion(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            50,
            PointEventType::ReferralConversion,
            'referral-uuid',
            'Referral: BCN Yoga Studio subscribed'
        );

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'points' => 50,
        ]);
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'points' => 50,
            'event_type' => 'referral_conversion',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | evaluateBadges()
    |--------------------------------------------------------------------------
    */

    public function test_first_kolab_badge_awarded_after_first_collaboration_complete(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'first_kolab',
        ]);
    }

    public function test_content_creator_badge_awarded_after_3_reviews(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 0]);

        // Award 3 review points
        for ($i = 1; $i <= 3; $i++) {
            $this->service->awardPoints(
                $profile->id,
                1,
                PointEventType::ReviewPosted,
                "review-$i",
                "Review $i"
            );
        }

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'content_creator',
        ]);
    }

    public function test_content_creator_badge_not_awarded_with_only_2_reviews(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 0]);

        for ($i = 1; $i <= 2; $i++) {
            $this->service->awardPoints(
                $profile->id,
                1,
                PointEventType::ReviewPosted,
                "review-$i",
                "Review $i"
            );
        }

        $this->assertDatabaseMissing('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'content_creator',
        ]);
    }

    public function test_community_earner_badge_awarded_at_100_points(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 99]);

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'community_earner',
        ]);
    }

    public function test_referral_pioneer_badge_awarded_after_first_referral(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            50,
            PointEventType::ReferralConversion,
            'referral-uuid',
            'Referral converted'
        );

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'referral_pioneer',
        ]);
    }

    public function test_power_partner_badge_awarded_after_5_collaborations(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 0]);

        for ($i = 1; $i <= 5; $i++) {
            $this->service->awardPoints(
                $profile->id,
                1,
                PointEventType::CollaborationComplete,
                "collab-$i",
                "Collab $i"
            );
        }

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'power_partner',
        ]);
    }

    public function test_badge_not_awarded_twice(): void
    {
        $profile = Profile::factory()->community()->create();

        // Award 2 collab points — first_kolab badge should only be created once
        $this->service->awardPoints($profile->id, 1, PointEventType::CollaborationComplete, 'c1', 'C1');
        $this->service->awardPoints($profile->id, 1, PointEventType::CollaborationComplete, 'c2', 'C2');

        $count = EarnedBadge::query()
            ->where('profile_id', $profile->id)
            ->where('badge_slug', 'first_kolab')
            ->count();

        $this->assertSame(1, $count);
    }

    /*
    |--------------------------------------------------------------------------
    | getOrCreateWallet()
    |--------------------------------------------------------------------------
    */

    public function test_get_or_create_wallet_creates_new_wallet(): void
    {
        $profile = Profile::factory()->community()->create();

        $wallet = $this->service->getOrCreateWallet($profile->id);

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertSame($profile->id, $wallet->profile_id);
        $this->assertSame(0, $wallet->points);
    }

    public function test_get_or_create_wallet_returns_existing(): void
    {
        $profile = Profile::factory()->community()->create();
        $existing = Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 42,
        ]);

        $wallet = $this->service->getOrCreateWallet($profile->id);

        $this->assertSame($existing->id, $wallet->id);
        $this->assertSame(42, $wallet->points);
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Services/GamificationWalletServiceTest.php`
Expected: FAIL — class `GamificationWalletService` not found

**Step 3: Create GamificationWalletService**

Create `app/Services/GamificationWalletService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GamificationBadgeSlug;
use App\Enums\PointEventType;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class GamificationWalletService
{
    /**
     * Award points to a profile. Creates wallet if none exists.
     * Evaluates badge conditions after awarding.
     */
    public function awardPoints(
        string $profileId,
        int $points,
        PointEventType $eventType,
        ?string $referenceId = null,
        ?string $description = null,
    ): PointLedger {
        return DB::transaction(function () use ($profileId, $points, $eventType, $referenceId, $description): PointLedger {
            $ledgerEntry = PointLedger::create([
                'profile_id' => $profileId,
                'points' => $points,
                'event_type' => $eventType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);

            $wallet = Wallet::query()->firstOrCreate(
                ['profile_id' => $profileId],
                ['points' => 0, 'redeemed_points' => 0, 'pending_withdrawal' => false]
            );

            $wallet->increment('points', $points);

            $this->evaluateBadges($profileId);

            return $ledgerEntry;
        });
    }

    /**
     * Get or create a wallet for the given profile.
     */
    public function getOrCreateWallet(string $profileId): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['profile_id' => $profileId],
            ['points' => 0, 'redeemed_points' => 0, 'pending_withdrawal' => false]
        );
    }

    /**
     * Evaluate all badge conditions for a profile and award any newly earned badges.
     */
    public function evaluateBadges(string $profileId): void
    {
        $wallet = Wallet::query()->where('profile_id', $profileId)->first();

        foreach (GamificationBadgeSlug::cases() as $badgeSlug) {
            // Skip if already earned
            $alreadyEarned = EarnedBadge::query()
                ->where('profile_id', $profileId)
                ->where('badge_slug', $badgeSlug)
                ->exists();

            if ($alreadyEarned) {
                continue;
            }

            if ($this->isBadgeConditionMet($profileId, $badgeSlug, $wallet)) {
                EarnedBadge::create([
                    'profile_id' => $profileId,
                    'badge_slug' => $badgeSlug,
                    'earned_at' => now(),
                ]);
            }
        }
    }

    /**
     * Check if a specific badge condition is met.
     */
    private function isBadgeConditionMet(string $profileId, GamificationBadgeSlug $badge, ?Wallet $wallet): bool
    {
        return match ($badge) {
            GamificationBadgeSlug::FirstKolab => $this->countLedgerEvents($profileId, [PointEventType::CollaborationComplete]) >= 1,
            GamificationBadgeSlug::ContentCreator => $this->countLedgerEvents($profileId, [PointEventType::ReviewPosted, PointEventType::UgcPosted]) >= 3,
            GamificationBadgeSlug::CommunityEarner => ($wallet?->points ?? 0) >= 100,
            GamificationBadgeSlug::ReferralPioneer => $this->countLedgerEvents($profileId, [PointEventType::ReferralConversion]) >= 1,
            GamificationBadgeSlug::PowerPartner => $this->countLedgerEvents($profileId, [PointEventType::CollaborationComplete]) >= 5,
        };
    }

    /**
     * Count ledger entries for specific event types.
     *
     * @param  array<PointEventType>  $eventTypes
     */
    private function countLedgerEvents(string $profileId, array $eventTypes): int
    {
        return PointLedger::query()
            ->where('profile_id', $profileId)
            ->whereIn('event_type', $eventTypes)
            ->count();
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Services/GamificationWalletServiceTest.php`
Expected: All 12 tests PASS

**Step 5: Commit**

```bash
git add app/Services/GamificationWalletService.php tests/Unit/Services/GamificationWalletServiceTest.php
git commit -m "feat: add GamificationWalletService with awardPoints, evaluateBadges, and full test coverage"
```

---

## Task 6: GamificationController + Resources + Routes

**Files:**
- Create: `app/Http/Controllers/Api/V1/GamificationController.php`
- Create: `app/Http/Resources/Api/V1/WalletResource.php`
- Create: `app/Http/Resources/Api/V1/PointLedgerResource.php`
- Create: `app/Http/Resources/Api/V1/GamificationBadgeResource.php`
- Create: `app/Http/Resources/Api/V1/ReferralCodeResource.php`
- Create: `app/Http/Resources/Api/V1/WithdrawalRequestResource.php`
- Create: `app/Http/Requests/Api/V1/StoreWithdrawalRequest.php`
- Modify: `routes/api.php`

**Step 1: Create WalletResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'points' => $this->points,
            'redeemed_points' => $this->redeemed_points,
            'available_points' => $this->getAvailablePoints(),
            'eur_value' => $this->getEurValue(),
            'progress' => $this->getProgress(),
            'can_withdraw' => $this->canWithdraw(),
            'pending_withdrawal' => $this->pending_withdrawal,
            'withdrawal_threshold' => 375,
        ];
    }
}
```

**Step 2: Create PointLedgerResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PointLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PointLedger
 */
class PointLedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'points' => $this->points,
            'event_type' => $this->event_type->value,
            'description' => $this->description,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

**Step 3: Create GamificationBadgeResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\GamificationBadgeSlug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamificationBadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GamificationBadgeSlug $slug */
        $slug = $this->resource['slug'];
        $earnedBadge = $this->resource['earned_badge'] ?? null;

        return [
            'slug' => $slug->value,
            'name' => $slug->displayName(),
            'description' => $slug->description(),
            'is_unlocked' => $earnedBadge !== null,
            'earned_at' => $earnedBadge?->earned_at?->toIso8601String(),
        ];
    }
}
```

**Step 4: Create ReferralCodeResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferralCode
 */
class ReferralCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'referral_link' => config('app.url') . '/ref/' . $this->code,
            'total_conversions' => $this->total_conversions,
            'total_points_earned' => $this->total_points_earned,
        ];
    }
}
```

**Step 5: Create WithdrawalRequestResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawalRequest
 */
class WithdrawalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'points' => $this->points,
            'eur_amount' => (float) $this->eur_amount,
            'iban' => $this->getMaskedIban(),
            'account_holder' => $this->account_holder,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

**Step 6: Create StoreWithdrawalRequest form request**

Run: `php artisan make:request Api/V1/StoreWithdrawalRequest --no-interaction`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWithdrawalRequest extends FormRequest
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
            'iban' => ['required', 'string', 'min:15', 'max:50'],
            'account_holder' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iban.required' => 'IBAN is required.',
            'iban.min' => 'IBAN must be at least 15 characters.',
            'account_holder.required' => 'Account holder name is required.',
        ];
    }

    /**
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
```

**Step 7: Create GamificationController**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\GamificationBadgeSlug;
use App\Enums\PointEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWithdrawalRequest;
use App\Http\Resources\Api\V1\GamificationBadgeResource;
use App\Http\Resources\Api\V1\PointLedgerResource;
use App\Http\Resources\Api\V1\ReferralCodeResource;
use App\Http\Resources\Api\V1\WalletResource;
use App\Http\Resources\Api\V1\WithdrawalRequestResource;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Models\WithdrawalRequest;
use App\Services\GamificationWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    public function __construct(
        private readonly GamificationWalletService $walletService
    ) {}

    /**
     * GET /api/v1/gamification/wallet
     */
    public function wallet(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($profile->id);

        return response()->json([
            'success' => true,
            'data' => new WalletResource($wallet),
        ]);
    }

    /**
     * GET /api/v1/gamification/ledger
     */
    public function ledger(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $perPage = min((int) $request->query('per_page', 20), 100);

        $entries = PointLedger::query()
            ->where('profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PointLedgerResource::collection($entries),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/gamification/badges
     */
    public function badges(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $earnedBadges = EarnedBadge::query()
            ->where('profile_id', $profile->id)
            ->get()
            ->keyBy(fn (EarnedBadge $b) => $b->badge_slug->value);

        $badges = collect(GamificationBadgeSlug::cases())->map(function (GamificationBadgeSlug $slug) use ($earnedBadges) {
            return [
                'slug' => $slug,
                'earned_badge' => $earnedBadges->get($slug->value),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => GamificationBadgeResource::collection($badges),
        ]);
    }

    /**
     * GET /api/v1/gamification/referral-code
     */
    public function referralCode(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $code = ReferralCode::query()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'code' => ReferralCode::generateCode(),
                'total_conversions' => 0,
                'total_points_earned' => 0,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => new ReferralCodeResource($code),
        ]);
    }

    /**
     * POST /api/v1/gamification/withdrawal
     */
    public function withdrawal(StoreWithdrawalRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $validated = $request->validated();

        $wallet = $this->walletService->getOrCreateWallet($profile->id);

        if ($wallet->pending_withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'A withdrawal is already pending.',
            ], 409);
        }

        $availablePoints = $wallet->getAvailablePoints();
        if ($availablePoints < 375) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient points. Need 375, have {$availablePoints}.",
            ], 400);
        }

        $withdrawalRequest = DB::transaction(function () use ($profile, $wallet, $validated): WithdrawalRequest {
            $eurAmount = round(375 * 0.20, 2);

            $withdrawalRequest = WithdrawalRequest::create([
                'profile_id' => $profile->id,
                'points' => 375,
                'eur_amount' => $eurAmount,
                'iban' => $validated['iban'],
                'account_holder' => $validated['account_holder'],
            ]);

            PointLedger::create([
                'profile_id' => $profile->id,
                'points' => -375,
                'event_type' => PointEventType::Withdrawal,
                'reference_id' => $withdrawalRequest->id,
                'description' => "Withdrawal of \u{20AC}" . number_format($eurAmount, 2),
            ]);

            $wallet->increment('redeemed_points', 375);
            $wallet->update(['pending_withdrawal' => true]);

            return $withdrawalRequest;
        });

        return response()->json([
            'success' => true,
            'data' => new WithdrawalRequestResource($withdrawalRequest),
            'message' => 'Withdrawal request submitted. Processing within 5-7 business days.',
        ], 201);
    }
}
```

**Step 8: Register routes in `routes/api.php`**

Add at the end of the `auth:sanctum` middleware group (before the closing `});`), add import at top:

```php
use App\Http\Controllers\Api\V1\GamificationController;
```

Add route group inside auth middleware group:

```php
/*
|--------------------------------------------------------------------------
| Gamification - Wallet & Rewards
|--------------------------------------------------------------------------
*/

Route::get('gamification/wallet', [GamificationController::class, 'wallet'])
    ->name('api.v1.gamification.wallet');

Route::get('gamification/ledger', [GamificationController::class, 'ledger'])
    ->name('api.v1.gamification.ledger');

Route::get('gamification/badges', [GamificationController::class, 'badges'])
    ->name('api.v1.gamification.badges');

Route::get('gamification/referral-code', [GamificationController::class, 'referralCode'])
    ->name('api.v1.gamification.referral-code');

Route::post('gamification/withdrawal', [GamificationController::class, 'withdrawal'])
    ->name('api.v1.gamification.withdrawal');
```

**Step 9: Commit**

```bash
git add app/Http/Controllers/Api/V1/GamificationController.php app/Http/Resources/Api/V1/WalletResource.php app/Http/Resources/Api/V1/PointLedgerResource.php app/Http/Resources/Api/V1/GamificationBadgeResource.php app/Http/Resources/Api/V1/ReferralCodeResource.php app/Http/Resources/Api/V1/WithdrawalRequestResource.php app/Http/Requests/Api/V1/StoreWithdrawalRequest.php routes/api.php
git commit -m "feat: add GamificationController with wallet, ledger, badges, referral, withdrawal endpoints"
```

---

## Task 7: Feature Tests for All Gamification Endpoints

**Files:**
- Create: `tests/Feature/Api/V1/GamificationWalletTest.php`

**Step 1: Write feature tests**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\GamificationBadgeSlug;
use App\Enums\PointEventType;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationWalletTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/wallet
    |--------------------------------------------------------------------------
    */

    public function test_wallet_returns_auto_created_wallet_for_new_user(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/wallet');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points', 0)
            ->assertJsonPath('data.redeemed_points', 0)
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.eur_value', 0.0)
            ->assertJsonPath('data.progress', 0.0)
            ->assertJsonPath('data.can_withdraw', false)
            ->assertJsonPath('data.pending_withdrawal', false)
            ->assertJsonPath('data.withdrawal_threshold', 375);
    }

    public function test_wallet_returns_existing_wallet_with_points(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 127,
            'redeemed_points' => 0,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/wallet');

        $response->assertOk()
            ->assertJsonPath('data.points', 127)
            ->assertJsonPath('data.available_points', 127)
            ->assertJsonPath('data.eur_value', 25.4)
            ->assertJsonPath('data.can_withdraw', false);
    }

    public function test_wallet_shows_can_withdraw_true_when_eligible(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/wallet');

        $response->assertOk()
            ->assertJsonPath('data.can_withdraw', true)
            ->assertJsonPath('data.progress', 1.0);
    }

    public function test_wallet_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/gamification/wallet');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/ledger
    |--------------------------------------------------------------------------
    */

    public function test_ledger_returns_paginated_entries(): void
    {
        $profile = $this->createCommunityProfile();
        PointLedger::factory()->count(3)->forProfile($profile)->collaborationComplete()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'points', 'event_type', 'description', 'reference_id', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_ledger_returns_empty_for_user_without_entries(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_ledger_does_not_show_other_users_entries(): void
    {
        $profile = $this->createCommunityProfile();
        $otherProfile = $this->createCommunityProfile();
        PointLedger::factory()->forProfile($otherProfile)->collaborationComplete()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_ledger_pagination_works(): void
    {
        $profile = $this->createCommunityProfile();
        PointLedger::factory()->count(25)->forProfile($profile)->collaborationComplete()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/badges
    |--------------------------------------------------------------------------
    */

    public function test_badges_returns_all_5_badges(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/badges');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data');

        $slugs = collect($response->json('data'))->pluck('slug')->sort()->values()->toArray();
        $this->assertSame([
            'community_earner',
            'content_creator',
            'first_kolab',
            'power_partner',
            'referral_pioneer',
        ], $slugs);
    }

    public function test_badges_shows_unlocked_status_for_earned_badges(): void
    {
        $profile = $this->createCommunityProfile();
        EarnedBadge::factory()->forProfile($profile)->slug(GamificationBadgeSlug::FirstKolab)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/badges');

        $response->assertOk();

        $data = collect($response->json('data'));
        $firstKolab = $data->firstWhere('slug', 'first_kolab');
        $contentCreator = $data->firstWhere('slug', 'content_creator');

        $this->assertTrue($firstKolab['is_unlocked']);
        $this->assertNotNull($firstKolab['earned_at']);
        $this->assertFalse($contentCreator['is_unlocked']);
        $this->assertNull($contentCreator['earned_at']);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/referral-code
    |--------------------------------------------------------------------------
    */

    public function test_referral_code_creates_code_on_first_access(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/referral-code');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['code', 'referral_link', 'total_conversions', 'total_points_earned'],
            ])
            ->assertJsonPath('data.total_conversions', 0);

        $this->assertStringStartsWith('KOLAB-', $response->json('data.code'));
        $this->assertDatabaseHas('referral_codes', ['profile_id' => $profile->id]);
    }

    public function test_referral_code_returns_existing_code(): void
    {
        $profile = $this->createCommunityProfile();
        ReferralCode::factory()->forProfile($profile)->create(['code' => 'KOLAB-TEST']);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/referral-code');

        $response->assertOk()
            ->assertJsonPath('data.code', 'KOLAB-TEST');
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/v1/gamification/withdrawal
    |--------------------------------------------------------------------------
    */

    public function test_withdrawal_succeeds_with_enough_points(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points', 375)
            ->assertJsonPath('data.eur_amount', 75.0)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.account_holder', 'BCN Running Club SL');

        // IBAN should be masked
        $this->assertStringContainsString('****', $response->json('data.iban'));

        // Wallet should be updated
        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'redeemed_points' => 375,
            'pending_withdrawal' => true,
        ]);

        // Ledger entry should exist
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'points' => -375,
            'event_type' => 'withdrawal',
        ]);
    }

    public function test_withdrawal_fails_with_insufficient_points(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withPoints(120)->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient points. Need 375, have 120.');
    }

    public function test_withdrawal_fails_with_pending_withdrawal(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->pendingWithdrawal()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A withdrawal is already pending.');
    }

    public function test_withdrawal_fails_without_iban(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertStatus(422);
    }

    public function test_withdrawal_fails_without_account_holder(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
            ]);

        $response->assertStatus(422);
    }
}
```

**Step 2: Run feature tests**

Run: `php artisan test --compact tests/Feature/Api/V1/GamificationWalletTest.php`
Expected: All tests PASS

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/GamificationWalletTest.php
git commit -m "test: add feature tests for gamification wallet, ledger, badges, referral, withdrawal endpoints"
```

---

## Task 8: Integration — Award Points on Collaboration Completion

**Files:**
- Modify: `app/Services/CollaborationService.php`
- Create: `tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php`

**Step 1: Write failing integration test**

Create `tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CollaborationStatus;
use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationCollaborationIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    public function test_completing_collaboration_awards_points_to_both_parties(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Both parties should have received 1 point
        $this->assertDatabaseHas('wallets', [
            'profile_id' => $creator->id,
            'points' => 1,
        ]);

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $applicant->id,
            'points' => 1,
        ]);

        // Ledger entries for both parties
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $creator->id,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collaboration->id,
        ]);

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $applicant->id,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collaboration->id,
        ]);
    }

    public function test_completing_collaboration_evaluates_badges(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete");

        // Both should earn first_kolab badge
        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $creator->id,
            'badge_slug' => 'first_kolab',
        ]);

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $applicant->id,
            'badge_slug' => 'first_kolab',
        ]);
    }

    public function test_cancelling_collaboration_does_not_award_points(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/cancel", [
                'reason' => 'Schedule conflict',
            ]);

        $this->assertDatabaseMissing('wallets', ['profile_id' => $creator->id]);
        $this->assertDatabaseMissing('wallets', ['profile_id' => $applicant->id]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php`
Expected: FAIL — no wallets/ledger entries created because CollaborationService doesn't award points yet

**Step 3: Modify CollaborationService to award points on completion**

In `app/Services/CollaborationService.php`, add the dependency injection and point-awarding logic:

Add import at top:
```php
use App\Enums\PointEventType;
```

Change the constructor to inject GamificationWalletService:

```php
public function __construct(
    private readonly GamificationWalletService $walletService
) {}
```

Add import:
```php
use App\Services\GamificationWalletService;
```

Modify the `complete()` method to award points after status update:

```php
public function complete(Collaboration $collaboration, ?string $feedback = null): Collaboration
{
    if ($collaboration->isInTerminalState()) {
        throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
    }

    if (! $collaboration->canBeCompleted()) {
        throw CollaborationException::cannotComplete($collaboration->status->value);
    }

    $collaboration->update([
        'status' => CollaborationStatus::Completed,
        'completed_at' => Carbon::now(),
    ]);

    // Award points to both parties
    $this->awardCollaborationPoints($collaboration);

    return $collaboration->fresh([
        'collabOpportunity',
        'creatorProfile',
        'applicantProfile',
        'application',
    ]);
}
```

Add private method:

```php
/**
 * Award collaboration completion points to both parties.
 */
private function awardCollaborationPoints(Collaboration $collaboration): void
{
    $collaboration->loadMissing(['collabOpportunity']);
    $title = $collaboration->collabOpportunity?->title ?? 'a collaboration';

    $this->walletService->awardPoints(
        $collaboration->creator_profile_id,
        PointEventType::CollaborationComplete->defaultPoints(),
        PointEventType::CollaborationComplete,
        $collaboration->id,
        "Collaboration completed: {$title}"
    );

    $this->walletService->awardPoints(
        $collaboration->applicant_profile_id,
        PointEventType::CollaborationComplete->defaultPoints(),
        PointEventType::CollaborationComplete,
        $collaboration->id,
        "Collaboration completed: {$title}"
    );
}
```

**Step 4: Run integration tests**

Run: `php artisan test --compact tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php`
Expected: All 3 tests PASS

**Step 5: Run existing collaboration tests to ensure no regression**

Run: `php artisan test --compact tests/Feature/Api/V1/CollaborationChallengeTest.php tests/Feature/Api/V1/CollaborationQrCodeTest.php`
Expected: All existing tests still PASS

**Step 6: Commit**

```bash
git add app/Services/CollaborationService.php tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php
git commit -m "feat: award points to both parties on collaboration completion, with badge evaluation"
```

---

## Task 9: Add NotificationType for Badges + Points

**Files:**
- Modify: `app/Enums/NotificationType.php`

**Step 1: Add new notification types**

Add these cases to `NotificationType` enum in `app/Enums/NotificationType.php`:

```php
case PointsEarned = 'points_earned';
case GamificationBadgeEarned = 'gamification_badge_earned';
case WithdrawalProcessed = 'withdrawal_processed';
```

**Step 2: Commit**

```bash
git add app/Enums/NotificationType.php
git commit -m "feat: add PointsEarned, GamificationBadgeEarned, WithdrawalProcessed notification types"
```

---

## Task 10: Run Full Test Suite

**Step 1: Run all tests**

Run: `php artisan test --compact`
Expected: All tests PASS (existing 443+ tests + new tests)

**Step 2: Run Pint formatting**

Run: `vendor/bin/pint --dirty`
Expected: All files formatted correctly

**Step 3: Commit formatting fixes if any**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

---

## Summary of Created/Modified Files

| Action | File |
|--------|------|
| Create | `app/Enums/PointEventType.php` |
| Create | `app/Enums/WithdrawalStatus.php` |
| Create | `app/Enums/GamificationBadgeSlug.php` |
| Create | 5 migrations (wallets, point_ledger, earned_badges, referral_codes, withdrawal_requests) |
| Create | `app/Models/Wallet.php` |
| Create | `app/Models/PointLedger.php` |
| Create | `app/Models/EarnedBadge.php` |
| Create | `app/Models/ReferralCode.php` |
| Create | `app/Models/WithdrawalRequest.php` |
| Create | 5 factories (Wallet, PointLedger, EarnedBadge, ReferralCode, WithdrawalRequest) |
| Create | `app/Services/GamificationWalletService.php` |
| Create | `app/Http/Controllers/Api/V1/GamificationController.php` |
| Create | `app/Http/Resources/Api/V1/WalletResource.php` |
| Create | `app/Http/Resources/Api/V1/PointLedgerResource.php` |
| Create | `app/Http/Resources/Api/V1/GamificationBadgeResource.php` |
| Create | `app/Http/Resources/Api/V1/ReferralCodeResource.php` |
| Create | `app/Http/Resources/Api/V1/WithdrawalRequestResource.php` |
| Create | `app/Http/Requests/Api/V1/StoreWithdrawalRequest.php` |
| Create | `tests/Unit/Services/GamificationWalletServiceTest.php` |
| Create | `tests/Feature/Api/V1/GamificationWalletTest.php` |
| Create | `tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php` |
| Modify | `app/Models/Profile.php` (add 5 relationships) |
| Modify | `app/Services/CollaborationService.php` (inject service, award points on complete) |
| Modify | `app/Enums/NotificationType.php` (add 3 cases) |
| Modify | `routes/api.php` (add 5 gamification routes) |

## Deferred Work (Not in Scope)

- **Review system** (`review_posted` trigger): No Review model/endpoint exists. Call `awardPoints()` when built.
- **UGC submission** (`ugc_posted` trigger): No content submission endpoint. Call `awardPoints()` when built.
- **Referral tracking webhook**: Needs referral_code lookup on business signup → award referrer. Implementation depends on signup flow changes.
- **Push notifications** for badge awards / point milestones: `NotificationType` cases added, call `NotificationService::createNotification()` from `GamificationWalletService::evaluateBadges()` when ready.
