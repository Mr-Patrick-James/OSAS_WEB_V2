<?php
require_once __DIR__ . '/../core/Model.php';

class PushSubscriptionModel extends Model
{
    protected $table = 'push_subscriptions';

    public function upsert(?int $userId, array $subscription, $userAgent = null, string $scope = 'announcements')
    {
        $scope = $scope === 'full' ? 'full' : 'announcements';
        $endpoint = $subscription['endpoint'] ?? '';
        $keys = $subscription['keys'] ?? [];
        $p256dh = $keys['p256dh'] ?? '';
        $auth = $keys['auth'] ?? '';
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new InvalidArgumentException('Invalid push subscription');
        }

        $hash = hash('sha256', $endpoint);
        $existing = $this->query('SELECT id, user_id, scope FROM push_subscriptions WHERE endpoint_hash = ? LIMIT 1', [$hash]);

        if (!empty($existing)) {
            $id = (int) $existing[0]['id'];
            $finalScope = $scope === 'full' ? 'full' : ($existing[0]['scope'] ?? 'announcements');
            if ($finalScope !== 'full' && $scope === 'full') {
                $finalScope = 'full';
            }

            if ($userId !== null) {
                $stmt = $this->conn->prepare(
                    'UPDATE push_subscriptions SET user_id=?, scope=?, endpoint=?, p256dh=?, auth=?, user_agent=?, updated_at=NOW() WHERE id=?'
                );
                $stmt->bind_param('isssssi', $userId, $finalScope, $endpoint, $p256dh, $auth, $userAgent, $id);
            } else {
                $stmt = $this->conn->prepare(
                    'UPDATE push_subscriptions SET scope=?, endpoint=?, p256dh=?, auth=?, user_agent=?, updated_at=NOW() WHERE id=?'
                );
                $stmt->bind_param('sssssi', $finalScope, $endpoint, $p256dh, $auth, $userAgent, $id);
            }
            $stmt->execute();
            $stmt->close();
            return $id;
        }

        if ($userId !== null) {
            $stmt = $this->conn->prepare(
                'INSERT INTO push_subscriptions (user_id, scope, endpoint_hash, endpoint, p256dh, auth, user_agent) VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('issssss', $userId, $scope, $hash, $endpoint, $p256dh, $auth, $userAgent);
        } else {
            $stmt = $this->conn->prepare(
                'INSERT INTO push_subscriptions (user_id, scope, endpoint_hash, endpoint, p256dh, auth, user_agent) VALUES (NULL,?,?,?,?,?,?)'
            );
            $stmt->bind_param('ssssss', $scope, $hash, $endpoint, $p256dh, $auth, $userAgent);
        }
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function upgradeEndpointToStudent(int $userId, string $endpoint)
    {
        $hash = hash('sha256', $endpoint);
        $stmt = $this->conn->prepare(
            'UPDATE push_subscriptions SET user_id=?, scope=\'full\', updated_at=NOW() WHERE endpoint_hash=?'
        );
        $stmt->bind_param('is', $userId, $hash);
        $stmt->execute();
        $stmt->close();
    }

    public function removeByEndpoint(string $endpoint, ?int $userId = null)
    {
        $hash = hash('sha256', $endpoint);
        if ($userId !== null) {
            $stmt = $this->conn->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash=? AND user_id=?');
            $stmt->bind_param('si', $hash, $userId);
        } else {
            $stmt = $this->conn->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash=?');
            $stmt->bind_param('s', $hash);
        }
        $stmt->execute();
        $stmt->close();
    }

    /** Everyone who opted in (installed app / landing page) — announcements only. */
    public function getAnnouncementSubscriptions()
    {
        return $this->query(
            'SELECT endpoint, p256dh, auth FROM push_subscriptions'
        );
    }

    /** Logged-in students only — violations. */
    public function getSubscriptionsForStudentId($studentId)
    {
        return $this->query(
            "SELECT ps.endpoint, ps.p256dh, ps.auth FROM push_subscriptions ps
             INNER JOIN users u ON u.id = ps.user_id
             WHERE u.role = 'user' AND u.is_active = 1
               AND BINARY u.student_id = BINARY ?
             LIMIT 20",
            [$studentId]
        );
    }

    /** Get head admin subscriptions (highest hierarchy) for notifications */
    public function getAdminSubscriptions($excludeUserId = null)
    {
        if ($excludeUserId !== null) {
            return $this->query(
                "SELECT ps.endpoint, ps.p256dh, ps.auth FROM push_subscriptions ps
                 INNER JOIN users u ON u.id = ps.user_id
                 WHERE u.role = 'admin' AND u.is_active = 1 AND u.id != ?
                 LIMIT 20",
                [$excludeUserId]
            );
        }
        return $this->query(
            "SELECT ps.endpoint, ps.p256dh, ps.auth FROM push_subscriptions ps
             INNER JOIN users u ON u.id = ps.user_id
             WHERE u.role = 'admin' AND u.is_active = 1
             LIMIT 20"
        );
    }

    public function deleteByEndpoint($endpoint)
    {
        $this->removeByEndpoint($endpoint, null);
    }
}
