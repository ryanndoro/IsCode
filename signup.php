<?php
include "connect.php";

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $gender = trim($_POST['gender']);
        $user_type = trim($_POST['user_type']);
        
        // Validate inputs
        $is_valid = true;
        
        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format";
            $is_valid = false;
        }
        
        // Password validation
        if (strlen($password) < 8) {
            $error_message = "Password must be at least 8 characters long";
            $is_valid = false;
        } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
            $error_message = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character";
            $is_valid = false;
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match";
            $is_valid = false;
        }
        
        // Phone validation (optional field)
        if (!empty($phone) && !preg_match("/^[0-9]{10,15}$/", $phone)) {
            $error_message = "Invalid phone number format";
            $is_valid = false;
        }
        
        if ($is_valid) {
            // Hash password after validation
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email or username already exists.";
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert into users table
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone, date_of_birth, gender, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssss", $username, $email, $hashed_password, $first_name, $last_name, $phone, $date_of_birth, $gender, $user_type);
                    $stmt->execute();
                    
                    // Based on user type, insert into specific role table
                    if ($user_type == 'patient') {
                        $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                    } 
                    else if ($user_type == 'doctor') {
                        // Validate doctor-specific fields
                        if (empty($_POST['specialty']) || empty($_POST['license_number'])) {
                            throw new Exception("Specialty and license number are required for doctors");
                        }
                        
                        $specialty = trim($_POST['specialty']);
                        $license_number = trim($_POST['license_number']);
                        
                        $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialty, license_number) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $user_id, $specialty, $license_number);
                        $stmt->execute();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
                    
                    // Clear form data on success
                    $_POST = array();
                    
                    // Generate new CSRF token after successful submission
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_token'];
                } 
                catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error_message = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MedTrack</title>
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
                <li><a href="login.php">Login</a></li>
                <li><a href="signup.php" class="active">Register</a></li>
                <li><a href="about.html">About</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <div class="container">
            <div class="auth-container">
                <div class="auth-tabs">
                    <button class="auth-tab" onclick="window.location.href='login.php'">Login</button>
                    <button class="auth-tab active">Register</button>
                </div>
                
                <div class="auth-form-container">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <form class="auth-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <h2>Create an Account</h2>
                        
                        <div class="form-group">
                            <label for="user_type">I am a:</label>
                            <select id="user_type" name="user_type" required onchange="toggleSpecialtyFields()">
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                            <small class="form-text text-muted">Password must be at least 8 characters and include uppercase, lowercase, number, and special character.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" required value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" required>
                                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Doctor-specific fields -->
                        <div id="doctor_fields" style="display: none;">
                            <div class="form-group">
                                <label for="specialty">Specialty</label>
                                <input type="text" id="specialty" name="specialty" value="<?php echo isset($_POST['specialty']) ? htmlspecialchars($_POST['specialty']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn primary-btn">Register</button>
                        
                        <div class="auth-links">
                            Already have an account? <a href="login.php">Login</a>
                        </div>
                    </form>
                </div>
            </div>
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
                    <li><a href="login.php">Login</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="contact.html">Contact</a></li>
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
    
    <script>
        function toggleSpecialtyFields() {
            const userType = document.getElementById('user_type').value;
            const doctorFields = document.getElementById('doctor_fields');
            
            if (userType === 'doctor') {
                doctorFields.style.display = 'block';
                document.getElementById('specialty').required = true;
                document.getElementById('license_number').required = true;
            } else {
                doctorFields.style.display = 'none';
                document.getElementById('specialty').required = false;
                document.getElementById('license_number').required = false;
            }
        }
        
        // Call the function on page load to set initial state
        document.addEventListener('DOMContentLoaded', function() {
            toggleSpecialtyFields();
        });
    </script>
</body>
</html>