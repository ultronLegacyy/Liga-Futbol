<?php

$supportsArgon2id = defined('PASSWORD_ARGON2ID') && in_array(PASSWORD_ARGON2ID, password_algos());
$driver = env('HASH_DRIVER', $supportsArgon2id ? 'argon2id' : 'bcrypt');

if ($driver === 'argon2id' && ! $supportsArgon2id) {
    $driver = 'bcrypt';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Conforme a RNF-26: "Se guardan con Argon2id (o bcrypt de costo >= 12)".
    | Si argon2id está soportado por PHP se usa por defecto; de lo contrario
    | se usa bcrypt garantizando costo >= 12.
    |
    */

    'driver' => $driver,

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', '12'),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash Options
    |--------------------------------------------------------------------------
    */

    'rehash_on_login' => true,

];
