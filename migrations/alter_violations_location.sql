-- Update violation location options: add campus/canteen, remove gates/cafeteria
-- Run once: mysql -u root -p osas < migrations/alter_violations_location.sql

UPDATE `violations` SET `location` = 'campus' WHERE `location` IN ('gate_1', 'gate_2');
UPDATE `violations` SET `location` = 'canteen' WHERE `location` = 'cafeteria';

ALTER TABLE `violations`
  MODIFY COLUMN `location` ENUM(
    'campus',
    'canteen',
    'classroom',
    'library',
    'gym',
    'others'
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'campus';
