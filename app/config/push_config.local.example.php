<?php
/**
 * Copy to push_config.local.php on the server (never commit the real file).
 *
 *   cp app/config/push_config.local.example.php app/config/push_config.local.php
 *   nano app/config/push_config.local.php
 *
 * Generate keys (PC):
 *   npx web-push generate-vapid-keys
 *
 * VAPID subject must match your HTTPS site URL.
 */
return [
    'enabled' => true,
    'vapid' => [
        'subject'    => 'https://eosas.duckdns.org',
        'publicKey'  => 'PASTE_PUBLIC_KEY_HERE',
        'privateKey' => 'PASTE_PRIVATE_KEY_HERE',
    ],
];
