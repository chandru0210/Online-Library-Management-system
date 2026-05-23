<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit();
}

$error   = '';
$success = '';

// ── ADD BOOK ──────────────────────────────────────────────────────
if (isset($_POST['add_book'])) {
    $title     = trim(mysqli_real_escape_string($conn, $_POST['title']));
    $author    = trim(mysqli_real_escape_string($conn, $_POST['author']));
    $publisher = trim(mysqli_real_escape_string($conn, $_POST['publisher']));
    $quantity  = (int)$_POST['quantity'];

    if (empty($title) || empty($author) || empty($publisher) || $quantity < 1) {
        $error = "All fields are required and quantity must be ≥ 1.";
    } else {
        mysqli_query($conn, "INSERT INTO books (title, author, publisher) VALUES ('$title','$author','$publisher')");
        $book_id = mysqli_insert_id($conn);

        // Generate one book_copies row per physical copy
        $generated = [];
        for ($i = 0; $i < $quantity; $i++) {
            $lib = nextLibNumber($conn);
            mysqli_query($conn, "INSERT INTO book_copies (book_id, book_number) VALUES ($book_id, '$lib')");
            $generated[] = $lib;
        }
        $success = "Book '<strong>$title</strong>' added with $quantity cop" . ($quantity === 1 ? 'y' : 'ies') . ": "
                 . implode(', ', $generated) . ".";
    }
}

// ── DELETE BOOK ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Check if any copy is currently issued
    $active = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM issued_books ib
         JOIN book_copies bc ON ib.copy_id = bc.copy_id
         WHERE bc.book_id = $del_id AND ib.status = 'issued'"))['c'];
    if ($active > 0) {
        $error = "Cannot delete: $active cop" . ($active === 1 ? 'y is' : 'ies are') . " currently issued.";
    } else {
        mysqli_query($conn, "DELETE FROM books WHERE book_id = $del_id");
        $success = "Book deleted successfully.";
    }
}

// ── EDIT BOOK (title, author, publisher + total copies) ───────────
if (isset($_POST['edit_book'])) {
    $edit_id   = (int)$_POST['edit_id'];
    $title     = trim(mysqli_real_escape_string($conn, $_POST['title']));
    $author    = trim(mysqli_real_escape_string($conn, $_POST['author']));
    $publisher = trim(mysqli_real_escape_string($conn, $_POST['publisher']));
    $new_total = (int)$_POST['new_total'];

    if (empty($title) || empty($author) || empty($publisher) || $new_total < 1) {
        $error = "All fields are required and total copies must be ≥ 1.";
    } else {
        // Update book details
        mysqli_query($conn, "UPDATE books SET title='$title', author='$author', publisher='$publisher' WHERE book_id=$edit_id");

        // Adjust copy count
        $cur_total  = (int)mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) c FROM book_copies WHERE book_id=$edit_id"))['c'];
        $cur_issued = (int)mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) c FROM book_copies WHERE book_id=$edit_id AND status='issued'"))['c'];
        $diff = $new_total - $cur_total;

        if ($diff > 0) {
            // Need to ADD copies
            $generated = [];
            for ($i = 0; $i < $diff; $i++) {
                $lib = nextLibNumber($conn);
                mysqli_query($conn, "INSERT INTO book_copies (book_id, book_number) VALUES ($edit_id, '$lib')");
                $generated[] = $lib;
            }
            $success = "Book '<strong>$title</strong>' updated. Added <strong>$diff</strong> new cop"
                     . ($diff === 1 ? 'y' : 'ies') . ": " . implode(', ', $generated) . ".";

        } elseif ($diff < 0) {
            // Need to REMOVE copies — only available ones
            $to_remove  = abs($diff);
            $avail_now  = $cur_total - $cur_issued;

            if ($new_total < $cur_issued) {
                $error = "Cannot set total to <strong>$new_total</strong> — <strong>$cur_issued</strong> cop"
                       . ($cur_issued === 1 ? 'y is' : 'ies are')
                       . " currently issued and cannot be removed. Minimum allowed total: <strong>$cur_issued</strong>.";
            } else {
                $rem_rows = mysqli_query($conn,
                    "SELECT copy_id, book_number FROM book_copies
                     WHERE book_id=$edit_id AND status='available'
                     ORDER BY copy_id DESC LIMIT $to_remove");
                $removed = [];
                while ($rc = mysqli_fetch_assoc($rem_rows)) {
                    mysqli_query($conn, "DELETE FROM book_copies WHERE copy_id={$rc['copy_id']}");
                    $removed[] = $rc['book_number'];
                }
                $success = "Book '<strong>$title</strong>' updated. Removed <strong>" . count($removed)
                         . "</strong> cop" . (count($removed) === 1 ? 'y' : 'ies')
                         . ": " . implode(', ', $removed) . ".";
            }
        } else {
            // No copy count change — just details updated
            $success = "Book '<strong>$title</strong>' details updated successfully.";
        }
    }
}

