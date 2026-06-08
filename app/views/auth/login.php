<?php
/**
 * Auth Wrapper - Maintains backward compatibility
 * Routes to MVC Controller
 */
require_once __DIR__ . '/../../core/Model.php';
require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/UserModel.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$controller = new AuthController();
$controller->login();
