<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateIO(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo P100');
    }

    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../Tapo P100 Discovery');
    }
}