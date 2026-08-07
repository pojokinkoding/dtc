<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Secara default arahkan ke halaman utama (dtc) jika tidak ada parameter.
$page = isset($_GET['page']) ? $_GET['page'] : 'dtc';

// 1. Load Header & Sidebar
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

// 2. Load Content
switch ($page) {
    case 'dtc':
    case 'dtc_list':
        require_once 'views/dtc_list.php';
        break;
    case 'dtc_history':
        require_once 'views/dtc_history.php';
        break;
    case 'dtc_dashboard': // Kept for backward compatibility if old links exist
    case 'dtc_detail':
        require_once 'views/dtc_detail.php';
        break;
    case 'dtc_matrix_qualitative':
        require_once 'views/dtc_matrix_qualitative.php';
        break;
    case 'docs':
        require_once 'views/docs.php';
        break;
    case 'master_spec':
    case 'missing_data':
    case 'oos_summary':
    case 'settings':
    case 'users':
        if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
            if ($page === 'master_spec') require_once 'views/dtc_master_spec.php';
            if ($page === 'missing_data') require_once 'views/dtc_missing_data.php';
            if ($page === 'oos_summary') require_once 'views/dtc_oos_summary.php';
            if ($page === 'settings') require_once 'views/dtc_settings.php';
            if ($page === 'users') require_once 'views/dtc_users.php';
        } else {
            // Unauthorized access, redirect to main dtc page
            echo "<script>alert('Unauthorized access.'); window.location.href='index.php?page=dtc';</script>";
        }
        break;
        
    default:
        // Halaman tidak ditemukan (404), untuk saat ini kembalikan ke dtc.
        require_once 'views/dtc_dashboard.php';
        break;
}

// 3. Load Footer (which closes the tags)
require_once 'includes/footer.php';
?>
