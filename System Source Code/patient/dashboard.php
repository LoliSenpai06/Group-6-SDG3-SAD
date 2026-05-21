<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];

// Get current section from URL parameter
$current_section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// Get patient info
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient_info = $stmt->fetch();

if (!$patient_info) {
    die("Patient not found. Please contact administrator.");
}

// Handle appointment booking
$book_success = '';
$book_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $service_id = (int)$_POST['service_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $reason = $_POST['reason'];
    
    // Determine doctor based on service (MUST match your database)
    $doctor_id = null;
    if ($service_id == 1) {
        $doctor_id = 1; // Dr. Santos for General Checkup
    } elseif ($service_id == 2) {
        $doctor_id = 2; // Dr. Reyes for Prenatal
    } elseif ($service_id == 3) {
        $doctor_id = 3; // Dr. Cruz for Pediatric
    }
    
    if ($doctor_id) {
        try {
            // Insert appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, service_id, date, time, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$patient_id, $doctor_id, $service_id, $date, $time, $reason]);
            $book_success = "✓ Appointment booked successfully! Waiting for admin approval.";
            
            // Refresh to show new appointment
            header("Refresh:2");
        } catch(PDOException $e) {
            $book_error = "Error: " . $e->getMessage();
        }
    } else {
        $book_error = "Invalid service selected. Please try again.";
    }
}

