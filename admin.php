<?php
    session_start();
    
    if (!isset($_SESSION["user_id"]) || $_SESSION["category"] != "admin") {
        header("Location: login.php");
        exit();
    }
    
    include "connect.php";
    
    // Get admin information
    $user_id = $_SESSION["user_id"];
    $query = "SELECT * FROM users WHERE user_id = '$user_id'";
    $result = mysqli_query($connect, $query);
    $user = mysqli_fetch_assoc($result);
    
    // Get user statistics
    $patients_query = "SELECT COUNT(*) as count FROM users WHERE category = 'patient'";
    $patients_result = mysqli_query($connect, $patients_query);
    $patients_count = mysqli_fetch_assoc($patients_result)["count"];
    
    $doctors_query = "SELECT COUNT(*) as count FROM users WHERE category = 'doctor'";
    $doctors_result = mysqli_query($connect, $doctors_query);
    $doctors_count = mysqli_fetch_assoc($doctors_result)["count"];
    
    $caregivers_query = "SELECT COUNT(*) as count FROM users WHERE category = 'caregiver'";
    $caregivers_result = mysqli_query($connect, $caregivers_query);
    $caregivers_count = mysqli_fetch_assoc($caregivers_result)["count"];
    
    // Get recent users
    $recent_users_query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
    $recent_users_result = mysqli_query($connect, $recent_users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedTrack - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>MedTrack</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="admin.php" class="active">Dashboard</a></li>
                <li><a href="user.html">Profile</a></li>
                <li><a href="settings.html">Settings</a></li>
                <li><a href="logout.php" id="logout-btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard">
        <aside class="sidebar">
            <div class="user-info">
                <img src="images/profile-placeholder.jpg" alt="Admin profile" class="profile-pic">
                <h3 id="admin-name"><?php echo $user["name"]; ?></h3>
                <p id="admin-role">Administrator</p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#overview" class="active">Overview</a></li>
                    <li><a href="#users">User Management</a></li>
                    <li><a href="#system">System Monitoring</a></li>
                    <li><a href="#logs">Logs & Security</a></li>
                </ul>
            </nav>
        </aside>

        <div class="dashboard-content">
            <section id="overview" class="dashboard-section">
                <h2>Dashboard Overview</h2>
                <div class="overview-cards">
                    <div class="overview-card">
                        <h3>Total Patients</h3>
                        <div class="stat-large"><?php echo $patients_count; ?></div>
                        <a href="#users" class="card-link">Manage Patients</a>
                    </div>
                    
                    <div class="overview-card">
                        <h3>Total Doctors</h3>
                        <div class="stat-large"><?php echo $doctors_count; ?></div>
                        <a href="#users" class="card-link">Manage Doctors</a>
                    </div>
                    
                    <div class="overview-card">
                        <h3>Total Caregivers</h3>
                        <div class="stat-large"><?php echo $caregivers_count; ?></div>
                        <a href="#users" class="card-link">Manage Caregivers</a>
                    </div>
                    
                    <div class="overview-card">
                        <h3>System Status</h3>
                        <div class="system-status">
                            <div class="status-item">
                                <span class="status-label">Database</span>
                                <span class="status-value status-ok">Online</span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">API</span>
                                <span class="status-value status-ok">Online</span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">Notifications</span>
                                <span class="status-value status-ok">Working</span>
                            </div>
                        </div>
                        <a href="#system" class="card-link">View System Status</a>
                    </div>
                </div>
                
                <div class="recent-activity">
                    <h3>Recent User Registrations</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                while ($user = mysqli_fetch_assoc($recent_users_result)) {
                                    echo '<tr>';
                                    echo '<td>' . $user["name"] . '</td>';
                                    echo '<td>' . $user["email"] . '</td>';
                                    echo '<td>' . ucfirst($user["category"]) . '</td>';
                                    echo '<td>' . date("M d, Y", strtotime($user["created_at"])) . '</td>';
                                    echo '<td>';
                                    echo '<button class="btn small-btn view-user-btn" data-id="' . $user["user_id"] . '">View</button>';
                                    echo '<button class="btn small-btn edit-user-btn" data-id="' . $user["user_id"] . '">Edit</button>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Other admin sections would go here -->
            
            <section id="users" class="dashboard-section hidden">
                <h2>User Management</h2>
                <!-- User management content -->
            </section>
            
            <section id="system" class="dashboard-section hidden">
                <h2>System Monitoring</h2>
                <!-- System monitoring content -->
            </section>
            
            <section id="logs" class="dashboard-section hidden">
                <h2>Logs & Security</h2>
                <!-- Logs and security content -->
            </section>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <h2>MedTrack</h2>
                <p>Your health companion</p>
            </div>
            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="settings.html">Settings</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h3>Contact Us</h3>
                <p>Email: support@medtrack.com</p>
                <p>Phone: (123) 456-7890</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 MedTrack. All rights reserved.</p>
        </div>
    </footer>

    <script src="app.js"></script>
</body>
</html>