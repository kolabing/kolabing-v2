<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GoogleAuthService;
use Tests\TestCase;

class GoogleAuthServiceTest extends TestCase
{
    public function test_verification_client_ids_include_the_web_client_when_configured(): void
    {
        config([
            'services.google.client_id' => 'primary.apps.googleusercontent.com',
            'services.google.client_id_ios' => 'ios.apps.googleusercontent.com',
            'services.google.client_id_android' => 'android.apps.googleusercontent.com',
            'services.google.client_id_web' => 'web.apps.googleusercontent.com',
        ]);

        $ids = (new GoogleAuthService)->verificationClientIds();

        // Web audience is accepted (so Google Identity Services on kolabing.com works),
        // and the primary client stays first (it is set as the default on the client).
        $this->assertContains('web.apps.googleusercontent.com', $ids);
        $this->assertSame('primary.apps.googleusercontent.com', $ids[0]);
        $this->assertSame([
            'primary.apps.googleusercontent.com',
            'ios.apps.googleusercontent.com',
            'android.apps.googleusercontent.com',
            'web.apps.googleusercontent.com',
        ], $ids);
    }

    public function test_verification_client_ids_skip_unconfigured_clients(): void
    {
        config([
            'services.google.client_id' => 'primary.apps.googleusercontent.com',
            'services.google.client_id_ios' => null,
            'services.google.client_id_android' => null,
            'services.google.client_id_web' => 'web.apps.googleusercontent.com',
        ]);

        $ids = (new GoogleAuthService)->verificationClientIds();

        $this->assertSame([
            'primary.apps.googleusercontent.com',
            'web.apps.googleusercontent.com',
        ], $ids);
    }
}
