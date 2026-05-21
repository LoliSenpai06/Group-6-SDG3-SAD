# Barangay Clinic Management System

## Project Overview

The **Barangay Clinic Management System** is a web-based application designed to digitize and streamline healthcare management for barangay health centers. The system automates patient registration, appointment booking, medical records management, and Local Government Unit (LGU) reporting.

### System Objectives

- Eliminate double booking of appointments
- Enforce doctor schedule validation
- Prevent duplicate patient records
- Generate automated LGU reports with service utilization and age breakdowns

---

## User Roles

| Role | Username | Password | Access |
|------|----------|----------|--------|
| **Admin** | admin | admin123 | Full system control |
| **Patient** | 09123456789 | Juan Dela Cruz | Book appointments, view records |
| **Doctor** | Dr. Santos | doctor123 | View schedule, add medical notes |
| **Staff** | staff | staff123 | View residents, generate LGU reports |

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| **Frontend** | HTML5, CSS3, JavaScript |
| **Backend** | PHP 8.0+ |
| **Database** | MySQL 10.4+ (MariaDB) |
| **Server** | XAMPP / WAMP / Apache |
| **IDE** | Visual Studio Code (Recommended) |

---

## Installation Guide

### Step 1: Install Required Software

#### A. Install XAMPP
Download and install XAMPP from: https://www.apachefriends.org/

### Step 2: Start Services

Open XAMPP Control Panel and start:
- **Apache** (Web Server)
- **MySQL** (Database)

### Step 3: Copy Project Files

Copy the `SYSTEM_SOURCE_CODE` folder contents to:
C:\xampp\htdocs\brgy_clinic\


### Step 4: Import Database

1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click **New** to create a database named `barangay_clinic`
3. Click **Import** tab
4. Choose file: `barangay_clinic.sql` from the `SYSTEM_SOURCE_CODE` folder
5. Click **Go**


#### B. Install Visual Studio Code (VS Code)
Download and install VS Code from: https://code.visualstudio.com/

### Step 2: Start XAMPP Services

Open **XAMPP Control Panel** and start:
-  **Apache** (Web Server)
-  **MySQL** (Database)

---

### Step 3: Open Project in VS Code

#### Option A: Open VS Code then Open Folder
1. Open **VS Code**
2. Click **File** → **Open Folder**
3. Navigate to: `C:\xampp\htdocs\`
4. Create a new folder named `brgy_clinic`
5. Select the folder and click **Select Folder**

#### Option B: Copy Files First, Then Open
1. Copy the `SYSTEM_SOURCE_CODE` folder contents
2. Paste to: `C:\xampp\htdocs\brgy_clinic\`
3. Open VS Code
4. Click **File** → **Open Folder**
5. Select `C:\xampp\htdocs\brgy_clinic`

---

### Step 4: Import Database

1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click **New** to create a database named `barangay_clinic`
3. Select `utf8_general_ci` as collation
4. Click **Create**
5. Click **Import** tab
6. Click **Choose File** and select `brgy_clinic.sql` from your project folder
7. Click **Go**

---

### Step 5: Configure Database Connection

Open `config/database.php` in VS Code and verify settings:

```php
<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'barangay_clinic';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>


Step 6: Run the System
Using Browser (Recommended for PHP)
Open any browser (Chrome, Firefox, Edge)

Type in the address bar:

http://localhost/brgy_clinic/login.php
