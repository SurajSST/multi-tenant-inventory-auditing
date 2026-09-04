<?php

namespace Tests\Unit;

use App\Support\FiscalYear;
use App\Support\Money;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_money_formats_with_south_asian_grouping(): void
    {
        $this->assertSame('Rs. 1,23,456.00', Money::npr(123456));
        $this->assertSame('Rs. 15,000.00', Money::npr(15000));
        $this->assertSame('Rs. 100.00', Money::npr(100));
        $this->assertSame('Rs. 0.00', Money::npr(0));
        $this->assertSame('Rs. -3,500.00', Money::npr(-3500));
        $this->assertSame('Rs. 1,00,00,000.00', Money::npr(10000000));
    }

    public function test_money_arithmetic_does_not_lose_paisa(): void
    {
        // 0.1 + 0.2 is the classic float trap.
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertTrue(Money::eq(Money::add('0.10', '0.20'), '0.30'));

        $this->assertSame('4500.00', Money::mul('1500', 3));
        $this->assertSame('3500.00', Money::sub('63500', '60000'));
        $this->assertSame('3500.00', Money::abs('-3500'));
        $this->assertTrue(Money::isZero('0.00'));
        $this->assertSame('60000.00', Money::sum(['15000', '25000', '20000']));
    }

    public function test_money_comparisons(): void
    {
        $this->assertTrue(Money::gt('20000', '15000'));
        $this->assertTrue(Money::lte('15000', '15000'));
        $this->assertTrue(Money::lt('99.99', '100'));
        $this->assertFalse(Money::gt('15000', '15000'));
    }

    public function test_the_fiscal_year_rolls_on_sixteen_july(): void
    {
        // Before Shrawan 1
        $this->assertSame('2081/82', FiscalYear::label(Carbon::create(2025, 7, 15)));
        // On and after Shrawan 1
        $this->assertSame('2082/83', FiscalYear::label(Carbon::create(2025, 7, 16)));
        $this->assertSame('2082/83', FiscalYear::label(Carbon::create(2025, 12, 31)));
        $this->assertSame('2082/83', FiscalYear::label(Carbon::create(2026, 7, 15)));
        $this->assertSame('2083/84', FiscalYear::label(Carbon::create(2026, 7, 16)));
    }

    public function test_the_reference_year_is_the_start_of_the_fiscal_year(): void
    {
        $this->assertSame('2083', FiscalYear::startYear(Carbon::create(2026, 9, 1)));
    }
}
