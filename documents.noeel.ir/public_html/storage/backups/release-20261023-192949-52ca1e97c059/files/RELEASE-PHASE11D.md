# Noel Phase 11D — documents 1.18.0

- Server-side SSO for secure updater authentication, with Panel as browser broker and Identity as authorization authority.
- PKCE-bound, one-use authorization codes; 45-second code TTL and 20-minute grants.
- SSO grants revalidate the super_admin role with Identity on protected updater requests.
- The existing password login remains available for emergency recovery.
- No database migration, no production configuration or login credentials bundled.
- Production requires correctly configured shared secret (32+ characters) and HTTPS with trusted reverse-proxy settings when applicable.
