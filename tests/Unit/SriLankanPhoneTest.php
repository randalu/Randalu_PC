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

    public function test_rejects_landlines_and_non_mobile_ranges(): void
    {
        // Colombo / Kandy area-code landlines cannot receive SMS OTPs.
        $this->assertNull(SriLankanPhone::normalize('0112345678'));
        $this->assertNull(SriLankanPhone::normalize('0812345678'));
        $this->assertNull(SriLankanPhone::normalize('+94112345678'));
        $this->assertNull(SriLankanPhone::normalize('0381234567'));
    }

    public function test_accepts_all_07x_mobile_ranges(): void
    {
        $this->assertSame('+94701234567', SriLankanPhone::normalize('0701234567'));
        $this->assertSame('+94711234567', SriLankanPhone::normalize('071 123 4567'));
        $this->assertSame('+94741234567', SriLankanPhone::normalize('+94741234567'));
        $this->assertSame('+94781234567', SriLankanPhone::normalize('781234567'));
    }

    public function test_same_compares_normalized_numbers(): void
    {
        $this->assertTrue(SriLankanPhone::same('0771234567', '+94 77 123 4567'));
        $this->assertFalse(SriLankanPhone::same('0771234567', '0761234567'));
        $this->assertFalse(SriLankanPhone::same(null, '0771234567'));
    }
}
