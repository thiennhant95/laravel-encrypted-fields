<?php

namespace Thiennhant95\LaravelEncryptedFields\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Thiennhant95\LaravelEncryptedFields\Crypto\Cipher;
use Thiennhant95\LaravelEncryptedFields\Crypto\KeyRing;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;
use Thiennhant95\LaravelEncryptedFields\Tests\Fixtures\User;
use Thiennhant95\LaravelEncryptedFields\Tests\TestCase;
use Thiennhant95\LaravelEncryptedFields\Validation\EncryptedUnique;

final class EncryptedFieldsTest extends TestCase
{
    public function test_fields_are_encrypted_at_rest_and_decrypted_by_eloquent(): void
    {
        $user = $this->createUser('Alice@Example.com', 'Alice', '+1 (555) 123-4567');

        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertStringStartsWith('enc:v1:main:', (string) $row->email);
        $this->assertStringStartsWith('enc:v1:main:', (string) $row->phone);
        $this->assertNotSame('Alice@Example.com', $row->email);
        $this->assertSame('Alice@Example.com', $user->fresh()->email);
        $this->assertSame('Alice', $user->fresh()->name);
        $this->assertSame('+1 (555) 123-4567', $user->fresh()->phone);
        $this->assertSame(64, strlen((string) $row->email_bidx));
        $this->assertSame(64, strlen((string) $row->phone_bidx));
    }

    public function test_exact_lookup_is_case_insensitive_and_partial_values_do_not_match(): void
    {
        $user = $this->createUser('Alice@Example.com', 'Alice', '+1 (555) 123-4567');

        $this->assertTrue(User::whereEncrypted('email', 'ALICE@example.com')->first()->is($user));
        $this->assertTrue(User::whereEncrypted('phone', '+15551234567')->first()->is($user));
        $this->assertTrue(User::whereEncrypted('email', 'alice@')->doesntExist());
    }

    public function test_encrypted_unique_validation_uses_the_blind_index(): void
    {
        $this->createUser('taken@example.com', 'Taken', null);

        $duplicate = Validator::make(
            array('email' => 'TAKEN@example.com'),
            array('email' => array(new EncryptedUnique(User::class, 'email')))
        );

        $available = Validator::make(
            array('email' => 'available@example.com'),
            array('email' => array(new EncryptedUnique(User::class, 'email')))
        );

        $this->assertTrue($duplicate->fails());
        $this->assertTrue($available->passes());
    }

    public function test_login_and_password_reset_lookup_work_with_encrypted_email(): void
    {
        $user = $this->createUser('login@example.com', 'Login', null);

        $this->assertTrue(Auth::attempt(array(
            'email' => 'LOGIN@example.com',
            'password' => 'secret-password',
        )));
        $this->assertSame($user->id, Auth::id());

        $provider = Auth::createUserProvider('users');
        $found = $provider->retrieveByCredentials(array('email' => 'login@example.com'));

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->getAuthIdentifier());
    }

    public function test_password_reset_tokens_do_not_store_plaintext_email(): void
    {
        $user = $this->createUser('reset@example.com', 'Reset', null);

        $token = Password::broker()->createToken($user);
        $record = DB::table('password_reset_tokens')->first();

        $this->assertNotNull($record);
        $this->assertNotSame('reset@example.com', $record->email);
        $this->assertSame(64, strlen((string) $record->email));

        $status = Password::reset(
            array(
                'email' => 'reset@example.com',
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
                'token' => $token,
            ),
            function (User $user, string $password): void {
                $user->forceFill(array('password' => Hash::make($password)))->save();
            }
        );

        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertTrue(Hash::check('new-secret-password', (string) $user->fresh()->password));
    }

    public function test_encrypt_command_backfills_plaintext_rows_and_indexes(): void
    {
        DB::table('users')->insert(array(
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'email_bidx' => null,
            'phone' => null,
            'phone_bidx' => null,
            'password' => Hash::make('secret-password'),
            'created_at' => now(),
            'updated_at' => now(),
        ));

        $this->artisan('encrypted-fields:encrypt')->assertExitCode(0);

        $row = DB::table('users')->where('email_bidx', '!=', null)->first();

        $this->assertNotNull($row);
        $this->assertStringStartsWith('enc:v1:main:', (string) $row->email);
        $this->assertSame(64, strlen((string) $row->email_bidx));
        $this->assertSame('legacy@example.com', User::first()->email);
    }

    public function test_rotation_reencrypts_values_with_the_new_key(): void
    {
        $oldKey = base64_encode(str_repeat('o', 32));
        $newKey = base64_encode(str_repeat('n', 32));

        config(array(
            'encrypted-fields.key' => $oldKey,
            'encrypted-fields.key_id' => 'old',
            'encrypted-fields.keys' => array(),
        ));
        $this->forgetEncryptedServices();

        $user = $this->createUser('rotate@example.com', 'Rotate', '+1 555 000 1111');
        $rawBefore = (string) DB::table('users')->where('id', $user->id)->value('email');

        $this->assertStringStartsWith('enc:v1:old:', $rawBefore);

        config(array(
            'encrypted-fields.key' => $newKey,
            'encrypted-fields.key_id' => 'new',
            'encrypted-fields.keys' => array('old' => $oldKey),
        ));
        $this->forgetEncryptedServices();

        $this->artisan('encrypted-fields:rotate')->assertExitCode(0);

        $rawAfter = (string) DB::table('users')->where('id', $user->id)->value('email');

        $this->assertStringStartsWith('enc:v1:new:', $rawAfter);
        $this->assertSame('rotate@example.com', User::first()->email);
    }

    private function createUser(string $email, string $name, ?string $phone): User
    {
        return User::create(array(
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'password' => Hash::make('secret-password'),
        ));
    }

    private function forgetEncryptedServices(): void
    {
        $this->app->forgetInstance(EncryptedFieldsManager::class);
        $this->app->forgetInstance(Cipher::class);
        $this->app->forgetInstance(KeyRing::class);
    }
}
