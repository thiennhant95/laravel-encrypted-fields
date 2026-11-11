# Changelog

All notable changes to this project are documented here.

## 0.1.0 - 2026-11-11

### Added

- AES-256-GCM field encryption with a key ring and key ids.
- Config-driven Eloquent casts through `HasEncryptedFields`.
- Blind indexes for encrypted email, phone and other searchable fields.
- `encrypted-eloquent` auth provider for login and password reset.
- `EncryptedUnique` validation rule.
- `encrypted-fields:encrypt`, `encrypted-fields:rotate` and
  `encrypted-fields:status` Artisan commands.
- Config and migration publishing.
- Feature tests for encryption, lookup, auth, validation, backfill and rotation.
