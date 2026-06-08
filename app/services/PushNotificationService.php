<?php
require_once __DIR__ . '/../models/PushSubscriptionModel.php';

class PushNotificationService
{
    private $config;
    private $model;
    private $webPush;
    private $ready = false;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/push_config.php';
        $this->model = new PushSubscriptionModel();
        $this->bootstrap();
    }

    public function isEnabled() { return $this->ready; }
    public function getPublicKey() { return $this->config['vapid']['publicKey'] ?? ''; }

    public function notifyAllStudents($title, $body, array $data = [])
    {
        if (!$this->ready) return ['sent' => 0, 'skipped' => true];
        return $this->sendToRows($this->model->getAnnouncementSubscriptions(), $title, $body, $data);
    }

    public function notifyStudent($studentId, $title, $body, array $data = [])
    {
        if (!$this->ready || $studentId === '') return ['sent' => 0, 'skipped' => true];
        return $this->sendToRows($this->model->getSubscriptionsForStudentId($studentId), $title, $body, $data);
    }

    public function notifyAdmins($title, $body, array $data = [], $excludeUserId = null)
    {
        if (!$this->ready) return ['sent' => 0, 'skipped' => true];
        return $this->sendToRows($this->model->getAdminSubscriptions($excludeUserId), $title, $body, $data);
    }

    private function bootstrap()
    {
        if (empty($this->config['enabled'])) return;
        $pub = trim($this->config['vapid']['publicKey'] ?? '');
        $priv = trim($this->config['vapid']['privateKey'] ?? '');
        $sub = trim($this->config['vapid']['subject'] ?? '');
        if ($pub === '' || $priv === '' || $sub === '') return;

        $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoload) return;
        require_once $autoload;
        if (!class_exists(\Minishlink\WebPush\WebPush::class)) return;

        $this->webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => ['subject' => $sub, 'publicKey' => $pub, 'privateKey' => $priv],
        ]);
        $this->ready = true;
    }

    private function sendToRows(array $rows, $title, $body, array $data)
    {
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => $this->iconUrl(),
            'badge' => $this->iconUrl(),
            'tag'   => $data['tag'] ?? 'eosas-' . time(),
            'data'  => $data,
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        $seen = [];
        foreach ($rows as $row) {
            $ep = $row['endpoint'] ?? '';
            if ($ep === '' || isset($seen[$ep])) continue;
            $seen[$ep] = true;
            try {
                $sub = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $ep,
                    'keys' => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
                ]);
                $report = $this->webPush->sendOneNotification($sub, $payload);
                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                    if (in_array($code, [404, 410], true)) $this->model->deleteByEndpoint($ep);
                }
            } catch (Throwable $e) {
                error_log('Push error: ' . $e->getMessage());
            }
        }
        return ['sent' => $sent];
    }

    private function iconUrl()
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = preg_replace('#/api.*$#', '', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return $scheme . '://' . $host . $base . '/app/assets/img/default.png';
    }
}
