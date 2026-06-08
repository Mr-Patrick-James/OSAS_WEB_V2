<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/PushSubscriptionModel.php';
require_once __DIR__ . '/../services/PushNotificationService.php';

class PushController extends Controller
{
    private $push;
    private $subs;

    public function __construct()
    {
        @session_start();
        $this->push = new PushNotificationService();
        $this->subs = new PushSubscriptionModel();
    }

    public function vapidPublicKey()
    {
        $key = $this->push->getPublicKey();
        if ($key === '') {
            $this->error('Push not configured', '', 503);
        }
        $this->success('VAPID public key', ['publicKey' => $key]);
    }

    public function subscribe()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->error('Invalid JSON');
        }

        $scope = ($input['scope'] ?? 'announcements') === 'full' ? 'full' : 'announcements';
        $sub = $input;
        unset($sub['scope']);

        $userId = null;
        if ($scope === 'full') {
            // Allow any logged-in user (student or admin) to subscribe for full notifications
            if (empty($_SESSION['user_id'])) {
                // Not logged in — still allow subscription but without user_id link
                // They'll get announcements; violations will work once they log in
                $scope = 'full';
                $userId = null;
            } else {
                $userId = (int) $_SESSION['user_id'];
            }
        }

        $id = $this->subs->upsert(
            $userId,
            $sub,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            $scope
        );

        $this->success('Subscribed', ['id' => $id, 'scope' => $scope]);
    }

    /** After login — link device to account for push notifications. */
    public function upgrade()
    {
        if (empty($_SESSION['user_id'])) {
            $this->error('Login required', '', 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['endpoint'])) {
            $this->error('Subscription endpoint required');
        }

        $userId = (int) $_SESSION['user_id'];
        $id = $this->subs->upsert(
            $userId,
            $input,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            'full'
        );

        $this->success('Upgraded to full alerts', ['id' => $id, 'scope' => 'full']);
    }

    public function unsubscribe()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $ep = is_array($input) ? ($input['endpoint'] ?? '') : '';
        if ($ep === '') {
            $this->error('Endpoint required');
        }

        $userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $this->subs->removeByEndpoint($ep, $userId);
        $this->success('Unsubscribed');
    }
}
