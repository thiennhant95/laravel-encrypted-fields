<?php

namespace Thiennhant95\LaravelEncryptedFields\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Thiennhant95\LaravelEncryptedFields\EncryptedFieldsManager;

final class EncryptedDatabaseTokenRepository extends DatabaseTokenRepository
{
    public function __construct(
        ConnectionInterface $connection,
        HasherContract $hasher,
        private EncryptedFieldsManager $manager,
        string $table,
        string $hashKey,
        int $expires = 3600,
        int $throttle = 60,
        private string $model
    ) {
        parent::__construct($connection, $hasher, $table, $hashKey, $expires, $throttle);
    }

    public function create(CanResetPasswordContract $user): string
    {
        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($this->tokenKey($user), $token));

        return $token;
    }

    public function exists(CanResetPasswordContract $user, $token): bool
    {
        $record = (array) $this->getTable()->where('email', $this->tokenKey($user))->first();

        return array() !== $record
            && !$this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user): bool
    {
        $record = (array) $this->getTable()->where('email', $this->tokenKey($user))->first();

        return array() !== $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    protected function deleteExisting(CanResetPasswordContract $user): int
    {
        return $this->getTable()->where('email', $this->tokenKey($user))->delete();
    }

    private function tokenKey(CanResetPasswordContract $user): string
    {
        return $this->manager->blindIndex(
            (string) $user->getEmailForPasswordReset(),
            $this->model,
            'email'
        );
    }
}
