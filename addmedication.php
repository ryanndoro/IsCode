<?php
include "connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $row = $result->fetch_assoc();
    $doctor_id = $row['doctor_id'];
} else {
    header("Location: doctor_dashboard.php?error=not_found");
    exit();
}

if (!isset($_GET['patient_id'])) {
    header("Location: doctor_dashboard.php");
    exit();
}

$patient_id = trim($_GET['patient_id']);

$stmt = $conn->prepare("
    SELECT p.patient_id, u.first_name, u.last_name
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows != 1) {
    header("Location: doctor_dashboard.php?error=patient_not_found");
    exit();
}
$patient = $result->fetch_assoc();

$stmt = $conn->prepare("
    SELECT medication_id, name, description, dosage_form, strength
    FROM medications
    ORDER BY name
");
$stmt->execute();
$medications_result = $stmt->get_result();
$medications = [];
while ($row = $medications_result->fetch_assoc()) {
    $medications[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $medication_id = trim($_POST['medication_id']);
    $dosage = trim($_POST['dosage']);
    $frequency = trim($_POST['frequency']);
    $start_date = trim($_POST['start_date']);
    $end_date = empty($_POST['end_date']) ? NULL : trim($_POST['end_date']);
    $instructions = trim($_POST['instructions']);
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO patient_medications 
            (patient_id, medication_id, doctor_id, dosage, frequency, start_date, end_date, instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiisssss", $patient_id, $medication_id, $doctor_id, $dosage, $frequency, $start_date, $end_date, $instructions);
        $stmt->execute();
        
        if (strpos($frequency, 'daily') !== false) {
            $times = explode(',', $_POST['schedule_times']);
            foreach ($times as $time) {
                $time = trim($time);
                $stmt = $conn->prepare("
                    INSERT INTO medication_schedule (patient_medication_id, scheduled_time, days_of_week) 
                    VALUES (?, ?, '1,2,3,4,5,6,7')
                ");
                $stmt->bind_param("is", $patient_medication_id, $time);
                $stmt->execute();
            }
        } else if (strpos($frequency, 'weekly') !== false) {
            $days = $_POST['schedule_days'];
            $time = $_POST['schedule_time'];
            $days_string = implode(',', $days);
            
            $stmt = $conn->prepare("
                INSERT INTO medication_schedule (patient_medication_id, scheduled_time, days_of_week) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", $patient_medication_id, $time, $days_string);
            $stmt->execute();
        }
        
        $conn->commit();
        
        header("Location: patient_details.php?id=$patient_id&success=medication_added");
        exit();
    } 
    catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to add medication: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medication - MedTrack</title>
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
                <li><a href="about.html" class="active">About</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <div class="container">
            <div class="card">
                <h2>Add Medication for <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?patient_id=' . $patient_id); ?>">
                    <div class="form-group">
                        <label for="medication_id">Medication:</label>
                        <select id="medication_id" name="medication_id" required>
                            <option value="">-- Select Medication --</option>
                            <?php foreach ($medications as $medication): ?>
                                <option value="<?php echo $medication['medication_id']; ?>">
                                    <?php echo $medication['name']; ?> 
                                    <?php if ($medication['strength']): ?>
                                        (<?php echo $medication['strength']; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dosage">Dosage:</label>
                            <input type="text" id="dosage" name="dosage" required placeholder="e.g., 1 tablet">
                        </div>
                        
                        <div class="form-group">
                            <label for="frequency">Frequency:</label>
                            <select id="frequency" name="frequency" required onchange="toggleScheduleFields()">
                                <option value="">-- Select Frequency --</option>
                                <option value="once daily">Once Daily</option>
                                <option value="twice daily">Twice Daily</option>
                                <option value="three times daily">Three Times Daily</option>
                                <option value="four times daily">Four Times Daily</option>
                                <option value="once weekly">Once Weekly</option>
                                <option value="as needed">As Needed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="daily_schedule" style="display: none;">
                        <div class="form-group">
                            <label for="schedule_times">Times of Day:</label>
                            <input type="text" id="schedule_times" name="schedule_times" placeholder="e.g., 08:00, 20:00">
                            <small>Enter times in 24-hour format (HH:MM), separated by commas.</small>
                        </div>
                    </div>
                    
                    <div id="weekly_schedule" style="display: none;">
                        <div class="form-group">
                            <label>Days of Week:</label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="schedule_days[]" value="1"> Monday</label>
                                <label><input type="checkbox" name="schedule_days[]" value="2"> Tuesday</label>
                                <label><input type="checkbox" name="schedule_days[]" value="3"> Wednesday</label>
                                <label><input type="checkbox" name="schedule_days[]" value="4"> Thursday</label>
                                <label><input type="checkbox" name="schedule_days[]" value="5"> Friday</label>
                                <label><input type="checkbox" name="schedule_days[]" value="6"> Saturday</label>
                                <label><input type="checkbox" name="schedule_days[]" value="7"> Sunday</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="schedule_time">Time:</label>
                            <input type="time" id="schedule_time" name="schedule_time">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date (optional):</label>
                            <input type="date" id="end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions">Instructions:</label>
                        <textarea id="instructions" name="instructions" rows="3" placeholder="e.g., Take with food"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Add Medication</button>
                        <a href="patient_details.php?id=<?php echo $patient_id; ?>" class="btn">Cancel</a>
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
    
    <script>
        function toggleScheduleFields() {
            const frequency = document.getElementById('frequency').value;
            const dailySchedule = document.getElementById('daily_schedule');
            const weeklySchedule = document.getElementById('weekly_schedule');
            
            dailySchedule.style.display = 'none';
            weeklySchedule.style.display = 'none';
            
            if (frequency.includes('daily')) {
                dailySchedule.style.display = 'block';
                document.getElementById('schedule_times').required = true;
            } else if (frequency.includes('weekly')) {
                weeklySchedule.style.display = 'block';
                document.getElementById('schedule_time').required = true;
            }
        }
    </script>
</body>
</html>