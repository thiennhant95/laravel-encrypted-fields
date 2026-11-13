# Changelog

All notable changes to this project are documented here.

## [Unreleased]

### Added

- English, Vietnamese and Japanese GitHub Pages landing pages with
  hreflang, Open Graph metadata, sitemap and robots.txt.
- `orderByEncrypted` scope for deterministic ordering by blind index.
- Screenshots, architecture diagram, production checklist and
  troubleshooting guide in the README and landing pages.

### Tests

- Expanded the suite to 15 tests and 53 assertions, including multi-value
  lookup, ordering, null handling and wrong-key failure.

## 0.1.2 - 2026-11-12

### Documentation

- Document VCS installation until the package is available on Packagist.

## 0.1.1 - 2026-11-11

### Added

- `encrypted-database` password broker driver. It stores the blind index in
  `password_reset_tokens.email` instead of the plaintext address.

### Fixed

- Laravel's default password token repository no longer leaks the plaintext
  email when the user email is encrypted.

### Tests

- Added a password-reset token privacy test.

## 0.1.0 - 2026-11-11

### Added

- AES-256-GCM field encryption with a key ring and key ids.
- Config-driven Eloquent casts through `HasEncryptedFields`.
- Blind indexes for encrypted email, phone and other searchable fields.
- `encrypted-eloquent` auth provider for login.
- `encrypted-database` password broker driver so reset tokens never store the
  plaintext email.
- `EncryptedUnique` validation rule.
- `encrypted-fields:encrypt`, `encrypted-fields:rotate` and
  `encrypted-fields:status` Artisan commands.
- Config and migration publishing.
- Feature tests for encryption, lookup, auth, validation, backfill and rotation.
