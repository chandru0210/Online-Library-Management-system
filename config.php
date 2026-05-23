<?php
// config.php — Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_system');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("<div style='font-family:sans-serif;padding:40px;color:red;'>
        <h2>Database Connection Failed</h2>
        <p>Error: " . mysqli_connect_error() . "</p>
        <p>Make sure XAMPP MySQL is running and the database <b>library_system</b> exists.</p>
        <p>Import <b>library_system.sql</b> in phpMyAdmin first.</p>
    </div>");
}

// ----------------------------------------------------------------
// Helper: generate next LIB number
//   Looks at the highest existing book_number in book_copies and
//   returns the next one in the sequence (e.g. LIB018).
// ----------------------------------------------------------------
function nextLibNumber($conn) {
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT book_number FROM book_copies ORDER BY copy_id DESC LIMIT 1"
    ));
    if (!$row) return 'LIB001';
    $num = (int) substr($row['book_number'], 3);   // strip 'LIB'
    return 'LIB' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
}

// ----------------------------------------------------------------
// Common CSS injected into every page
// ----------------------------------------------------------------
define('COMMON_STYLE', '
    @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Lato:wght@300;400;700&display=swap");

    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --primary:       #4A6CF7;
        --primary-dark:  #3451d1;
        --primary-light: #eef1fe;
        --secondary:     #7B5EA7;
        --success:       #22c55e;
        --danger:        #ef4444;
        --warning:       #f59e0b;
        --text:          #1e293b;
        --text-muted:    #64748b;
        --bg:            #f0f4ff;
        --border:        #e2e8f0;
        --shadow:        0 4px 20px rgba(74,108,247,0.12);
        --radius:        12px;
    }

    body { font-family:"Lato",sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
    h1,h2,h3,h4 { font-family:"Nunito",sans-serif; }

    .alert { padding:12px 18px; border-radius:8px; margin-bottom:16px; font-size:14px; font-weight:600; }
    .alert-danger  { background:#fee2e2; color:#991b1b; border-left:4px solid var(--danger); }
    .alert-success { background:#dcfce7; color:#166534; border-left:4px solid var(--success); }
    .alert-warning { background:#fef9c3; color:#854d0e; border-left:4px solid var(--warning); }

    .btn { display:inline-block; padding:10px 22px; border-radius:8px; border:none; font-family:"Nunito",sans-serif; font-weight:700; font-size:14px; cursor:pointer; text-decoration:none; transition:all .2s ease; }
    .btn-primary { background:var(--primary); color:white; }
    .btn-primary:hover { background:var(--primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(74,108,247,.4); }
    .btn-danger  { background:var(--danger);  color:white; }
    .btn-danger:hover  { background:#dc2626; }
    .btn-success { background:var(--success); color:white; }
    .btn-success:hover { background:#16a34a; }
    .btn-warning { background:var(--warning); color:white; }
    .btn-sm { padding:6px 14px; font-size:13px; }

    table { width:100%; border-collapse:collapse; background:white; border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); }
    th { background:var(--primary); color:white; padding:14px 16px; text-align:left; font-family:"Nunito",sans-serif; font-weight:700; font-size:14px; letter-spacing:.3px; }
    td { padding:12px 16px; border-bottom:1px solid var(--border); font-size:14px; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:var(--primary-light); }

    .badge { display:inline-block; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; }
    .badge-issued   { background:#fef3c7; color:#92400e; }
    .badge-returned { background:#d1fae5; color:#065f46; }
    .badge-available{ background:#d1fae5; color:#065f46; }
    .badge-admin    { background:#ede9fe; color:#5b21b6; }
    .badge-member   { background:#dbeafe; color:#1e40af; }

    .lib-tag {
        background:#eef2ff; color:#4338ca;
        padding:3px 10px; border-radius:20px;
        font-weight:700; font-size:13px;
        font-family:"Nunito",sans-serif;
    }
');
?>
