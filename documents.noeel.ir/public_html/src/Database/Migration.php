<?php
declare(strict_types=1);
namespace Babanoell\Documents\Database;
interface Migration { public function version(): string; public function name(): string; public function up(\PDO $pdo): void; }
