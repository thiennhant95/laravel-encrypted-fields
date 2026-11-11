<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Current key
    |--------------------------------------------------------------------------
    |
    | A 32-byte key encoded as base64, base64:..., or 64 hex characters.
    | Keep it outside the database, for example in the environment.
    |
    */
    'key' => env('ENCRYPTED_FIELDS_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Key id
    |--------------------------------------------------------------------------
    |
    | The id is stored in every ciphertext so old keys can still decrypt while
    | a rotation is in progress. Use only letters, numbers, dashes or
    | underscores.
    |
    */
    'key_id' => env('ENCRYPTED_FIELDS_KEY_ID', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Old keys
    |--------------------------------------------------------------------------
    |
    | Keep previous keys here during a rotation:
    |
    | 'keys' => [
    |     '2025-01' => env('ENCRYPTED_FIELDS_KEY_2025_01'),
    | ],
    |
    */
    'keys' => [],

    /*
    |--------------------------------------------------------------------------
    | Blind index secret
    |--------------------------------------------------------------------------
    |
    | A stable secret used for searchable fields (email, phone, ...). It must
    | not change after data has been indexed. If it leaks, reindex all fields.
    |
    */
    'token_secret' => env('ENCRYPTED_FIELDS_TOKEN_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Encrypted models and fields
    |--------------------------------------------------------------------------
    |
    | Every field listed here is encrypted by the HasEncryptedFields trait.
    | Add an "index" column for fields that need exact lookup/search.
    |
    | "normalize" is used for the blind index: lower, email or phone.
    |
    */
    'models' => [
        // App\Models\User::class => [
        //     'email' => [
        //         'index' => 'email_bidx',
        //         'normalize' => 'email',
        //     ],
        //     'name' => [],
        //     'phone' => [
        //         'index' => 'phone_bidx',
        //         'normalize' => 'phone',
        //     ],
        // ],
    ],
];
