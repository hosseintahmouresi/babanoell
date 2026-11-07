<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Platform;

/** Local icons are available before login, installation, or database access. */
final class Favicon
{
    public static function head(bool $javascript = true): string
    {
        $version = (string) (require dirname(__DIR__, 2).'/config/version.php')['ui'];
        $url = static fn(string $file): string => htmlspecialchars(
            '/ui-asset.php?file='.rawurlencode($file).'&v='.rawurlencode($version), ENT_QUOTES, 'UTF-8');
        return '<link rel="icon" type="image/x-icon" sizes="16x16 32x32 48x48" href="'.$url('favicon.ico').'" data-bn-favicon-fallback>'
            .'<link rel="icon" type="image/png" sizes="32x32" href="'.$url('favicon-navy.png').'" data-bn-favicon-fallback>'
            .'<link rel="icon" type="image/gif" sizes="32x32" media="(prefers-reduced-motion: no-preference)" href="'.$url('favicon.gif').'" data-bn-favicon>'
            .'<link rel="apple-touch-icon" sizes="180x180" href="'.$url('apple-touch-icon.png').'">'
            .($javascript ? '<script defer src="'.$url('favicon.js').'"></script>' : '');
    }
}
