<?php
$local = __DIR__ . '/push_config.local.php';
if (file_exists($local)) {
    return include $local;
}

$publicKey  = trim(getenv('VAPID_PUBLIC_KEY') ?: '');
$privateKey = trim(getenv('VAPID_PRIVATE_KEY') ?: '');
$subject    = trim(getenv('VAPID_SUBJECT') ?: 'mailto:osas@colegiodenaujan.edu.ph');
$pushEnv    = strtolower(trim(getenv('PUSH_ENABLED') ?: ''));

if ($publicKey !== '' && $privateKey !== '') {
    $enabled = in_array($pushEnv, ['0', 'false', 'no', 'off'], true) ? false : true;
    return [
        'enabled' => $enabled,
        'vapid' => [
            'subject'    => $subject,
            'publicKey'  => $publicKey,
            'privateKey' => $privateKey,
        ],
    ];
}

return [
    'enabled' => false,
    'vapid' => ['subject' => $subject, 'publicKey' => '', 'privateKey' => ''],
];
