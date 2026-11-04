<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Babanoell\Auth\UseCases\Login\SessionIssueResult;

interface SessionRotatorInterface
{
    /**
     * Rotates an active session: revokes the current row and creates a
     * successor row in the same token family with fresh credentials.
     */
    public function rotate(SessionRecord $session, ?LoginSecurityContext $context = null): SessionIssueResult;
}
