<?php
/**
 * InfinityFree free-plan PDO configuration template.
 * Copy to config/database.php and replace all database placeholders with the
 * exact values from Control Panel > MySQL Databases.
 */

return [
    'host'      => 'sql123.infinityfree.com',
    'port'      => 3306,
    'database'  => 'if0_00000000_spep_portal',
    'username'  => 'if0_00000000',
    'password'  => 'REPLACE_WITH_INFINITYFREE_DATABASE_PASSWORD',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'timezone'  => '+00:00',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', collation_connection = 'utf8mb4_unicode_ci'",
    ],
];
