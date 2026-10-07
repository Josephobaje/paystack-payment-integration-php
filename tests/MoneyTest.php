<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public static function amounts(): array
    {
        return [['1', 100], ['150000', 15000000], ['99.9', 9990], ['0.10', null], ['2500.55', 250055], [1200, 120000]];
    }

    #[DataProvider('amounts')]
    public function testToKobo(string|int $naira, ?int $kobo): void
    {
        if ($kobo === null) {
            $this->expectException(InvalidArgumentException::class);
        }
        $this->assertSame($kobo, Money::toKobo($naira));
    }

    public function testRejectsGarbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toKobo('12.345');
    }

    public function testFormat(): void
    {
        $this->assertSame('₦150,000.00', Money::format(15000000));
        $this->assertSame('$12.50', Money::format(1250, 'USD'));
    }
}
