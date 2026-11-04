<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests\Unit;

use Babanoell\Auth\Support\IniSize;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

final class IniSizeTest extends TestCase
{
    public function testParsesCommonPhpIniSizeSuffixes(): void
    {
        Assertions::same(64 * 1024 * 1024, IniSize::toBytes('64M'));
        Assertions::same(8 * 1024 * 1024, IniSize::toBytes('8M'));
        Assertions::same(1024 * 1024, IniSize::toBytes('1024K'));
        Assertions::same(2 * 1024 * 1024 * 1024, IniSize::toBytes('2G'));
    }

    public function testPlainBytesAndUnlimitedValuesAreHandled(): void
    {
        Assertions::same(65536, IniSize::toBytes('65536'));
        Assertions::same(PHP_INT_MAX, IniSize::toBytes('-1'));
        Assertions::same(0, IniSize::toBytes(false));
    }
}

return new IniSizeTest();
