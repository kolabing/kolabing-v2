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
