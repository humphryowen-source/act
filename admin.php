<?php
session_start();

$action_msg = "";
$login_error = "";

$default_admin_pass_hash = password_hash("admin123", PASSWORD_DEFAULT);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_login'])) {
    $entered_pass = trim($_POST['admin_password']);
    
    if ($entered_pass === "admin123" || password_verify($entered_pass, $default_admin_pass_hash)) {
        $_SESSION['is_admin_logged'] = true;
        header("Location: admin.php?tab=dashboard");
        exit();
    } else {
        $login_error = "Incorrect Admin Password. Please try again.";
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin_logged']);
    session_destroy();
    header("Location: admin.php");
    exit();
}

$is_logged_in = isset($_SESSION['is_admin_logged']) && $_SESSION['is_admin_logged'] === true;

// Mock Data para sa Services kung wala pa sa Session
if (!isset($_SESSION['services_list'])) {
    $_SESSION['services_list'] = [
        ['id' => 1, 'service_name' => 'Tooth Extraction (Bunot)', 'price' => 500.00],
        ['id' => 2, 'service_name' => 'Teeth Whitening', 'price' => 3500.00],
        ['id' => 3, 'service_name' => 'Dental Cleaning (Pasta/Linis)', 'price' => 800.00]
    ];
}

// Mock Data para sa Appointments
if (!isset($_SESSION['appointments'])) {
    $_SESSION['appointments'] = [
        ['id' => 1, 'patient_name' => 'Juan Dela Cruz', 'service' => 'Tooth Extraction (Bunot)', 'date' => '2026-06-10', 'time' => '10:00', 'status' => 'Confirmed'],
        ['id' => 2, 'patient_name' => 'Maria Santos', 'service' => 'Teeth Whitening', 'date' => '2026-06-11', 'time' => '14:00', 'status' => 'Pending']
    ];
}

// Mock Data para sa Reviews
if (!isset($_SESSION['reviews_list'])) {
    $_SESSION['reviews_list'] = [
        ['id' => 1, 'reviewer_name' => 'Juan Dela Cruz', 'rating' => 5, 'comment' => 'Very painless procedure! Magaling si doc.', 'created_at' => '2026-06-09 08:00:00']
    ];
}

// Function para makuha ang presyo ng serbisyo
function getServicePriceSession($service_name) {
    foreach ($_SESSION['services_list'] as $serv) {
        if ($serv['service_name'] === $service_name) {
            return (float)$serv['price'];
        }
    }
    return 500.00; // default
}

// Pagdagdag ng Service
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_service'])) {
    $new_service = trim($_POST['service_name']);
    $new_price = trim($_POST['service_price']);
    if (!empty($new_service) && is_numeric($new_price)) {
        $new_id = count($_SESSION['services_list']) > 0 ? max(array_column($_SESSION['services_list'], 'id')) + 1 : 1;
        $_SESSION['services_list'][] = ['id' => $new_id, 'service_name' => $new_service, 'price' => (float)$new_price];
        $action_msg = "New service added successfully!";
    }
}

// Pag-update ng Price
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_service_price'])) {
    $serv_id = (int)$_POST['service_id'];
    $updated_price = trim($_POST['updated_price']);
    if ($serv_id > 0 && is_numeric($updated_price)) {
        foreach ($_SESSION['services_list'] as &$serv) {
            if ($serv['id'] == $serv_id) {
                $serv['price'] = (float)$updated_price;
                $action_msg = "Service price updated successfully!";
                break;
            }
        }
    }
}

// Delete Service
if (isset($_GET['delete_service'])) {
    $serv_id = (int)$_GET['delete_service'];
    $_SESSION['services_list'] = array_filter($_SESSION['services_list'], function($s) use ($serv_id) {
        return $s['id'] != $serv_id;
    });
    $action_msg = "Service deleted successfully!";
}

