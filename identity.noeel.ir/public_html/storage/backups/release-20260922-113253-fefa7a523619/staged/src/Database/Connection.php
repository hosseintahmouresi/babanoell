<?php
declare(strict_types=1);

namespace Babanoell\Auth\Database;

use InvalidArgumentException;
use PDO;

final class Connection
{
    /**
     * @param array{driver:string,host:?string,port:int,database:?string,username:?string,password:?string,charset:?string} $config
     */
    public static function fromConfig(array $config): PDO
    {
        if (($config['driver'] ?? '') !== 'mysql') {
            throw new InvalidArgumentException('Only mysql PDO connections are supported by auth-service migrations.');
        }

        foreach (['host', 'database', 'username'] as $requiredKey) {
            if (!isset($config[$requiredKey]) || $config[$requiredKey] === '') {
                throw new InvalidArgumentException("Missing required auth database config value: {$requiredKey}");
            }
        }

        $charset = $config['charset'] ?: 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $charset
        );

        return new PDO($dsn, (string) $config['username'], (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
