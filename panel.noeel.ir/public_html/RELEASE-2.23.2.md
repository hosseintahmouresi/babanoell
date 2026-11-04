# Panel2.23.2 / UI2.14.1 — installation-specific missing service-key repair

Env loading can complete this installation's missing service-auth key from an encrypted capsule bound to its existing private session encryption material. The session key itself is neither changed nor included in the ZIP. Existing effective keys win; the main private configuration, login/session behavior, service URLs, roles and UI remain unchanged.

The recovered shared key is written only to a protected0600 private override with atomic no-clobber publication. This finite mechanism adds no secret-export/bootstrap API or auth bypass. Wrong private material, tampering, unavailable Sodium, incompatible filesystem permissions/hardlinks or conflicting overrides leave the installation key unchanged. First-time repair takes effect on a fresh request after code installation; later upgrades preserve the saved override even without the capsule. No migration.

Generic source uses an empty capsule; the explicit personalized repair builder keeps secrets/anchors out of all archive entries. It cannot install host-level Cron/PHP extensions or prove production financial/provider acceptance.
