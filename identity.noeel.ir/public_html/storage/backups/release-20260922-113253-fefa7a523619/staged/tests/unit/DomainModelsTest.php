<?php
declare(strict_types=1);

use Babanoell\Auth\Models\AuditLogEntry;
use Babanoell\Auth\Models\Permission;
use Babanoell\Auth\Models\Role;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testUserHydratesFromSchemaAlignedRow(): void
    {
        $user = User::fromRow([
            'id' => 'usr_phase1d_local',
            'mobile' => null,
            'email' => 'local@example.test',
            'display_name' => 'Local Check',
            'status' => 'active',
            'password_hash' => '$argon2id$local-verifier-only',
            'password_changed_at' => null,
            'last_login_at' => null,
            'locked_until' => null,
            'disabled_at' => null,
            'created_at' => '2026-06-13 00:00:00.000000',
            'updated_at' => '2026-06-13 00:00:00.000000',
        ]);

        Assertions::same('usr_phase1d_local', $user->id);
        Assertions::same('local@example.test', $user->email);
        Assertions::same('active', $user->status);
        Assertions::same('$argon2id$local-verifier-only', $user->passwordHash);
    }

    public function testRoleAndPermissionHydrateFromRows(): void
    {
        $role = Role::fromRow([
            'id' => 'rol_phase1d_local',
            'name' => 'operations_expert',
            'label' => 'Operations expert',
            'description' => null,
            'is_system' => 1,
            'created_at' => null,
            'updated_at' => null,
        ]);

        $permission = Permission::fromRow([
            'id' => 'prm_phase1d_local',
            'name' => 'auth.roles.read',
            'description' => 'Read roles',
            'created_at' => null,
            'updated_at' => null,
        ]);

        Assertions::true($role->isSystem);
        Assertions::same('operations_expert', $role->name);
        Assertions::same('auth.roles.read', $permission->name);
    }

    public function testSessionRecordHydratesWithoutPlainTokenValue(): void
    {
        $session = SessionRecord::fromRow([
            'id' => 'ses_phase1d_local',
            'user_id' => 'usr_phase1d_local',
            'refresh_token_hash' => 'hash-only-local-value',
            'token_family_id' => 'fam_phase1d_local',
            'user_agent_hash' => null,
            'ip_address_hash' => null,
            'created_at' => null,
            'updated_at' => null,
            'last_used_at' => null,
            'access_expires_at' => '2026-06-13 01:00:00.000000',
            'refresh_expires_at' => '2026-06-14 01:00:00.000000',
            'revoked_at' => null,
            'revoked_reason' => null,
        ]);

        Assertions::same('hash-only-local-value', $session->refreshTokenHash);
        Assertions::false($session->isRevoked());
    }

    public function testAuditLogEntryHydratesMetadata(): void
    {
        $entry = AuditLogEntry::fromRow([
            'id' => 'aud_phase1d_local',
            'event_type' => 'auth.local_check',
            'actor_user_id' => null,
            'target_user_id' => null,
            'session_id' => null,
            'ip_address_hash' => null,
            'user_agent_hash' => null,
            'result' => 'success',
            'metadata_json' => '{"scope":"unit"}',
            'created_at' => null,
        ]);

        Assertions::same('auth.local_check', $entry->eventType);
        Assertions::same('unit', $entry->metadata['scope']);
    }
};
