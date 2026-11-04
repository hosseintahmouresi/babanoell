<?php
declare(strict_types=1);
namespace Babanoell\Loans\Database;

use Babanoell\Loans\App\Config;
use PDO;

final class Connection
{
    public static function fromConfig(Config $config): PDO
    {
        $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$config->dbHost(),$config->dbPort(),$config->dbName(),$config->dbCharset());
        return new PDO($dsn,$config->dbUser(),$config->dbPassword(),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
    }
}
