<?php
session_start();
require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    
    if ($role === 'admin') {
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['role'] = 'admin';
            $_SESSION['name'] = 'Administrator';
            header('Location: admin/dashboard.php');
            exit();
        } else {
            $error = 'Invalid admin credentials';
        }
    } elseif ($role === 'staff') {
        if ($username === 'staff' && $password === 'staff123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['role'] = 'staff';
            $_SESSION['name'] = 'Barangay Staff';
            header('Location: staff/dashboard.php');
            exit();
        } else {
            $error = 'Invalid staff credentials';
        }
    } elseif ($role === 'doctor') {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE name = ?");
        $stmt->execute([$username]);
        $doctor = $stmt->fetch();
        if ($doctor && $password === 'doctor123') {
            $_SESSION['user_id'] = $doctor['id'];
            $_SESSION['role'] = 'doctor';
            $_SESSION['name'] = $doctor['name'];
            $_SESSION['specialization'] = $doctor['specialization'];
            header('Location: doctor/dashboard.php');
            exit();
        } else {
            $error = 'Invalid doctor credentials';
        }
    } elseif ($role === 'patient') {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE contact_no = ?");
        $stmt->execute([$username]);
        $patient = $stmt->fetch();
        if ($patient && $password === 'patient123') {
            $_SESSION['user_id'] = $patient['id'];
            $_SESSION['role'] = 'patient';
            $_SESSION['name'] = $patient['name'];
            header('Location: patient/dashboard.php');
            exit();
        } else {
            $error = 'Invalid patient credentials';
        }
    } else {
        $error = 'Please select a valid role';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Health Clinic - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #a3bfc3ff;
            position: relative;
            overflow-x: hidden;
        }

        /* Hero Section with Background Image */
        .hero-section {
            position: relative;
            background:             url('photo4.png');
            background-size: cover;
            background-position: center 30%;
            padding: 100px 20px;
            text-align: center;
            color: white;
            overflow: hidden;
            
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
            z-index: 0;
        }

        .hero-section h1, .hero-section p {
            position: relative;
            z-index: 1;
        }

        .hero-section h1 {
            font-size: 56px;
            margin-bottom: 20px;
            animation: fadeInDown 0.8s ease;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-section p {
            font-size: 20px;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Container */
        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Service Cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 35px 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(82, 80, 80, 0.79);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .info-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(54, 53, 53, 1);
        }

        .info-card i {
            font-size: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 20px;
        }

        .info-card h3 {
            font-size: 22px;
            margin-bottom: 12px;
            color: #333;
        }

        .info-card p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }

        .stat-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 12px;
            margin-top: 15px;
        }

        /* Login Box */
        .login-container {
            background: white;
            border-radius: 30px;
            padding: 45px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 28px;
        }

        .login-container h2 i {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        

        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.15); }
            35% { transform: scale(1.1); }
            45% { transform: scale(1.2); }
            55% { transform: scale(1.1); }
            65% { transform: scale(1.15); }
            75% { transform: scale(1); }
        }

        .btn-bp-container {
            text-align: center;
            margin: 1rem 0;
            padding-top: 0.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .error {
            background: #fff5f5;
            color: #c53030;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c53030;
        }

        .info {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }

        .info strong {
            color: #667eea;
        }

        /* Doctors Section with Images */
        .doctor-list {
            background: white;
            border-radius: 30px;
            padding: 50px 40px;
            margin-top: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .doctor-list h3 {
            text-align: center;
            margin-bottom: 40px;
            font-size: 32px;
            color: #333;
        }

        .doctor-list h3 i {
            background: linear-gradient(135deg, #4b66dcff 0%, #6055ffff 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .doctor-item {
            background: #d7d6d6ff;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .doctor-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.15);
        }

        .doctor-img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            object-position: center top;
        }

        .doctor-info {
            padding: 20px;
        }

        .doctor-info h4 {
            font-size: 22px;
            margin-bottom: 5px;
            color: #333;
        }

        .doctor-info .specialty {
            color: #2546ddff;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .doctor-info .desc {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }

        .schedule {
            font-size: 13px;
            color: #999;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #bbb9b9ff;
        }

        .schedule i {
            color: #4360e4ff;
            margin-right: 5px;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            text-align: center;
            padding: 40px;
            margin-top: 60px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 32px; }
            .hero-section p { font-size: 16px; }
            .login-container { margin: 0 20px; padding: 30px; }
            .cards { grid-template-columns: 1fr; }
            .doctor-grid { grid-template-columns: 1fr; }
            .doctor-list { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <!-- Hero Section with Background Image -->
    <div class="hero-section"> 
    <br>
     <br>
      <br>
        <br>
          <br>
            <br>
              <br>
                <br>
                  <br>
                    <br>
                      <br>
    </div>

    <div class="container">
        <!-- Service Cards -->
        <div class="cards">
            <div class="info-card">
                <i class="fas fa-stethoscope"></i>
                <h3>Medical Services</h3>
                <p>General Checkup, Prenatal Care, and Pediatric services available. Affordable healthcare for all residents.</p>
                <span class="stat-badge">24/7 Emergency Ready</span>
            </div>
            <div class="info-card">
                <i class="fas fa-clock"></i>
                <h3>Clinic Hours</h3>
                <p>Monday - Friday: 8:00 AM - 4:00 PM<br>Saturday: 9:00 AM - 12:00 PM<br>Sunday: Closed</p>
                <span class="stat-badge">Walk-ins Welcome</span>
            </div>
            <div class="info-card">
                <i class="fas fa-phone-alt"></i>
                <h3>Contact Us</h3>
                <p>📍 Barangay Hall, Main Street<br>📞 (02) 8123 4567<br>✉️ clinic@barangay.gov.ph</p>
                <span class="stat-badge">Emergency Hotline: 911</span>
            </div>
        </div>

        <!-- Login Form -->
        <div class="login-container">
            <h2><i class="fas fa-lock"></i> Login Portal</h2>
            <?php if($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username / Contact Number</label>
                    <input type="text" name="username" placeholder="Enter username or contact number" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-briefcase"></i> Login As</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin"> Administrator</option>
                        <option value="staff"> Barangay Staff</option>
                        <option value="doctor"> Doctor</option>
                        <option value="patient"> Patient</option>
                    </select>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                </button>
            </form>
            <div class="info">
                <p><strong> Demo Credentials:</strong></p>
                <p> Admin: <strong>admin</strong> / <strong>admin123</strong> | Staff: <strong>staff</strong> / <strong>staff123</strong></p>
                <p> Doctor: <strong>Dr. Santos</strong> / <strong>doctor123</strong> | Patient: <strong>09123456789</strong> / <strong>patient123</strong></p>
            </div>
        </div>

        <!-- Doctors Section with Photos -->
        <div class="doctor-list">
            <h3><i class="fas fa-user-md"></i> Our Medical Team</h3>
            <div class="doctor-grid">
                <!-- Dr. Santos -->
                <div class="doctor-item">
                    <img src="images/doctor1.jpg" alt="Dr. Santos" class="doctor-img" 
                         onerror="this.src='photo1.png'">
                    <div class="doctor-info">
                        <h4>Dr. Santos</h4>
                        <p class="specialty"> General Practitioner</p>
                        <p class="desc">Family Medicine, Primary Care, Preventive Healthcare</p>
                        <div class="schedule">
                            <i class="fas fa-calendar-alt"></i> Mon-Fri, 9:00 AM - 5:00 PM
                        </div>
                    </div>
                </div>

                <!-- Dr. Reyes -->
                <div class="doctor-item">
                    <img src="images/doctor2.jpg" alt="Dr. Reyes" class="doctor-img"
                         onerror="this.src='photo2.png'">
                    <div class="doctor-info">
                        <h4>Dr. Reyes</h4>
                        <p class="specialty"> Obstetrician</p>
                        <p class="desc">Prenatal Care, Women's Health, Maternity Services</p>
                        <div class="schedule">
                            <i class="fas fa-calendar-alt"></i> Tue-Thu, 10:00 AM - 4:00 PM
                        </div>
                    </div>
                </div>

                <!-- Dr. Cruz -->
                <div class="doctor-item">
                    <img src="images/doctor3.jpg" alt="Dr. Cruz" class="doctor-img"
                         onerror="this.src='photo3.png'">
                    <div class="doctor-info">
                        <h4>Dr. Cruz</h4>
                        <p class="specialty"> Pediatrician</p>
                        <p class="desc">Child Healthcare, Vaccinations, Growth Monitoring</p>
                        <div class="schedule">
                            <i class="fas fa-calendar-alt"></i> Mon-Wed, 8:00 AM - 3:00 PM
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 Barangay Health Clinic. Serving the community with care and compassion.Vhone Sildo best of the best</p>
        <p style="margin-top: 12px; font-size: 13px; opacity: 0.8;">
            <i class="fas fa-heart" style="color: #ff6b6b;"></i> Committed to Quality Healthcare for All Residents of our brgy
        </p>
    </footer>
</body>
</html>