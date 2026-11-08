<?php
declare(strict_types=1);

namespace Babanoell\Auth\Audit;

enum AuditEventType: string
{
    case ApplicantRegistered = 'auth.applicant.registered';
    case SmartSettingsChanged = 'auth.smart_settings.changed';
    case LoginAttempt = 'auth.login.attempt';
    case LoginSucceeded = 'auth.login.succeeded';
    case LoginFailed = 'auth.login.failed';
    case LogoutRequested = 'auth.logout.requested';
    case TokenRefreshRequested = 'auth.token_refresh.requested';
    case TokenReuseDetected = 'auth.token_refresh.reuse_detected';
    case PermissionDenied = 'auth.permission.denied';
    case AuthorizationChecked = 'auth.authorization.checked';
    case UpdaterLoginSucceeded = 'auth.updater.login.succeeded';
    case UpdaterLoginFailed = 'auth.updater.login.failed';
}
