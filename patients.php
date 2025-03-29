<?php
include "connect.php";

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get patient information
$stmt = $conn->prepare("
    SELECT p.patient_id, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, u.gender, 
           p.blood_type, p.allergies, p.medical_conditions
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$patient_id = $patient['patient_id'];

// Get patient's medications
$stmt = $conn->prepare("
    SELECT pm.id, m.name, m.description, pm.dosage, pm.frequency, pm.start_date, pm.end_date, 
           pm.instructions, pm.status
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.medication_id
    WHERE pm.patient_id = ? AND pm.status = 'active'
    ORDER BY pm.start_date DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medications_result = $stmt->get_result();
$medications = [];
while ($row = $medications_result->fetch_assoc()) {
    $medications[] = $row;
}

// Get today's medication schedule
$today = date('Y-m-d');
$dayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)

$stmt = $conn->prepare("
    SELECT ms.schedule_id, ms.scheduled_time, pm.id as patient_medication_id,
           m.name, pm.dosage, pm.instructions
    FROM medication_schedule ms
    JOIN patient_medications pm ON ms.patient_medication_id = pm.id
    JOIN medications m ON pm.medication_id = m.medication_id
    WHERE pm.patient_id = ? AND pm.status = 'active'
    AND (ms.days_of_week LIKE ? OR ms.days_of_week IS NULL)
    ORDER BY ms.scheduled_time
");
$dayPattern = '%' . $dayOfWeek . '%';
$stmt->bind_param("is", $patient_id, $dayPattern);
$stmt->execute();
$schedule_result = $stmt->get_result();
$schedule = [];
while ($row = $schedule_result->fetch_assoc()) {
    // Check if medication has been logged today
    $logStmt = $conn->prepare("
        SELECT status FROM medication_logs
        WHERE patient_medication_id = ? 
        AND DATE(taken_at) = ?
        ORDER BY taken_at DESC
        LIMIT 1
    ");
    $logStmt->bind_param("is", $row['patient_medication_id'], $today);
    $logStmt->execute();
    $logResult = $logStmt->get_result();
    
    if ($logResult->num_rows > 0) {
        $log = $logResult->fetch_assoc();
        $row['logged_status'] = $log['status'];
    } else {
        $row['logged_status'] = null;
    }
    
    $schedule[] = $row;
}

// Get patient's doctors
$stmt = $conn->prepare("
    SELECT d.doctor_id, u.first_name, u.last_name, d.specialty
    FROM patient_doctor pd
    JOIN doctors d ON pd.doctor_id = d.doctor_id
    JOIN users u ON d.user_id = u.user_id
    WHERE pd.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$doctors_result = $stmt->get_result();
$doctors = [];
while ($row = $doctors_result->fetch_assoc()) {
    $doctors[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - MedTrack</title>
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
        <div class="dashboard">
            <div class="sidebar">
                <div class="user-info">
                    <img src="/placeholder.svg?height=100&width=100" alt="Profile" class="profile-pic">
                    <h3><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></h3>
                    <p>Patient</p>
                </div>
                
                <nav class="sidebar-nav">
                    <ul>
                        <li><a href="#overview" class="active">Overview</a></li>
                        <li><a href="#medications">My Medications</a></li>
                        <li><a href="#schedule">Today's Schedule</a></li>
                        <li><a href="#doctors">My Doctors</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </nav>
                
                <div class="emergency-btn-container">
                    <button id="emergency-btn" class="emergency-btn">
                        <span class="emergency-icon">⚠️</span> Emergency Alert
                    </button>
                </div>
            </div>
            
            <div class="dashboard-content">
                <section id="overview" class="dashboard-section">
                    <h2>Overview</h2>
                    
                    <div class="overview-cards">
                        <div class="overview-card">
                            <h3>Medication Adherence</h3>
                            <div class="medication-progress">
                                <div class="progress-circle">
                                    <span class="progress-text">85%</span>
                                </div>
                                <p>Last 30 days</p>
                            </div>
                            <a href="#medications" class="card-link">View All Medications</a>
                        </div>
                        
                        <div class="overview-card">
                            <h3>Today's Medications</h3>
                            <ul class="reminder-list">
                                <?php if (count($schedule) > 0): ?>
                                    <?php foreach ($schedule as $med): ?>
                                        <li>
                                            <?php echo $med['name']; ?> - <?php echo date('g:i A', strtotime($med['scheduled_time'])); ?>
                                            <?php if ($med['logged_status'] == 'taken'): ?>
                                                <span class="status-ok">✓ Taken</span>
                                            <?php elseif ($med['logged_status'] == 'skipped'): ?>
                                                <span class="status-warning">⚠ Skipped</span>
                                            <?php elseif ($med['logged_status'] == 'missed'): ?>
                                                <span class="status-error">✗ Missed</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>No medications scheduled for today.</li>
                                <?php endif; ?>
                            </ul>
                            <a href="#schedule" class="card-link">View Full Schedule</a>
                        </div>
                        
                        <div class="overview-card">
                            <h3>My Doctors</h3>
                            <?php if (count($doctors) > 0): ?>
                                <ul class="reminder-list">
                                    <?php foreach ($doctors as $doctor): ?>
                                        <li>Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?> (<?php echo $doctor['specialty']; ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No doctors assigned yet.</p>
                            <?php endif; ?>
                            <a href="#doctors" class="card-link">View All Doctors</a>
                        </div>
                    </div>
                </section>
                
                <section id="medications" class="dashboard-section hidden">
                    <h2>My Medications</h2>
                    
                    <?php if (count($medications) > 0): ?>
                        <div class="card">
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
                                        <tr>
                                            <td><?php echo $med['name']; ?></td>
                                            <td><?php echo $med['dosage']; ?></td>
                                            <td><?php echo $med['frequency']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($med['start_date'])); ?></td>
                                            <td><?php echo $med['end_date'] ? date('M d, Y', strtotime($med['end_date'])) : 'Ongoing'; ?></td>
                                            <td><?php echo ucfirst($med['status']); ?></td>
                                            <td>
                                                <a href="medication_details.php?id=<?php echo $med['id']; ?>" class="btn small-btn">Details</a>
                                                <a href="log_medication.php?id=<?php echo $med['id']; ?>" class="btn primary-btn small-btn">Log</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <p>You don't have any active medications.</p>
                        </div>
                    <?php endif; ?>
                </section>
                
                <section id="schedule" class="dashboard-section hidden">
                    <h2>Today's Medication Schedule</h2>
                    
                    <?php if (count($schedule) > 0): ?>
                        <div class="card">
                            <h3><?php echo date('l, F j, Y'); ?></h3>
                            
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Instructions</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedule as $med): ?>
                                        <tr>
                                            <td><?php echo date('g:i A', strtotime($med['scheduled_time'])); ?></td>
                                            <td><?php echo $med['name']; ?></td>
                                            <td><?php echo $med['dosage']; ?></td>
                                            <td><?php echo $med['instructions']; ?></td>
                                            <td>
                                                <?php if ($med['logged_status'] == 'taken'): ?>
                                                    <span class="status-ok">✓ Taken</span>
                                                <?php elseif ($med['logged_status'] == 'skipped'): ?>
                                                    <span class="status-warning">⚠ Skipped</span>
                                                <?php elseif ($med['logged_status'] == 'missed'): ?>
                                                    <span class="status-error">✗ Missed</span>
                                                <?php else: ?>
                                                    <span>Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$med['logged_status']): ?>
                                                    <a href="log_medication.php?id=<?php echo $med['patient_medication_id']; ?>" class="btn primary-btn small-btn">Log</a>
                                                <?php else: ?>
                                                    <a href="log_medication.php?id=<?php echo $med['patient_medication_id']; ?>&edit=1" class="btn small-btn">Update</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <p>No medications scheduled for today.</p>
                        </div>
                    <?php endif; ?>
                </section>
                
                <section id="doctors" class="dashboard-section hidden">
                    <h2>My Doctors</h2>
                    
                    <?php if (count($doctors) > 0): ?>
                        <div class="patient-list">
                            <?php foreach ($doctors as $doctor): ?>
                                <div class="patient-card">
                                    <div class="patient-header">
                                        <img src="/placeholder.svg?height=60&width=60" alt="Doctor">
                                        <div>
                                            <h3 class="patient-name">Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?></h3>
                                            <p class="patient-info"><?php echo $doctor['specialty']; ?></p>
                                        </div>
                                    </div>
                                    <div class="patient-actions">
                                        <a href="doctor_details.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn small-btn">View Profile</a>
                                        <a href="message.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" class="btn primary-btn small-btn">Message</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <p>You don't have any doctors assigned yet.</p>
                        </div>
                    <?php endif; ?>
                </section>
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
    
    <script src="app.js"></script>
</body>
</html>