<?php
/**
 * Copy this file to config.php and fill in your database credentials.
 *
 * On InfinityFree you find them in: Control Panel > MySQL Databases.
 *   host: something like sql123.infinityfree.com  (NOT "localhost")
 *   name: if0_12345678_games
 *   user: if0_12345678
 *   pass: your vPanel / hosting account password
 */
return [
    'db_host' => 'sqlXXX.infinityfree.com',
    'db_name' => 'if0_XXXXXXXX_games',
    'db_user' => 'if0_XXXXXXXX',
    'db_pass' => 'your-password',

    // Link shown in the header to go back to your main site.
    'home_url' => 'https://dwens.gamer.gd/',

    // Rooms with no activity for this many hours are deleted automatically.
    'room_ttl_hours' => 24,
];
