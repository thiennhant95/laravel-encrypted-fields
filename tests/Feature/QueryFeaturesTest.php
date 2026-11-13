<?php

namespace Thiennhant95\LaravelEncryptedFields\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Thiennhant95\LaravelEncryptedFields\Crypto\Cipher;
use Thiennhant95\LaravelEncryptedFields\Crypto\DecryptionException;
use Thiennhant95\LaravelEncryptedFields\Crypto\KeyRing;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;
use Thiennhant95\LaravelEncryptedFields\Tests\Fixtures\User;
use Thiennhant95\LaravelEncryptedFields\Tests\TestCase;

final class QueryFeaturesTest extends TestCase
{
    public function test_order_by_encrypted_is_deterministic(): void
    {
        $this->createUser('one@example.com', 'One');
        $this->createUser('two@example.com', 'Two');
        $this->createUser('three@example.com', 'Three');

        $first = User::orderByEncrypted('email')->pluck('name')->all();
        $second = User::orderByEncrypted('email')->pluck('name')->all();

        $this->assertSame(3, count($first));
        $this->assertSame($first, $second);
        $this->assertNotSame(array('One', 'Two', 'Three'), $first);
    }

    public function test_order_by_encrypted_desc_reverses_the_asc_order(): void
    {
        $this->createUser('first@example.com', 'First');
        $this->createUser('second@example.com', 'Second');

        $asc = User::orderByEncrypted('email')->pluck('name')->all();
        $desc = User::orderByEncrypted('email', 'desc')->pluck('name')->all();

        $this->assertSame(array_reverse($asc), $desc);
        $this->assertCount(2, $asc);
        $this->assertNotSame($asc[0], $asc[1]);
    }

    public function test_order_by_encrypted_without_index_column_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::orderByEncrypted('name')->get();
    }

    public function test_where_encrypted_in_matches_all_given_values(): void
    {
        $first = $this->createUser('one@example.com', 'One');
        $second = $this->createUser('two@example.com', 'Two');
        $this->createUser('three@example.com', 'Three');

        $found = User::whereEncryptedIn('email', array('ONE@example.com', 'two@example.com'))->get();

        $this->assertSame(2, $found->count());
        $this->assertTrue($found->contains($first));
        $this->assertTrue($found->contains($second));
    }

    public function test_null_values_are_indexed_as_null_and_matched(): void
    {
        $withPhone = $this->createUser('phone@example.com', 'WithPhone', '+15550001111');
        $withoutPhone = $this->createUser('none@example.com', 'NoPhone');

        $this->assertNull(DB::table('users')->where('id', $withoutPhone->id)->value('phone_bidx'));

        $found = User::whereEncrypted('phone', null)->get();

        $this->assertSame(1, $found->count());
        $this->assertTrue($found->contains($withoutPhone));
        $this->assertFalse($found->contains($withPhone));
    }

    public function test_where_encrypted_on_field_without_index_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::whereEncrypted('name', 'anything')->get();
    }

    public function test_decrypting_ciphertext_with_a_wrong_key_throws(): void
    {
        config(array(
            'encrypted-fields.key' => base64_encode(str_repeat('k', 32)),
            'encrypted-fields.key_id' => 'main',
            'encrypted-fields.keys' => array(),
        ));
        $this->forgetEncryptedServices();

        $user = $this->createUser('locked@example.com', 'Locked');

        config(array(
            'encrypted-fields.key' => base64_encode(str_repeat('x', 32)),
            'encrypted-fields.key_id' => 'main',
            'encrypted-fields.keys' => array(),
        ));
        $this->forgetEncryptedServices();

        $this->expectException(DecryptionException::class);

        $user->fresh()->email;
    }

    public function test_manager_reports_key_configuration_state(): void
    {
        $manager = app(EncryptedFieldsManager::class);

        $this->assertTrue($manager->isConfigured());
        $this->assertSame(array('main'), $manager->keyIds());
        $this->assertSame('main', $manager->currentKeyId());
        $this->assertTrue($manager->isEncrypted($manager->encrypt('value')));
        $this->assertFalse($manager->isEncrypted('plain-value'));
        $this->assertNull($manager->keyIdOf('plain-value'));
    }

    private function createUser(string $email, string $name, ?string $phone = null): User
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