// ── FETCH ALL BOOKS WITH COPY STATS ──────────────────────────────
$books = mysqli_query($conn, "
    SELECT b.*,
           COUNT(bc.copy_id)                                    AS total_copies,
           SUM(bc.status = 'available')                         AS available_copies,
           SUM(bc.status = 'issued')                            AS issued_copies,
           MIN(bc.book_number)                                  AS first_lib,
           MAX(bc.book_number)                                  AS last_lib
    FROM books b
    LEFT JOIN book_copies bc ON b.book_id = bc.book_id
    GROUP BY b.book_id
    ORDER BY b.book_id
");

// Sidebar shared style block
$sidebar_css = '
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
    .section{background:white;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:24px;}
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
    .add-form{display:none;background:var(--bg);border-radius:12px;padding:24px;margin-bottom:24px;border:2px dashed var(--primary);}
    .add-form.visible{display:block;}
    .form-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
    .form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;font-family:"Nunito",sans-serif;}
    .form-group input,.form-group select{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:8px;font-size:14px;font-family:"Lato",sans-serif;outline:none;}
    .form-group input:focus,.form-group select:focus{border-color:var(--primary);}
    .form-actions{margin-top:16px;display:flex;gap:10px;}
    .copy-pills{display:flex;flex-wrap:wrap;gap:4px;}
    .qty-bar{width:100%;background:#e2e8f0;border-radius:4px;height:6px;margin-top:4px;}
    .qty-fill{height:6px;border-radius:4px;background:var(--success);}

    /* Search bar above table */
    .search-bar{display:flex;gap:10px;align-items:center;margin-bottom:18px;}
    .search-bar input{flex:1;padding:10px 14px;border:2px solid var(--border);border-radius:8px;font-size:14px;font-family:"Lato",sans-serif;outline:none;transition:border-color .2s;}
    .search-bar input:focus{border-color:var(--primary);}

    /* Edit modal overlay */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:white;border-radius:16px;padding:36px;width:540px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative;}
    .modal-box h3{font-size:20px;margin-bottom:4px;color:var(--primary);display:flex;align-items:center;gap:10px;}
    .modal-subtitle{font-size:13px;color:var(--text-muted);margin-bottom:22px;}
    .modal-close{position:absolute;top:14px;right:18px;font-size:22px;cursor:pointer;color:var(--text-muted);background:none;border:none;line-height:1;}
    .modal-close:hover{color:var(--danger);}
    .modal-form-group{margin-bottom:16px;}
    .modal-form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;font-family:"Nunito",sans-serif;color:var(--text);}
    .modal-form-group input{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:8px;font-size:14px;font-family:"Lato",sans-serif;outline:none;transition:border-color .2s;}
    .modal-form-group input:focus{border-color:var(--primary);}
    .modal-form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .modal-divider{border:none;border-top:2px dashed var(--border);margin:18px 0;}
    .modal-tip{background:var(--primary-light);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--primary);margin-bottom:16px;}
    .modal-actions{display:flex;gap:10px;margin-top:22px;}

    /* action cell */
    .action-cell{display:flex;gap:6px;align-items:center;}
