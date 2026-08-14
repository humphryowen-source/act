<?php
session_start();

if (isset($_GET['logout'])) {
    unset($_SESSION['client_id']);
    unset($_SESSION['patient_name']);
    header("Location: index.php?page=login");
    exit();
}

$success_msg = "";
$error_msg = "";

// Mock Data initialization sa Session kung wala pa
if (!isset($_SESSION['services_list'])) {
    $_SESSION['services_list'] = [
        ['id' => 1, 'service_name' => 'Tooth Extraction (Bunot)', 'price' => 500.00],
        ['id' => 2, 'service_name' => 'Teeth Whitening', 'price' => 3500.00],
        ['id' => 3, 'service_name' => 'Dental Cleaning (Pasta/Linis)', 'price' => 800.00]
    ];
}

if (!isset($_SESSION['clients_db'])) {
    // Default mock client account: test@gmail.com / password123
    $_SESSION['clients_db'] = [
        ['id' => 1, 'fullname' => 'Juan Dela Cruz', 'email' => 'test@gmail.com', 'password' => password_hash('password123', PASSWORD_DEFAULT)]
    ];
}

if (!isset($_SESSION['appointments'])) {
    $_SESSION['appointments'] = [
        ['id' => 1, 'patient_name' => 'Juan Dela Cruz', 'service' => 'Tooth Extraction (Bunot)', 'date' => '2026-06-10', 'time' => '10:00', 'status' => 'Confirmed']
    ];
}

if (!isset($_SESSION['reviews_list'])) {
    $_SESSION['reviews_list'] = [
        ['id' => 1, 'reviewer_name' => 'Juan Dela Cruz', 'rating' => 5, 'comment' => 'Very painless procedure! Magaling si doc.', 'created_at' => '2026-06-09 08:00:00']
    ];
}

// Registration Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_submit'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($fullname) && !empty($email) && !empty($password)) {
        $exists = false;
        foreach ($_SESSION['clients_db'] as $c) {
            if ($c['email'] === $email) {
                $exists = true;
                break;
            }
        }
        if ($exists) {
            $error_msg = "Email address is already registered.";
        } else {
            $new_id = count($_SESSION['clients_db']) + 1;
            $_SESSION['clients_db'][] = [
                'id' => $new_id,
                'fullname' => $fullname,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ];
            $success_msg = "Registration successful! You can now log in.";
        }
    } else {
        $error_msg = "All fields are required.";
    }
}

