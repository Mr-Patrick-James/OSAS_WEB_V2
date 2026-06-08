-- Fix push_subscriptions when table exists without endpoint_hash / scope (older deploys)
ALTER TABLE `push_subscriptions` MODIFY `user_id` int NULL DEFAULT NULL;

ALTER TABLE `push_subscriptions`
  ADD COLUMN `scope` enum('announcements','full') NOT NULL DEFAULT 'announcements' AFTER `user_id`;

ALTER TABLE `push_subscriptions`
  ADD COLUMN `endpoint_hash` char(64) NULL AFTER `scope`;

UPDATE `push_subscriptions` SET `endpoint_hash` = SHA2(`endpoint`, 256) WHERE `endpoint_hash` IS NULL OR `endpoint_hash` = '';

ALTER TABLE `push_subscriptions` MODIFY `endpoint_hash` char(64) NOT NULL;

ALTER TABLE `push_subscriptions` ADD UNIQUE KEY `uq_endpoint_hash` (`endpoint_hash`);

ALTER TABLE `push_subscriptions` ADD KEY `idx_push_scope` (`scope`);
