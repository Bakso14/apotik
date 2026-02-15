<?php
// Basic environment config (edit for production)

define('ENV', [
    'DB_HOST' => '127.0.0.1',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'apotikdb',
    'DB_PORT' => 3306,
    // Application/server timezone (PHP)
    'TIMEZONE' => 'Asia/Jakarta',
    // MySQL session time zone. Use "+07:00" for WIB
    'DB_TIMEZONE' => '+07:00',
    'REQUIRE_API_KEY' => false,
    'API_KEY' => 'dev-key',
]);