// Login Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $logged_client = null;
        foreach ($_SESSION['clients_db'] as $c) {
            if ($c['email'] === $email) {
                if (password_verify($password, $c['password'])) {
                    $logged_client = $c;
                }
                break;
            }
        }

        if ($logged_client) {
            $_SESSION['client_id'] = $logged_client['id'];
            $_SESSION['patient_name'] = $logged_client['fullname'];
            header("Location: index.php?page=dashboard");
            exit();
        } else {
            $error_msg = "Invalid email or password.";
        }
    } else {
        $error_msg = "Please enter both email and password.";
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$protected_pages = ['dashboard', 'history', 'services', 'reviews', 'faq', 'book'];
if (in_array($page, $protected_pages) && !isset($_SESSION['client_id'])) {
    header("Location: index.php?page=login");
    exit();
}

$current_patient = isset($_SESSION['patient_name']) ? $_SESSION['patient_name'] : "Guest";

// Booking Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    $patient_name = trim($_POST['patient_name']);
    $service = $_POST['service'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    $_SESSION['patient_name'] = $patient_name;
    $current_patient = $patient_name;

    $new_id = count($_SESSION['appointments']) > 0 ? max(array_column($_SESSION['appointments'], 'id')) + 1 : 1;
    $_SESSION['appointments'][] = [
        'id' => $new_id,
        'patient_name' => $patient_name,
        'service' => $service,
        'date' => $date,
        'time' => $time,
        'status' => 'Pending'
    ];
    $success_msg = "Appointment booked successfully!";
}

// Review Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    $reviewer_name = trim($_POST['reviewer_name']);
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    if (!empty($reviewer_name) && !empty($comment)) {
        $new_id = count($_SESSION['reviews_list']) > 0 ? max(array_column($_SESSION['reviews_list'], 'id')) + 1 : 1;
        array_unshift($_SESSION['reviews_list'], [
            'id' => $new_id,
            'reviewer_name' => $reviewer_name,
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        header("Location: index.php?page=reviews&success=1");
        exit();
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_msg = "Thank you for your review! Successfully posted.";
}

// Services list formatting
$services = [];
foreach ($_SESSION['services_list'] as $row) {
    $services[] = [
        "name" => $row['service_name'],
        "price" => "₱" . number_format($row['price'], 2),
        "duration" => "30 - 60 mins",
        "desc" => "Professional dental treatment offered at SmileCare PH."
    ];
}

$rev_limit = 4;
$rev_page = isset($_GET['rp']) ? max(1, (int)$_GET['rp']) : 1;
$all_reviews = $_SESSION['reviews_list'];
$total_rows_rev = count($all_reviews);
$total_reviews_pages = max(1, ceil($total_rows_rev / $rev_limit));
$reviews = array_slice($all_reviews, ($rev_page - 1) * $rev_limit, $rev_limit);

$faqs = [
    ["q" => "How do I book an appointment?", "a" => "Just click 'Book' below or on the navigation menu, fill out the form, and submit."],
    ["q" => "Can I walk-in?", "a" => "Yes, but we prioritize those with online reservations to avoid long lines."],
    ["q" => "How much is the consultation fee?", "a" => "The initial consultation is free when you avail of our service."]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmileCare PH - Client Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body, html { width: 100%; height: 100%; background-color: #f0f2f5; overflow: hidden; }
        .responsive-container { width: 100vw; height: 100vh; background: #ffffff; display: flex; flex-direction: column; position: relative; }
        @media screen and (max-width: 768px) { .app-content { padding-bottom: 80px !important; } .desktop-sidebar { display: none !important; } }
        @media screen and (min-width: 769px) { .bottom-nav { display: none !important; } .desktop-sidebar { display: flex !important; } }
        .desktop-sidebar { background: #0284c7; padding: 12px 25px; gap: 20px; align-items: center; width: 100%; }
        .desktop-sidebar a { color: #bae6fd; text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 6px; transition: 0.2s; }
        .desktop-sidebar a.active, .desktop-sidebar a:hover { color: #ffffff; background: #0284c7dd; }
        .app-header { background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: white; padding: 16px 25px; display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .app-header h5 { font-size: 16px; font-weight: bold; }
        .app-header small { font-size: 11px; opacity: 0.9; display: block; }
        .btn-logout { background: #dc3545; color: #ffffff; padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 11px; font-weight: bold; }
        .app-content { flex: 1; width: 100%; padding: 25px; overflow-y: auto; }
        .alert { background: #d1e7dd; color: #0f5132; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }
        .alert-error { background: #f8d7da; color: #842029; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }
        .section-title { font-size: 16px; font-weight: bold; color: #212529; margin-bottom: 4px; }
        .section-desc { font-size: 12px; color: #6c757d; margin-bottom: 15px; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .card.primary-bg { background: #0d6efd; color: white; border: none; }
        .card.light-bg { background: #f8f9fa; }
        .card-title { font-size: 14px; font-weight: bold; color: #212529; }
        .card-text { font-size: 12px; color: #495057; margin-top: 4px; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; display: inline-block; }
        .badge-success { background: #d1e7dd; color: #0f5132; }
        .badge-warning { background: #fff3cd; color: #664d03; }
        .badge-light { background: #ffffff; color: #212529; border: 1px solid #dee2e6; }
        .form-group { margin-bottom: 12px; text-align: left; }
        .form-label { display: block; font-size: 12px; font-weight: bold; color: #212529; margin-bottom: 4px; }
        .form-control, .form-select { width: 100%; padding: 10px; font-size: 13px; border: 1px solid #ced4da; border-radius: 6px; outline: none; }
        .form-control:focus, .form-select:focus { border-color: #0d6efd; }
        .btn-submit { background: #0d6efd; color: white; border: none; padding: 11px; width: 100%; border-radius: 20px; font-size: 13px; font-weight: bold; cursor: pointer; margin-top: 5px; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-item { list-style: none; }
        .page-link { padding: 6px 12px; font-size: 13px; background: #ffffff; border: 1px solid #dee2e6; color: #212529; text-decoration: none; border-radius: 6px; display: block; }
        .page-item.active .page-link { background: #0d6efd; color: #ffffff; border-color: #0d6efd; }
        .page-item.disabled .page-link { color: #6c757d; pointer-events: none; background: #e9ecef; }
        .bottom-nav { position: absolute; bottom: 0; left: 0; width: 100%; background: #ffffff; display: flex; justify-content: space-around; padding: 10px 0; border-top: 1px solid #dee2e6; z-index: 10; }
        .bottom-nav a { color: #6c757d; text-align: center; text-decoration: none; font-size: 11px; font-weight: 600; }
        .bottom-nav a.active { color: #0d6efd; }
    </style>
</head>
<body>
<div class="responsive-container">
    <div class="app-header">
        <div><h5>SmileCare PH</h5><small>Patient: <?= htmlspecialchars($current_patient) ?></small></div>
        <div><?php if (isset($_SESSION['client_id'])): ?><a href="index.php?logout=1" class="btn-logout">Logout</a><?php endif; ?></div>
    </div>
    <?php if (isset($_SESSION['client_id'])): ?>
    <div class="desktop-sidebar">
        <a href="index.php?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>">🏠 Home</a>
        <a href="index.php?page=history" class="<?= $page == 'history' ? 'active' : '' ?>">📋 History</a>
        <a href="index.php?page=services" class="<?= $page == 'services' ? 'active' : '' ?>">🛠️ Services</a>
        <a href="index.php?page=reviews" class="<?= $page == 'reviews' ? 'active' : '' ?>">⭐ Reviews</a>
        <a href="index.php?page=book" class="<?= $page == 'book' ? 'active' : '' ?>">📅 Book Now</a>
        <a href="index.php?page=faq" class="<?= $page == 'faq' ? 'active' : '' ?>">❓ FAQ</a>
    </div>
    <?php endif; ?>
    <div class="app-content">
        <?php if(!empty($success_msg)): ?><div class="alert"><?= $success_msg ?></div><?php endif; ?>
        <?php if(!empty($error_msg)): ?><div class="alert-error"><?= $error_msg ?></div><?php endif; ?>
        <?php if ($page == 'login'): ?>
            <div style="text-align: center; max-width: 400px; margin: 0 auto; padding-top: 20px;">
                <div class="section-title" style="font-size: 20px; margin-bottom: 5px;">👋 Welcome Back</div>
                <div class="section-desc">Log in to manage your appointments and records. (Default: test@gmail.com / password123)</div>
                <form action="index.php?page=login" method="POST" class="card" style="text-align: left; background: #f8f9fa; padding: 20px;">
                    <input type="hidden" name="login_submit" value="1">
                    <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" placeholder="Enter email..." required></div>
                    <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="Enter password..." required></div>
                    <button type="submit" class="btn-submit">Login</button>
                </form>
                <a href="index.php?page=register" style="font-size: 12px; color: #0d6efd; text-decoration: none; display: block; margin-top: 15px;">Don't have an account? Register here</a>
            </div>
        <?php elseif ($page == 'register'): ?>
            <div style="text-align: center; max-width: 400px; margin: 0 auto; padding-top: 10px;">
                <div class="section-title" style="font-size: 20px; margin-bottom: 5px;">✨ Create Account</div>
                <div class="section-desc">Sign up to book dental appointments.</div>
                <form action="index.php?page=register" method="POST" class="card" style="text-align: left; background: #f8f9fa; padding: 20px;">
                    <input type="hidden" name="register_submit" value="1">
                    <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="fullname" class="form-control" placeholder="Full name..." required></div>
                    <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" placeholder="Email address..." required></div>
                    <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="Password..." required></div>
                    <button type="submit" class="btn-submit">Register Account</button>
                </form>
                <a href="index.php?page=login" style="font-size: 12px; color: #0d6efd; text-decoration: none; display: block; margin-top: 15px;">Already have an account? Login here</a>
            </div>
        <?php elseif ($page == 'dashboard'): ?>
            <div class="section-title">Hello, Client! 👋</div>
            <div class="section-desc">Your trusted partner for a brighter smile.</div>
            <div class="card primary-bg" style="padding: 20px;">
                <div style="font-weight: bold; font-size: 15px; margin-bottom: 6px;">📢 Free Dental Checkup!</div>
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 12px;">Book any cleaning or extraction service this week and get a free professional consultation.</div>
                <a href="index.php?page=book" style="background: white; color: #0d6efd; padding: 6px 15px; border-radius: 15px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block;">Book Now</a>
            </div>
            <div class="card light-bg">
                <div style="font-weight: bold; font-size: 13px; color: #212529; margin-bottom: 3px;">📍 SmileCare Dental Clinic</div>
                <div style="font-size: 12px; color: #6c757d;">Main Branch, Sorsogon</div>
                <div style="font-size: 12px; color: #6c757d;">Mon - Sat: 8:00 AM - 5:00 PM</div>
            </div>
            <div class="section-title" style="margin-top: 20px;">Featured Services</div>
            <div>
                <?php foreach(array_slice($services, 0, 3) as $srv): ?>
                    <div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 12px;">
                        <span style="font-size: 13px; font-weight: bold; color: #0d6efd;"><?= $srv['name'] ?></span>
                        <span class="badge badge-light"><?= $srv['price'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; margin-bottom: 8px;">
                <div class="section-title" style="margin-bottom: 0;">Patient Reviews</div>
                <a href="index.php?page=reviews" style="font-size: 12px; text-decoration: none; color: #0d6efd; font-weight: bold;">View All</a>
            </div>
            <div>
                <?php foreach(array_slice($reviews, 0, 2) as $rev): ?>
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 13px;">
                            <span><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                            <span style="color: #ffc107; font-size: 12px;"><?php for($i=0; $i<(int)$rev['rating']; $i++): ?>★<?php endfor; ?></span>
                        </div>
                        <div class="card-text" style="font-style: italic;">"<?= htmlspecialchars($rev['comment']) ?>"</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($page == 'history'): ?>
            <div class="section-title">My Dental History</div>
            <div class="section-desc">Previous records and appointment tracking.</div>
            <?php 
            $limit_hp = 3;
            $page_num = isset($_GET['hp']) ? max(1, (int)$_GET['hp']) : 1;
            
            // Salain ang appointments para lang sa current logged-in patient
            $client_history = array_filter($_SESSION['appointments'], function($a) use ($current_patient) {
                return $a['patient_name'] === $current_patient;
            });
            // Ayusin ang order mula newest hanggang oldest
            $client_history = array_reverse(array_values($client_history));
            
            $total_rows_hp = count($client_history);
            $total_pages_hp = max(1, ceil($total_rows_hp / $limit_hp));
            $history = array_slice($client_history, ($page_num - 1) * $limit_hp, $limit_hp);
            ?>
            <div>
                <?php if (empty($history)): ?>
                    <div class="card" style="text-align: center; color: #6c757d; padding: 25px;">No past dental records found.</div>
                <?php else: ?>
                    <?php foreach($history as $rec): 
                        $status_bug = $rec['status'] ?? 'Pending'; 
                        $badge_class = ($status_bug == 'Confirmed') ? 'badge-success' : 'badge-warning';
                    ?>
                        <div class="card" style="border-left: 4px solid <?= $status_bug == 'Confirmed' ? '#198754' : '#ffc107' ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span class="card-title"><?= htmlspecialchars($rec['service']) ?></span>
                                <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($status_bug) ?></span>
                            </div>
                            <div style="font-size: 12px; color: #6c757d; border-top: 1px solid #f1f3f5; padding-top: 6px; display: flex; justify-content: space-between;">
                                <span>📅 <?= htmlspecialchars($rec['date']) ?></span>
                                <span>⏰ <?= htmlspecialchars($rec['time']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($total_pages_hp > 1): ?>
                <ul class="pagination">
                    <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="index.php?page=history&hp=<?= $page_num - 1 ?>">Prev</a></li>
                    <?php for ($i = 1; $i <= $total_pages_hp; $i++): ?>
                        <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>"><a class="page-link" href="index.php?page=history&hp=<?= $i ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page_num >= $total_pages_hp) ? 'disabled' : '' ?>"><a class="page-link" href="index.php?page=history&hp=<?= $page_num + 1 ?>">Next</a></li>
                </ul>
            <?php endif; ?>
        <?php elseif ($page == 'services'): ?>
            <div class="section-title">All Dental Services</div>
            <div class="section-desc">Complete updated list of clinical treatments.</div>
            <div>
                <?php foreach($services as $srv): ?>
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span class="card-title" style="color: #0d6efd;"><?= $srv['name'] ?></span>
                            <span class="badge badge-success"><?= $srv['price'] ?></span>
                        </div>
                        <div class="card-text"><?= $srv['desc'] ?></div>
                        <div style="font-size: 11px; color: #6c757d; margin-top: 6px;">⏱️ Duration: <?= $srv['duration'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($page == 'reviews'): ?>
            <div class="section-title">Patient Reviews</div>
            <div class="section-desc">Share your experience at our clinic.</div>
            <form action="index.php?page=reviews" method="POST" class="card light-bg" style="padding: 15px;">
                <input type="hidden" name="submit_review" value="1">
                <div style="font-weight: bold; font-size: 13px; color: #0d6efd; margin-bottom: 10px;">Write your Review</div>
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="reviewer_name" class="form-control" value="<?= htmlspecialchars($current_patient) ?>" required></div>
                <div class="form-group">
                    <label class="form-label">Rating (Stars)</label>
                    <select name="rating" class="form-select">
                        <option value="5">⭐⭐⭐⭐⭐ 5 Stars - Excellent!</option>
                        <option value="4">⭐⭐⭐⭐ 4 Stars - Good</option>
                        <option value="3">⭐⭐⭐ 3 Stars - Okay</option>
                        <option value="2">⭐⭐ 2 Stars - Fair</option>
                        <option value="1">⭐ 1 Star - Unsatisfied</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Comment / Feedback</label><textarea name="comment" class="form-control" rows="3" placeholder="What can you say about the service?" required></textarea></div>
                <button type="submit" class="btn-submit">Submit Review</button>
            </form>
            <div class="section-title" style="margin-top: 20px;">Patient Comments</div>
            <div>
                <?php if(empty($reviews)): ?>
                    <div class="card" style="text-align: center; color: #6c757d; padding: 25px;">No reviews yet. Leave the first one!</div>
                <?php else: ?>
                    <?php foreach($reviews as $rev): ?>
                        <div class="card">
                            <div style="display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 13px;">
                                <span><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                <span style="color: #ffc107; font-size: 12px;"><?php for($i=0; $i<(int)$rev['rating']; $i++): ?>★<?php endfor; ?> (<?= $rev['rating'] ?>/5)</span>
                            </div>
                            <div class="card-text" style="font-style: italic; color: #555;">"<?= htmlspecialchars($rev['comment']) ?>"</div>
                            <div style="font-size: 11px; color: #6c757d; margin-top: 6px; text-align: right;"><?= $rev['created_at'] ?? 'N/A' ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($total_reviews_pages > 1): ?>
                <ul class="pagination">
                    <li class="page-item <?= ($rev_page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="index.php?page=reviews&rp=<?= $rev_page - 1 ?>">Prev</a></li>
                    <?php for ($i = 1; $i <= $total_reviews_pages; $i++): ?>
                        <li class="page-item <?= ($rev_page == $i) ? 'active' : '' ?>"><a class="page-link" href="index.php?page=reviews&rp=<?= $i ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($rev_page >= $total_reviews_pages) ? 'disabled' : '' ?>"><a class="page-link" href="index.php?page=reviews&rp=<?= $rev_page + 1 ?>">Next</a></li>
                </ul>
            <?php endif; ?>
        <?php elseif ($page == 'faq'): ?>
            <div class="section-title">Frequently Asked Questions</div>
            <div class="section-desc">Got questions? Find answers here.</div>
            <div>
                <?php foreach($faqs as $faq): ?>
                    <div class="card">
                        <div class="card-title" style="color: #0d6efd; margin-bottom: 4px;">❓ <?= $faq['q'] ?></div>
                        <div class="card-text"><?= $faq['a'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($page == 'book'): ?>
            <div class="section-title">Book Appointment</div>
            <div class="section-desc">Fill out details for your clinical visit.</div>
            <form action="index.php?page=book" method="POST" class="card" style="padding: 20px;">
                <input type="hidden" name="book_appointment" value="1">
                <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="patient_name" class="form-control" value="<?= htmlspecialchars($current_patient) ?>" required></div>
                <div class="form-group">
                    <label class="form-label">Select Service</label>
                    <select name="service" class="form-select">
                        <?php foreach($services as $srv): ?>
                            <option value="<?= $srv['name'] ?>"><?= $srv['name'] ?> (<?= $srv['price'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Date</label><input type="date" name="date" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Time</label><input type="time" name="time" class="form-control" required></div>
                <button type="submit" class="btn-submit">Submit Booking</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (isset($_SESSION['client_id'])): ?>
    <div class="bottom-nav">
        <a href="index.php?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>">Home</a>
        <a href="index.php`?page=history" class="<?= $page == 'history' ? 'active' : '' ?>">History</a>
        <a href="index.php?page=services" class="<?= $page == 'services' ? 'active' : '' ?>">Services</a>
        <a href="index.php?page=reviews" class="<?= $page == 'reviews' ? 'active' : '' ?>">Reviews</a>
        <a href="index.php?page=book" class="<?= $page == 'book' ? 'active' : '' ?>">Book</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>