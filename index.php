<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$currentUserRole = strtolower(trim($_SESSION['role'] ?? ''));
$isManagement = (strpos($currentUserRole, 'management') !== false);

// Secara default arahkan ke halaman utama (dtc) jika tidak ada parameter, kecuali role management diarahkan ke missing_data.
if ($isManagement) {
    $page = isset($_GET['page']) ? $_GET['page'] : 'missing_data';
} else {
    $page = isset($_GET['page']) ? $_GET['page'] : 'dtc';
}

// 1. Load Header & Sidebar
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

// 2. Load Content
switch ($page) {
    case 'dtc':
    case 'dtc_list':
        if ($isManagement) {
            echo "<script>window.location.href='index.php?page=missing_data';</script>";
            exit;
        }
        require_once 'views/dtc_list.php';
        break;
    case 'missing_data':
        if ($currentUserRole === 'admin' || strpos($currentUserRole, 'supervisor') !== false || $isManagement) {
            require_once 'views/dtc_missing_data.php';
        } else {
            echo "<script>alert('Unauthorized access.'); window.location.href='index.php?page=dtc';</script>";
        }
        break;
    case 'dtc_history':
        if ($isManagement) {
            echo "<script>window.location.href='index.php?page=missing_data';</script>";
            exit;
        }
        require_once 'views/dtc_history.php';
        break;
    case 'dtc_detail':
        require_once 'views/dtc_detail.php';
        break;
    case 'dtc_matrix_qualitative':
        require_once 'views/dtc_matrix_qualitative.php';
        break;
    case 'docs':
        if ($isManagement) {
            echo "<script>window.location.href='index.php?page=missing_data';</script>";
            exit;
        }
        require_once 'views/docs.php';
        break;
    case 'master_spec':
    case 'settings':
    case 'users':
        if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
            if ($page === 'master_spec') require_once 'views/dtc_master_spec.php';
            if ($page === 'settings') require_once 'views/dtc_settings.php';
            if ($page === 'users') require_once 'views/dtc_users.php';
        } else {
            // Unauthorized access, redirect to main dtc/missing_data page
            $redirectPage = $isManagement ? 'missing_data' : 'dtc';
            echo "<script>alert('Unauthorized access.'); window.location.href='index.php?page={$redirectPage}';</script>";
        }
        break;
        
    default:
        // Unknown page, redirect to default page
        $redirectPage = $isManagement ? 'missing_data' : 'dtc';
        header("Location: index.php?page={$redirectPage}");
        exit;
}

// 3. Load Footer (which closes the tags)
require_once 'includes/footer.php';
?>
