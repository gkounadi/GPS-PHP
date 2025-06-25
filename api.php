<?php
session_start();
require_once 'config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    // Auth
    case 'login':
    case 'register':
    case 'logout':
        require 'auth.php'; break;
    // CSRF
    case 'get_csrf_token':
        require 'csrf.php'; break;
    // Course info
    case 'get_course':
        require 'course.php'; break;
    // Round actions
    case 'start_round':
    case 'submit_round':
    case 'end_round':
    case 'check_round_session':
    case 'get_profile':
    case 'get_round_details':
    case 'edit_round':
    case 'delete_round':
        require 'round.php'; break;
    // Distance
    case 'calculate_distances':
        require 'distance.php'; break;
    default:
        echo json_encode(['error'=>'Unknown or missing action']);
}