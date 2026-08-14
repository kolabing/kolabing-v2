<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin panel maintainer provisioning
    |--------------------------------------------------------------------------
    |
    | The /admin panel is gated by the `maintainer` middleware
    | (EnsureAdminUserIsMaintainer -> users.is_maintainer = true). Without at
    | least one maintainer row, nobody can pass the gate and /admin is
    | effectively inaccessible.
    |
    | MaintainerSeeder provisions (or promotes + resets the password of) a
    | maintainer from the values below, so admin access is reproducible and
    | secret-driven -- the password comes from a managed secret, never a
    | plaintext argument in a shell / Cloud command log.
    |
    | Leave email/password unset in dev + CI: the seeder no-ops when either is
    | blank, so it is safe to run and safe to leave wired into a deploy command.
    | See docs/admin-access.md.
    |
    */
    'maintainer' => [
        'name' => env('ADMIN_MAINTAINER_NAME', 'Kolabing Maintainer'),
        'email' => env('ADMIN_MAINTAINER_EMAIL'),
        'password' => env('ADMIN_MAINTAINER_PASSWORD'),
    ],
];
