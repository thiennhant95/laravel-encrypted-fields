# Laravel Encrypted Fields

Config-driven field-level encryption for Laravel. Encrypt customer PII at rest,
keep Eloquent readable, and look up encrypted email addresses through a
blind index.

- AES-256-GCM with a key ring for rotation
- Eloquent encryption cast for declared fields
- Encrypted email with a unique `email_bidx` lookup column
- Login, password-reset lookup and `EncryptedUnique` validation
- `encrypt` and `rotate` Artisan commands
- Laravel 10/11/12, PHP 8.1+

## Requirements

- PHP 8.1 or newer with OpenSSL
- Laravel 10, 11 or 12
- A database column wide enough for ciphertext (see below)

## Install

```bash
composer require thiennhant95/laravel-encrypted-fields

php artisan vendor:publish --tag=encrypted-fields-config
php artisan vendor:publish --tag=encrypted-fields-migrations
php artisan migrate
```

Set stable secrets in `.env`:

```dotenv
ENCRYPTED_FIELDS_KEY=base64:REPLACE_WITH_32_BYTE_KEY
ENCRYPTED_FIELDS_KEY_ID=main
ENCRYPTED_FIELDS_TOKEN_SECRET=REPLACE_WITH_A_STABLE_SECRET
```

Generate a key locally:

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Never put these values in the database or commit them. Keep them in a secret
manager or the environment.

## Configure fields

Edit `config/encrypted-fields.php`:

```php
'models' => [
    App\Models\User::class => [
        'email' => [
            'index' => 'email_bidx',
            'normalize' => 'email',
        ],
        'name' => [],
        'phone' => [
            'index' => 'phone_bidx',
            'normalize' => 'phone',
        ],
    ],
],
```

Only listed fields are encrypted (default deny). Fields without an `index` are
encrypted and decrypted but cannot be searched by equality.

Add the trait to the model:

```php
use Thiennhant95\LaravelEncryptedFields\Concerns\HasEncryptedFields;

final class User extends Authenticatable
{
    use HasEncryptedFields;
}
```

The trait adds the encryption casts automatically. You do not need to duplicate
the field list in `$casts`.

## Email and schema

Encrypting `email` means the raw column no longer contains the address. Keep a
separate blind-index column for exact lookup and uniqueness:

```php
Schema::table('users', function (Blueprint $table): void {
    // Drop the old plaintext unique index; email_bidx replaces it.
    $table->dropUnique(['email']);

    // Ciphertext is longer than plaintext. Use at least varchar(512) or TEXT.
    $table->string('email', 512)->change();

    $table->string('email_bidx', 64)->nullable()->unique()->after('email');
});
```

The published migration adds `email_bidx`. Add `phone_bidx` or other index
columns in the same way if those fields need lookup. On Laravel 10, `change()`
requires `doctrine/dbal`; Laravel 11+ supports it natively.

A blind index is an HMAC, not a reversible value. It is deterministic for the
same normalized value and field, so it supports exact lookup but not partial
`LIKE` search.

## Auth, reset and validation

Change the user provider in `config/auth.php`:

```php
'providers' => [
    'users' => [
        'driver' => 'encrypted-eloquent',
        'model' => App\Models\User::class,
    ],
],
```

This maps `email` credentials to `email_bidx` for `Auth::attempt()` and the
password broker. Mail and notifications still receive plaintext because the
Eloquent cast decrypts `$user->email`.

Use the encrypted-aware unique rule for registration/profile forms:

```php
use Thiennhant95\LaravelEncryptedFields\Validation\EncryptedUnique;

$request->validate([
    'email' => ['required', 'email', new EncryptedUnique(User::class, 'email')],
]);
```

To ignore the current user during an update:

```php
new EncryptedUnique(User::class, 'email', $user->getKey())
```

## Query encrypted fields

```php
User::whereEncrypted('email', 'Alice@Example.com')->first();
User::whereEncrypted('phone', '+1 (555) 123-4567')->first();
User::whereEncryptedIn('email', $emails)->get();
```

Normalization is applied before hashing. `email`/`lower` ignore case; `phone`
removes spaces, dashes, parentheses and other non-digit characters except `+`.
Use the same normalization when writing and querying.

## Migrate existing plaintext

Always back up the database first. Preview, then run:

```bash
php artisan encrypted-fields:encrypt --dry-run
php artisan encrypted-fields:encrypt
```

The command is idempotent: already-encrypted values are skipped, while missing
blind indexes are filled. Use `--model="App\Models\User"` to process one model
and `--chunk=500` to tune batch size.

## Rotate keys

1. Generate a new key and set `ENCRYPTED_FIELDS_KEY_ID` to a new id.
2. Set `ENCRYPTED_FIELDS_KEY` to the new key.
3. Keep the old key in `encrypted-fields.keys` under its old id.
4. Dry-run and rotate:

```bash
php artisan encrypted-fields:rotate --dry-run
php artisan encrypted-fields:rotate
```

Only remove the old key after the command succeeds and you have verified the
application. Stored values include their key id, so old data remains readable
while both keys are configured.

## Commands

| Command | Purpose |
|---|---|
| `encrypted-fields:status` | Show models, fields, indexes and key configuration |
| `encrypted-fields:encrypt` | Encrypt existing plaintext and fill blind indexes |
| `encrypted-fields:rotate` | Re-encrypt with the current key |

## Laravel integration notes

- `Auth::attempt(['email' => $email, 'password' => $password])` works through
  the custom provider; keep the provider driver configured.
- Password reset uses the same provider and sends mail to the decrypted email.
- The default `unique:users,email` rule cannot see through encryption. Use
  `EncryptedUnique` instead.
- Exact lookup only. Partial email search, sorting by email and database
  `LIKE` queries are not supported by design.
- `email_bidx` is unique. Keep it in sync with the trait; do not update the
  encrypted email with raw SQL.
- Encrypting a column changes its length and index behavior. Plan a migration
  for existing columns before backfilling.
- This package does not provide an admin UI or key escrow. Keys are yours.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite covers encryption at rest, blind indexes, encrypted email login,
unique validation, backfilling and key rotation.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
