<?php
declare(strict_types=1);

/**
 * Environment loader for the identity service.
 *
 * Resolution order (first non-empty value wins):
 *   1. config/local.env.php (installer-generated; authoritative on file deployments)
 *   2. Real process environment (getenv / $_ENV / $_SERVER)
 *   3. .env in the service root
 *
 * Important for shared cPanel hosting:
 * some providers disable putenv() and some inject stale AUTH_* variables at the
 * Apache/PHP layer. The installer-generated local.env.php must therefore win
 * whenever it exists. Call babanoell_auth_env() to read effective values.
 */

/**
 * @return array<string,string>
 */
function babanoell_auth_local_env_values(): array
{
    static $values = null;

    if (is_array($values)) {
        return $values;
    }

    $values = [];
    $serviceRoot = dirname(__DIR__);
    $localConfigFile = $serviceRoot . '/config/local.env.php';

    if (!is_file($localConfigFile) || !is_readable($localConfigFile)) {
        return $values;
    }

    $localValues = require $localConfigFile;

    if (!is_array($localValues)) {
        return $values;
    }

    foreach ($localValues as $key => $value) {
        if (!is_string($key) || (!is_string($value) && !is_int($value))) {
            continue;
        }

        $key = trim($key);
        $value = trim((string) $value);

        if ($key !== '' && $value !== '') {
            $values[$key] = $value;
        }
    }

    return $values;
}

/**
 * @return array<string,string>
 */
function babanoell_auth_dotenv_values(): array
{
    static $values = null;

    if (is_array($values)) {
        return $values;
    }

    $values = [];
    $serviceRoot = dirname(__DIR__);
    $envFile = $serviceRoot . '/.env';

    if (!is_file($envFile) || !is_readable($envFile)) {
        return $values;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return $values;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $separator = strpos($line, '=');

        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($key === '') {
            continue;
        }

        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            $value = substr($value, 1, -1);
        }

        if ($value !== '') {
            $values[$key] = $value;
        }
    }

    return $values;
}

/**
 * Backward-compatible merged file values. local.env.php always wins over .env.
 *
 * @return array<string,string>
 */
function babanoell_auth_file_env_values(): array
{
    return babanoell_auth_local_env_values() + babanoell_auth_dotenv_values();
}

function babanoell_auth_env(string $key, ?string $default = null): ?string
{
    $key = trim($key);

    if ($key === '') {
        return $default;
    }

    // cPanel/file deployment contract: installer-generated local config is
    // authoritative. This prevents stale Apache/PHP AUTH_* values from routing
    // the runtime to a different database after a successful installer write.
    $localValues = babanoell_auth_local_env_values();

    if (isset($localValues[$key]) && trim($localValues[$key]) !== '') {
        return $localValues[$key];
    }

    if (function_exists('getenv')) {
        $value = getenv($key);

        if ($value !== false && trim((string) $value) !== '') {
            return (string) $value;
        }
    }

    foreach ([$_ENV, $_SERVER] as $source) {
        if (!array_key_exists($key, $source)) {
            continue;
        }

        $value = $source[$key];

        if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
            return (string) $value;
        }
    }

    $dotenvValues = babanoell_auth_dotenv_values();

    if (isset($dotenvValues[$key]) && trim($dotenvValues[$key]) !== '') {
        return $dotenvValues[$key];
    }

    return $default;
}

/**
 * Compatibility/bootstrap helper.
 *
 * Effective values are mirrored to $_ENV / $_SERVER. Installer-generated
 * local.env.php values overwrite stale process/superglobal values. putenv() is
 * best-effort only; the application itself reads through babanoell_auth_env().
 */
function babanoell_auth_load_env(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }
    $loaded = true;

    $localValues = babanoell_auth_local_env_values();
    $dotenvValues = babanoell_auth_dotenv_values();
    $keys = array_unique(array_merge(array_keys($localValues), array_keys($dotenvValues)));

    foreach ($keys as $key) {
        $value = babanoell_auth_env($key);

        if ($value === null || trim($value) === '') {
            continue;
        }

        $isAuthoritativeLocal = array_key_exists($key, $localValues);
        $hasSuperglobalValue = (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '')
            || (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '');
        $hasProcessValue = false;

        if (function_exists('getenv')) {
            $current = getenv($key);
            $hasProcessValue = $current !== false && trim((string) $current) !== '';
        }

        if ($isAuthoritativeLocal || (!$hasProcessValue && !$hasSuperglobalValue)) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;

            if (function_exists('putenv')) {
                @putenv($key . '=' . $value);
            }
        }
    }
}
