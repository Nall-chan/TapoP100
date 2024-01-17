<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateDiscovery(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Discovery');
    }

    public function testValidateSocket(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Socket');
    }

    public function testValidateSocketaMulti(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Sockets Multi');
    }

    public function testValidateEnergySocket(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Energy Socket');
    }

    public function testValidateLight(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Light');
    }

    public function testValidateLightColor(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Light Color');
    }
}