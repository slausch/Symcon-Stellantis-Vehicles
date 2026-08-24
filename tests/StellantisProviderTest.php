<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/MyOpelProvider.php';
require_once __DIR__ . '/../libs/VehicleStatusMapper.php';

final class StellantisProviderTest extends TestCase
{
    public function testAuthorizationRedirectIsParsed(): void
    {
        $redirect = 'mymopsdk://oauth2redirect/de-de?code=abc123&scope=openid';

        self::assertSame('abc123', MyOpelProvider::extractAuthorizationCode($redirect));
    }

    public function testConfiguredClientIsUsedForAuthorization(): void
    {
        $provider = new MyOpelProvider('test-client-id', 'test-client-secret', 'DE');

        self::assertStringContainsString('client_id=test-client-id', $provider->getAuthorizationUrl());
        self::assertSame(base64_encode('test-client-id:test-client-secret'), $provider->getBasicAuthorization());
    }

    public function testElectricVehicleStatusIsMapped(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(__DIR__ . '/fixtures/status-electric.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $mapped = VehicleStatusMapper::map($fixture);

        self::assertSame(63.0, $mapped['SOC']);
        self::assertSame(278.0, $mapped['Range']);
        self::assertTrue($mapped['ChargingPlugged']);
        self::assertTrue($mapped['Charging']);
        self::assertSame(90, $mapped['ChargingRemainingMinutes']);
        self::assertTrue($mapped['Preconditioning']);
    }
}
