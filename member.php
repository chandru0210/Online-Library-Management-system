<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: login.php"); exit();
}

$uid = (int)$_SESSION['user_id'];

// All books with per-title copy stats
$books = mysqli_query($conn, "
    SELECT b.*,
           COUNT(bc.copy_id)          AS total_copies,
           SUM(bc.status='available') AS available_copies
    FROM books b
    LEFT JOIN book_copies bc ON b.book_id = bc.book_id
    GROUP BY b.book_id
    ORDER BY b.title
");

// Member's issued books
$my_issued = mysqli_query($conn, "
    SELECT ib.issue_id, ib.issue_date, ib.return_date, ib.status,
           bc.book_number,
           b.title, b.author, b.book_id
    FROM issued_books ib
    JOIN book_copies bc ON ib.copy_id = bc.copy_id
    JOIN books b        ON bc.book_id = b.book_id
    WHERE ib.user_id = $uid
    ORDER BY ib.status ASC, ib.issue_date DESC
");

$cnt_issued   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE user_id=$uid AND status='issued'"))['c'];
$cnt_returned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE user_id=$uid AND status='returned'"))['c'];
$total_titles = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM books"))['c'];
$avail_copies = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM book_copies WHERE status='available'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Member Dashboard – Library System</title>
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
        .nav-item{display:flex;align-items:center;gap:12px;padding:13px 24px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;font-weight:600;transition:all .2s;border-left:3px solid transparent;}
        .nav-item:hover,.nav-item.active{color:white;background:rgba(255,255,255,.1);border-left-color:#60a5fa;}
        .nav-item .icon{font-size:18px;width:24px;text-align:center;}
        .sidebar-footer{padding:20px 24px;border-top:1px solid rgba(255,255,255,.1);font-size:13px;opacity:.6;}
        .main{margin-left:240px;flex:1;display:flex;flex-direction:column;}
        .topbar{background:white;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.06);}
        .topbar h1{font-size:20px;}
        .user-pill{display:flex;align-items:center;gap:10px;background:#eff6ff;padding:8px 16px;border-radius:50px;font-size:14px;font-weight:700;color:#1d4ed8;}
        .user-pill .avatar{width:32px;height:32px;border-radius:50%;background:#2563eb;color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;}
        .content{padding:32px;}
        .welcome-banner{background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:white;border-radius:16px;padding:28px 32px;margin-bottom:28px;}
        .welcome-banner h1{font-size:24px;margin-bottom:6px;}
        .welcome-banner p{opacity:.85;font-size:15px;}
        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
        .sc{background:white;border-radius:12px;padding:16px 18px;box-shadow:var(--shadow);border-top:4px solid #2563eb;text-align:center;}
        .sc.g{border-top-color:#22c55e;}
        .sc.p{border-top-color:#7c3aed;}
        .sc.o{border-top-color:#f97316;}
        .sc h3{font-size:28px;color:#2563eb;margin-bottom:4px;}
        .sc.g h3{color:#22c55e;} .sc.p h3{color:#7c3aed;} .sc.o h3{color:#f97316;}
        .sc p{color:#888;font-size:13px;}
        .panel{background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:24px;}
        .panel h2{font-size:20px;margin-bottom:20px;}
        .tab-buttons{display:flex;gap:10px;margin-bottom:20px;}
        .tab-btn{padding:8px 20px;border:2px solid #2563eb;border-radius:8px;cursor:pointer;background:white;color:#2563eb;font-weight:700;font-size:14px;}
        .tab-btn.active{background:#2563eb;color:white;}
        .tab-content{display:none;}
        .tab-content.active{display:block;}
        .overdue td{background:#fff1f2 !important;}
    </style>
    <script>
    function switchTab(tab, btn) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        btn.classList.add('active');
    }
    function searchBooks() {
        const q = document.getElementById('bookSearch').value.toLowerCase();
        const rows = document.querySelectorAll('#booksTableBody tr');
        let found = 0;
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const match = text.includes(q);
            row.style.display = match ? '' : 'none';
            if (match) found++;
        });
        // Show/hide no-results row
        let noRes = document.getElementById('noResultsRow');
        if (found === 0) {
            if (!noRes) {
                noRes = document.createElement('tr');
                noRes.id = 'noResultsRow';
                noRes.innerHTML = '<td colspan="5" style="text-align:center;padding:24px;color:#aaa;">🔍 No books match your search.</td>';
                document.getElementById('booksTableBody').appendChild(noRes);
            }
            noRes.style.display = '';
        } else if (noRes) {
            noRes.style.display = 'none';
        }
    }
    </script>
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
            <a href="member.php"        class="nav-item active"><span class="icon">🏠</span> Dashboard</a>
            <a href="member_issued.php" class="nav-item"><span class="icon">📚</span> My Books</a>
            <div class="nav-label">Account</div>
            <a href="logout.php" class="nav-item"><span class="icon">🚪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">Member Portal</div>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>Member Dashboard</h1>
            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
        </div>

        <div class="content">
            <div class="welcome-banner">
                <h1>👋 Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</h1>
                <p>Browse the library catalogue and track your borrowed books below.</p>
            </div>

            <div class="stats-row">
                <div class="sc"><h3><?= $total_titles ?></h3><p>📖 Book Titles</p></div>
                <div class="sc g"><h3><?= $avail_copies ?></h3><p>✅ Copies Available</p></div>
                <div class="sc p"><h3><?= $cnt_issued ?></h3><p>📤 Books I Hold</p></div>
                <div class="sc o"><h3><?= $cnt_returned ?></h3><p>↩️ I've Returned</p></div>
            </div>

            <div class="panel">
                <h2>📖 Library</h2>
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="switchTab('browse',this)">📚 Browse Books</button>
                    <button class="tab-btn"        onclick="switchTab('mybooks',this)">📋 My Issued Books</button>
                </div>

                <!-- BROWSE -->
                <div id="tab-browse" class="tab-content active">
                    <div style="margin-bottom:16px;">
                        <input type="text" id="bookSearch" onkeyup="searchBooks()" placeholder="🔍 Search by title, author or publisher..." style="width:100%;padding:10px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;outline:none;transition:border-color .2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
                    </div>
                    <div style="overflow-x:auto">
                    <table id="booksTable">

                        <thead>
                            <tr>
                                <th>Book ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Publisher</th>
                                <th>Copies Available</th>
                            </tr>
                        </thead>
                        <tbody id="booksTableBody">
                        <?php while ($book = mysqli_fetch_assoc($books)):
                            $av = (int)$book['available_copies'];
                            $tot= (int)$book['total_copies'];
                        ?>
                        <tr>
                            <td><strong>#<?= $book['book_id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                            <td><?= htmlspecialchars($book['author']) ?></td>
                            <td><?= htmlspecialchars($book['publisher']) ?></td>
                            <td>
                                <?php if ($av > 0): ?>
                                <span class="badge" style="background:#dcfce7;color:#166534;padding:4px 12px;border-radius:20px;font-weight:700">
                                    ✅ <?= $av ?> / <?= $tot ?> Available
                                </span>
                                <?php else: ?>
                                <span class="badge" style="background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:20px;font-weight:700">
                                    ❌ All <?= $tot ?> Issued
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                    <p style="margin-top:14px;color:#888;font-size:13px;">
                        💡 To borrow a book, contact the library admin. They will select your name and assign a specific LIB copy to you.
                    </p>
                </div>

                <!-- MY BOOKS -->
                <div id="tab-mybooks" class="tab-content">
                    <?php if (mysqli_num_rows($my_issued) === 0): ?>
                    <div style="text-align:center;padding:32px;color:#aaa">
                        <p style="font-size:40px">📭</p>
                        <p>You haven't borrowed any books yet.</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Issue ID</th>
                                <th>LIB Number</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Issue Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysqli_fetch_assoc($my_issued)):
                            $is_overdue = $row['status'] === 'issued' && strtotime($row['issue_date']) < strtotime('-14 days');
                        ?>
                        <tr <?= $is_overdue ? 'class="overdue"' : '' ?>>
                            <td><strong>#<?= $row['issue_id'] ?></strong></td>
                            <td><span class="lib-tag"><?= $row['book_number'] ?></span></td>
                            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                            <td><?= htmlspecialchars($row['author']) ?></td>
                            <td><?= date('d:m:Y', strtotime($row['issue_date'])) ?></td>
                            <td><?= $row['return_date'] ? date('d:m:Y', strtotime($row['return_date'])) : '—' ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status'] ?>">
                                    <?= $row['status'] === 'issued' ? '📤 Issued' : '✅ Returned' ?>
                                </span>
                                <?php if ($is_overdue): ?>
                                    <br><span style="font-size:11px;color:#b91c1c;font-weight:700">⚠️ Overdue!</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
