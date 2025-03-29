<?php
include "connect.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if medication ID is provided
if (!isset($_GET['id'])) {
    header("Location: " . ($user_type == 'patient' ? 'patient_dashboard.php' : 'doctor_dashboard.php'));
    exit();
}

$patient_medication_id = trim($_GET['id']);

// Get medication information
$stmt = $conn->prepare("
    SELECT pm.id, pm.patient_id, pm.dosage, pm.instructions, pm.frequency,
           m.name as medication_name, m.description,
           p.user_id as patient_user_id
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.medication_id
    JOIN patients p ON pm.patient_id = p.patient_id
    WHERE pm.id = ?
");
$stmt->bind_param("i", $patient_medication_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    header("Location: " . ($user_type == 'patient' ? 'patient_dashboard.php' : 'doctor_dashboard.php') . "?error=medication_not_found");
    exit();
}

$medication = $result->fetch_assoc();

// Check if user has permission to log this medication
if ($user_type == 'patient' && $medication['patient_user_id'] != $user_id) {
    header("Location: patient_dashboard.php?error=unauthorized");
    exit();
}

// Get patient information
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $medication['patient_id']);
$stmt->execute();
$patient_result = $stmt->get_result();
$patient = $patient_result->fetch_assoc();

// Check if editing an existing log
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 1;
$log_data = null;

if ($edit_mode) {
    // Get the most recent log for today
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT log_id, taken_at, status, notes
        FROM medication_logs
        WHERE patient_medication_id = ? AND DATE(taken_at) = ?
        ORDER BY taken_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $patient_medication_id, $today);
    $stmt->execute();
    $log_result = $stmt->get_result();
    
    if ($log_result->num_rows > 0) {
        $log_data = $log_result->fetch_assoc();
    } else {
        $edit_mode = false;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $status = trim($_POST['status']);
    $notes = trim($_POST['notes']);
    $taken_at = trim($_POST['taken_at']);
    
    if ($edit_mode && isset($_POST['log_id'])) {
        // Update existing log
        $log_id = trim($_POST['log_id']);
        $stmt = $conn->prepare("
            UPDATE medication_logs
            SET status = ?, notes = ?, taken_at = ?, logged_by = ?
            WHERE log_id = ?
        ");
        $stmt->bind_param("sssii", $status, $notes, $taken_at, $user_id, $log_id);
    } else {
        // Insert new log
        $stmt = $conn->prepare("
            INSERT INTO medication_logs (patient_medication_id, taken_at, status, notes, logged_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssi", $patient_medication_id, $taken_at, $status, $notes, $user_id);
    }
    
    if ($stmt->execute()) {
        // Redirect based on user type
        if ($user_type == 'patient') {
            header("Location: patient_dashboard.php?success=medication_logged#schedule");
        } else {
            header("Location: patient_details.php?id=" . $medication['patient_id'] . "&success=medication_logged");
        }
        exit();
    } else {
        $mysqli_error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Medication - MedTrack</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>MedTrack</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.html" class="active">Home</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="about.html">About</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <div class="container">
            <div class="card">
                <h2><?php echo $edit_mode ? 'Update' : 'Log'; ?> Medication</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <div class="medication-info">
                    <h3><?php echo $medication['medication_name']; ?></h3>
                    <p><strong>Patient:</strong> <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></p>
                    <p><strong>Dosage:</strong> <?php echo $medication['dosage']; ?></p>
                    <p><strong>Instructions:</strong> <?php echo $medication['instructions']; ?></p>
                </div>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $patient_medication_id . ($edit_mode ? '&edit=1' : '')); ?>">
                    <?php if ($edit_mode && $log_data): ?>
                        <input type="hidden" name="log_id" value="<?php echo $log_data['log_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="taken" <?php echo ($edit_mode && $log_data && $log_data['status'] == 'taken') ? 'selected' : ''; ?>>Taken</option>
                            <option value="skipped" <?php echo ($edit_mode && $log_data && $log_data['status'] == 'skipped') ? 'selected' : ''; ?>>Skipped</option>
                            <option value="missed" <?php echo ($edit_mode && $log_data && $log_data['status'] == 'missed') ? 'selected' : ''; ?>>Missed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="taken_at">Date & Time:</label>
                        <input type="datetime-local" id="taken_at" name="taken_at" required 
                               value="<?php echo $edit_mode && $log_data ? date('Y-m-d\TH:i', strtotime($log_data['taken_at'])) : date('Y-m-d\TH:i'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (optional):</label>
                        <textarea id="notes" name="notes" rows="3"><?php echo $edit_mode && $log_data ? $log_data['notes'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn"><?php echo $edit_mode ? 'Update' : 'Log'; ?> Medication</button>
                        <a href="<?php echo $user_type == 'patient' ? 'patient_dashboard.php#schedule' : 'patient_details.php?id=' . $medication['patient_id']; ?>" class="btn">Cancel</a>
                    </div>
                </form>
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
</body>
</html>