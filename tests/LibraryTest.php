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

    public function testValidateP100(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo P100');
    }

    public function testValidateP110(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo P110');
    }

    public function testValidateLight(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo Light');
    }
}