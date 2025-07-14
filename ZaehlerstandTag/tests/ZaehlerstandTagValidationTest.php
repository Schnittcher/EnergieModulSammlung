<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class ZaehlerstandTagValidationTest extends TestCaseSymconValidation
{
    public function testValidateZaehlerstandTag(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateReadingDayModule(): void
    {
        $this->validateModule(__DIR__ . '/../ReadingDay');
    }
}