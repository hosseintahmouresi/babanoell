<?php
declare(strict_types=1);

if (!function_exists('babanoell_panel_load_env')) {
    /** @return array<string,string> */
    function babanoell_panel_load_env(): array
    {
        static $loaded = null;
        if (is_array($loaded)) {
            return $loaded;
        }

        $root = dirname(__DIR__);
        $values = [];
        $local = $root . '/config/local.env.php';
        if (is_file($local)) {
            $candidate = require $local;
            if (is_array($candidate)) {
                foreach ($candidate as $key => $value) {
                    if (is_string($key) && (is_scalar($value) || $value === null)) {
                        $values[$key] = (string) $value;
                    }
                }
            }
        }

        $dotenv = $root . '/config/.env';
        if (is_file($dotenv)) {
            foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if ($key !== '' && !array_key_exists($key, $values)) {
                    $values[$key] = trim($value, "\"'");
                }
            }
        }

        $loaded = $values;
        return $loaded;
    }
}

if (!function_exists('babanoell_panel_env')) {
    function babanoell_panel_env(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false && trim((string) $real) !== '') {
            return (string) $real;
        }
        $values = babanoell_panel_load_env();
        return $values[$key] ?? $default;
    }
}
