<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in as doctor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'];

// Get current section from URL parameter
$current_section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// Get doctor's appointments
$stmt = $pdo->prepare("
    SELECT a.*, p.name as patient_name, p.age, p.contact_no, 
           s.name as service_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN services s ON a.service_id = s.id
    WHERE a.doctor_id = ?
    ORDER BY a.date DESC, a.time DESC
");
$stmt->execute([$doctor_id]);
$appointments = $stmt->fetchAll();

// Count appointments by status
$total = count($appointments);
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

// Handle adding medical notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $stmt = $pdo->prepare("UPDATE appointments SET reason = CONCAT(IFNULL(reason,''), '\n[Doctor Note: ', ?, ']') WHERE id = ?");
    $stmt->execute([$_POST['note'], $_POST['appointment_id']]);
    header('Location: dashboard.php?section=appointments');
    exit();
}

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
    <title>Doctor Dashboard - Barangay Clinic</title>
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
        }
        .doctor-profile { text-align: center; margin-bottom: 30px; }
        .doctor-profile i { font-size: 60px; margin-bottom: 10px; }
        .doctor-profile h3 { font-size: 18px; }
        .doctor-profile p { font-size: 14px; opacity: 0.8; }
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
        .sidebar nav a i { margin-right: 12px; }
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
        }
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
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-Pending { background: #ffc107; color: #333; }
        .status-Confirmed { background: #28a745; color: white; }
        .status-Completed { background: #17a2b8; color: white; }
        .status-Cancelled { background: #dc3545; color: white; }
        .btn-primary {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 500px;
            max-width: 90%;
        }
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .note-text {
            font-size: 12px;
            color: #666;
            max-width: 200px;
            word-wrap: break-word;
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .doctor-profile span, .sidebar nav a span { display: none; }
            .main-content { margin-left: 70px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="doctor-profile">
            <i class="fas fa-user-md"></i>
            <h3><?php echo htmlspecialchars($doctor_name); ?></h3>
            <p>Doctor</p>
        </div>
        <nav>
            <a href="?section=dashboard" class="<?php echo isActive('dashboard'); ?>">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="?section=appointments" class="<?php echo isActive('appointments'); ?>">
                <i class="fas fa-calendar-check"></i> <span>Appointments</span>
            </a>
            <a href="?section=schedule" class="<?php echo isActive('schedule'); ?>">
                <i class="fas fa-clock"></i> <span>My Schedule</span>
            </a>
        </nav>
        <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Welcome,<?php echo htmlspecialchars($doctor_name); ?>!</h1>
            <div><i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y'); ?></div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard-section" style="display: <?php echo $current_section === 'dashboard' ? 'block' : 'none'; ?>">
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $total; ?></h3>
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
                    <i class="fas fa-user-check"></i>
                    <h3><?php echo $completed; ?></h3>
                    <p>Completed</p>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-bell"></i> Today's Schedule</h3>
                <?php
                $today = date('Y-m-d');
                $today_appointments = array_filter($appointments, function($a) use ($today) {
                    return $a['date'] === $today;
                });
                ?>
                <?php if(count($today_appointments) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach($today_appointments as $apt): ?>
                            <tr>
                                <td><?php echo date('h:i A', strtotime($apt['time'])); ?></td>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?> (<?php echo $apt['age']; ?> yrs)</td>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span></td>
                                <td>
                                    <?php if($apt['status'] === 'Confirmed'): ?>
                                    <button class="btn-primary" onclick="openNoteModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['patient_name']); ?>')">Add Note</button>
                                    <?php else: ?>
                                    <span class="btn-secondary" style="padding:6px 12px; font-size:11px;">No Action</span>
                                    <?php endif; ?>
                                 </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align:center; color:#999; padding:20px;">No appointments scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments Section -->
        <div id="appointments-section" style="display: <?php echo $current_section === 'appointments' ? 'block' : 'none'; ?>">
            <div class="card">
                <h3><i class="fas fa-list"></i> All Appointments</h3>
                <?php if(count($appointments) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Patient</th><th>Age</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Notes</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($appointments as $apt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?> </i>
                                <td><?php echo $apt['age']; ?> </i>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?> </i>
                                <td><?php echo $apt['date']; ?> </i>
                                <td><?php echo date('h:i A', strtotime($apt['time'])); ?> </i>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span> </i>
                                <td class="note-text"><?php echo htmlspecialchars(substr($apt['reason'] ?? '', 0, 60)); ?></i>
                                <td>
                                    <?php if($apt['status'] === 'Confirmed'): ?>
                                    <button class="btn-primary" onclick="openNoteModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['patient_name']); ?>')">Add Note</button>
                                    <?php else: ?>
                                    <span class="btn-secondary" style="padding:6px 12px; font-size:11px;">No Action</span>
                                    <?php endif; ?>
                                 </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="text-align:center; color:#999; padding:20px;">No appointments found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Schedule Section -->
        <div id="schedule-section" style="display: <?php echo $current_section === 'schedule' ? 'block' : 'none'; ?>">
            <div class="card">
                <h3><i class="fas fa-clock"></i> My Working Schedule</h3>
                <?php
                $schedule_info = [
                    'Dr. Santos' => ['days' => 'Monday - Friday', 'hours' => '9:00 AM - 4:00 PM'],
                    'Dr. Reyes' => ['days' => 'Tuesday - Thursday', 'hours' => '10:00 AM - 4:00 PM'],
                    'Dr. Cruz' => ['days' => 'Monday - Wednesday', 'hours' => '8:00 AM - 3:00 PM']
                ];
                $my_schedule = $schedule_info[$doctor_name] ?? ['days' => 'Contact admin', 'hours' => 'Contact admin'];
                ?>
                <div style="background: #e8f0fe; padding: 20px; border-radius: 15px;">
                    <p style="margin-bottom: 10px;"><strong><i class="fas fa-calendar-week"></i> Working Days:</strong> <?php echo $my_schedule['days']; ?></p>
                    <p><strong><i class="fas fa-clock"></i> Working Hours:</strong> <?php echo $my_schedule['hours']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-notes-medical"></i> Add Medical Note</h3>
            <p id="patientName"></p>
            <form method="POST">
                <textarea name="note" rows="4" placeholder="Enter medical notes, diagnosis, or prescription..." required></textarea>
                <input type="hidden" name="appointment_id" id="note_appt_id">
                <input type="hidden" name="add_note" value="1">
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn-primary">Save Note</button>
                    <button type="button" class="btn-secondary" style="margin-left:10px;" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('noteModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        function openNoteModal(id, name) {
            document.getElementById('note_appt_id').value = id;
            document.getElementById('patientName').innerHTML = '<strong>Patient:</strong> ' + name;
            document.getElementById('noteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('noteModal').style.display = 'none';
        }
    </script>
</body>
</html>