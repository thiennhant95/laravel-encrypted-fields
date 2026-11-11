<?php

namespace Thiennhant95\LaravelEncryptedFields\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use InvalidArgumentException;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class EncryptedPasswordBrokerManager extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config)
    {
        if ('encrypted-database' !== ($config['driver'] ?? 'database')) {
            return parent::createTokenRepository($config);
        }

        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $provider = (string) ($config['provider'] ?? 'users');
        $model = $config['model'] ?? $this->app['config']->get("auth.providers.{$provider}.model");

        if (!is_string($model) || '' === $model) {
            throw new InvalidArgumentException('The encrypted password broker requires an auth provider model.');
        }

        return new EncryptedDatabaseTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $this->app->make(EncryptedFieldsManager::class),
            (string) $config['table'],
            $key,
            (int) (($config['expire'] ?? 60) * 60),
            (int) ($config['throttle'] ?? 60),
            $model
        );
    }
}
