-- ============================================================
-- Migration: Rename violation levels to 1st–5th Offense
-- ============================================================

-- Step 1: Update violation_levels display names
UPDATE `violation_levels` SET `name` = '1st Offense', `description` = 'First offense' WHERE `level_order` = 1;
UPDATE `violation_levels` SET `name` = '2nd Offense', `description` = 'Second offense' WHERE `level_order` = 2;
UPDATE `violation_levels` SET `name` = '3rd Offense', `description` = 'Third offense' WHERE `level_order` = 3;
UPDATE `violation_levels` SET `name` = '4th Offense', `description` = 'Fourth offense' WHERE `level_order` = 4;
UPDATE `violation_levels` SET `name` = '5th Offense', `description` = 'Fifth offense — triggers disciplinary action' WHERE `level_order` = 5;

-- Step 2: Alter student_violation_levels ENUM
ALTER TABLE `student_violation_levels` MODIFY COLUMN `current_level` ENUM('offense1','offense2','offense3','offense4','offense5','disciplinary') NOT NULL DEFAULT 'offense1';

-- Step 3: Migrate existing student_violation_levels data
UPDATE `student_violation_levels` SET `current_level` = 'offense1' WHERE `current_level` = 'permitted1';
UPDATE `student_violation_levels` SET `current_level` = 'offense2' WHERE `current_level` = 'permitted2';
UPDATE `student_violation_levels` SET `current_level` = 'offense3' WHERE `current_level` = 'warning1';
UPDATE `student_violation_levels` SET `current_level` = 'offense4' WHERE `current_level` = 'warning2';
UPDATE `student_violation_levels` SET `current_level` = 'offense5' WHERE `current_level` = 'warning3';

-- Step 4: Drop old stored function
DROP FUNCTION IF EXISTS `get_next_violation_level`;
