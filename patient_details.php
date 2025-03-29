<?php
include "connect.php";

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get doctor ID
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $row = $result->fetch_assoc();
    $doctor_id = $row['doctor_id'];
} else {
    // Redirect if doctor ID not found
    header("Location: doctor_dashboard.php?error=not_found");
    exit();
}

// Check if patient ID is provided
if (!isset($_GET['id'])) {
    header("Location: doctor_dashboard.php");
    exit();
}

$patient_id = trim($_GET['id']);

// Check if this doctor has access to this patient
$stmt = $conn->prepare("
    SELECT id FROM patient_doctor 
    WHERE patient_id = ? AND doctor_id = ?
");
$stmt->bind_param("ii", $patient_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows != 1) {
    header("Location: doctor_dashboard.php?error=unauthorized");
    exit();
}

// Get patient information
$stmt = $conn->prepare("
    SELECT p.patient_id, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, u.gender, 
           p.blood_type, p.allergies, p.medical_conditions
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

// Calculate age
$dob = new DateTime($patient['date_of_birth']);
$now = new DateTime();
$patient['age'] = $now->diff($dob)->y;

// Get patient's medications
$stmt = $conn->prepare("
    SELECT pm.id, m.name, m.description, pm.dosage, pm.frequency, pm.start_date, pm.end_date, 
           pm.instructions, pm.status
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.medication_id
    WHERE pm.patient_id = ?
    ORDER BY pm.status, pm.start_date DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medications_result = $stmt->get_result();
$medications = [];
while ($row = $medications_result->fetch_assoc()) {
    $medications[] = $row;
}

// Get medication logs
$stmt = $conn->prepare("
    SELECT ml.log_id, ml.taken_at, ml.status, ml.notes, m.name as medication_name,
           pm.dosage, u.first_name, u.last_name
    FROM medication_logs ml
    JOIN patient_medications pm ON ml.patient_medication_id = pm.id
    JOIN medications m ON pm.medication_id = m.medication_id
    JOIN users u ON ml.logged_by = u.user_id
    WHERE pm.patient_id = ?
    ORDER BY ml.taken_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$logs_result = $stmt->get_result();
$logs = [];
while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - MedTrack</title>
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
                <div class="patient-header">
                    <img src="/placeholder.svg?height=150&width=150" alt="Patient" class="profile-pic">
                    <div class="profile-info">
                        <h2><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></h2>
                        <p><strong>Age:</strong> <?php echo $patient['age']; ?> years</p>
                        <p><strong>Gender:</strong> <?php echo ucfirst($patient['gender']); ?></p>
                        <p><strong>Email:</strong> <?php echo $patient['email']; ?></p>
                        <p><strong>Phone:</strong> <?php echo $patient['phone']; ?></p>
                    </div>
                    <div class="patient-actions">
                        <a href="add_medication.php?patient_id=<?php echo $patient_id; ?>" class="btn primary-btn">Add Medication</a>
                        <a href="message.php?patient_id=<?php echo $patient_id; ?>" class="btn">Message Patient</a>
                    </div>
                </div>
                
                <div class="profile-tabs">
                    <button class="profile-tab active" data-tab="medications">Medications</button>
                    <button class="profile-tab" data-tab="logs">Medication Logs</button>
                    <button class="profile-tab" data-tab="medical-info">Medical Information</button>
                </div>
                
                <div id="medications-tab" class="profile-tab-content">
                    <h3>Current Medications</h3>
                    
                    <?php if (count($medications) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medication</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medications as $med): ?>
                                    <?php if ($med['status'] == 'active'): ?>
                                        <tr>
                                            <td><?php echo $med['name']; ?></td>
                                            <td><?php echo $med['dosage']; ?></td>
                                            <td><?php echo $med['frequency']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($med['start_date'])); ?></td>
                                            <td><?php echo $med['end_date'] ? date('M d, Y', strtotime($med['end_date'])) : 'Ongoing'; ?></td>
                                            <td><?php echo ucfirst($med['status']); ?></td>
                                            <td>
                                                <a href="edit_medication.php?id=<?php echo $med['id']; ?>" class="btn small-btn">Edit</a>
                                                <a href="medication_details.php?id=<?php echo $med['id']; ?>" class="btn primary-btn small-btn">Details</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <h3>Past Medications</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medication</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasPastMeds = false;
                                foreach ($medications as $med): 
                                    if ($med['status'] != 'active'):
                                        $hasPastMeds = true;
                                ?>
                                    <tr>
                                        <td><?php echo $med['name']; ?></td>
                                        <td><?php echo $med['dosage']; ?></td>
                                        <td><?php echo $med['frequency']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($med['start_date'])); ?></td>
                                        <td><?php echo $med['end_date'] ? date('M d, Y', strtotime($med['end_date'])) : 'Ongoing'; ?></td>
                                        <td><?php echo ucfirst($med['status']); ?></td>
                                        <td>
                                            <a href="medication_details.php?id=<?php echo $med['id']; ?>" class="btn small-btn">Details</a>
                                        </td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                
                                if (!$hasPastMeds):
                                ?>
                                    <tr>
                                        <td colspan="7">No past medications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No medications found for this patient.</p>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <a href="add_medication.php?patient_id=<?php echo $patient_id; ?>" class="btn primary-btn">Add New Medication</a>
                    </div>
                </div>
                
                <div id="logs-tab" class="profile-tab-content hidden">
                    <h3>Recent Medication Logs</h3>
                    
                    <?php if (count($logs) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Medication</th>
                                    <th>Dosage</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Logged By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y g:i A', strtotime($log['taken_at'])); ?></td>
                                        <td><?php echo $log['medication_name']; ?></td>
                                        <td><?php echo $log['dosage']; ?></td>
                                        <td>
                                            <?php if ($log['status'] == 'taken'): ?>
                                                <span class="status-ok">✓ Taken</span>
                                            <?php elseif ($log['status'] == 'skipped'): ?>
                                                <span class="status-warning">⚠ Skipped</span>
                                            <?php elseif ($log['status'] == 'missed'): ?>
                                                <span class="status-error">✗ Missed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $log['notes']; ?></td>
                                        <td><?php echo $log['first_name'] . ' ' . $log['last_name']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No medication logs found for this patient.</p>
                    <?php endif; ?>
                </div>
                
                <div id="medical-info-tab" class="profile-tab-content hidden">
                    <h3>Medical Information</h3>
                    
                    <div class="form-group">
                        <label>Blood Type:</label>
                        <p><?php echo $patient['blood_type'] ? $patient['blood_type'] : 'Not specified'; ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Allergies:</label>
                        <p><?php echo $patient['allergies'] ? $patient['allergies'] : 'None reported'; ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Medical Conditions:</label>
                        <p><?php echo $patient['medical_conditions'] ? $patient['medical_conditions'] : 'None reported'; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="doctor_dashboard.php" class="btn">Back to Dashboard</a>
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
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.profile-tab');
            const tabContents = document.querySelectorAll('.profile-tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all tab content
                    tabContents.forEach(content => content.classList.add('hidden'));
                    
                    // Show selected tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(`${tabId}-tab`).classList.remove('hidden');
                });
            });
        });
    </script>
</body>
</html>