';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Books – Library System</title>
    <style>
        <?= COMMON_STYLE ?>
        <?= $sidebar_css ?>
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
            <a href="admin.php"        class="nav-item"><span class="icon">🏠</span> Dashboard</a>
            <a href="admin_books.php"  class="nav-item active"><span class="icon">📚</span> Manage Books</a>
            <a href="admin_users.php"  class="nav-item"><span class="icon">👥</span> Manage Users</a>
            <a href="admin_issued.php" class="nav-item"><span class="icon">📋</span> Issued Books</a>
            <div class="nav-label">Account</div>
            <a href="logout.php" class="nav-item"><span class="icon">🚪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">Admin Panel v3.0</div>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>Manage Books</h1>
            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
        </div>

        <div class="content">
            <?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <!-- Info Banner -->
            <div style="background:linear-gradient(135deg,#4A6CF7,#7B5EA7);border-radius:14px;padding:20px 28px;color:white;margin-bottom:24px;display:flex;align-items:center;gap:16px;">
                <span style="font-size:36px">📖</span>
                <div>
                    <strong style="font-size:16px;font-family:'Nunito',sans-serif;">Per-Copy LIB Numbering</strong><br>
                    <span style="font-size:13px;opacity:.9;">Each physical copy gets its own unique LIB number.<br>
                    Adding "Computer Networks" with quantity 3 → creates LIBxxx, LIBxxx+1, LIBxxx+2.</span>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>📚 All Books &amp; Copies</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('visible')">+ Add New Book</button>
                </div>

                <!-- Add Form -->
                <div class="add-form" id="addForm">
                    <h3 style="margin-bottom:16px;color:var(--primary);">➕ Add New Book Title</h3>
                    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
                        Unique LIB numbers will be auto-generated for every physical copy based on quantity.
                    </p>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Book Title *</label>
                                <input type="text" name="title" placeholder="e.g. Computer Networks" required>
                            </div>
                            <div class="form-group">
                                <label>Author *</label>
                                <input type="text" name="author" placeholder="e.g. Andrew Tanenbaum" required>
                            </div>
                            <div class="form-group">
                                <label>Publisher *</label>
                                <input type="text" name="publisher" placeholder="e.g. Prentice Hall" required>
                            </div>
                            <div class="form-group">
                                <label>Quantity (copies) *</label>
                                <input type="number" name="quantity" min="1" max="50" value="1" required>
                            </div>
                            <div class="form-group" style="align-self:end">
                                <label style="visibility:hidden">x</label>
                                <div style="background:var(--primary-light);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--primary);font-weight:600;">
                                    💡 Next LIB: <strong><?= nextLibNumber($conn) ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="add_book" class="btn btn-primary">📚 Add Book &amp; Generate LIB Numbers</button>
                            <button type="button" class="btn btn-danger" onclick="document.getElementById('addForm').classList.remove('visible')">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- ① SEARCH BOX — above the books table -->
                <div class="search-bar">
                    <input type="text" id="bookSearch"
                           placeholder="🔍  Search by title, author, publisher or LIB number…"
                           oninput="liveSearch()"
                           value="<?= htmlspecialchars($_GET['search_lib'] ?? '') ?>">
                    <?php if (!empty($_GET['search_lib'])): ?>
                        <a href="admin_books.php" class="btn btn-warning btn-sm">✕ Clear</a>
                    <?php endif; ?>
                </div>

                <!-- ② BOOKS TABLE -->
                <div style="overflow-x:auto">
                <table id="booksTable">
                    <thead>
                        <tr>
                            <th>Book ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Publisher</th>
                            <th>LIB Numbers (All Copies)</th>
                            <th>Total</th>
                            <th>Available</th>
                            <th>Issued</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($b = mysqli_fetch_assoc($books)):
                        $copies = mysqli_query($conn, "SELECT * FROM book_copies WHERE book_id = {$b['book_id']} ORDER BY copy_id");
                        $avail  = (int)$b['available_copies'];
                        $total  = (int)$b['total_copies'];
                        $pct    = $total > 0 ? round(($avail / $total) * 100) : 0;

                        // Collect LIB numbers as plain text for search matching
                        $lib_text = '';
                        $copies_data = [];
                        while ($c = mysqli_fetch_assoc($copies)) {
                            $copies_data[] = $c;
                            $lib_text .= $c['book_number'] . ' ';
                        }
                    ?>
                    <tr data-search="<?= strtolower(htmlspecialchars($b['title'] . ' ' . $b['author'] . ' ' . $b['publisher'] . ' ' . $lib_text)) ?>">
                        <td><strong>#<?= $b['book_id'] ?></strong></td>
                        <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                        <td><?= htmlspecialchars($b['author']) ?></td>
                        <td><?= htmlspecialchars($b['publisher']) ?></td>
                        <td>
                            <div class="copy-pills">
                            <?php foreach ($copies_data as $c): ?>
                                <span class="lib-tag" style="<?= $c['status'] === 'issued' ? 'background:#fee2e2;color:#991b1b;' : '' ?>">
                                    <?= $c['book_number'] ?>
                                    <?= $c['status'] === 'issued' ? '🔴' : '🟢' ?>
                                </span>
                            <?php endforeach; ?>
                            </div>
                            <div class="qty-bar" style="margin-top:6px">
                                <div class="qty-fill" style="width:<?= $pct ?>%;background:<?= $pct > 50 ? '#22c55e' : ($pct > 0 ? '#f59e0b' : '#ef4444') ?>;"></div>
                            </div>
                        </td>
                        <td><strong><?= $total ?></strong></td>
                        <td><span style="color:<?= $avail > 0 ? '#166534' : '#991b1b' ?>;font-weight:700;"><?= $avail ?></span></td>
                        <td><?= (int)$b['issued_copies'] ?></td>
                        <td>
                            <div class="action-cell">
                                <!-- ③ EDIT BUTTON -->
                                <button class="btn btn-warning btn-sm"
                                        onclick="openEdit(
                                            <?= $b['book_id'] ?>,
                                            '<?= addslashes(htmlspecialchars($b['title'])) ?>',
                                            '<?= addslashes(htmlspecialchars($b['author'])) ?>',
                                            '<?= addslashes(htmlspecialchars($b['publisher'])) ?>',
                                            <?= $total ?>,
                                            <?= $cur_issued = (int)$b['issued_copies'] ?>
                                        )">
                                    ✏️ Edit
                                </button>
                                <a href="?delete=<?= $b['book_id'] ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete <?= addslashes(htmlspecialchars($b['title'])) ?>?\nAll copy records will be removed.')">
                                   🗑️ Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>

                <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                    🟢 = Available &nbsp;|&nbsp; 🔴 = Currently Issued
                </p>
            </div><!-- /section -->

            <!-- ④ EDIT MODAL -->
            <div class="modal-overlay" id="editModal">
                <div class="modal-box">
                    <button class="modal-close" onclick="closeEdit()" title="Close">✕</button>
                    <h3>✏️ Edit Book</h3>
                    <p class="modal-subtitle" id="modalSubtitle">Update book details and total copies</p>

                    <form method="POST">
                        <input type="hidden" name="edit_id" id="editId">

                        <!-- Title full width -->
                        <div class="modal-form-group">
                            <label>Book Title *</label>
                            <input type="text" name="title" id="editTitle" placeholder="Enter book title" required>
                        </div>

                        <!-- Author + Publisher side by side -->
                        <div class="modal-form-row">
                            <div class="modal-form-group">
                                <label>Author *</label>
                                <input type="text" name="author" id="editAuthor" placeholder="Enter author name" required>
                            </div>
                            <div class="modal-form-group">
                                <label>Publisher *</label>
                                <input type="text" name="publisher" id="editPublisher" placeholder="Enter publisher" required>
                            </div>
                        </div>

                        <hr class="modal-divider">

                        <!-- Total copies -->
                        <div class="modal-tip" id="modalTip"></div>
                        <div class="modal-form-group">
                            <label>Total Copies *</label>
                            <input type="number" name="new_total" id="editTotal" min="1" required>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" name="edit_book" class="btn btn-primary">💾 Save Changes</button>
                            <button type="button" class="btn btn-danger" onclick="closeEdit()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
