<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error   = '';
$success = '';

// --- ADD USER ---
if (isset($_POST['add_user'])) {
    $role     = $_POST['role'];
    $name     = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $password = trim(mysqli_real_escape_string($conn, $_POST['password']));
    $admin_id = trim(mysqli_real_escape_string($conn, $_POST['admin_id'] ?? ''));
    $reg_no   = trim(mysqli_real_escape_string($conn, $_POST['reg_no'] ?? ''));

    if (empty($name) || empty($username) || empty($password)) {
        $error = "Name, username, and password are required.";
    } elseif ($role === 'admin' && empty($admin_id)) {
        $error = "Admin ID is required for admin accounts.";
    } elseif ($role === 'member' && empty($reg_no)) {
        $error = "Registration Number is required for member accounts.";
    } else {
        // Check duplicate username
        $chk = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($chk) > 0) {
            $error = "Username '$username' already exists. Choose a different one.";
        } else {
            if ($role === 'admin') {
                $sql = "INSERT INTO users (admin_id, name, username, password, role) VALUES ('$admin_id','$name','$username','$password','admin')";
            } else {
                $sql = "INSERT INTO users (reg_no, name, username, password, role) VALUES ('$reg_no','$name','$username','$password','member')";
            }
            if (mysqli_query($conn, $sql)) {
                $success = "User '$name' added successfully!";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}

// --- DELETE USER ---
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Prevent deleting yourself
    if ($del_id === (int)$_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        // Check if user has active issued books
        $active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM issued_books WHERE user_id = $del_id AND status = 'issued'"))['cnt'];
        if ($active > 0) {
            $error = "Cannot delete: This user has $active unreturned book(s).";
        } else {
            mysqli_query($conn, "DELETE FROM issued_books WHERE user_id = $del_id");
            if (mysqli_query($conn, "DELETE FROM users WHERE id = $del_id AND role = 'member'")) {
                $success = "Member deleted successfully.";
            } else {
                $error = "Could not delete. Only member accounts can be deleted.";
            }
        }
    }
}

// --- EDIT MEMBER ---
if (isset($_POST['edit_member'])) {
    $edit_id  = (int)$_POST['edit_id'];
    $name     = trim(mysqli_real_escape_string($conn, $_POST['edit_name']));
    $username = trim(mysqli_real_escape_string($conn, $_POST['edit_username']));
    $password = trim(mysqli_real_escape_string($conn, $_POST['edit_password']));
    $reg_no   = trim(mysqli_real_escape_string($conn, $_POST['edit_reg_no']));

    if (empty($name) || empty($username)) {
        $error = "Name and username are required.";
    } else {
        // Check duplicate username (exclude self)
        $chk = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != $edit_id");
        if (mysqli_num_rows($chk) > 0) {
            $error = "Username '$username' is already taken.";
        } else {
            if (!empty($password)) {
                $sql = "UPDATE users SET name='$name', username='$username', password='$password', reg_no='$reg_no' WHERE id=$edit_id AND role='member'";
            } else {
                $sql = "UPDATE users SET name='$name', username='$username', reg_no='$reg_no' WHERE id=$edit_id AND role='member'";
            }
            if (mysqli_query($conn, $sql)) {
                $success = "Member '$name' updated successfully!";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}


$admins  = mysqli_query($conn, "SELECT * FROM users WHERE role='admin' ORDER BY id");
$members = mysqli_query($conn, "SELECT * FROM users WHERE role='member' ORDER BY id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users – Library System</title>
    <style>
        <?= COMMON_STYLE ?>

        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e1b4b 0%, #312e81 100%);
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
        }

        .sidebar-header { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .sidebar-logo { width: 55px; height: 55px; border-radius: 50%; background: rgba(255,255,255,0.15); margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; overflow: hidden; }
        .sidebar-logo img { width: 100%; height: 100%; object-fit: cover; }
        .sidebar-header h3 { font-size: 12px; letter-spacing: 1.5px; text-transform: uppercase; opacity: 0.7; }
        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-label { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; opacity: 0.4; padding: 0 24px; margin: 16px 0 8px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 13px 24px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 14px; font-weight: 600; font-family: "Nunito", sans-serif; transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { color: white; background: rgba(255,255,255,0.1); border-left-color: #818cf8; }
        .nav-item .icon { font-size: 18px; width: 24px; text-align: center; }

        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; }

        .topbar { background: white; padding: 18px 32px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .topbar h1 { font-size: 20px; }

        .user-pill { display: flex; align-items: center; gap: 10px; background: var(--primary-light); padding: 8px 16px; border-radius: 50px; font-size: 14px; font-weight: 700; color: var(--primary); }
        .user-pill .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; }

        .content { padding: 32px; }

        .section { background: white; border-radius: var(--radius); padding: 28px; box-shadow: var(--shadow); margin-bottom: 28px; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .section-header h2 { font-size: 20px; }

        /* Form Toggle */
        .add-form { display: none; background: var(--bg); border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 2px dashed var(--primary); }
        .add-form.visible { display: block; }

        .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .form-group { }
        .form-group label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; font-family: "Nunito", sans-serif; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; border: 2px solid var(--border); border-radius: 8px; font-size: 14px; font-family: "Lato", sans-serif; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); }
        .form-actions { margin-top: 16px; display: flex; gap: 10px; }

        #role-hint { font-size: 13px; color: var(--primary); font-weight: 600; background: var(--primary-light); padding: 8px 12px; border-radius: 8px; margin-top: 12px; display: none; }

        .sidebar-footer { padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 13px; opacity: 0.6; }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="logo.jpeg" alt="Logo" onerror="this.style.display='none';this.closest('.sidebar-logo').innerHTML='📚';">
            </div>
            <h3>Library System</h3>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main Menu</div>
            <a href="admin.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
            <a href="admin_books.php" class="nav-item"><span class="icon">📚</span> Manage Books</a>
            <a href="admin_users.php" class="nav-item active"><span class="icon">👥</span> Manage Users</a>
            <a href="admin_issued.php" class="nav-item"><span class="icon">📋</span> Issued Books</a>
            <div class="nav-label">Account</div>
            <a href="logout.php" class="nav-item"><span class="icon">🚪</span> Logout</a>
        </nav>
        <div class="sidebar-footer">Admin Panel v1.0</div>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>Manage Users</h1>
            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </div>
        </div>

        <div class="content">
            <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- Add User Section -->
            <div class="section">
                <div class="section-header">
                    <h2>👥 Add New User</h2>
                    <button class="btn btn-primary" onclick="toggleForm()">+ Add User</button>
                </div>

                <div class="add-form" id="addForm">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Role *</label>
                                <select name="role" id="roleSelect" onchange="handleRole(this.value)" required>
                                    <option value="">-- Select Role --</option>
                                    <option value="admin">Admin</option>
                                    <option value="member">Member</option>
                                </select>
                            </div>
                            <div class="form-group" id="adminIdField" style="display:none">
                                <label>Admin ID * (Manual)</label>
                                <input type="text" name="admin_id" placeholder="e.g. ADM002">
                            </div>
                            <div class="form-group" id="regNoField" style="display:none">
                                <label>Registration No * (Manual)</label>
                                <input type="text" name="reg_no" placeholder="e.g. REG001">
                            </div>
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="name" placeholder="Enter full name" required>
                            </div>
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" placeholder="Enter username" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="text" name="password" placeholder="Enter password" required>
                            </div>
                        </div>
                        <div id="role-hint"></div>
                        <div class="form-actions">
                            <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                            <button type="button" class="btn btn-danger" onclick="toggleForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Admins Table -->
                <h3 style="margin-bottom:14px; color: var(--secondary);">🛡️ Admin Accounts</h3>
                <table style="margin-bottom:28px">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Admin ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = mysqli_fetch_assoc($admins)): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['admin_id'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><span class="badge badge-admin">Admin</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Members Table -->
                <h3 style="margin-bottom:14px; color: var(--primary);">👤 Member Accounts</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg No</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = mysqli_fetch_assoc($members)): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['reg_no'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><span class="badge badge-member">Member</span></td>
                            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button class="btn btn-primary btn-sm"
                                    onclick="openEditModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['reg_no'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($row['name'])) ?>', '<?= addslashes(htmlspecialchars($row['username'])) ?>')">
                                    ✏️ Edit
                                </button>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete <?= htmlspecialchars($row['name']) ?>? This cannot be undone.')">
                                   🗑️ Delete
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($members) === 0): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:24px">No members found. Add one above!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- ── EDIT MEMBER MODAL ────────────────────────────────────── -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:32px;width:480px;max-width:95%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <h2 style="font-size:20px;margin:0;">✏️ Edit Member</h2>
            <button onclick="closeEditModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="edit_id" id="editId">
            <div style="display:grid;gap:14px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Registration No</label>
                    <input type="text" name="edit_reg_no" id="editRegNo" placeholder="e.g. REG001"
                        style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Full Name *</label>
                    <input type="text" name="edit_name" id="editName" required placeholder="Enter full name"
                        style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">Username *</label>
                    <input type="text" name="edit_username" id="editUsername" required placeholder="Enter username"
                        style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">New Password <span style="font-weight:400;color:#94a3b8;">(leave blank to keep current)</span></label>
                    <input type="text" name="edit_password" id="editPassword" placeholder="Enter new password"
                        style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:24px;">
                <button type="submit" name="edit_member" class="btn btn-primary" style="flex:1;">💾 Save Changes</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleForm() {
    const form = document.getElementById('addForm');
    form.classList.toggle('visible');
}

function handleRole(val) {
    const adminField = document.getElementById('adminIdField');
    const regField   = document.getElementById('regNoField');
    const hint       = document.getElementById('role-hint');

    adminField.style.display = 'none';
    regField.style.display   = 'none';
    hint.style.display = 'none';

    if (val === 'admin') {
        adminField.style.display = 'block';
        hint.style.display = 'block';
        hint.textContent = '⚠️ Admin ID must be entered manually (e.g., ADM002)';
    } else if (val === 'member') {
        regField.style.display = 'block';
        hint.style.display = 'block';
        hint.textContent = '⚠️ Registration Number must be entered manually (e.g., REG001)';
    }
}

function openEditModal(id, regNo, name, username) {
    document.getElementById('editId').value       = id;
    document.getElementById('editRegNo').value    = regNo;
    document.getElementById('editName').value     = name;
    document.getElementById('editUsername').value = username;
    document.getElementById('editPassword').value = '';
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
</body>
</html>
