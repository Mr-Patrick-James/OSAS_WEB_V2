-- Upgrade existing push_subscriptions table (safe to run once)
ALTER TABLE `push_subscriptions` MODIFY `user_id` int NULL DEFAULT NULL;

ALTER TABLE `push_subscriptions`
  ADD COLUMN `scope` enum('announcements','full') NOT NULL DEFAULT 'announcements' AFTER `user_id`;