// ── EDIT MODAL ────────────────────────────────────────────────────
function openEdit(id, title, author, publisher, total, issued) {
    document.getElementById('editId').value        = id;
    document.getElementById('editTitle').value     = title;
    document.getElementById('editAuthor').value    = author;
    document.getElementById('editPublisher').value = publisher;
    document.getElementById('editTotal').value     = total;
    document.getElementById('editTotal').min       = issued || 1;

    const tip = document.getElementById('modalTip');
    tip.innerHTML = issued > 0
        ? '⚠️ <strong>' + issued + '</strong> cop' + (issued === 1 ? 'y is' : 'ies are')
          + ' currently issued — total cannot go below <strong>' + issued + '</strong>.'
          + ' Reducing total removes available copies and their LIB numbers.'
        : '💡 Increasing total auto-generates new LIB numbers. Reducing total removes available copies.';

    document.getElementById('editModal').classList.add('open');
    setTimeout(() => document.getElementById('editTitle').focus(), 100);
}

function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
}

// Close on backdrop click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEdit();
});

// ── LIVE SEARCH ───────────────────────────────────────────────────
function liveSearch() {
    const q = document.getElementById('bookSearch').value.toLowerCase().trim();
    document.querySelectorAll('#booksTable tbody tr').forEach(tr => {
        tr.style.display = !q || tr.dataset.search.includes(q) ? '' : 'none';
    });
}

// Re-open modal on validation error
<?php if ($error && isset($_POST['edit_book'])): ?>
openEdit(
    <?= (int)$_POST['edit_id'] ?>,
    '<?= addslashes(htmlspecialchars($_POST['title']    ?? '')) ?>',
    '<?= addslashes(htmlspecialchars($_POST['author']   ?? '')) ?>',
    '<?= addslashes(htmlspecialchars($_POST['publisher'] ?? '')) ?>',
    <?= (int)($_POST['new_total'] ?? 1) ?>,
    0
);
<?php endif; ?>
</script>
</body>
</html>