// Appointment Actions (Confirm, Cancel, Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    foreach ($_SESSION['appointments'] as &$app) {
        if ($app['id'] == $id) {
            if ($action == 'confirm') {
                $app['status'] = 'Confirmed';
                $action_msg = "Appointment confirmed!";
            } elseif ($action == 'cancel') {
                $app['status'] = 'Cancelled';
                $action_msg = "Appointment cancelled.";
            }
        }
    }
    if ($action == 'delete') {
        $_SESSION['appointments'] = array_filter($_SESSION['appointments'], function($a) use ($id) {
            return $a['id'] != $id;
        });
        $action_msg = "Appointment deleted.";
    }
}

// Delete Review
if (isset($_GET['delete_review'])) {
    $review_id = (int)$_GET['delete_review'];
    $_SESSION['reviews_list'] = array_filter($_SESSION['reviews_list'], function($r) use ($review_id) {
        return $r['id'] != $review_id;
    });
    $action_msg = "Review deleted successfully!";
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$appointments = $_SESSION['appointments'];
$services_list = $_SESSION['services_list'];
$total_revenue = 0;
$total_appointments = count($appointments);
$pending_appointments = 0;

foreach ($appointments as $app) {
    $status = $app['status'] ?? 'Pending';
    if ($status == 'Pending' || $status == '') $pending_appointments++;
    if ($status == 'Confirmed') $total_revenue += getServicePriceSession($app['service']);
}

$limit = 4;
$rev_page = isset($_GET['rp']) ? max(1, (int)$_GET['rp']) : 1;
$all_reviews = $_SESSION['reviews_list'];
$total_rows = count($all_reviews);
$total_reviews_pages = max(1, ceil($total_rows / $limit));
$paginated_reviews = array_slice($all_reviews, ($rev_page - 1) * $limit, $limit);

if (!$is_logged_in):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SmileCare PH</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body, html { width: 100%; height: 100%; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; }
        .responsive-container { width: 100%; height: 100%; max-width: 100%; background: #ffffff; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; }
        @media screen and (min-width: 769px) { .responsive-container { max-width: 450px; height: auto; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 40px; } }
        .login-box { width: 100%; text-align: center; }
        .login-box h2 { font-size: 22px; font-weight: bold; color: #212529; margin-bottom: 8px; }
        .login-box p { font-size: 13px; color: #6c757d; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-label { display: block; font-size: 12px; font-weight: bold; color: #212529; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 12px; font-size: 13px; border: 1px solid #ced4da; border-radius: 8px; outline: none; }
        .btn-submit { background: #212529; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; margin-top: 5px; }
        .alert-error { background: #f8d7da; color: #842029; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 15px; }
        .back-link { display: block; margin-top: 15px; font-size: 12px; color: #0d6efd; text-decoration: none; text-align: center; }
    </style>
</head>
<body>
    <div class="responsive-container">
        <div class="login-box">
            <h2>🔒 Admin Security</h2>
            <p>Enter the Admin Password to access the dashboard.</p>
            <?php if(!empty($login_error)): ?><div class="alert-error"><?= $login_error ?></div><?php endif; ?>
            <form action="admin.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Password (Default: admin123)</label>
                    <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password..." required>
                </div>
                <button type="submit" name="admin_login" class="btn-submit">Admin Login</button>
            </form>
            <a href="index.php" class="back-link">← Back to Client Portal</a>
        </div>
    </div>
</body>
</html>
<?php exit(); endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SmileCare PH</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body, html { width: 100%; height: 100%; background-color: #f0f2f5; overflow: hidden; }
        .responsive-container { width: 100vw; height: 100vh; background: #ffffff; display: flex; flex-direction: column; position: relative; }
        @media screen and (max-width: 768px) { .app-content { padding-bottom: 80px !important; } .desktop-sidebar { display: none !important; } }
        @media screen and (min-width: 769px) { .bottom-nav { display: none !important; } .desktop-sidebar { display: flex !important; } }
        .desktop-sidebar { background: #1e293b; padding: 12px 25px; gap: 20px; align-items: center; width: 100%; }
        .desktop-sidebar a { color: #94a3b8; text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 6px; transition: 0.2s; }
        .desktop-sidebar a.active, .desktop-sidebar a:hover { color: #ffffff; background: #334155; }
        .app-header { background: #212529; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .app-header h5 { font-size: 16px; font-weight: bold; }
        .app-header small { font-size: 11px; opacity: 0.8; display: block; }
        .btn-logout { background: #dc3545; color: #ffffff; padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 11px; font-weight: bold; }
        .app-content { flex: 1; width: 100%; padding: 25px; overflow-y: auto; }
        .alert { background: #d1e7dd; color: #0f5132; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }
        .section-title { font-size: 16px; font-weight: bold; color: #212529; margin-bottom: 4px; }
        .section-desc { font-size: 12px; color: #6c757d; margin-bottom: 15px; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); border-left: 4px solid #adb5bd; }
        .card.confirmed { border-left-color: #198754; }
        .card.cancelled { border-left-color: #dc3545; }
        .card.pending { border-left-color: #ffc107; }
        .card-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .card-title { font-size: 14px; font-weight: bold; color: #212529; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #d1e7dd; color: #0f5132; }
        .badge-danger { background: #f8d7da; color: #842029; }
        .badge-warning { background: #fff3cd; color: #664d03; }
        .card-body-text { font-size: 13px; color: #495057; margin-bottom: 4px; }
        .card-footer-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #6c757d; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f3f5; }
        .btn-action { padding: 5px 10px; border-radius: 6px; font-size: 11px; text-decoration: none; font-weight: bold; border: 1px solid transparent; cursor: pointer; display: inline-block; margin-left: 3px; }
        .btn-confirm { background: #f8f9fa; color: #198754; border-color: #198754; }
        .btn-cancel { background: #f8f9fa; color: #ffc107; border-color: #ffc107; }
        .btn-delete { background: #f8f9fa; color: #dc3545; border-color: #dc3545; }
        .btn-update { background: #f8f9fa; color: #0d6efd; border-color: #0d6efd; }
        .form-group { margin-bottom: 12px; text-align: left; }
        .form-label { display: block; font-size: 12px; font-weight: bold; color: #212529; margin-bottom: 4px; }
        .form-control { width: 100%; padding: 10px; font-size: 13px; border: 1px solid #ced4da; border-radius: 6px; outline: none; }
        .btn-submit { background: #212529; color: white; border: none; padding: 11px; width: 100%; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 15px; }
        .stat-card { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; text-align: center; }
        .stat-number { font-size: 18px; font-weight: bold; color: #212529; }
        .stat-label { font-size: 11px; color: #6c757d; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-item { list-style: none; }
        .page-link { padding: 6px 12px; font-size: 13px; background: #ffffff; border: 1px solid #dee2e6; color: #212529; text-decoration: none; border-radius: 6px; display: block; }
        .page-item.active .page-link { background: #212529; color: #ffffff; border-color: #212529; }
        .page-item.disabled .page-link { color: #6c757d; pointer-events: none; background: #e9ecef; }
        .bottom-nav { position: absolute; bottom: 0; left: 0; width: 100%; background: #ffffff; display: flex; justify-content: space-around; padding: 10px 0; border-top: 1px solid #dee2e6; z-index: 10; }
        .bottom-nav a { color: #6c757d; text-align: center; text-decoration: none; font-size: 11px; font-weight: 600; }
        .bottom-nav a.active { color: #212529; }
    </style>
</head>
<body>
<div class="responsive-container">
    <div class="app-header">
        <div><h5>Admin Panel 🔒</h5><small>SmileCare Management System</small></div>
        <div><a href="admin.php?logout=1" class="btn-logout">Logout</a></div>
    </div>
    <div class="desktop-sidebar">
        <a href="admin.php?tab=dashboard" class="<?= $tab == 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="admin.php?tab=appointments" class="<?= $tab == 'appointments' ? 'active' : '' ?>">📅 Appointments</a>
        <a href="admin.php?tab=services" class="<?= $tab == 'services' ? 'active' : '' ?>">🛠️ Services & Prices</a>
        <a href="admin.php?tab=reviews" class="<?= $tab == 'reviews' ? 'active' : '' ?>">⭐ Patient Reviews</a>
    </div>
    <div class="app-content">
        <?php if(!empty($action_msg)): ?><div class="alert"><?= $action_msg ?></div><?php endif; ?>
        <?php if ($tab == 'dashboard'): ?>
            <div class="section-title">Admin Dashboard 👋</div>
            <div class="section-desc">Overview of reports and clinic revenue.</div>
            <div class="card" style="background: linear-gradient(135deg, #198754, #157347); color: white; border: none; padding: 20px;">
                <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; font-weight: bold;">Total Revenue</div>
                <div style="font-size: 26px; font-weight: bold; margin-top: 4px;">₱<?= number_format($total_revenue, 2) ?></div>
                <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;">*Computed from confirmed appointments.</div>
            </div>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?= $total_appointments ?></div><div class="stat-label">Total Bookings</div></div>
                <div class="stat-card"><div class="stat-number" style="color: #ffc107;"><?= $pending_appointments ?></div><div class="stat-label">Pending Requests</div></div>
            </div>
            <div class="section-title" style="margin-top: 20px;">Recent Bookings</div>
            <div>
                <?php 
                $recent_apps = array_slice($appointments, 0, 3);
                if(empty($recent_apps)):
                ?>
                    <div class="card" style="text-align: center; color: #6c757d; padding: 20px;">No appointments yet.</div>
                <?php else: ?>
                    <?php foreach($recent_apps as $app): 
                        $status = $app['status'] ?? 'Pending';
                        $price = getServicePriceSession($app['service']);
                    ?>
                        <div class="card">
                            <div class="card-header-row"><span class="card-title"><?= htmlspecialchars($app['patient_name']) ?></span><span class="badge <?= $status == 'Confirmed' ? 'badge-success' : 'badge-warning' ?>"><?= $status ?></span></div>
                            <div class="card-body-text" style="color: #0d6efd; font-weight: bold;"><?= htmlspecialchars($app['service']) ?></div>
                            <div class="card-footer-row"><span><?= htmlspecialchars($app['date']) ?></span><span style="font-weight: bold; color: #198754;">₱<?= number_format($price, 2) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($tab == 'appointments'): ?>
            <div class="section-title">Manage Appointments</div>
            <div class="section-desc">Confirm appointments to update clinical records and compute revenue.</div>
            <div>
                <?php if (empty($appointments)): ?>
                    <div class="card" style="text-align: center; color: #6c757d; padding: 25px;">No appointments found.</div>
                <?php else: ?>
                    <?php foreach($appointments as $app): 
                        $status = $app['status'] ?? 'Pending';
                        $status_class = ($status == 'Confirmed') ? 'confirmed' : (($status == 'Cancelled') ? 'cancelled' : 'pending');
                        $badge_class = ($status == 'Confirmed') ? 'badge-success' : (($status == 'Cancelled') ? 'badge-danger' : 'badge-warning');
                        $price = getServicePriceSession($app['service']);
                    ?>
                        <div class="card <?= $status_class ?>">
                            <div class="card-header-row"><span class="card-title">ID #<?= $app['id'] ?> - <?= htmlspecialchars($app['patient_name']) ?></span><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($status) ?></span></div>
                            <div class="card-body-text" style="color: #0d6efd; font-weight: bold;"><?= htmlspecialchars($app['service']) ?></div>
                            <div style="font-size: 12px; color: #198754; font-weight: bold; margin-bottom: 4px;">💰 Price: ₱<?= number_format($price, 2) ?></div>
                            <div class="card-footer-row">
                                <span><?= htmlspecialchars($app['date']) ?> | <?= htmlspecialchars($app['time']) ?></span>
                                <div>
                                    <a href="admin.php?tab=appointments&action=confirm&id=<?= $app['id'] ?>" class="btn-action btn-confirm">Confirm</a>
                                    <a href="admin.php?tab=appointments&action=cancel&id=<?= $app['id'] ?>" class="btn-action btn-cancel">Cancel</a>
                                    <a href="admin.php?tab=appointments&action=delete&id=<?= $app['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Delete this record?');">Delete</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($tab == 'services'): ?>
            <div class="section-title">Manage Services & Prices</div>
            <div class="section-desc">Add new treatments or modify current prices.</div>
            <div class="card" style="background: #f8f9fa; border-left: 4px solid #0d6efd;">
                <form action="admin.php?tab=services" method="POST">
                    <div class="form-group"><label class="form-label">New Service Name</label><input type="text" name="service_name" class="form-control" placeholder="e.g., Teeth Whitening" required></div>
                    <div class="form-group"><label class="form-label">Price (PHP)</label><input type="number" step="0.01" name="service_price" class="form-control" placeholder="e.g., 3500" required></div>
                    <button type="submit" name="add_service" class="btn-submit">Add Service</button>
                </form>
            </div>
            <div class="section-title" style="margin-top: 20px;">Existing Services List</div>
            <div>
                <?php if (empty($services_list)): ?>
                    <div class="card" style="text-align: center; color: #6c757d; padding: 20px;">No services added yet.</div>
                <?php else: ?>
                    <?php foreach($services_list as $serv): ?>
                        <div class="card">
                            <form action="admin.php?tab=services" method="POST" style="margin-bottom: 6px;">
                                <input type="hidden" name="service_id" value="<?= $serv['id'] ?>">
                                <div class="card-header-row"><span class="card-title" style="font-size: 13px;"><?= htmlspecialchars($serv['service_name']) ?></span></div>
                                <div style="display: flex; gap: 8px; margin-top: 8px;">
                                    <input type="number" step="0.01" name="updated_price" class="form-control" value="<?= $serv['price'] ?>" style="padding: 6px 10px; font-size: 12px;" required>
                                    <button type="submit" name="update_service_price" class="btn-action btn-update" style="padding: 6px 12px;">Update</button>
                                </div>
                            </form>
                            <div class="card-footer-row">
                                <span>ID: #<?= $serv['id'] ?></span>
                                <a href="admin.php?tab=services&delete_service=<?= $serv['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Delete this service?');">Delete Service</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($tab == 'reviews'): ?>
            <div class="section-title">Patient Reviews</div>
            <div class="section-desc">Manage feedback left by patients.</div>
            <div>
                <?php if (empty($paginated_reviews)): ?>
                    <div class="card" style="text-align: center; color: #6c757d; padding: 25px;">No reviews submitted yet.</div>
                <?php else: ?>
                    <?php foreach($paginated_reviews as $rev): ?>
                        <div class="card">
                            <div class="card-header-row">
                                <span class="card-title"><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                <span style="color: #ffc107; font-size: 12px;"><?php for($i=0; $i<(int)$rev['rating']; $i++): ?>★<?php endfor; ?> (<?= $rev['rating'] ?>/5)</span>
                            </div>
                            <div class="card-body-text" style="font-style: italic; color: #555;">"<?= htmlspecialchars($rev['comment']) ?>"</div>
                            <div class="card-footer-row">
                                <span style="font-size: 11px;"><?= $rev['created_at'] ?? 'N/A' ?></span>
                                <a href="admin.php?tab=reviews&delete_review=<?= $rev['id'] ?>&rp=<?= $rev_page ?>" class="btn-action btn-delete" onclick="return confirm('Delete this review?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($total_reviews_pages > 1): ?>
                <ul class="pagination">
                    <li class="page-item <?= ($rev_page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="admin.php?tab=reviews&rp=<?= $rev_page - 1 ?>">Prev</a></li>
                    <?php for ($i = 1; $i <= $total_reviews_pages; $i++): ?>
                        <li class="page-item <?= ($rev_page == $i) ? 'active' : '' ?>"><a class="page-link" href="admin.php?tab=reviews&rp=<?= $i ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($rev_page >= $total_reviews_pages) ? 'disabled' : '' ?>"><a class="page-link" href="admin.php?tab=reviews&rp=<?= $rev_page + 1 ?>">Next</a></li>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="bottom-nav">
        <a href="admin.php?tab=dashboard" class="<?= $tab == 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="admin.php?tab=appointments" class="<?= $tab == 'appointments' ? 'active' : '' ?>">Appointments</a>
        <a href="admin.php?tab=services" class="<?= $tab == 'services' ? 'active' : '' ?>">Services</a>
        <a href="admin.php?tab=reviews" class="<?= $tab == 'reviews' ? 'active' : '' ?>">Reviews</a>
    </div>
</div>
</body>
</html>