<?php

declare(strict_types=1);

require_once __DIR__ . '/ips_stubs.php';
require_once __DIR__ . '/../libs/MyOpelProvider.php';
require_once __DIR__ . '/../libs/VehicleStatusMapper.php';
require_once __DIR__ . '/../StellantisAccount/module.php';
require_once __DIR__ . '/../StellantisVehicle/module.php';

function expect(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf('%s: expected %s, got %s', $label, var_export($expected, true), var_export($actual, true)));
    }
}

expect(class_exists('StellantisAccount'), true, 'Account module class loading');
expect(class_exists('StellantisVehicle'), true, 'Vehicle module class loading');

$code = MyOpelProvider::extractAuthorizationCode('mymopsdk://oauth2redirect/de-de?code=abc123&scope=openid');
expect($code, 'abc123', 'OAuth redirect parsing');

$provider = new MyOpelProvider('test-client-id', 'test-client-secret', 'DE');
if (!str_starts_with($provider->getAuthorizationUrl(), 'https://idpcvs.opel.com/')) {
    throw new RuntimeException('Unexpected authorization endpoint');
}
if (!str_contains($provider->getAuthorizationUrl(), 'client_id=test-client-id')) {
    throw new RuntimeException('Configured client ID is missing from authorization URL');
}

$fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/status-electric.json'), true, 512, JSON_THROW_ON_ERROR);
$mapped = VehicleStatusMapper::map($fixture);
expect($mapped['SOC'], 63.0, 'SOC');
expect($mapped['Range'], 278.0, 'Range');
expect($mapped['ChargingPlugged'], true, 'Plugged');
expect($mapped['Charging'], true, 'Charging');
expect($mapped['ChargingRemainingMinutes'], 90, 'Remaining time');
expect($mapped['Preconditioning'], true, 'Preconditioning');
expect($mapped['DoorsLocked'], true, 'Doors');
expect($mapped['Latitude'], 50.1109, 'Latitude');
expect($mapped['Longitude'], 8.6821, 'Longitude');

echo "All tests passed.\n";