// Get patient's appointments with detailed info
$stmt = $pdo->prepare("
    SELECT a.*, 
           d.name as doctor_name, 
           d.specialization,
           s.name as service_name 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN services s ON a.service_id = s.id 
    WHERE a.patient_id = ? 
    ORDER BY a.date DESC, a.time DESC
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll();

// Debug - check if appointments exist
$appointment_count = count($appointments);

// Count appointments by status
$pending = 0;
$confirmed = 0;
$completed = 0;
$cancelled = 0;

foreach($appointments as $apt) {
    switch($apt['status']) {
        case 'Pending': $pending++; break;
        case 'Confirmed': $confirmed++; break;
        case 'Completed': $completed++; break;
        case 'Cancelled': $cancelled++; break;
    }
}

// Get health records
$stmt = $pdo->prepare("SELECT * FROM health_records WHERE patient_id = ? ORDER BY date DESC");
$stmt->execute([$patient_id]);
$health_records = $stmt->fetchAll();

// Helper function to set active class
function isActive($section) {
    global $current_section;
    return $current_section === $section ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Barangay Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            position: fixed;
            height: 100%;
            padding: 30px 20px;
            overflow-y: auto;
        }
        .patient-profile { text-align: center; margin-bottom: 30px; }
        .patient-profile i { font-size: 60px; margin-bottom: 10px; }
        .patient-profile h3 { font-size: 18px; word-break: break-word; }
        .patient-profile p { font-size: 14px; opacity: 0.8; }
        .sidebar nav a {
            display: block;
            padding: 12px 20px;
            color: #ddd;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .sidebar nav a:hover, .sidebar nav a.active { background: #667eea; color: white; }
        .sidebar nav a i { margin-right: 12px; width: 20px; }
        .logout {
            position: absolute;
            bottom: 30px;
            left: 20px;
            right: 20px;
            background: #dc3545;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .logout:hover { background: #c82333; }
        .main-content { margin-left: 280px; padding: 30px; }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card i { font-size: 35px; color: #667eea; margin-bottom: 10px; }
        .stat-card h3 { font-size: 28px; }
        .card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 { margin-bottom: 20px; border-left: 4px solid #667eea; padding-left: 15px; }
        table { width: 100%; border-collapse: collapse; overflow-x: auto; display: block; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .status-Pending { background: #ffc107; color: #333; }
        .status-Confirmed { background: #28a745; color: white; }
        .status-Completed { background: #17a2b8; color: white; }
        .status-Cancelled { background: #dc3545; color: white; }
        .btn-primary {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover { background: #5a67d8; transform: translateY(-2px); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .note-text {
            font-size: 12px;
            color: #666;
            max-width: 200px;
            word-wrap: break-word;
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; padding: 20px 10px; }
            .sidebar .patient-profile span, .sidebar nav a span, .logout span { display: none; }
            .sidebar nav a i { margin: 0; font-size: 20px; }
            .main-content { margin-left: 70px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="patient-profile">
            <i class="fas fa-user-circle"></i>
            <h3><?php echo htmlspecialchars($patient_info['name']); ?></h3>
            <p>Age: <?php echo $patient_info['age']; ?></p>
        </div>
        <nav>
            <a href="?section=dashboard" class="<?php echo isActive('dashboard'); ?>">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="?section=appointments" class="<?php echo isActive('appointments'); ?>">
                <i class="fas fa-calendar-check"></i> <span>My Appointments</span>
            </a>
            <a href="?section=book" class="<?php echo isActive('book'); ?>">
                <i class="fas fa-plus-circle"></i> <span>Book Appointment</span>
            </a>
            <a href="?section=records" class="<?php echo isActive('records'); ?>">
                <i class="fas fa-notes-medical"></i> <span>Health Records</span>
            </a>
        </nav>
        <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($patient_info['name']); ?>!</h1>
            <div><i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y'); ?></div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard-section" style="display: <?php echo $current_section === 'dashboard' ? 'block' : 'none'; ?>">
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $appointment_count; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $pending; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $confirmed; ?></h3>
                    <p>Confirmed</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file-medical"></i>
                    <h3><?php echo count($health_records); ?></h3>
                    <p>Health Records</p>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-bell"></i> Recent Appointments</h3>
                <?php if($appointment_count > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Doctor</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach(array_slice($appointments, 0, 5) as $apt): ?>
                            <tr>
                                <td><?php echo $apt['date']; ?></td>
                                <td><?php echo date('h:i A', strtotime($apt['time'])); ?> </i>
                                <td><?php echo htmlspecialchars($apt['doctor_name']); ?> </i>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?> </i>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span> </i>
                                <td><span class="note-text"><?php echo htmlspecialchars(substr($apt['reason'] ?? '', 0, 50)); ?></span> </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align:center; color:#999; padding:20px;">No appointments yet. Book your first appointment below!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments Section -->
        <div id="appointments-section" style="display: <?php echo $current_section === 'appointments' ? 'block' : 'none'; ?>">
            <div class="card">
                <h3><i class="fas fa-list"></i> All My Appointments</h3>
                <?php if($appointment_count > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Doctor</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Reason/Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($appointments as $apt): ?>
                            <tr>
                                <td><?php echo $apt['date']; ?> </i>
                                <td><?php echo date('h:i A', strtotime($apt['time'])); ?> </i>
                                <td><?php echo htmlspecialchars($apt['doctor_name']); ?> </i>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?> </i>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span> </i>
                                <td><?php echo nl2br(htmlspecialchars($apt['reason'] ?? '')); ?> </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align:center; color:#999; padding:20px;">No appointments found. Click "Book Appointment" to schedule one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Book Appointment Section -->
        <div id="book-section" style="display: <?php echo $current_section === 'book' ? 'block' : 'none'; ?>">
            <div class="card">
                <h3><i class="fas fa-calendar-plus"></i> Book New Appointment</h3>
                <?php if($book_success): ?>
                    <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $book_success; ?></div>
                <?php endif; ?>
                <?php if($book_error): ?>
                    <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $book_error; ?></div>
                <?php endif; ?>
                
                <form method="POST" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label><i class="fas fa-stethoscope"></i> Select Service</label>
                        <select name="service_id" id="service_id" required>
                            <option value="">-- Select Service --</option>
                            <option value="1">General Checkup (Dr. Santos)</option>
                            <option value="2">Prenatal (Dr. Reyes)</option>
                            <option value="3">Pediatric (Dr. Cruz)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> Preferred Date</label>
                        <input type="date" name="date" id="apptDate" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Preferred Time</label>
                        <select name="time" required>
                            <option value="">-- Select Time --</option>
                            <option value="08:00:00">8:00 AM</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="13:00:00">1:00 PM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Reason for Appointment</label>
                        <textarea name="reason" rows="3" placeholder="Describe your symptoms or reason for visit..." required></textarea>
                    </div>
                    <input type="hidden" name="book_appointment" value="1">
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Submit Appointment Request</button>
                </form>
            </div>
        </div>

        <!-- Health Records Section -->
        <div id="records-section" style="display: <?php echo $current_section === 'records' ? 'block' : 'none'; ?>">
            <div class="card">
                <h3><i class="fas fa-file-alt"></i> My Health Records</h3>
                <?php if(count($health_records) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Date</th><th>Details</th></tr></thead>
                        <tbody>
                            <?php foreach($health_records as $record): ?>
                            <tr>
                                <td><?php echo $record['date']; ?> </i>
                                <td><?php echo nl2br(htmlspecialchars($record['details'])); ?> </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align:center; color:#999; padding:20px;">No health records available yet. Records will appear after completed appointments.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Set minimum date to today
        document.getElementById('apptDate')?.setAttribute('min', new Date().toISOString().split('T')[0]);

        function validateForm() {
            var service = document.getElementById('service_id').value;
            if (!service) {
                alert('Please select a service');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>