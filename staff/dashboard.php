<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

// Get all patients
$patients = $pdo->query("SELECT * FROM patients ORDER BY id DESC")->fetchAll();

// Get statistics for dashboard
$total_patients = count($patients);
$seniors = count(array_filter($patients, fn($p) => $p['age'] >= 60));
$children = count(array_filter($patients, fn($p) => $p['age'] <= 12));
$adults = $total_patients - $seniors - $children;

// Get appointment stats
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$completed_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Completed'")->fetchColumn();
$pending_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();
$cancelled_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled'")->fetchColumn();
$confirmed_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Confirmed'")->fetchColumn();

// ========== SERVICE REPORT WITH AGE BREAKDOWN (Matches Admin) ==========
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            position: fixed;
            height: 100%;
            padding: 30px 20px;
            overflow-y: auto;
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
        
        /* Main Content */
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
        .card h3 {
            margin-bottom: 20px;
            font-size: 20px;
            border-left: 4px solid #667eea;
            padding-left: 15px;
        }
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
        th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            margin: 2px;
        }
        .badge-senior { background: #dc3545; color: white; }
        .badge-child { background: #17a2b8; color: white; }
        .badge-adult { background: #28a745; color: white; }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .ages-list {
            font-size: 11px;
            color: #666;
            max-width: 150px;
            word-wrap: break-word;
        }
        .service-summary { overflow-x: auto; }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
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
            .sidebar, .header, .btn, .logout, .action-buttons {
                display: none !important;
            }
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
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-clinic-medical"></i> <span>Brgy Clinic</span></h2>
        <nav>
            <a href="#" class="active" onclick="showSection('dashboard')">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="#" onclick="showSection('lgu-reports')">
                <i class="fas fa-chart-line"></i> <span>LGU Report</span>
            </a>
        </nav>
        <a href="../logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
                <p style="color: #666; margin-top: 5px;">Barangay Health Staff - Resident Management & LGU Reports</p>
            </div>
            <div>
                <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y'); ?>
            </div>
        </div>

        <!-- DASHBOARD SECTION (Combined: Stats + All Patients) -->
        <div id="dashboard-section">
            <!-- Statistics Cards -->
            <div class="stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Total Residents</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-graduate"></i>
                    <h3><?php echo $seniors; ?></h3>
                    <p>Senior Citizens (60+)</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-child"></i>
                    <h3><?php echo $children; ?></h3>
                    <p>Children (≤12)</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $completed_appointments; ?></h3>
                    <p>Completed Consults</p>
                </div>
            </div>

            <!-- Complete Patients List -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <h3><i class="fas fa-users"></i> Complete Resident Directory</h3>
                </div>
                <div class="search-box">
                    <input type="text" id="patientSearch" placeholder="Search by name, address, or contact number..." onkeyup="filterTable('patientTable', this.value)">
                </div>
                <div style="overflow-x: auto;">
                    <table id="patientTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Contact Number</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($patients as $p): ?>
                            <tr>
                                <td><?php echo $p['id']; ?></td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo $p['age']; ?></td>
                                <td><?php echo htmlspecialchars($p['address']); ?></td>
                                <td><?php echo $p['contact_no']; ?></td>
                                <td>
                                    <?php 
                                    if($p['age'] >= 60) echo '<span class="badge badge-senior">Senior</span>';
                                    elseif($p['age'] <= 12) echo '<span class="badge badge-child">Child</span>';
                                    else echo '<span class="badge badge-adult">Adult</span>';
                                    ?>
                                 </i>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top: 15px; font-style: italic; color: #666;">
                    Total Residents: <?php echo $total_patients; ?> | 
                    Seniors: <?php echo $seniors; ?> | 
                    Children: <?php echo $children; ?>
                </p>
            </div>
        </div>

        <!-- LGU REPORT SECTION (Matches Admin Version) -->
        <div id="lgu-reports-section" style="display:none;">
            <div class="report-header">
                <h2><i class="fas fa-chart-bar"></i> Barangay Health Center - LGU Report</h2>
                <p>Year: <?php echo date('Y'); ?> |<?php echo date('F d, Y h:i A'); ?></p>
                <p style="font-size: 14px; margin-top: 10px;">Official Report for Local Government Unit (LGU)</p>
                <p>Prepared by: <?php echo htmlspecialchars($_SESSION['name']); ?> (Barangay Health Staff)</p>
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
                    <h3><?php echo $completed_appointments; ?></h3>
                    <p>Completed Consultations</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percent"></i>
                    <h3><?php echo $total_appointments > 0 ? round($completed_appointments/$total_appointments*100,1) : 0; ?>%</h3>
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
                                <td><?php echo $s['pending']; ?></td>
                                <td><?php echo $s['confirmed']; ?></i>
                                <td><span class="ages-list"><?php echo $s['patient_ages'] ?: 'No data'; ?></span></td>
                                <td>
                                    <span class="badge badge-child">Child: <?php echo $s['children']; ?></span>
                                    <span class="badge badge-youth" style="background:#28a745;">Youth: <?php echo $s['youth']; ?></span>
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
                                <th><?php echo $completed_appointments; ?></th>
                                <th><?php echo $cancelled_appointments; ?></th>
                                <th><?php echo $pending_appointments; ?></th>
                                <th><?php echo $confirmed_appointments; ?></th>
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
                            <td><?php echo htmlspecialchars($ds['doctor_name']); ?> </i>
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
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="exportToCSV()">
                    <i class="fas fa-file-excel"></i> Export to CSV
                </button>
                <button class="btn btn-info" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <script>
        // Service Chart - Bar Graph (Matches Admin)
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

        // Navigation Functions
        function showSection(section) {
            document.getElementById('dashboard-section').style.display = 'none';
            document.getElementById('lgu-reports-section').style.display = 'none';
            document.getElementById(section + '-section').style.display = 'block';
            
            document.querySelectorAll('.sidebar nav a').forEach(a => a.classList.remove('active'));
            if(event.target.closest('a')) {
                event.target.closest('a').classList.add('active');
            }
        }

        // Filter Function
        function filterTable(tableId, searchTerm) {
            var table = document.getElementById(tableId);
            if(!table) return;
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

        // Export to CSV Function (Matches Admin)
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