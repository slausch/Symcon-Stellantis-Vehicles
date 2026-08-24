<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/stubs/autoload.php';
include_once __DIR__ . '/../StellantisAccount/module.php';
include_once __DIR__ . '/../StellantisVehicle/module.php';

final class TestableStellantisAccount extends StellantisAccount
{
    protected function getTime(): int
    {
        return 0;
    }
}

final class StellantisInstantiationTest extends TestCase
{
    private const LIBRARY_ID = '{E4053D3C-5E32-417E-A8CF-2C8831C1D7C9}';
    private const ACCOUNT_ID = '{DF16825B-E212-4F22-9648-FC5F1825B504}';
    private const VEHICLE_ID = '{55719996-CD7E-4825-8B64-294601469EB5}';

    protected function setUp(): void
    {
        IPS\Kernel::reset();
        IPS_CreateVariableProfile('~UnixTimestamp', VARIABLETYPE_INTEGER);
        IPS\ModuleLoader::loadSingleModule(__DIR__ . '/../StellantisAccount', self::LIBRARY_ID);
        IPS\ModuleLoader::loadSingleModule(__DIR__ . '/../StellantisVehicle', self::LIBRARY_ID);
    }

    public function testBothModulesCanBeCreated(): void
    {
        $accountModule = IPS_GetModule(self::ACCOUNT_ID);
        $accountModule['Class'] = TestableStellantisAccount::class;
        $accountId = IPS\ObjectManager::registerObject(1);
        IPS\InstanceManager::createInstance($accountId, $accountModule);

        $vehicleId = IPS_CreateInstance(self::VEHICLE_ID);

        self::assertTrue(IPS_InstanceExists($accountId));
        self::assertTrue(IPS_InstanceExists($vehicleId));
        self::assertSame($accountId, IPS_GetInstance($vehicleId)['ConnectionID']);
    }
}
