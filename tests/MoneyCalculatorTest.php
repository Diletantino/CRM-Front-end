<?php

use Espo\Modules\StudioManagement\Tools\MoneyCalculator;
use PHPUnit\Framework\TestCase;

final class MoneyCalculatorTest extends TestCase
{
    public function testAmountAndShareAreCalculatedInIntegerCents(): void
    {
        $calculator = new MoneyCalculator();

        self::assertSame(123456, $calculator->amountToCents('1234.56'));
        self::assertSame(15432, $calculator->share(123456, $calculator->percentToUnits('12.5')));
        self::assertSame('154.32', $calculator->centsToAmount(15432));
    }

    public function testFractionalPercentIsRoundedHalfUpToCent(): void
    {
        $calculator = new MoneyCalculator();

        self::assertSame(3333, $calculator->share(10000, $calculator->percentToUnits('33.3333')));
    }

    public function testRejectsMoreThanTwoAmountDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyCalculator())->amountToCents('1.001');
    }

    public function testRejectsPercentageAboveOneHundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyCalculator())->percentToUnits('100.0001');
    }
}
