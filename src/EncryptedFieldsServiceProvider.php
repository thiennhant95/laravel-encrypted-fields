<?php

namespace Thiennhant95\LaravelEncryptedFields;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Thiennhant95\LaravelEncryptedFields\Auth\EncryptedUserProvider;
use Thiennhant95\LaravelEncryptedFields\Console\EncryptCommand;
use Thiennhant95\LaravelEncryptedFields\Console\RotateKeysCommand;
use Thiennhant95\LaravelEncryptedFields\Console\StatusCommand;
use Thiennhant95\LaravelEncryptedFields\Crypto\BlindIndexer;
use Thiennhant95\LaravelEncryptedFields\Crypto\Cipher;
use Thiennhant95\LaravelEncryptedFields\Crypto\KeyRing;

final class EncryptedFieldsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/encrypted-fields.php', 'encrypted-fields');

        $this->app->singleton(KeyRing::class, function ($app): KeyRing {
            $config = (array) $app['config']->get('encrypted-fields', array());

            return new KeyRing(
                isset($config['key']) ? (string) $config['key'] : null,
                is_array($config['keys'] ?? null) ? $config['keys'] : array(),
                (string) ($config['key_id'] ?? 'main')
            );
        });

        $this->app->singleton(BlindIndexer::class, function ($app): BlindIndexer {
            $config = (array) $app['config']->get('encrypted-fields', array());

            return new BlindIndexer((string) ($config['token_secret'] ?? ''));
        });

        $this->app->singleton(Cipher::class, function ($app): Cipher {
            return new Cipher($app->make(KeyRing::class));
        });

        $this->app->singleton(EncryptedFieldsManager::class, function ($app): EncryptedFieldsManager {
            $config = (array) $app['config']->get('encrypted-fields', array());

            return new EncryptedFieldsManager(
                $app->make(Cipher::class),
                $app->make(BlindIndexer::class),
                $config
            );
        });
    }

    public function boot(): void
    {
        $this->publishes(array(
            __DIR__ . '/../config/encrypted-fields.php' => $this->configPath('encrypted-fields.php'),
        ), 'encrypted-fields-config');

        $this->publishes(array(
            __DIR__ . '/../database/migrations/add_email_blind_index_to_users.php.stub' => $this->databasePath('migrations/2026_01_01_000000_add_email_blind_index_to_users.php'),
        ), 'encrypted-fields-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands(array(
                EncryptCommand::class,
                RotateKeysCommand::class,
                StatusCommand::class,
            ));
        }

        Auth::provider('encrypted-eloquent', function ($app, array $config): EncryptedUserProvider {
            return new EncryptedUserProvider(
                $app['hash'],
                (string) $config['model'],
                $app->make(EncryptedFieldsManager::class)
            );
        });
    }

    private function configPath(string $file): string
    {
        return function_exists('config_path') ? config_path($file) : base_path('config/' . $file);
    }

    private function databasePath(string $file): string
    {
        return function_exists('database_path') ? database_path($file) : base_path('database/' . $file);
    }
}
