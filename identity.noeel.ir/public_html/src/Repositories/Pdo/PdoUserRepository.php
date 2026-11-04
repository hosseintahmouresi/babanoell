<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories\Pdo;

use Babanoell\Auth\Models\User;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $userId): ?User
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : User::fromRow($row);
    }

    public function findByLoginIdentifier(string $identifier): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM users WHERE email = :email_identifier OR mobile = :mobile_identifier LIMIT 1'
        );
        $statement->execute([
            'email_identifier' => $identifier,
            'mobile_identifier' => $identifier,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : User::fromRow($row);
    }
}
