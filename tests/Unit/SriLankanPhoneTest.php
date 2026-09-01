<?php

namespace Tests\Unit;

use App\Support\SriLankanPhone;
use PHPUnit\Framework\TestCase;

class SriLankanPhoneTest extends TestCase
{
    public function test_normalizes_local_mobile_format(): void
    {
        $this->assertSame('+94771234567', SriLankanPhone::normalize('0771234567'));
    }

    public function test_normalizes_international_format(): void
    {
        $this->assertSame('+94771234567', SriLankanPhone::normalize('94 77 123 4567'));
        $this->assertSame('+94771234567', SriLankanPhone::normalize('+94771234567'));
    }

    public function test_normalizes_nine_digit_mobile_without_prefix(): void
    {
        $this->assertSame('+94771234567', SriLankanPhone::normalize('771234567'));
    }

    public function test_rejects_numbers_that_are_not_recognisable_mobiles(): void
    {
        $this->assertNull(SriLankanPhone::normalize('123'));
        $this->assertNull(SriLankanPhone::normalize('555 0100'));
        $this->assertNull(SriLankanPhone::normalize(''));
        $this->assertNull(SriLankanPhone::normalize(null));
    }

    public function test_same_compares_normalized_numbers(): void
    {
        $this->assertTrue(SriLankanPhone::same('0771234567', '+94 77 123 4567'));
        $this->assertFalse(SriLankanPhone::same('0771234567', '0761234567'));
        $this->assertFalse(SriLankanPhone::same(null, '0771234567'));
    }
}
