<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit();
}

$error   = '';
$success = '';

// ── ISSUE BOOK ───────────────────────────────────────────────────
if (isset($_POST['issue_book'])) {
    $user_id    = (int)$_POST['user_id'];
    $copy_id    = (int)$_POST['copy_id'];
    $issue_date = mysqli_real_escape_string($conn, $_POST['issue_date']);

    if (!$user_id || !$copy_id || empty($issue_date)) {
        $error = "All fields are required.";
    } else {
        // Verify member exists
        $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND role = 'member'"));
        if (!$u) {
            $error = "Member ID $user_id not found.";
        } else {
            // Verify copy is available
            $c = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT bc.*, b.title FROM book_copies bc JOIN books b ON bc.book_id = b.book_id WHERE bc.copy_id = $copy_id"));
            if (!$c) {
                $error = "Copy not found.";
            } elseif ($c['status'] !== 'available') {
                $error = "Copy <strong>{$c['book_number']}</strong> of '{$c['title']}' is already issued.";
            } else {
                // Check: member already holds this title?
                $dup = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT ib.issue_id FROM issued_books ib
                     JOIN book_copies bc ON ib.copy_id = bc.copy_id
                     WHERE ib.user_id = $user_id AND bc.book_id = {$c['book_id']} AND ib.status = 'issued'"));
                if ($dup) {
                    $error = "Member <strong>{$u['name']}</strong> already has a copy of '<strong>{$c['title']}</strong>' issued (Issue #{$dup['issue_id']}).";
                } else {
                    mysqli_query($conn,
                        "INSERT INTO issued_books (user_id, copy_id, issue_date, status)
                         VALUES ($user_id, $copy_id, '$issue_date', 'issued')");
                    mysqli_query($conn, "UPDATE book_copies SET status = 'issued' WHERE copy_id = $copy_id");
                    $success = "Copy <strong>{$c['book_number']}</strong> — <em>{$c['title']}</em> issued to <strong>{$u['name']}</strong> successfully!";
                }
            }
        }
    }
}

// ── RETURN BOOK ──────────────────────────────────────────────────
if (isset($_POST['return_book'])) {
    $issue_id   = (int)$_POST['issue_id'];
    $return_date = mysqli_real_escape_string($conn, $_POST['return_date']);

    if (!$issue_id || empty($return_date)) {
        $error = "Issue ID and Return Date are required.";
    } else {
        $iss = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT ib.*, bc.book_number, b.title
             FROM issued_books ib
             JOIN book_copies bc ON ib.copy_id = bc.copy_id
             JOIN books b ON bc.book_id = b.book_id
             WHERE ib.issue_id = $issue_id AND ib.status = 'issued'"));
        if (!$iss) {
            $error = "No active issue found with Issue ID #$issue_id.";
        } else {
            mysqli_query($conn,
                "UPDATE issued_books SET status = 'returned', return_date = '$return_date'
                 WHERE issue_id = $issue_id");
            mysqli_query($conn,
                "UPDATE book_copies SET status = 'available' WHERE copy_id = {$iss['copy_id']}");
            $success = "Copy <strong>{$iss['book_number']}</strong> — <em>{$iss['title']}</em> marked as returned successfully!";
        }
    }
}

// ── DATA FOR DROPDOWNS ────────────────────────────────────────────
$members = mysqli_query($conn,
    "SELECT id, reg_no, name FROM users WHERE role='member' ORDER BY name");

// Only available copies grouped by title
$avail_copies = mysqli_query($conn,
    "SELECT bc.copy_id, bc.book_number, b.title, b.book_id
     FROM book_copies bc
     JOIN books b ON bc.book_id = b.book_id
     WHERE bc.status = 'available'
     ORDER BY b.title, bc.book_number");

// All issue records
$issued = mysqli_query($conn, "
    SELECT ib.issue_id, ib.issue_date, ib.return_date, ib.status,
           u.name AS member_name, u.reg_no,
           bc.book_number, bc.copy_id,
           b.title AS book_title, b.book_id
    FROM issued_books ib
    JOIN users u      ON ib.user_id  = u.id
    JOIN book_copies bc ON ib.copy_id = bc.copy_id
    JOIN books b      ON bc.book_id  = b.book_id
    ORDER BY ib.status ASC, ib.issue_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issued Books – Library System</title>
    <style>
        <?= COMMON_STYLE ?>

        .layout{display:flex;min-height:100vh;}
        .sidebar{width:260px;background:linear-gradient(180deg,#1e1b4b 0%,#312e81 100%);color:white;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;}
        .sidebar-header{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,.1);text-align:center;}
        .sidebar-logo{width:55px;height:55px;border-radius:50%;background:rgba(255,255,255,.15);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:24px;overflow:hidden;}
        .sidebar-logo img{width:100%;height:100%;object-fit:cover;}
        .sidebar-header h3{font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.7;}
        .sidebar-nav{flex:1;padding:20px 0;}
        .nav-label{font-size:10px;letter-spacing:2px;text-transform:uppercase;opacity:.4;padding:0 24px;margin:16px 0 8px;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:13px 24px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;font-weight:600;font-family:"Nunito",sans-serif;transition:all .2s;border-left:3px solid transparent;}
        .nav-item:hover,.nav-item.active{color:white;background:rgba(255,255,255,.1);border-left-color:#818cf8;}
        .nav-item .icon{font-size:18px;width:24px;text-align:center;}
        .sidebar-footer{padding:20px 24px;border-top:1px solid rgba(255,255,255,.1);font-size:13px;opacity:.6;}

        .main{margin-left:260px;flex:1;display:flex;flex-direction:column;}
        .topbar{background:white;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.06);}
        .topbar h1{font-size:20px;}
        .user-pill{display:flex;align-items:center;gap:10px;background:var(--primary-light);padding:8px 16px;border-radius:50px;font-size:14px;font-weight:700;color:var(--primary);}
        .user-pill .avatar{width:32px;height:32px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;}

        .content{padding:32px;}
        .panels{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;}
        .panel{background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);}
        .panel h3{font-size:18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;font-family:"Nunito",sans-serif;}
        .form-group input,.form-group select{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:8px;font-size:14px;font-family:"Lato",sans-serif;outline:none;transition:border-color .2s;}
        .form-group input:focus,.form-group select:focus{border-color:var(--primary);}
        .tip-box{background:var(--primary-light);border-radius:8px;padding:12px 14px;margin-top:14px;font-size:13px;color:var(--primary);}

        .section{background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);}
        .section-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
        .section-top h2{font-size:20px;}

        /* Filter row */
        .filter-row{display:flex;gap:10px;align-items:center;}
        .filter-row input,.filter-row select{padding:8px 12px;border:2px solid var(--border);border-radius:8px;font-size:13px;outline:none;}
        .filter-row input:focus,.filter-row select:focus{border-color:var(--primary);}

        /* Inline return form inside table */
        .return-form{display:flex;align-items:center;gap:6px;}
        .return-form input[type="date"]{padding:5px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;outline:none;}
        .return-form input:focus{border-color:var(--success);}

        .overdue td{background:#fff1f2 !important;}
        .overdue-tag{font-size:11px;color:#b91c1c;font-weight:700;display:block;margin-top:3px;}

        /* Stats strip */
        .stats-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
        .sc{background:white;border-radius:12px;padding:16px 18px;box-shadow:var(--shadow);text-align:center;border-top:4px solid var(--primary);}
        .sc.g{border-top-color:var(--success);}
        .sc.o{border-top-color:#f97316;}
        .sc.r{border-top-color:var(--danger);}
        .sc h3{font-size:26px;color:var(--primary);margin-bottom:4px;}
        .sc.g h3{color:var(--success);}
        .sc.o h3{color:#f97316;}
        .sc.r h3{color:var(--danger);}
        .sc p{color:#888;font-size:13px;}
    </style>
</head>
<body>
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><img src="logo.jpeg" alt="" onerror="this.style.display='none';this.closest('.sidebar-logo').innerHTML='📚';"></div>
            <h3>Library System</h3>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main Menu</div>
            <a href="admin.php"        class="nav-item"><span class="icon">🏠</span> Dashboard</a>
            <a href="admin_books.php"  class="nav-item"><span class="icon">📚</span> Manage Books</a>
            <a href="admin_users.php"  class="nav-item"><span class="icon">👥</span> Manage Users</a>
            <a href="admin_issued.php" class="nav-item active"><span class="icon">📋</span> Issued Books</a>
            <div class="nav-label">Account</div>
            <a href="logout.php" class="nav-item"><span class="icon">🚪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">Admin Panel v3.0</div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <div class="topbar">
            <h1>Issue &amp; Return Books</h1>
            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
        </div>

        <div class="content">

            <?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <!-- Stats -->
            <?php
                $s_total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books"))['c'];
                $s_issued   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE status='issued'"))['c'];
                $s_returned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM issued_books WHERE status='returned'"))['c'];
                $s_overdue  = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) c FROM issued_books WHERE status='issued' AND issue_date < DATE_SUB(CURDATE(), INTERVAL 14 DAY)"))['c'];
            ?>
            <div class="stats-strip">
                <div class="sc"><h3><?= $s_total ?></h3><p>📋 Total Records</p></div>
                <div class="sc o"><h3><?= $s_issued ?></h3><p>📤 Currently Issued</p></div>
                <div class="sc g"><h3><?= $s_returned ?></h3><p>✅ Returned</p></div>
                <div class="sc r"><h3><?= $s_overdue ?></h3><p>⚠️ Overdue (&gt;14 days)</p></div>
            </div>

            <!-- Issue / Return Panels -->
            <div class="panels">

                <!-- ISSUE PANEL -->
                <div class="panel">
                    <h3>📤 Issue a Book Copy</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Member</label>
                            <select name="user_id" required>
                                <option value="">-- Choose Member --</option>
                                <?php while ($m = mysqli_fetch_assoc($members)): ?>
                                <option value="<?= $m['id'] ?>">
                                    [ID:<?= $m['id'] ?>] <?= htmlspecialchars($m['name']) ?>
                                    <?= $m['reg_no'] ? " | " . $m['reg_no'] : '' ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Select Available Copy (LIB Number)</label>
                            <select name="copy_id" required>
                                <option value="">-- Choose a Copy --</option>
                                <?php
                                $last_title = '';
                                while ($ac = mysqli_fetch_assoc($avail_copies)):
                                    if ($ac['title'] !== $last_title) {
                                        if ($last_title !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($ac['title']) . '">';
                                        $last_title = $ac['title'];
                                    }
                                ?>
                                <option value="<?= $ac['copy_id'] ?>">
                                    <?= $ac['book_number'] ?> — <?= htmlspecialchars($ac['title']) ?>
                                </option>
                                <?php endwhile; if ($last_title !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Issue Date</label>
                            <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" name="issue_book" class="btn btn-primary" style="width:100%">
                            📤 Issue This Copy
                        </button>
                    </form>
                    <div class="tip-box">
                        <strong>Note:</strong> Each LIB number = one specific physical book on the shelf.
                        Only <em>available</em> copies appear in the dropdown.
                    </div>
                </div>

                <!-- RETURN PANEL -->
                <div class="panel">
                    <h3>📥 Return a Book</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Issue ID</label>
                            <input type="number" name="issue_id" placeholder="Enter Issue ID from the table below" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Return Date</label>
                            <input type="date" name="return_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" name="return_book" class="btn btn-success" style="width:100%">
                            📥 Mark as Returned
                        </button>
                    </form>
                    <div class="tip-box">
                        <strong>Tip:</strong> Find the <strong>Issue ID</strong> in the table below under the
                        "📤 Issued" rows. You can also use the inline Return button there.
                    </div>

                    <!-- Quick-return from table tip -->
                    <div style="margin-top:14px;background:#f0fdf4;border-radius:8px;padding:12px 14px;font-size:13px;color:#166534;border-left:3px solid var(--success);">
                        ✅ You can also click <strong>Return</strong> directly from the table rows below.
                    </div>
                </div>

            </div>

            <!-- ISSUE RECORDS TABLE -->
            <div class="section">
                <div class="section-top">
                    <h2>📋 All Issue Records</h2>
                    <!-- Filter -->
                    <div class="filter-row">
                        <input type="text" id="filterInput" placeholder="🔍 Search name / LIB / title…" oninput="filterTable()">
                        <select id="filterStatus" onchange="filterTable()">
                            <option value="">All Status</option>
                            <option value="issued">📤 Issued</option>
                            <option value="returned">✅ Returned</option>
                        </select>
                    </div>
                </div>

                <div style="overflow-x:auto">
                <table id="issueTable">
                    <thead>
                        <tr>
                            <th>Issue ID</th>
                            <th>Member</th>
                            <th>Reg No</th>
                            <th>LIB Number</th>
                            <th>Book Title</th>
                            <th>Issue Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($issued)):
                        $is_overdue = $row['status'] === 'issued'
                                   && strtotime($row['issue_date']) < strtotime('-14 days');
                    ?>
                    <tr class="<?= $is_overdue ? 'overdue' : '' ?>"
                        data-status="<?= $row['status'] ?>"
                        data-text="<?= strtolower($row['member_name'] . ' ' . $row['book_number'] . ' ' . $row['book_title'] . ' ' . $row['reg_no']) ?>">
                        <td><strong>#<?= $row['issue_id'] ?></strong></td>
                        <td><?= htmlspecialchars($row['member_name']) ?></td>
                        <td><?= htmlspecialchars($row['reg_no'] ?? '—') ?></td>
                        <td><span class="lib-tag"><?= $row['book_number'] ?></span></td>
                        <td><?= htmlspecialchars($row['book_title']) ?></td>
                        <td><?= date('d:m:Y', strtotime($row['issue_date'])) ?></td>
                        <td><?= $row['return_date'] ? date('d:m:Y', strtotime($row['return_date'])) : '—' ?></td>
                        <td>
                            <span class="badge badge-<?= $row['status'] ?>">
                                <?= $row['status'] === 'issued' ? '📤 Issued' : '✅ Returned' ?>
                            </span>
                            <?php if ($is_overdue): ?>
                                <span class="overdue-tag">⚠️ Overdue!</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'issued'): ?>
                            <!-- Inline quick-return -->
                            <form method="POST" class="return-form">
                                <input type="hidden" name="issue_id" value="<?= $row['issue_id'] ?>">
                                <input type="date" name="return_date" value="<?= date('Y-m-d') ?>">
                                <button type="submit" name="return_book" class="btn btn-success btn-sm"
                                        onclick="return confirm('Return copy <?= $row['book_number'] ?> from <?= htmlspecialchars($row['member_name']) ?>?')">
                                    📥 Return
                                </button>
                            </form>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:13px;">✅ Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>

                <p style="margin-top:12px;font-size:13px;color:var(--text-muted);">
                    📅 Dates displayed as DD:MM:YYYY &nbsp;|&nbsp; ⚠️ Overdue after 14 days from issue date.
                </p>
            </div>
        </div><!-- /content -->
    </main>
</div>

<script>
// Live search / filter on issue table
function filterTable() {
    const q      = document.getElementById('filterInput').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    document.querySelectorAll('#issueTable tbody tr').forEach(tr => {
        const textMatch   = !q      || tr.dataset.text.includes(q);
        const statusMatch = !status || tr.dataset.status === status;
        tr.style.display  = textMatch && statusMatch ? '' : 'none';
    });
}
</script>
</body>
</html>
