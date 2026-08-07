<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTC - Quality Time Checker</title>
    
    <!-- Favicon -->
    <link rel="icon" href="logo.png" type="image/png">
    
    <!-- Kendo UI CSS -->
    <link href="assets/css/kendo.default.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="assets/css/inter.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="assets/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="assets/css/select2.min.css" rel="stylesheet" />
    
    <style>
        /* Select2 Dark Theme Overrides */
        .select2-container--default .select2-selection--single {
            background-color: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            height: 38px;
            padding: 4px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: white;
            font-size: 14px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-dropdown {
            background-color: var(--bg-dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .select2-search--dropdown .select2-search__field {
            background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: var(--primary);
            color: white;
        }
        .select2-results__option {
            padding: 8px 12px;
            font-size: 13px;
        }
        :root {
            --bg-dark: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-sidebar: rgba(15, 23, 42, 0.95);
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --accent: #10b981;
            --danger: #ef4444;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --purple: #8b5cf6;
            --sidebar-width: 260px;
            --topbar-height: 52px;
        }

        body.theme-robot {
            --bg-dark: #050b14;
            --bg-card: rgba(8, 14, 26, 0.75);
            --bg-sidebar: rgba(5, 11, 20, 0.95);
            --primary: #00f3ff;
            --primary-hover: #00c3cc;
            --accent: #00ff9d;
            --danger: #ff003c;
            --text-light: #e2f1f8;
            --text-muted: #849bb3;
            --purple: #b829ff;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            margin: 0;
            padding: 0;
            background-image: radial-gradient(circle at 10% 20%, rgb(14, 26, 48) 0%, rgb(9, 16, 29) 90%);
            min-height: 100vh;
            display: flex;
            transition: background-color 0.3s ease, background-image 0.3s ease;
            zoom: 1;
        }

        body.theme-robot {
            background-image: 
                linear-gradient(rgba(0, 243, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 243, 255, 0.03) 1px, transparent 1px),
                radial-gradient(circle at 50% 0%, rgba(0, 243, 255, 0.1) 0%, transparent 50%);
            background-size: 30px 30px, 30px 30px, 100% 100%;
            background-position: center top;
        }

        /* Sci-Fi Animations */
        @keyframes cyber-pulse {
            0% { box-shadow: 0 0 5px var(--primary), inset 0 0 5px var(--primary); }
            50% { box-shadow: 0 0 15px var(--primary), inset 0 0 10px var(--primary); }
            100% { box-shadow: 0 0 5px var(--primary), inset 0 0 5px var(--primary); }
        }
        
        @keyframes neon-pulse-purple {
            0% { box-shadow: 0 0 5px var(--purple), inset 0 0 5px var(--purple); text-shadow: 0 0 5px var(--purple); }
            50% { box-shadow: 0 0 20px var(--purple), inset 0 0 10px var(--purple); text-shadow: 0 0 15px var(--purple); }
            100% { box-shadow: 0 0 5px var(--purple), inset 0 0 5px var(--purple); text-shadow: 0 0 5px var(--purple); }
        }

        body.theme-robot #btn-input-data {
            color: #050b14 !important;
            animation: cyber-pulse 2s infinite;
        }
        
        body.theme-robot #btn-train-ai {
            animation: neon-pulse-purple 2s infinite;
        }

        body.theme-robot .detail-header-title {
            color: var(--primary) !important;
            text-shadow: 0 0 10px rgba(0, 243, 255, 0.5);
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-brand h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar-menu li {
            padding: 0 15px;
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 15px;
        }

        .sidebar-menu a i {
            width: 25px;
            font-size: 18px;
            margin-right: 10px;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(0, 243, 255, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
            text-shadow: 0 0 8px rgba(0, 243, 255, 0.6);
        }

        /* --- MAIN WRAPPER --- */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-width: 0;
            transition: margin-left 0.3s ease;
        }

        /* --- COLLAPSED SIDEBAR (Desktop: Mini sidebar, Mobile: Hidden sidebar) --- */
        .sidebar.collapsed {
            width: 50px;
        }
        
        .sidebar.collapsed .sidebar-brand {
            padding: 0;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-brand .logo-text {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-menu a {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-menu a span {
            display: none;
        }

        .sidebar.collapsed .sidebar-menu a i {
            margin-right: 0;
            text-align: center;
            width: 100%;
            font-size: 16px;
        }

        .sidebar.collapsed ~ .main-wrapper {
            margin-left: 50px;
        }

        /* Responsive Layouts */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }
            .sidebar.active-mobile {
                transform: translateX(0);
            }
            .sidebar.collapsed ~ .main-wrapper,
            .main-wrapper {
                margin-left: 0;
            }
            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }
            .docs-grid {
                grid-template-columns: 1fr !important;
            }
            .topbar {
                padding: 0 15px;
            }
            .content-area {
                padding: 15px;
            }
            .user-info {
                display: none; /* Hide user name on small screens */
            }
            .user-avatar {
                margin-left: auto;
            }
        }

        /* --- TOPBAR --- */
        .topbar {
            height: var(--topbar-height);
            background: var(--bg-sidebar);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 99;
            transition: all 0.3s ease;
        }

        body.theme-robot .topbar {
            border-bottom: 1px solid rgba(0, 243, 255, 0.2);
            box-shadow: 0 4px 30px rgba(0, 243, 255, 0.1);
        }

        .topbar-left .page-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: transparent;
            border: 2px solid var(--primary);
            box-shadow: 0 0 10px var(--primary);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }

        .dtc-grid th, .dtc-grid td {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .dtc-grid th {
            background-color: rgba(15, 23, 42, 0.6);
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .dtc-grid td {
            font-size: 14px;
            color: var(--text-light);
        }

        /* Responsive Chart Fonts */
        @media (max-width: 1200px) {
            .k-chart svg text {
                font-size: 10px !important;
            }
        }
        @media (max-width: 768px) {
            .k-chart svg text {
                font-size: 9px !important;
            }
        }

        /* --- BUTTONS & INPUTS --- */
        input[type="date"],
        input[type="month"],
        input[type="time"],
        input[type="datetime-local"] {
            color-scheme: dark;
        }

        div[id^="modal-"] input[type="text"], 
        div[id^="modal-"] input[type="date"], 
        div[id^="modal-"] input[type="month"], 
        div[id^="modal-"] input[type="password"], 
        div[id^="modal-"] input[type="number"], 
        div[id^="modal-"] input[type="file"], 
        div[id^="modal-"] select, 
        div[id^="modal-"] textarea,
        div[id="panel-input-data"] input[type="text"],
        div[id="panel-input-data"] input[type="date"],
        div[id="panel-input-data"] input[type="month"],
        div[id="panel-input-data"] select,
        div[id="panel-input-data"] textarea {
            border: 1px solid rgba(255, 255, 255, 0.6) !important;
            box-shadow: 0 0 5px rgba(255, 255, 255, 0.1) !important;
            color-scheme: dark;
        }

        div[id^="modal-"] input[type="text"]:focus, 
        div[id^="modal-"] input[type="date"]:focus, 
        div[id^="modal-"] input[type="month"]:focus, 
        div[id^="modal-"] input[type="password"]:focus, 
        div[id^="modal-"] input[type="number"]:focus, 
        div[id^="modal-"] select:focus, 
        div[id^="modal-"] textarea:focus,
        div[id="panel-input-data"] input:focus,
        div[id="panel-input-data"] select:focus,
        div[id="panel-input-data"] textarea:focus {
            border-color: #ffffff !important;
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.5) !important;
            outline: none !important;
        }

        /* Fix calendar icons in WebKit (Chrome, Edge, Safari) */
        ::-webkit-calendar-picker-indicator {
            opacity: 0.8;
            cursor: pointer;
        }

        .btn {
            padding: 8px 16px;
            flex-grow: 1;
            min-width: 0;
            width: 100%;
        }

        /* --- UNIFIED RICH BUTTON UI --- */
        .btn-rich-primary {
            background-color: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); transition: all 0.2s;
        }
        .btn-rich-primary:hover { filter: brightness(1.1); }
        
        .btn-rich-secondary {
            background-color: transparent; border: 1px solid var(--text-muted); color: var(--text-light); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;
        }
        .btn-rich-secondary:hover { background-color: rgba(255,255,255,0.05); }
        
        .btn-rich-success {
            background-color: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); transition: all 0.2s;
        }
        .btn-rich-success:hover { filter: brightness(1.1); }
        
        .btn-rich-danger {
            background-color: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); transition: all 0.2s;
        }
        .btn-rich-danger:hover { filter: brightness(1.1); }

        .btn-group-rich {
            display: inline-flex;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .btn-group-rich > button,
        .btn-group-rich > a {
            border-radius: 0 !important;
            margin: 0 !important;
            border: none !important;
            border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        .btn-group-rich > button.btn-rich-secondary {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .btn-group-rich > button.btn-rich-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .btn-group-rich > button:last-child,
        .btn-group-rich > a:last-child {
            border-right: none !important;
        }

        .btn-group-action {
            display: inline-flex;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.25);
        }
        .btn-group-action > button,
        .btn-group-action > a {
            border-radius: 0 !important;
            margin: 0 !important;
            border: none !important;
            border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        .btn-group-action > button:last-child,
        .btn-group-action > a:last-child {
            border-right: none !important;
        }
        
        .filter-tab-btn {
            padding: 6px 16px; background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; font-size: 13px; cursor: pointer; transition: all 0.2s;
        }
        .filter-tab-btn:hover { background: rgba(255,255,255,0.15); color: var(--text-light); }
        .filter-tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4); }

        @keyframes badgePulseGlow {
            0% {
                box-shadow: 0 0 4px rgba(239, 68, 68, 0.7), 0 0 8px rgba(239, 68, 68, 0.4);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 12px rgba(239, 68, 68, 1), 0 0 20px rgba(239, 68, 68, 0.8);
                transform: scale(1.08);
            }
            100% {
                box-shadow: 0 0 4px rgba(239, 68, 68, 0.7), 0 0 8px rgba(239, 68, 68, 0.4);
                transform: scale(1);
            }
        }

        .badge-notif-glow {
            background: #ef4444 !important;
            color: #ffffff !important;
            animation: badgePulseGlow 1.8s infinite ease-in-out;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.8);
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
        }

        @keyframes buttonNotifGlow {
            0% {
                border-color: rgba(239, 68, 68, 0.5);
                box-shadow: 0 0 6px rgba(239, 68, 68, 0.4), inset 0 0 4px rgba(239, 68, 68, 0.2);
            }
            50% {
                border-color: rgba(239, 68, 68, 1);
                box-shadow: 0 0 15px rgba(239, 68, 68, 0.7), inset 0 0 10px rgba(239, 68, 68, 0.3);
            }
            100% {
                border-color: rgba(239, 68, 68, 0.5);
                box-shadow: 0 0 6px rgba(239, 68, 68, 0.4), inset 0 0 4px rgba(239, 68, 68, 0.2);
            }
        }

        .filter-tab-btn.has-notif {
            animation: buttonNotifGlow 1.8s infinite ease-in-out;
            border-color: rgba(239, 68, 68, 0.7) !important;
            color: #f8fafc !important;
        }

        .filter-tab-btn.active.has-notif {
            background: var(--primary) !important;
            animation: buttonNotifGlow 1.8s infinite ease-in-out;
            border-color: #ef4444 !important;
            box-shadow: 0 0 16px rgba(239, 68, 68, 0.8), 0 2px 8px rgba(59, 130, 246, 0.4) !important;
        }

        /* CCTV Wall Monitor Animations */
        @keyframes cctvBeaconPulse {
            0% {
                box-shadow: 0 0 6px rgba(239, 68, 68, 0.7), inset 0 0 6px rgba(239, 68, 68, 0.3);
                border-color: rgba(239, 68, 68, 0.6);
            }
            50% {
                box-shadow: 0 0 22px rgba(239, 68, 68, 1), 0 0 35px rgba(239, 68, 68, 0.5), inset 0 0 12px rgba(239, 68, 68, 0.4);
                border-color: rgba(239, 68, 68, 1);
            }
            100% {
                box-shadow: 0 0 6px rgba(239, 68, 68, 0.7), inset 0 0 6px rgba(239, 68, 68, 0.3);
                border-color: rgba(239, 68, 68, 0.6);
            }
        }

        .cctv-tile-critical {
            animation: cctvBeaconPulse 2s infinite ease-in-out;
            background: radial-gradient(circle at 50% 0%, rgba(239, 68, 68, 0.15) 0%, rgba(15, 23, 42, 0.95) 80%) !important;
        }

        .live-dot-pulse {
            animation: badgePulseGlow 1.5s infinite ease-in-out;
        }

        /* Wall Display Focus Mode (No Topbar, No Sidebar) */
        body.wall-display-focus .topbar {
            display: none !important;
        }

        body.wall-display-focus .sidebar {
            display: none !important;
        }

        body.wall-display-focus .main-wrapper {
            margin-left: 0 !important;
            padding-top: 10px !important;
            transition: all 0.3s ease;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* --- CONTENT AREA --- */
        .content-area {
            padding: 10px 20px 15px 20px;
            flex-grow: 1;
            min-width: 0;
            width: 100%;
        }

        /* Existing Dashboard Styles */
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid rgba(0, 243, 255, 0.15);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), inset 0 0 20px rgba(0, 243, 255, 0.02);
            padding: 14px 18px;
            margin-bottom: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .card:hover {
            border: 1px solid rgba(0, 243, 255, 0.4);
            box-shadow: 0 8px 32px rgba(0, 243, 255, 0.1), inset 0 0 20px rgba(0, 243, 255, 0.05);
        }

        .chart-container {
            flex: 1 !important;
            min-height: 0 !important;
            height: 100% !important;
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        .chart-container > div,
        .chart-container .k-chart,
        .chart-container .k-chart-surface,
        .chart-container svg {
            height: 100% !important;
            width: 100% !important;
            flex: 1 !important;
        }

        /* Ensure Modals have ZERO X-axis scrollbars and fit content cleanly */
        div[id^="modal-"] .card,
        div[id^="modal-"] .modal-content,
        .modal-card {
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }

        /* Select2 Multi-line text wrapping (No text truncation / No horizontal scroll) */
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        .select2-container .select2-selection--single {
            height: auto !important;
            min-height: 38px !important;
            padding: 6px 30px 6px 10px !important;
            display: flex !important;
            align-items: center !important;
            background-color: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 6px !important;
        }

        .select2-container .select2-selection--single .select2-selection__rendered {
            white-space: normal !important;
            word-break: break-word !important;
            overflow: visible !important;
            text-overflow: clip !important;
            line-height: 1.4 !important;
            color: #f8fafc !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .select2-container .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
            top: 0 !important;
            right: 8px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-results__option {
            white-space: normal !important;
            word-break: break-word !important;
            line-height: 1.4 !important;
        }

        .card-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Customizing Kendo UI for Dark Theme */
        .k-grid, .k-widget {
            background-color: transparent !important;
            border: none !important;
            color: var(--text-light) !important;
        }

        .k-grid-header, .k-grid-header-wrap {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        .k-grid-header th {
            color: var(--text-muted) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            background: transparent !important;
        }

        .k-grid td {
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        
        .k-grid tr, .k-grid tr.k-alt {
            background-color: transparent !important;
        }
        
        .k-grid tr:hover {
            background-color: rgba(255, 255, 255, 0.05) !important;
        }

        /* Toolbar and Buttons */
        .k-grid-toolbar {
            background: transparent !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            padding: 10px 0 !important;
        }

        .k-button {
            background-color: rgba(59, 130, 246, 0.2) !important;
            color: #60a5fa !important;
            border: 1px solid #3b82f6 !important;
            border-radius: 6px !important;
            padding: 6px 14px !important;
            transition: all 0.2s !important;
            box-shadow: none !important;
            background-image: none !important;
        }

        .k-button:hover {
            background-color: #3b82f6 !important;
            color: white !important;
        }

        /* Input fields in edit mode */
        .k-textbox, .k-input, .k-numeric-wrap {
            background-color: rgba(15, 23, 42, 0.5) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: var(--text-light) !important;
            border-radius: 4px !important;
            box-shadow: none !important;
            background-image: none !important;
        }

        /* Disable native browser invalid highlighting (like Firefox's red box-shadow) */
        input:invalid, select:invalid, textarea:invalid {
            box-shadow: none !important;
        }

        .oos-row {
            background-color: rgba(239, 68, 68, 0.2) !important;
            border-left: 4px solid var(--danger);
        }

        .k-tooltip {
            background: rgba(15, 23, 42, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-radius: 8px !important;
        }
    </style>
    <script>
        // Apply theme immediately to html element to prevent flicker, 
        // then transfer to body once it exists.
        (function() {
            const savedTheme = localStorage.getItem('dtq-theme');
            if (savedTheme === 'robot') {
                document.documentElement.classList.add('theme-robot');
            }
        })();
        
        document.addEventListener('DOMContentLoaded', () => {
            if (document.documentElement.classList.contains('theme-robot')) {
                document.body.classList.add('theme-robot');
                document.documentElement.classList.remove('theme-robot');
            }
        });
    </script>
</head>
<body>
