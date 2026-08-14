# Admin panel access (`/admin`)

The operator panel at `https://kolabing.com/admin` is **not** a public page and is
**not broken** when it "won't open" — it is a gated Laravel + AdminLTE panel.

## How the gate works (code truth)

- `GET /admin` → `302` → `/admin/login` for any unauthenticated visitor
  (`routes/web.php`, `guest:admin`). Landing on the login screen is expected.
- Every operator route is behind `middleware(['auth:admin', 'maintainer'])`.
- The `maintainer` middleware (`App\Http\Middleware\EnsureAdminUserIsMaintainer`)
  `abort(403)` unless `Auth::user('admin')->isMaintainer()`.
- `isMaintainer()` is simply the boolean column **`users.is_maintainer`**.
- The `admin` guard uses the **same `users` table** (`config/auth.php`), so any
  user may *attempt* login, but `Admin\AuthController@store` logs out and rejects
  ("This account is not allowed to access the admin panel.") unless
  `is_maintainer` is true.

**Therefore:** if no user has `is_maintainer = true`, nobody can pass the gate and
`/admin` looks dead. There is no seeder that creates one by default, so a fresh
database has **zero** maintainers.

## Provision a maintainer (the fix)

### Preferred — secret-driven (Laravel Cloud)

1. In the Cloud dashboard set env vars on the **production (`master`) environment**:
   - `ADMIN_MAINTAINER_NAME`
   - `ADMIN_MAINTAINER_EMAIL`
   - `ADMIN_MAINTAINER_PASSWORD`  (a strong password — this is the login password)
2. Run once in the **Commands** tab:
   ```
   php artisan db:seed --class=MaintainerSeeder --force
   ```
   `MaintainerSeeder` is idempotent (`updateOrCreate` on email): it **creates** the
   account if missing, or **promotes + resets the password** of an existing one.
   The password is hashed by the `User` `password` cast.
3. (Optional, self-healing) append to the `master` deploy command so a maintainer
   always exists after a deploy:
   ```
   php artisan migrate --force && php artisan db:seed --class=MaintainerSeeder --force
   ```

### Quick one-off (equivalent)

```
php artisan admin:create-maintainer "Full Name" "email@kolabing.com" "the-password"
```

Same result; use when you don't want to set the env vars. (Note the password is a
plaintext argument here, so it lands in the command log — prefer the seeder for
anything long-lived.)

## Verify

Open `https://kolabing.com/admin/login`, sign in with the email + password above.
Success redirects to the users dashboard (`admin.users.index`). A `403` after login
means the account exists but `is_maintainer` is still false — re-run the seeder.
