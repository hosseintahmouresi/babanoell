<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests\Unit;

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\Update\UpdateTool;

final class UiAssetsTest extends TestCase
{
    private const BRAND_LOGO_SHA256 = '9930573d696809165d4cf66ce96b0ce750579397b6abe82d3ea81b00fcbfb69b';

    public function testAdminViewsDoNotEmbedCssOrInlineStyleAttributes(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (array_filter(['public/update.php', 'public/install.php'], static fn (string $relative): bool => is_file($root . '/' . $relative)) as $relative) {
            $source = (string) file_get_contents($root . '/' . $relative);
            Assertions::false(str_contains($source, '<style'), $relative . ' must not embed a style block.');
            Assertions::false(str_contains($source, 'style="'), $relative . ' must not use inline style attributes.');
        }
    }

    public function testAdminViewsUseExternalVersionedUiAssets(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (array_filter(['public/update.php', 'public/install.php'], static fn (string $relative): bool => is_file($root . '/' . $relative)) as $relative) {
            $source = (string) file_get_contents($root . '/' . $relative);
            Assertions::contains('ui-asset.php?file=babanoell-ui.css&amp;v=1.4.1', $source);
            Assertions::contains('ui-asset.php?file=babanoell-ui.js&amp;v=1.4.1', $source);
            Assertions::contains('ui-asset.php?file=brand-logo.png&amp;v=1.4.1', $source);
        }
    }

    public function testCentralStylesheetContainsApprovedBrandPalette(): void
    {
        $root = dirname(__DIR__, 2);
        $css = (string) file_get_contents($root . '/public/assets/babanoell-ui.css');

        Assertions::contains('#1D3559', $css);
        Assertions::contains('#D0E2F2', $css);
        Assertions::contains('#EDA900', $css);
    }

    public function testExactApprovedBrandLogoIsBundled(): void
    {
        $root = dirname(__DIR__, 2);
        $actual = hash_file('sha256', $root . '/public/assets/brand-logo.png');

        Assertions::same(self::BRAND_LOGO_SHA256, $actual);
    }

    public function testUiAssetGatewayIsInstallableByUpdater(): void
    {
        Assertions::same('public/ui-asset.php', UpdateTool::entryTarget('identity/public/ui-asset.php'));
        Assertions::same('public/assets/babanoell-ui.css', UpdateTool::entryTarget('identity/public/assets/babanoell-ui.css'));
        Assertions::same('public/assets/babanoell-ui.js', UpdateTool::entryTarget('identity/public/assets/babanoell-ui.js'));
        Assertions::same('public/assets/brand-logo.png', UpdateTool::entryTarget('identity/public/assets/brand-logo.png'));
        Assertions::same('src/Support/PersianDateTime.php', UpdateTool::entryTarget('identity/src/Support/PersianDateTime.php'));
        Assertions::same('src/Support/IniSize.php', UpdateTool::entryTarget('identity/src/Support/IniSize.php'));
    }
}

return new UiAssetsTest();
