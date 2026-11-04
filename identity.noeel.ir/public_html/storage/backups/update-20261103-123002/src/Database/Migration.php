<?php
declare(strict_types=1);

namespace Babanoell\Auth\Database;

use PDO;

interface Migration
{
    public function version(): string;

    public function name(): string;

    public function up(PDO $pdo): void;
}
