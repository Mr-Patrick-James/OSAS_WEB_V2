<?php
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Run: composer install\n");
    exit(1);
}
require_once $autoload;
try {
    $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\nUse: npx web-push generate-vapid-keys\n");
    exit(1);
}
echo "publicKey:  " . $keys['publicKey'] . "\n";
echo "privateKey: " . $keys['privateKey'] . "\n";
