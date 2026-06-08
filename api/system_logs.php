<?php
/**
 * System Logs API
 */
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/SystemLogModel.php';
require_once __DIR__ . '/../app/controllers/SystemLogController.php';

$controller = new SystemLogController();
$controller->listLogs();
