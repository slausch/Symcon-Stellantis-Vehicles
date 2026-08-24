<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

final class StellantisValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateAccountModule(): void
    {
        $this->validateModule(__DIR__ . '/../StellantisAccount');
    }

    public function testValidateVehicleModule(): void
    {
        $this->validateModule(__DIR__ . '/../StellantisVehicle');
    }
}
