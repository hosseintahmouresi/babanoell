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

        require_once $root.'/src/Platform/InstallationRepair.php';
        $runtime=getenv('NOEEL_SERVICE_SHARED_SECRET');
        $effective=$runtime!==false&&trim((string)$runtime)!==''?(string)$runtime:(string)($values['NOEEL_SERVICE_SHARED_SECRET']??'');
        $repair=\Babanoell\Panel\Platform\InstallationRepair::resolve($root,'panel',$values,$effective);
        $GLOBALS['babanoell_panel_service_auth_repair_status']=$repair['status'];
        if($repair['secret']!==''&&trim($effective)==='')$values['NOEEL_SERVICE_SHARED_SECRET']=$repair['secret'];
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
