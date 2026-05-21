<?php
// admin/dashboard.php - UPDATED with Professional LGU Report
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header('Location: ../login.php');
    exit();
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ========== ADD PATIENT ==========
    if (isset($_POST['add_patient'])) {
        $name = trim($_POST['name']);
        $age = intval($_POST['age']);
        $address = trim($_POST['address']);
        $contact_no = trim($_POST['contact_no']);
        
        if (empty($name)) {
            $error = "❌ Patient name is required!";
        } elseif (empty($age) || $age <= 0 || $age > 150) {
            $error = "❌ Please enter a valid age (1-150)!";
        } elseif (empty($address)) {
            $error = "❌ Address is required!";
        } elseif (empty($contact_no)) {
            $error = "❌ Contact number is required!";
        } else {
            try {
                $check_name = $pdo->prepare("SELECT id, name FROM patients WHERE name = ?");
                $check_name->execute([$name]);
                $name_exists = $check_name->fetch();
                
                $check_contact = $pdo->prepare("SELECT id, name FROM patients WHERE contact_no = ?");
                $check_contact->execute([$contact_no]);
                $contact_exists = $check_contact->fetch();
                
                $check_exact = $pdo->prepare("SELECT id FROM patients WHERE name = ? AND age = ? AND address = ? AND contact_no = ?");
                $check_exact->execute([$name, $age, $address, $contact_no]);
                $exact_exists = $check_exact->fetch();
                
                if ($exact_exists) {
                    $error = "❌ Duplicate Record! This exact patient already exists in the database.";
                } elseif ($name_exists && $contact_exists) {
                    $error = "❌ Duplicate Entry! Both the name and contact number already exist.";
                } elseif ($name_exists) {
                    $error = "❌ Duplicate Name! Patient '".htmlspecialchars($name)."' already exists.";
                } elseif ($contact_exists) {
                    $error = "❌ Duplicate Contact Number! Already registered to: " . htmlspecialchars($contact_exists['name']);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO patients (name, age, address, contact_no) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $age, $address, $contact_no]);
                    $success = "✅ Patient added successfully!";
                }
            } catch (PDOException $e) {
                $error = "❌ Database error: " . $e->getMessage();
            }
        }
    }
    
    // ========== UPDATE PATIENT ==========
    elseif (isset($_POST['update_patient'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $age = intval($_POST['age']);
        $address = trim($_POST['address']);
        $contact_no = trim($_POST['contact_no']);
        
        if (empty($name)) {
            $error = "❌ Patient name is required!";
        } elseif (empty($age) || $age <= 0 || $age > 150) {
            $error = "❌ Please enter a valid age (1-150)!";
        } elseif (empty($address)) {
            $error = "❌ Address is required!";
        } elseif (empty($contact_no)) {
            $error = "❌ Contact number is required!";
        } else {
            try {
                $check_name = $pdo->prepare("SELECT id FROM patients WHERE name = ? AND id != ?");
                $check_name->execute([$name, $id]);
                $name_exists = $check_name->fetch();
                
                $check_contact = $pdo->prepare("SELECT id, name FROM patients WHERE contact_no = ? AND id != ?");
                $check_contact->execute([$contact_no, $id]);
                $contact_exists = $check_contact->fetch();
                
                if ($name_exists && $contact_exists) {
                    $error = "❌ Duplicate Entry! Both name and contact number belong to other patients.";
                } elseif ($name_exists) {
                    $error = "❌ Duplicate Name! Another patient already has this name.";
                } elseif ($contact_exists) {
                    $error = "❌ Duplicate Contact Number! Already registered to another patient.";
                } else {
                    $stmt = $pdo->prepare("UPDATE patients SET name=?, age=?, address=?, contact_no=? WHERE id=?");
                    $stmt->execute([$name, $age, $address, $contact_no, $id]);
                    $success = "✅ Patient updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "❌ Update failed: " . $e->getMessage();
            }
        }
    }
    
    // ========== DELETE PATIENT ==========
    elseif (isset($_POST['delete_patient'])) {
        $id = intval($_POST['id']);
        
        try {
            $check_appointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
            $check_appointments->execute([$id]);
            $appointment_count = $check_appointments->fetchColumn();
            
            if ($appointment_count > 0) {
                $error = "❌ Cannot delete patient! This patient has " . $appointment_count . " appointment record(s).";
            } else {
                $stmt = $pdo->prepare("DELETE FROM patients WHERE id=?");
                $stmt->execute([$id]);
                $success = "✅ Patient deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "❌ Delete failed: " . $e->getMessage();
        }
    }
    
    // ========== UPDATE APPOINTMENT STATUS ==========
    elseif (isset($_POST['update_appointment_status'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $status = $_POST['status'];
        
        $valid_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
        if (!in_array($status, $valid_statuses)) {
            $error = "❌ Invalid status value!";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE appointments SET status=? WHERE id=?");
                $stmt->execute([$status, $appointment_id]);
                $success = "✅ Appointment status updated to '{$status}'!";
            } catch (PDOException $e) {
                $error = "❌ Status update failed: " . $e->getMessage();
            }
        }
    }
}

// ========== ALL DYNAMIC COUNTS ==========
$patients = $pdo->query("SELECT * FROM patients ORDER BY id DESC")->fetchAll();

$appointments = $pdo->query("
    SELECT a.*, p.name as patient_name, p.age as patient_age, p.address, p.contact_no,
           d.name as doctor_name, d.specialization, s.name as service_name 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN services s ON a.service_id = s.id 
    ORDER BY a.date DESC, a.time DESC
")->fetchAll();

$total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$completed_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Completed'")->fetchColumn();
$pending_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();
$cancelled_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled'")->fetchColumn();
$confirmed_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Confirmed'")->fetchColumn();

// ========== SERVICE REPORT WITH AGE LIST (NO AVERAGE, NO RANGE) ==========
$service_report = $pdo->query("
    SELECT 
        s.name as service_name, 
        COUNT(a.id) as total_appointments,
        COUNT(CASE WHEN a.status = 'Completed' THEN 1 END) as completed,
        COUNT(CASE WHEN a.status = 'Cancelled' THEN 1 END) as cancelled,
        COUNT(CASE WHEN a.status = 'Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN a.status = 'Confirmed' THEN 1 END) as confirmed,
        GROUP_CONCAT(DISTINCT p.age ORDER BY p.age SEPARATOR ', ') as patient_ages,
        COUNT(CASE WHEN p.age <= 12 THEN 1 END) as children,
        COUNT(CASE WHEN p.age BETWEEN 13 AND 35 THEN 1 END) as youth,
        COUNT(CASE WHEN p.age BETWEEN 36 AND 59 THEN 1 END) as adult,
        COUNT(CASE WHEN p.age >= 60 THEN 1 END) as senior
    FROM services s
    LEFT JOIN appointments a ON s.id = a.service_id
    LEFT JOIN patients p ON a.patient_id = p.id
    GROUP BY s.id
    ORDER BY total_appointments DESC
")->fetchAll();

// ========== DOCTOR SERVICE BREAKDOWN ==========
$doctor_service_report = $pdo->query("
    SELECT 
        d.id as doctor_id,
        d.name as doctor_name,
        d.specialization,
        s.name as service_name,
        COUNT(a.id) as total_appointments,
        COUNT(CASE WHEN a.status = 'Completed' THEN 1 END) as completed
    FROM doctors d
    JOIN services s ON (d.id = s.id)
    LEFT JOIN appointments a ON a.doctor_id = d.id AND a.service_id = s.id
    GROUP BY d.id, s.id
    ORDER BY d.id, total_appointments DESC
")->fetchAll();

$doctors = $pdo->query("SELECT d.*, s.name as service_name FROM doctors d JOIN services s ON d.id = s.id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Barangay Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            z-index: 100;
        }
        .sidebar h2 { margin-bottom: 30px; font-size: 24px; }
        .sidebar h2 i { margin-right: 10px; }
        .sidebar nav a {
            display: block;
            padding: 12px 20px;
            color: #ddd;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .sidebar nav a:hover, .sidebar nav a.active {
            background: #667eea;
            color: white;
        }
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
        }
        .logout:hover { background: #c82333; }
        .main-content {
            margin-left: 280px;
            padding: 30px;
        }
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .stat-card i { font-size: 40px; color: #667eea; margin-bottom: 10px; }
        .stat-card h3 { font-size: 32px; margin: 10px 0; }
        .card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 { margin-bottom: 20px; font-size: 20px; border-left: 4px solid #667eea; padding-left: 15px; }
        table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-print { background: #6c757d; color: white; }
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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
        .modal-content input, .modal-content select, .modal-content textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .search-box { margin-bottom: 20px; }
        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .chart-container { max-width: 100%; margin: 20px 0; }
        canvas { max-height: 400px; }
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .filter-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .service-summary { overflow-x: auto; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            margin: 2px;
        }
        .badge-child { background: #17a2b8; color: white; }
        .badge-youth { background: #28a745; color: white; }
        .badge-adult { background: #ffc107; color: #333; }
        .badge-senior { background: #dc3545; color: white; }
        .ages-list {
            font-size: 11px;
            color: #666;
            max-width: 150px;
            word-wrap: break-word;
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; padding: 20px 10px; }
            .sidebar h2, .sidebar nav a span { display: none; }
            .sidebar nav a i { margin: 0; font-size: 20px; }
            .main-content { margin-left: 70px; }
            .logout span { display: none; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
        /* Print styles - only for LGU Report */
        @media print {
            body * {
                visibility: hidden;
            }
            #lgu-reports-section, #lgu-reports-section * {
                visibility: visible;
            }
            #lgu-reports-section {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                margin: 0;
                padding: 20px;
            }
            .sidebar, .header, .btn, .logout, .stats:first-child {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-clinic-medical"></i> BRGY<span style="color:#667eea">Clinic</span></h2>
        <nav>
            <a href="#" class="active" onclick="showSection('overview')"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="#" onclick="showSection('patients')"><i class="fas fa-users"></i> <span>Patients</span></a>
            <a href="#" onclick="showSection('appointments')"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a>
            <a href="#" onclick="showSection('lgu-reports')"><i class="fas fa-chart-line"></i> <span>LGU Report</span></a>
            <a href="#" onclick="showSection('doctors')"><i class="fas fa-user-md"></i> <span>Doctors</span></a>
        </nav>
        <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
                <p style="color: #666; margin-top: 5px;"><?php echo $_SESSION['role'] === 'admin' ? 'Administrator' : 'Barangay Staff'; ?> Dashboard</p>
            </div>
            <div>
                <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y'); ?>
            </div>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Overview Section -->
        <div id="overview-section">
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Total Registered Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $completed_count; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-ban"></i>
                    <h3><?php echo $cancelled_count; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-chart-line"></i> Recent Appointments</h3>
                <div class="search-box">
                    <input type="text" id="recentSearch" placeholder="Search recent appointments..." onkeyup="filterTable('recentTable', this.value)">
                </div>
                <div style="overflow-x: auto;">
                    <table id="recentTable">
                        <thead>
                            <tr><th>Patient</th><th>Age</th><th>Doctor</th><th>Service</th><th>Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach(array_slice($appointments, 0, 10) as $apt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo $apt['patient_age']; ?></td>
                                <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                <td><?php echo $apt['date']; ?> <?php echo date('h:i A', strtotime($apt['time'])); ?></td>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span></td>
                                <td>
                                    <select onchange="updateStatus(<?php echo $apt['id']; ?>, this.value)">
                                        <option value="">Change</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Confirmed">Confirm</option>
                                        <option value="Completed">Complete</option>
                                        <option value="Cancelled">Cancel</option>
                                    </select>
                                 </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Patients Section -->
        <div id="patients-section" style="display:none;">
            <div class="card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <h3><i class="fas fa-users"></i> Patient Management</h3>
                    <button class="btn btn-primary" onclick="openModal('addPatientModal')"><i class="fas fa-plus"></i> Add Patient</button>
                </div>
                <div class="search-box">
                    <input type="text" id="patientSearch" placeholder="Search patients by name, address, or contact..." onkeyup="filterTable('patientTable', this.value)">
                </div>
                <div style="overflow-x: auto;">
                    <table id="patientTable">
                        <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Address</th><th>Contact</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($patients as $p): ?>
                            <tr>
                                <td><?php echo $p['id']; ?></td>
                                <td><?php echo htmlspecialchars($p['name']); ?></i>
                                <td><?php echo $p['age']; ?></td>
                                <td><?php echo htmlspecialchars($p['address']); ?></i>
                                <td><?php echo $p['contact_no']; ?></i>
                                <td>
                                    <button class="btn btn-primary" onclick='editPatient(<?php echo $p['id']; ?>, "<?php echo addslashes($p['name']); ?>", <?php echo $p['age']; ?>, "<?php echo addslashes($p['address']); ?>", "<?php echo $p['contact_no']; ?>")'><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger" onclick="deletePatient(<?php echo $p['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Appointments Section -->
        <div id="appointments-section" style="display:none;">
            <div class="card">
                <h3><i class="fas fa-calendar-check"></i> All Appointments</h3>
                <div class="filter-group">
                    <select id="statusFilter" onchange="filterAppointments()">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <input type="date" id="dateFilter" onchange="filterAppointments()" placeholder="Filter by date">
                    <input type="text" id="apptSearch" placeholder="Search by patient or doctor..." onkeyup="filterAppointments()">
                </div>
                <div style="overflow-x: auto;">
                    <table id="apptTable">
                        <thead>
                            <tr><th>Patient</th><th>Age</th><th>Doctor</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($appointments as $apt): ?>
                            <tr data-status="<?php echo $apt['status']; ?>" data-date="<?php echo $apt['date']; ?>">
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo $apt['patient_age']; ?></td>
                                <td><?php echo htmlspecialchars($apt['doctor_name']); ?></i>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?></i>
                                <td><?php echo $apt['date']; ?></i>
                                <td><?php echo date('h:i A', strtotime($apt['time'])); ?></i>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span></i>
                                <td>
                                    <select onchange="updateStatus(<?php echo $apt['id']; ?>, this.value)">
                                        <option value="">Change</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Confirmed">Confirm</option>
                                        <option value="Completed">Complete</option>
                                        <option value="Cancelled">Cancel</option>
                                    </select>
                                 </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- LGU REPORT SECTION - Professional Version -->
        <div id="lgu-reports-section" style="display:none;">
            <div class="report-header">
                <h2><i class="fas fa-chart-bar"></i> Barangay Health Center - LGU Report</h2>
                <p>Year: <?php echo date('Y'); ?> | <?php echo date('F d, Y h:i A'); ?></p>
                <p style="font-size: 14px; margin-top: 10px;">Official Report for Local Government Unit (LGU)</p>
            </div>

            <!-- EXCLUSIVE SUMMARY CARDS -->
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Registered Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $completed_count; ?></h3>
                    <p>Completed Consultations</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percent"></i>
                    <h3><?php echo round(($completed_count / max(1, $total_appointments)) * 100, 1); ?>%</h3>
                    <p>Success Rate</p>
                </div>
            </div>

            <!-- SERVICE UTILIZATION WITH AGE BREAKDOWN -->
            <div class="card">
                <h3><i class="fas fa-stethoscope"></i> Service Utilization Report</h3>
                <div class="chart-container">
                    <canvas id="serviceChart"></canvas>
                </div>
                
                <div class="service-summary">
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Cancelled</th>
                                <th>Pending</th>
                                <th>Confirmed</th>
                                <th>Patient Ages</th>
                                <th>Age Breakdown</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($service_report as $s): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['service_name']); ?></strong></td>
                                <td><?php echo $s['total_appointments']; ?></td>
                                <td><?php echo $s['completed']; ?></td>
                                <td><?php echo $s['cancelled']; ?></td>
                                <td><?php echo $s['pending']; ?></i>
                                <td><?php echo $s['confirmed']; ?></i>
                                <td><span class="ages-list"><?php echo $s['patient_ages'] ?: 'No data'; ?></span></i>
                                <td>
                                    <span class="badge badge-child">Child: <?php echo $s['children']; ?></span>
                                    <span class="badge badge-youth">Youth: <?php echo $s['youth']; ?></span>
                                    <span class="badge badge-adult">Adult: <?php echo $s['adult']; ?></span>
                                    <span class="badge badge-senior">Senior: <?php echo $s['senior']; ?></span>
                                 </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <th>Total</th>
                                <th><?php echo $total_appointments; ?></th>
                                <th><?php echo $completed_count; ?></th>
                                <th><?php echo $cancelled_count; ?></th>
                                <th><?php echo $pending_count; ?></th>
                                <th><?php echo $confirmed_count; ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- DOCTOR SERVICE BREAKDOWN -->
            <div class="card">
                <h3><i class="fas fa-user-md"></i> Doctor Service Summary</h3>
                <table>
                    <thead>
                        <tr><th>Doctor</th><th>Specialization</th><th>Service</th><th>Total Appointments</th><th>Completed</th><th>Success Rate</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($doctor_service_report as $ds):
                            $success_rate = round(($ds['completed'] / max(1, $ds['total_appointments'])) * 100, 1);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ds['doctor_name']); ?></i>
                            <td><?php echo htmlspecialchars($ds['specialization']); ?></i>
                            <td><?php echo htmlspecialchars($ds['service_name']); ?></i>
                            <td><?php echo $ds['total_appointments']; ?></i>
                            <td><?php echo $ds['completed']; ?></i>
                            <td><?php echo $success_rate; ?>%</i>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Print/Export Buttons - Only in LGU Report -->
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <button class="btn btn-primary" onclick="exportToCSV()"><i class="fas fa-file-excel"></i> Export to CSV</button>
                <button class="btn btn-info" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
            </div>
        </div>

        <!-- Doctors Section -->
        <div id="doctors-section" style="display:none;">
            <div class="card">
                <h3><i class="fas fa-user-md"></i> Doctors & Services</h3>
                <table>
                    <thead><tr><th>Doctor Name</th><th>Specialization</th><th>Service</th><th>Schedule</th> </thead>
                    <tbody>
                        <tr><td>Dr. Santos</i><td>General Practitioner</i><td>General Checkup</i><td>Mon-Fri, 9AM-5PM</i></tr>
                        <tr><td>Dr. Reyes</i><td>Obstetrician</i><td>Prenatal</i><td>Tue-Thu, 10AM-4PM</i></tr>
                        <tr><td>Dr. Cruz</i><td>Pediatrician</i><td>Pediatric</i><td>Mon-Wed, 8AM-3PM</i></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <h3>Add New Patient</h3>
            <form method="POST">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="number" name="age" placeholder="Age" required>
                <input type="text" name="address" placeholder="Address" required>
                <input type="text" name="contact_no" placeholder="Contact Number" required>
                <input type="hidden" name="add_patient" value="1">
                <button type="submit" class="btn btn-primary">Add Patient</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addPatientModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editPatientModal" class="modal">
        <div class="modal-content">
            <h3>Edit Patient</h3>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <input type="text" name="name" id="edit_name" placeholder="Full Name" required>
                <input type="number" name="age" id="edit_age" placeholder="Age" required>
                <input type="text" name="address" id="edit_address" placeholder="Address" required>
                <input type="text" name="contact_no" id="edit_contact" placeholder="Contact Number" required>
                <input type="hidden" name="update_patient" value="1">
                <button type="submit" class="btn btn-primary">Update Patient</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('editPatientModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Service Chart - Bar Graph
        const serviceCtx = document.getElementById('serviceChart')?.getContext('2d');
        if(serviceCtx) {
            new Chart(serviceCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($service_report, 'service_name')); ?>,
                    datasets: [
                        {
                            label: 'Total Appointments',
                            data: <?php echo json_encode(array_column($service_report, 'total_appointments')); ?>,
                            backgroundColor: '#667eea',
                            borderRadius: 8
                        },
                        {
                            label: 'Completed',
                            data: <?php echo json_encode(array_column($service_report, 'completed')); ?>,
                            backgroundColor: '#28a745',
                            borderRadius: 8
                        },
                        {
                            label: 'Cancelled',
                            data: <?php echo json_encode(array_column($service_report, 'cancelled')); ?>,
                            backgroundColor: '#dc3545',
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Number of Appointments' } },
                        x: { title: { display: true, text: 'Services' } }
                    }
                }
            });
        }

        function showSection(section) {
            document.getElementById('overview-section').style.display = 'none';
            document.getElementById('patients-section').style.display = 'none';
            document.getElementById('appointments-section').style.display = 'none';
            document.getElementById('lgu-reports-section').style.display = 'none';
            document.getElementById('doctors-section').style.display = 'none';
            document.getElementById(section + '-section').style.display = 'block';
            
            let links = document.querySelectorAll('.sidebar nav a');
            links.forEach(a => a.classList.remove('active'));
            if(event.target.closest('a')) {
                event.target.closest('a').classList.add('active');
            }
        }

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function editPatient(id, name, age, address, contact) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_age').value = age;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_contact').value = contact;
            openModal('editPatientModal');
        }

        function deletePatient(id) {
            if(confirm('Are you sure you want to delete this patient?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="delete_patient" value="1"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateStatus(appointmentId, status) {
            if(status) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="update_appointment_status" value="1"><input type="hidden" name="appointment_id" value="' + appointmentId + '"><input type="hidden" name="status" value="' + status + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterTable(tableId, searchTerm) {
            var table = document.getElementById(tableId);
            var rows = table.getElementsByTagName('tr');
            searchTerm = searchTerm.toLowerCase();
            for(var i = 1; i < rows.length; i++) {
                var cells = rows[i].getElementsByTagName('td');
                var match = false;
                for(var j = 0; j < cells.length; j++) {
                    if(cells[j] && cells[j].innerText.toLowerCase().indexOf(searchTerm) > -1) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

        function filterAppointments() {
            var status = document.getElementById('statusFilter').value;
            var date = document.getElementById('dateFilter').value;
            var search = document.getElementById('apptSearch').value.toLowerCase();
            var rows = document.querySelectorAll('#apptTable tbody tr');
            
            rows.forEach(row => {
                var rowStatus = row.getAttribute('data-status');
                var rowDate = row.getAttribute('data-date');
                var rowText = row.innerText.toLowerCase();
                var show = true;
                
                if(status && rowStatus !== status) show = false;
                if(date && rowDate !== date) show = false;
                if(search && !rowText.includes(search)) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }

        function exportToCSV() {
            var csv = [];
            var headers = ['Service', 'Total', 'Completed', 'Cancelled', 'Pending', 'Confirmed', 'Patient Ages', 'Children', 'Youth', 'Adult', 'Senior'];
            csv.push(headers.join(','));
            
            <?php foreach($service_report as $s): ?>
                var row = [
                    "<?php echo addslashes($s['service_name']); ?>",
                    "<?php echo $s['total_appointments']; ?>",
                    "<?php echo $s['completed']; ?>",
                    "<?php echo $s['cancelled']; ?>",
                    "<?php echo $s['pending']; ?>",
                    "<?php echo $s['confirmed']; ?>",
                    "<?php echo addslashes($s['patient_ages'] ?: 'No data'); ?>",
                    "<?php echo $s['children']; ?>",
                    "<?php echo $s['youth']; ?>",
                    "<?php echo $s['adult']; ?>",
                    "<?php echo $s['senior']; ?>"
                ];
                csv.push(row.join(','));
            <?php endforeach; ?>
            
            var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'lgu_report_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
        }
    </script>
</body>
</html>