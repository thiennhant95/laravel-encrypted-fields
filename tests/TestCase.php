<?php

namespace Thiennhant95\LaravelEncryptedFields\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsServiceProvider;
use Thiennhant95\LaravelEncryptedFields\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return array(EncryptedFieldsServiceProvider::class);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', array(
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ));
        $app['config']->set('encrypted-fields.key', base64_encode(str_repeat('k', 32)));
        $app['config']->set('encrypted-fields.key_id', 'main');
        $app['config']->set('encrypted-fields.keys', array());
        $app['config']->set('encrypted-fields.token_secret', 'test-token-secret');
        $app['config']->set('encrypted-fields.models', array(
            User::class => array(
                'email' => array('index' => 'email_bidx', 'normalize' => 'email'),
                'name' => array(),
                'phone' => array('index' => 'phone_bidx', 'normalize' => 'phone'),
            ),
        ));
        $app['config']->set('auth.providers.users', array(
            'driver' => 'encrypted-eloquent',
            'model' => User::class,
        ));
        $app['config']->set('auth.passwords.users', array(
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'driver' => 'encrypted-database',
        ));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('email')->nullable();
            $table->string('email_bidx', 64)->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('phone_bidx', 64)->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::dropIfExists('password_reset_tokens');
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 64)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}
