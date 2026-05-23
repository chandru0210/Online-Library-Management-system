<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: login.php"); exit();
}

$uid = (int)$_SESSION['user_id'];

$issued = mysqli_query($conn, "
    SELECT ib.issue_id, ib.issue_date, ib.return_date, ib.status,
           bc.book_number,
           b.title, b.author, b.publisher
    FROM issued_books ib
    JOIN book_copies bc ON ib.copy_id = bc.copy_id
    JOIN books b        ON bc.book_id = b.book_id
    WHERE ib.user_id = $uid
    ORDER BY ib.status ASC, ib.issue_date DESC
");

$member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $uid"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Books – Library System</title>
    <style>
        <?= COMMON_STYLE ?>
        .layout{display:flex;min-height:100vh;}
        .sidebar{width:240px;background:linear-gradient(180deg,#0f172a 0%,#1e3a5f 100%);color:white;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;}
        .sidebar-header{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,.1);text-align:center;}
        .sidebar-logo{width:55px;height:55px;border-radius:50%;background:rgba(255,255,255,.15);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:24px;overflow:hidden;}
        .sidebar-logo img{width:100%;height:100%;object-fit:cover;}
        .sidebar-header h3{font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.7;}
        .sidebar-nav{flex:1;padding:20px 0;}
        .nav-label{font-size:10px;letter-spacing:2px;text-transform:uppercase;opacity:.4;padding:0 24px;margin:16px 0 8px;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:13px 24px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;font-weight:600;font-family:"Nunito",sans-serif;transition:all .2s;border-left:3px solid transparent;}
        .nav-item:hover,.nav-item.active{color:white;background:rgba(255,255,255,.1);border-left-color:#60a5fa;}
        .nav-item .icon{font-size:18px;width:24px;text-align:center;}
        .sidebar-footer{padding:20px 24px;border-top:1px solid rgba(255,255,255,.1);font-size:13px;opacity:.6;}
        .main{margin-left:240px;flex:1;display:flex;flex-direction:column;}
        .topbar{background:white;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.06);}
        .topbar h1{font-size:20px;}
        .user-pill{display:flex;align-items:center;gap:10px;background:#eff6ff;padding:8px 16px;border-radius:50px;font-size:14px;font-weight:700;color:#1d4ed8;}
        .user-pill .avatar{width:32px;height:32px;border-radius:50%;background:#2563eb;color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;}
        .content{padding:32px;}
        .member-card{background:linear-gradient(135deg,#1d4ed8,#3b82f6);border-radius:16px;padding:24px 28px;color:white;margin-bottom:28px;display:flex;align-items:center;gap:20px;}
        .member-avatar{width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;}
        .member-info h3{font-size:20px;}
        .member-info p{opacity:.8;font-size:14px;margin-top:4px;}
        .section{background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);}
        .section h2{font-size:20px;margin-bottom:20px;}
        .overdue td{background:#fff1f2 !important;}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><img src="logo.jpeg" alt="" onerror="this.style.display='none';this.closest('.sidebar-logo').innerHTML='📚';"></div>
            <h3>Library Portal</h3>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="member.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
            <a href="member_issued.php" class="nav-item active"><span class="icon">📚</span> My Books</a>
            <div class="nav-label">Account</div>
            <a href="logout.php" class="nav-item"><span class="icon">🚪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">Member Portal</div>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>My Issued Books</h1>
            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
        </div>

        <div class="content">
            <div class="member-card">
                <div class="member-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <div class="member-info">
                    <h3><?= htmlspecialchars($member['name']) ?></h3>
                    <p>
                        Username: <?= htmlspecialchars($member['username']) ?>
                        <?php if ($member['reg_no']): ?>&nbsp;|&nbsp; Reg No: <?= htmlspecialchars($member['reg_no']) ?><?php endif; ?>
                        &nbsp;|&nbsp; Role: Member
                    </p>
                </div>
            </div>

            <div class="section">
                <h2>📚 My Book History</h2>
                <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Issue ID</th>
                            <th>LIB Number</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>Issue Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = [];
                        while ($row = mysqli_fetch_assoc($issued)) $rows[] = $row;

                        if (empty($rows)):
                        ?>
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">
                                📭 You haven't borrowed any books yet.
                            </td>
                        </tr>
                        <?php else: foreach ($rows as $row):
                            $is_overdue = $row['status'] === 'issued' && strtotime($row['issue_date']) < strtotime('-14 days');
                        ?>
                        <tr <?= $is_overdue ? 'class="overdue"' : '' ?>>
                            <td><strong>#<?= $row['issue_id'] ?></strong></td>
                            <td><span class="lib-tag"><?= $row['book_number'] ?></span></td>
                            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                            <td><?= htmlspecialchars($row['author']) ?></td>
                            <td><?= htmlspecialchars($row['publisher']) ?></td>
                            <td><?= date('d:m:Y', strtotime($row['issue_date'])) ?></td>
                            <td><?= $row['return_date'] ? date('d:m:Y', strtotime($row['return_date'])) : '—' ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status'] ?>">
                                    <?= $row['status'] === 'issued' ? '📤 Issued' : '✅ Returned' ?>
                                </span>
                                <?= $is_overdue ? '<br><span style="font-size:11px;color:#b91c1c;font-weight:700">⚠️ Overdue!</span>' : '' ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>

                <div style="margin-top:16px;padding:12px 16px;background:var(--primary-light);border-radius:8px;font-size:13px;color:var(--primary);">
                    📌 Books are marked overdue after 14 days. Please return on time to avoid penalties.
                    Contact the library admin to return your books. &nbsp;|&nbsp; 📅 Dates shown as DD:MM:YYYY.
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
