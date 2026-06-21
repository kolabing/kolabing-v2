<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AppleIAPService;
use Firebase\JWT\JWT;
use Tests\TestCase;

class AppleIAPServiceTest extends TestCase
{
    /**
     * Regression for PHP-LARAVEL-6: a real Apple notification payload has nested
     * objects (e.g. `data.signedTransactionInfo`). decodeSignedJwt() must return
     * a *deeply* associative array, not a top-level array wrapping stdClass —
     * otherwise $notification['data']['signedTransactionInfo'] throws
     * "Cannot use object of type stdClass as array".
     */
    public function test_decode_signed_jwt_returns_a_deeply_associative_array(): void
    {
        if (! function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('openssl extension required.');
        }

        [$privatePem, $x5c] = $this->makeEs256KeyAndCert();

        // Synthetic payload shaped like an Apple App Store Server notification —
        // no real transaction data (security: never embed real event payloads).
        $payload = [
            'notificationType' => 'TEST',
            'subtype' => 'INITIAL_BUY',
            'data' => [
                'signedTransactionInfo' => 'inner.transaction.jws',
                'environment' => 'Sandbox',
                'nested' => ['deep' => 'value'],
            ],
        ];

        $jws = JWT::encode($payload, $privatePem, 'ES256', null, ['x5c' => [$x5c]]);

        $result = app(AppleIAPService::class)->decodeSignedJwt($jws);

        $this->assertIsArray($result);
        // The crux: nested claims are arrays, not stdClass — array access is safe.
        $this->assertIsArray($result['data']);
        $this->assertSame('inner.transaction.jws', $result['data']['signedTransactionInfo']);
        $this->assertIsArray($result['data']['nested']);
        $this->assertSame('value', $result['data']['nested']['deep']);
        $this->assertSame('TEST', $result['notificationType']);
    }

    /**
     * Regression for the Sentry warning
     * "file_get_contents(.../storage/app/apple/AuthKey.p8): Failed to open stream":
     * the key may be supplied inline via APPLE_PRIVATE_KEY (config services.apple.private_key)
     * so containerised deploys do not depend on an on-disk file.
     */
    public function test_resolve_private_key_prefers_inline_key_contents(): void
    {
        config([
            'services.apple.private_key' => "-----BEGIN PRIVATE KEY-----\ninline\n-----END PRIVATE KEY-----",
            'services.apple.private_key_path' => '/nonexistent/AuthKey.p8',
        ]);

        $key = $this->invokeResolvePrivateKey();

        $this->assertStringContainsString('inline', $key);
    }

    public function test_resolve_private_key_reads_from_path_when_no_inline_key(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'p8');
        file_put_contents($path, "-----BEGIN PRIVATE KEY-----\nfromfile\n-----END PRIVATE KEY-----");

        config([
            'services.apple.private_key' => null,
            'services.apple.private_key_path' => $path,
        ]);

        try {
            $key = $this->invokeResolvePrivateKey();
        } finally {
            @unlink($path);
        }

        $this->assertStringContainsString('fromfile', $key);
    }

    /**
     * The crux of the Sentry fix: a missing key file must NOT trigger a raw
     * file_get_contents() E_WARNING — it must fail with a clear RuntimeException
     * and produce no PHP warning for Sentry to capture.
     */
    public function test_resolve_private_key_throws_clean_exception_without_php_warning_when_file_missing(): void
    {
        config([
            'services.apple.private_key' => null,
            'services.apple.private_key_path' => '/nonexistent/path/AuthKey.p8',
        ]);

        $warnings = [];
        set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $this->invokeResolvePrivateKey();
            $this->fail('Expected RuntimeException for missing key file.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Apple private key', $e->getMessage());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'Missing key file must not emit a PHP warning.');
    }

    private function invokeResolvePrivateKey(): string
    {
        $service = app(AppleIAPService::class);
        $method = new \ReflectionMethod($service, 'resolvePrivateKey');

        return $method->invoke($service);
    }

    /**
     * Generate an EC P-256 key pair and a matching self-signed X.509 leaf cert,
     * returning [private key PEM, base64 DER cert for the JWS x5c header].
     *
     * @return array{0: string, 1: string}
     */
    private function makeEs256KeyAndCert(): array
    {
        $pkey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($pkey, 'Failed to generate EC key pair.');

        openssl_pkey_export($pkey, $privatePem);

        $csr = openssl_csr_new(['commonName' => 'Test Apple Leaf'], $pkey, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);

        $der = base64_decode((string) preg_replace(
            '/-----(BEGIN|END) CERTIFICATE-----|\s+/',
            '',
            $certPem
        ));

        return [$privatePem, base64_encode($der)];
    }
}
