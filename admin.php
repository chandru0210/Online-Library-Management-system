<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit();
}

// Stats using book_copies table
$total_titles  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM books"))['c'];
$total_copies  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM book_copies"))['c'];
$total_members = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role='member'"))['c'];
$total_issued  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE status='issued'"))['c'];
$total_returned= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE status='returned'"))['c'];
$total_avail   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM book_copies WHERE status='available'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard – Library System</title>
    <style>
        <?= COMMON_STYLE ?>

        .layout{display:flex;min-height:100vh;}
        .sidebar{width:260px;background:linear-gradient(180deg,#1e1b4b 0%,#312e81 100%);color:white;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;}
        .sidebar-header{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,.1);text-align:center;}
        .sidebar-logo{width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.15);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:26px;overflow:hidden;}
        .sidebar-logo img{width:100%;height:100%;object-fit:cover;}
        .sidebar-header h3{font-size:13px;letter-spacing:1.5px;text-transform:uppercase;opacity:.7;}
        .sidebar-nav{flex:1;padding:20px 0;}
        .nav-label{font-size:10px;letter-spacing:2px;text-transform:uppercase;opacity:.4;padding:0 24px;margin:16px 0 8px;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:13px 24px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;font-weight:600;font-family:"Nunito",sans-serif;transition:all .2s;border-left:3px solid transparent;}
        .nav-item:hover,.nav-item.active{color:white;background:rgba(255,255,255,.1);border-left-color:#818cf8;}
        .nav-item .icon{font-size:18px;width:24px;text-align:center;}
        .sidebar-footer{padding:20px 24px;border-top:1px solid rgba(255,255,255,.1);font-size:13px;opacity:.6;}

        .main{margin-left:260px;flex:1;display:flex;flex-direction:column;}
        .topbar{background:white;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.06);}
        .topbar h1{font-size:20px;color:var(--text);}
        .user-pill{display:flex;align-items:center;gap:10px;background:var(--primary-light);padding:8px 16px;border-radius:50px;font-size:14px;font-weight:700;color:var(--primary);}
        .user-pill .avatar{width:32px;height:32px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;}
        .content{padding:32px;}

        .welcome-banner{background:linear-gradient(135deg,#4A6CF7,#7B5EA7);border-radius:16px;padding:32px;color:white;margin-bottom:32px;position:relative;overflow:hidden;}
        .welcome-banner::after{content:"📚";position:absolute;right:32px;top:50%;transform:translateY(-50%);font-size:72px;opacity:.2;}
        .welcome-banner h2{font-size:26px;margin-bottom:6px;}
        .welcome-banner p{opacity:.85;font-size:15px;}

        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:32px;}
        .stat-card{background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);display:flex;align-items:center;gap:16px;transition:transform .2s;}
        .stat-card:hover{transform:translateY(-3px);}
        .stat-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;}
        .stat-icon.blue{background:#eff6ff;}
        .stat-icon.teal{background:#f0fdfa;}
        .stat-icon.green{background:#f0fdf4;}
        .stat-icon.orange{background:#fff7ed;}
        .stat-icon.purple{background:#f5f3ff;}
        .stat-icon.red{background:#fff1f2;}
        .stat-info h3{font-size:28px;font-weight:800;color:var(--text);line-height:1;}
        .stat-info p{font-size:13px;color:var(--text-muted);margin-top:4px;}

        .quick-actions{background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);}
        .quick-actions h3{font-size:18px;margin-bottom:20px;color:var(--text);}
        .action-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
        .action-card{background:var(--bg);border-radius:12px;padding:24px;text-decoration:none;color:var(--text);display:flex;flex-direction:column;align-items:center;gap:12px;transition:all .2s;border:2px solid transparent;text-align:center;}
        .action-card:hover{border-color:var(--primary);background:var(--primary-light);transform:translateY(-2px);}
        .action-card .icon{font-size:36px;}
        .action-card span{font-size:14px;font-weight:700;font-family:"Nunito",sans-serif;}

        /* LIB concept explainer */
        .concept-box{background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:14px;padding:20px 24px;margin-bottom:32px;border-left:4px solid #0ea5e9;display:flex;gap:14px;align-items:flex-start;}
        .concept-box .icon{font-size:28px;flex-shrink:0;}
        .concept-box h4{font-size:15px;color:#0c4a6e;margin-bottom:6px;}
        .concept-box p{font-size:13px;color:#0369a1;line-height:1.6;}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><img src="logo.jpeg" alt="" onerror="this.style.display='none';this.closest('.sidebar-logo').innerHTML='📚';"></div>
            <h3>Library System</h3>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main Menu</div>
            <a href="admin.php"        class="nav-item active"><span class="icon">🏠</span> Dashboard</a>
            <a href="admin_books.php"  class="nav-item"><span class="icon">📚</span> Manage Books</a>
            <a href="admin_users.php"  class="nav-item"><span class="icon">👥</span> Manage Users</a>
            <a href="admin_issued.php" class="nav-item"><span class="icon">📋</span> Issued Books</a>
            <div class="nav-label">Account</div>
            <a href="logout.php" class="nav-item"><span class="icon">🚪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">Admin Panel v3.0</div>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>Admin Dashboard</h1>
            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
        </div>

        <div class="content">
            <div class="welcome-banner">
                <h2>Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>! 👋</h2>
                <p>Here's what's happening in your library today.</p>
            </div>

            <!-- LIB Concept Box -->
            <div class="concept-box">
                <span class="icon">📌</span>
                <div>
                    <h4>Per-Copy LIB Numbering Active</h4>
                    <p>Every physical book copy has its own unique LIB number stickered on the spine.
                    Example: "Computer Networks" (qty 3) → <strong>LIB015</strong>, <strong>LIB016</strong>, <strong>LIB017</strong>.
                    Each can be issued to a different student simultaneously.</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">📖</div>
                    <div class="stat-info"><h3><?= $total_titles ?></h3><p>Book Titles</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon teal">📚</div>
                    <div class="stat-info"><h3><?= $total_copies ?></h3><p>Total Copies (LIB Numbers)</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-info"><h3><?= $total_avail ?></h3><p>Copies Available</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">👤</div>
                    <div class="stat-info"><h3><?= $total_members ?></h3><p>Members</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">📤</div>
                    <div class="stat-info"><h3><?= $total_issued ?></h3><p>Books Currently Issued</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">↩️</div>
                    <div class="stat-info"><h3><?= $total_returned ?></h3><p>Books Returned</p></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-grid">
                    <a href="admin_books.php" class="action-card">
                        <span class="icon">📚</span><span>Manage Books &amp; LIB Numbers</span>
                    </a>
                    <a href="admin_users.php" class="action-card">
                        <span class="icon">👥</span><span>Manage Users</span>
                    </a>
                    <a href="admin_issued.php" class="action-card">
                        <span class="icon">📋</span><span>Issue / Return Books</span>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
