<?php
declare(strict_types=1);
namespace Babanoell\Auth\Authorization;
interface ScopeRepositoryInterface { /** @return array<string,mixed> */ public function forUser(string $userId): array; }
