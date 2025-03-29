<?php
include "connect.php";

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get doctor information
$stmt = $conn->prepare("
    SELECT d.doctor_id, u.first_name, u.last_name, u.email, u.phone, 
           d.specialty, d.license_number
    FROM doctors d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();
$doctor_id = $doctor['doctor_id'];

// Get doctor's patients
$stmt = $conn->prepare("
    SELECT p.patient_id, u.first_name, u.last_name, u.gender, u.date_of_birth, 
           pd.relationship_start_date, p.medical_conditions
    FROM patient_doctor pd
    JOIN patients p ON pd.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.user_id
    WHERE pd.doctor_id = ?
    ORDER BY u.last_name, u.first_name
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients_result = $stmt->get_result();
$patients = [];
while ($row = $patients_result->fetch_assoc()) {
    // Calculate age from date of birth
    $dob = new DateTime($row['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    $row['age'] = $age;
    
    $patients[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - MedTrack</title>
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
                    <h3>Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?></h3>
                    <p><?php echo $doctor['specialty']; ?></p>
                </div>
                
                <nav class="sidebar-nav">
                    <ul>
                        <li><a href="#overview" class="active">Overview</a></li>
                        <li><a href="#patients">My Patients</a></li>
                        <li><a href="#prescriptions">Prescriptions</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </nav>
            </div>
            
            <div class="dashboard-content">
                <section id="overview" class="dashboard-section">
                    <h2>Overview</h2>
                    
                    <div class="overview-cards">
                        <div class="overview-card">
                            <h3>My Patients</h3>
                            <div class="stat-large"><?php echo count($patients); ?></div>
                            <a href="#patients" class="card-link">View All Patients</a>
                        </div>
                        
                        <div class="overview-card">
                            <h3>Active Prescriptions</h3>
                            <?php
                            // Get count of active prescriptions
                            $stmt = $conn->prepare("
                                SELECT COUNT(*) as count
                                FROM patient_medications pm
                                WHERE pm.doctor_id = ? AND pm.status = 'active'
                            ");
                            $stmt->bind_param("i", $doctor_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $prescription_count = $result->fetch_assoc()['count'];
                            ?>
                            <div class="stat-large"><?php echo $prescription_count; ?></div>
                            <a href="#prescriptions" class="card-link">View All Prescriptions</a>
                        </div>
                    </div>
                </section>
                
                <section id="patients" class="dashboard-section hidden">
                    <h2>My Patients</h2>
                    
                    <div class="search-filter">
                        <input type="text" id="patient-search" placeholder="Search patients...">
                    </div>
                    
                    <?php if (count($patients) > 0): ?>
                        <div class="patient-list">
                            <?php foreach ($patients as $patient): ?>
                                <div class="patient-card" data-id="<?php echo $patient['patient_id']; ?>">
                                    <div class="patient-header">
                                        <img src="/placeholder.svg?height=60&width=60" alt="Patient">
                                        <div>
                                            <h3 class="patient-name"><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></h3>
                                            <p class="patient-info"><?php echo $patient['gender']; ?>, <?php echo $patient['age']; ?> years old</p>
                                            <p class="patient-info">Patient since: <?php echo date('M d, Y', strtotime($patient['relationship_start_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="patient-actions">
                                        <a href="patient_details.php?id=<?php echo $patient['patient_id']; ?>" class="btn small-btn">View Details</a>
                                        <a href="add_medication.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn primary-btn small-btn">Add Medication</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <p>You don't have any patients assigned yet.</p>
                        </div>
                    <?php endif; ?>
                </section>
                
                <section id="prescriptions" class="dashboard-section hidden">
                    <h2>Prescriptions</h2>
                    
                    <?php
                    // Get prescriptions
                    $stmt = $conn->prepare("
                        SELECT pm.id, pm.patient_id, pm.start_date, pm.end_date, pm.status,
                               m.name as medication_name, pm.dosage, pm.frequency,
                               u.first_name, u.last_name
                        FROM patient_medications pm
                        JOIN medications m ON pm.medication_id = m.medication_id
                        JOIN patients p ON pm.patient_id = p.patient_id
                        JOIN users u ON p.user_id = u.user_id
                        WHERE pm.doctor_id = ?
                        ORDER BY pm.start_date DESC
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $prescriptions_result = $stmt->get_result();
                    $prescriptions = [];
                    while ($row = $prescriptions_result->fetch_assoc()) {
                        $prescriptions[] = $row;
                    }
                    ?>
                    
                    <?php if (count($prescriptions) > 0): ?>
                        <div class="card">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
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
                                    <?php foreach ($prescriptions as $prescription): ?>
                                        <tr>
                                            <td><?php echo $prescription['first_name'] . ' ' . $prescription['last_name']; ?></td>
                                            <td><?php echo $prescription['medication_name']; ?></td>
                                            <td><?php echo $prescription['dosage']; ?></td>
                                            <td><?php echo $prescription['frequency']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($prescription['start_date'])); ?></td>
                                            <td><?php echo $prescription['end_date'] ? date('M d, Y', strtotime($prescription['end_date'])) : 'Ongoing'; ?></td>
                                            <td><?php echo ucfirst($prescription['status']); ?></td>
                                            <td>
                                                <a href="edit_medication.php?id=<?php echo $prescription['id']; ?>" class="btn small-btn">Edit</a>
                                                <a href="medication_details.php?id=<?php echo $prescription['id']; ?>" class="btn primary-btn small-btn">Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <p>You haven't prescribed any medications yet.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <h3>Add New Prescription</h3>
                        <p>Select a patient to add a new medication prescription:</p>
                        
                        <form action="add_medication.php" method="get">
                            <div class="form-group">
                                <label for="patient_select">Select Patient:</label>
                                <select id="patient_select" name="patient_id" required>
                                    <option value="">-- Select Patient --</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['patient_id']; ?>"><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn primary-btn">Continue</button>
                        </form>
                    </div>
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