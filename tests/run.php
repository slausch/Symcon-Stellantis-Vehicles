<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/MyOpelProvider.php';
require_once __DIR__ . '/../libs/VehicleStatusMapper.php';

function expect(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf('%s: expected %s, got %s', $label, var_export($expected, true), var_export($actual, true)));
    }
}

IPS\ModuleLoader::loadSingleModule(
    __DIR__ . '/../StellantisAccount',
    '{E4053D3C-5E32-417E-A8CF-2C8831C1D7C9}'
);
IPS\ModuleLoader::loadSingleModule(
    __DIR__ . '/../StellantisVehicle',
    '{E4053D3C-5E32-417E-A8CF-2C8831C1D7C9}'
);
expect(IPS_ModuleExists('{DF16825B-E212-4F22-9648-FC5F1825B504}'), true, 'Account module registration');
expect(IPS_ModuleExists('{55719996-CD7E-4825-8B64-294601469EB5}'), true, 'Vehicle module registration');

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
