<?php

namespace Tests\Unit;

use App\Support\PhoneNumberFormatter;
use PHPUnit\Framework\TestCase;

class PhoneNumberFormatterTest extends TestCase
{
    public function test_it_keeps_extensions_visible_without_appending_them_to_the_main_dialled_number(): void
    {
        $number = '+1 (604) 261-5310 ext 205';

        $this->assertSame('+16042615310', PhoneNumberFormatter::dialable($number));
        $this->assertSame('+16042615310;ext=205', PhoneNumberFormatter::telUri($number));
        $this->assertSame('16042615310', PhoneNumberFormatter::comparisonKey($number));
    }
}
