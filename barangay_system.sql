-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 06:37 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `barangay_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `book_appointment` (IN `p_patient_id` INT, IN `p_doctor_id` INT, IN `p_service_id` INT, IN `p_date` DATE, IN `p_time` TIME, IN `p_reason` TEXT)   BEGIN
    INSERT INTO appointments
        (patient_id, doctor_id, service_id, date, time, reason)
    VALUES
        (p_patient_id, p_doctor_id, p_service_id, p_date, p_time, p_reason);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `service_id`, `date`, `time`, `reason`, `status`) VALUES
(1, 1, 1, 1, '2025-12-15', '10:00:00', 'General checkup', 'Completed'),
(2, 2, 1, 1, '2025-12-19', '10:00:00', 'Test appointment', 'Completed'),
(3, 5, 3, 3, '2025-12-22', '09:00:00', 'Pediatric checkup', 'Cancelled'),
(5, 10, 1, 1, '2025-12-22', '11:00:00', 'Pediatric checkup', 'Cancelled'),
(6, 41, 1, 1, '2025-12-30', '09:30:00', 'checkup', 'Completed'),
(7, 41, 1, 1, '2026-01-06', '09:30:00', 'checkup', 'Completed'),
(8, 41, 1, 1, '2025-12-25', '09:30:00', 'checkup', 'Completed'),
(10, 1, 1, 1, '2025-12-23', '10:00:00', 'body checkup', 'Completed'),
(11, 13, 1, 1, '2025-12-15', '09:00:00', ' checkup', 'Completed'),
(12, 6, 1, 1, '2025-12-16', '11:00:00', 'back checkup', 'Completed'),
(13, 1, 1, 1, '2025-12-17', '09:00:00', 'General Checkup', 'Completed'),
(14, 1, 1, 1, '2026-04-27', '16:00:00', 'back hurt\n[Doctor Note: try to rest and sleep well]\n[Doctor Note: matulog kapo plss]', 'Completed'),
(15, 3, 1, 1, '2026-04-27', '09:00:00', 'my legs hurt and my back\n[Doctor Note: matulog kapo]', 'Completed'),
(16, 57, 1, 1, '2026-04-29', '13:00:00', 'ang sakit ng likod ko kaka code\n[Doctor Note: bumili ka kase ng gaming chair]', 'Completed');

--
-- Triggers `appointments`
--
DELIMITER $$
CREATE TRIGGER `after_appointment_complete` AFTER UPDATE ON `appointments` FOR EACH ROW BEGIN
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        INSERT INTO health_records (patient_id, date, details)
        VALUES (
            NEW.patient_id,
            NEW.date,
            CONCAT('Appointment completed: ', NEW.reason)
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `validate_appointment_time` BEFORE INSERT ON `appointments` FOR EACH ROW BEGIN
    DECLARE day_name VARCHAR(10);

    SET day_name = DAYNAME(NEW.date);

    -- Past date
    IF NEW.date < CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Appointment date cannot be in the past.';
    END IF;

    -- Patient exists
    IF NOT EXISTS (SELECT 1 FROM patients WHERE id = NEW.patient_id) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Patient does not exist.';
    END IF;

    -- Doctor exists
    IF NOT EXISTS (SELECT 1 FROM doctors WHERE id = NEW.doctor_id) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Doctor does not exist.';
    END IF;

    -- Service exists
    IF NOT EXISTS (SELECT 1 FROM services WHERE id = NEW.service_id) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Service does not exist.';
    END IF;

    -- Doctor-Service rule
    IF NEW.doctor_id = 1 AND NEW.service_id != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'GP can only do General Checkup.';
    END IF;

    IF NEW.doctor_id = 2 AND NEW.service_id != 2 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'OB can only do Prenatal.';
    END IF;

    IF NEW.doctor_id = 3 AND NEW.service_id != 3 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pediatrician can only do Pediatric.';
    END IF;

    -- Double booking protection
    IF EXISTS (
        SELECT 1 FROM appointments
        WHERE doctor_id = NEW.doctor_id
          AND date = NEW.date
          AND time = NEW.time
          AND status IN ('Pending','Confirmed')
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Doctor already booked at this time.';
    END IF;

    -- Doctor schedules
    IF NEW.doctor_id = 1 AND (
        day_name NOT IN ('Monday','Tuesday','Wednesday','Thursday','Friday')
        OR NEW.time < '09:00:00'
        OR NEW.time > '17:00:00'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'GP available Mon–Fri, 9AM–5PM only.';
    END IF;

    IF NEW.doctor_id = 2 AND (
        day_name NOT IN ('Tuesday','Wednesday','Thursday')
        OR NEW.time < '10:00:00'
        OR NEW.time > '16:00:00'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'OB available Tue–Thu, 10AM–4PM only.';
    END IF;

    IF NEW.doctor_id = 3 AND (
        day_name NOT IN ('Monday','Tuesday','Wednesday')
        OR NEW.time < '08:00:00'
        OR NEW.time > '15:00:00'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pediatrician available Mon–Wed, 8AM–3PM only.';
    END IF;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `validate_appointment_update` BEFORE UPDATE ON `appointments` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1 FROM appointments
        WHERE doctor_id = NEW.doctor_id
          AND date = NEW.date
          AND time = NEW.time
          AND id != OLD.id
          AND status IN ('Pending','Confirmed')
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Doctor already booked for this schedule.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `name`, `specialization`) VALUES
(1, 'Dr. Santos', 'General Practitioner'),
(2, 'Dr. Reyes', 'Obstetrician'),
(3, 'Dr. Cruz', 'Pediatrician');

-- --------------------------------------------------------

--
-- Stand-in structure for view `elderly_appointments`
-- (See below for the actual view)
--
CREATE TABLE `elderly_appointments` (
`name` varchar(100)
,`age` int(11)
,`date` date
,`status` enum('Pending','Confirmed','Completed','Cancelled')
,`specialization` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`id`, `patient_id`, `date`, `details`) VALUES
(1, 1, '2025-12-15', 'Appointment completed: General checkup'),
(2, 13, '2025-12-15', 'Appointment completed:  checkup'),
(3, 6, '2025-12-16', 'Appointment completed: back checkup'),
(4, 1, '2025-12-17', 'Appointment completed: General Checkup'),
(5, 2, '2025-12-19', 'Appointment completed: Test appointment'),
(6, 5, '2025-12-22', 'Appointment completed: Pediatric checkup'),
(7, 41, '2026-01-06', 'Appointment completed: checkup'),
(8, 41, '2025-12-30', 'Appointment completed: checkup'),
(9, 41, '2025-12-25', 'Appointment completed: checkup'),
(10, 3, '2026-04-27', 'Appointment completed: my legs hurt and my back\n[Doctor Note: matulog kapo]'),
(11, 1, '2025-12-23', 'Appointment completed: body checkup'),
(12, 1, '2026-04-27', 'Appointment completed: back hurt\n[Doctor Note: try to rest and sleep well]\n[Doctor Note: matulog kapo plss]'),
(13, 57, '2026-04-29', 'Appointment completed: ang sakit ng likod ko kaka code\n[Doctor Note: bumili ka kase ng gaming chair]');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL CHECK (`age` > 0),
  `address` varchar(255) NOT NULL,
  `contact_no` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `name`, `age`, `address`, `contact_no`) VALUES
(1, 'Juan Dela Cruz', 45, 'Barangay 1', '09123456789'),
(2, 'Maria Santos', 32, 'Barangay 2', '09234567890'),
(3, 'Pedro Reyes', 65, 'Barangay 3', '09345678901'),
(4, 'Juan Dela Cruz', 45, 'Barangay 1', '09123456701'),
(5, 'Maria Santos', 32, 'Barangay 2', '09123456702'),
(6, 'Pedro Reyes', 65, 'Barangay 3', '09123456703'),
(7, 'Ana Lopez', 28, 'Barangay 4', '09123456704'),
(8, 'Carlos Mendoza', 72, 'Barangay 5', '09123456705'),
(9, 'Rosa Garcia', 55, 'Barangay 1', '09123456706'),
(10, 'Miguel Torres', 40, 'Barangay 2', '09123456707'),
(11, 'Elena Cruz', 68, 'Barangay 3', '09123456708'),
(12, 'Fernando Ramos', 25, 'Barangay 4', '09123456709'),
(13, 'Isabel Flores', 60, 'Barangay 5', '09123456710'),
(14, 'Ricardo Bautista', 35, 'Barangay 1', '09123456711'),
(15, 'Carmen Villanueva', 75, 'Barangay 2', '09123456712'),
(16, 'Antonio Morales', 50, 'Barangay 3', '09123456713'),
(17, 'Sofia Aguilar', 22, 'Barangay 4', '09123456714'),
(18, 'Diego Castro', 80, 'Barangay 5', '09123456715'),
(19, 'Luz Rivera', 38, 'Barangay 1', '09123456716'),
(20, 'Manuel Herrera', 70, 'Barangay 2', '09123456717'),
(21, 'Teresa Medina', 45, 'Barangay 3', '09123456718'),
(22, 'Roberto Guzman', 30, 'Barangay 4', '09123456719'),
(23, 'Patricia Ortiz', 85, 'Barangay 5', '09123456720'),
(24, 'Emilio Fernandez', 55, 'Barangay 1', '09123456721'),
(25, 'Gloria Chavez', 40, 'Barangay 2', '09123456722'),
(26, 'Rafael Delgado', 65, 'Barangay 3', '09123456723'),
(27, 'Victoria Ruiz', 20, 'Barangay 4', '09123456724'),
(28, 'Alberto Vargas', 75, 'Barangay 5', '09123456725'),
(29, 'Monica Soto', 50, 'Barangay 1', '09123456726'),
(30, 'Oscar Luna', 28, 'Barangay 2', '09123456727'),
(31, 'Diana Morales', 62, 'Barangay 3', '09123456728'),
(32, 'Hugo Pena', 35, 'Barangay 4', '09123456729'),
(33, 'Silvia Navarro', 78, 'Barangay 5', '09123456730'),
(34, 'Luis Estrada', 42, 'Barangay 1', '09123456731'),
(35, 'Angela Rojas', 55, 'Barangay 2', '09123456732'),
(36, 'Pablo Jimenez', 68, 'Barangay 3', '09123456733'),
(37, 'Beatriz Silva', 25, 'Barangay 4', '09123456734'),
(38, 'Ruben Alvarez', 80, 'Barangay 5', '09123456735'),
(39, 'Natalia Gomez', 38, 'Barangay 1', '09123456736'),
(40, 'Felipe Torres', 70, 'Barangay 2', '09123456737'),
(41, 'Adriana Reyes', 6, 'Barangay 3', '09123456738'),
(42, 'Gustavo Lopez', 10, 'Barangay 4', '09123456739'),
(43, 'Camila Mendoza', 4, 'Barangay 5', '09123456740'),
(44, 'Hector Garcia', 5, 'Barangay 1', '09123456741'),
(45, 'Ines Cruz', 10, 'Barangay 2', '09123456742'),
(46, 'Javier Ramos', 10, 'Barangay 3', '09123456743'),
(47, 'Lorena Flores', 10, 'Barangay 4', '09123456744'),
(48, 'Mario Bautista', 7, 'Barangay 5', '09123456745'),
(49, 'Pilar Villanueva', 6, 'Barangay 1', '09123456746'),
(50, 'Salvador Morales', 6, 'Barangay 2', '09123456747'),
(51, 'Tomas Aguilar', 60, 'Barangay 3', '09123456748'),
(52, 'Ursula Castro', 27, 'Barangay 4', '09123456749'),
(53, 'Victor Rivera', 72, 'Barangay 5', '09123456750'),
(57, 'Vhone', 21, 'Tondo', '0912345678'),
(58, 'MarthTumlos', 13, 'Barangay 32', '0912345'),
(59, 'paolo', 15, 'Barangay 23', '09123'),
(60, 'Rafael Sanchez', 57, 'barangay 43', '09123789'),
(61, 'Eizel Eco', 29, 'Barangay 12', '0935671589');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`) VALUES
(1, 'General Checkup'),
(2, 'Prenatal'),
(3, 'Pediatric');

-- --------------------------------------------------------

--
-- Stand-in structure for view `service_summary`
-- (See below for the actual view)
--
CREATE TABLE `service_summary` (
`service_name` varchar(100)
,`total_appointments` bigint(21)
,`avg_patient_age` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','patient') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES
(1, 'Admin', 'admin', '0192023a7bbd73250516f069df18b500', 'admin');

-- --------------------------------------------------------

--
-- Structure for view `elderly_appointments`
--
DROP TABLE IF EXISTS `elderly_appointments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `elderly_appointments`  AS SELECT `p`.`name` AS `name`, `p`.`age` AS `age`, `a`.`date` AS `date`, `a`.`status` AS `status`, `d`.`specialization` AS `specialization` FROM ((`patients` `p` join `appointments` `a` on(`p`.`id` = `a`.`patient_id`)) join `doctors` `d` on(`a`.`doctor_id` = `d`.`id`)) WHERE `p`.`age` >= 60 ;

-- --------------------------------------------------------

--
-- Structure for view `service_summary`
--
DROP TABLE IF EXISTS `service_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `service_summary`  AS SELECT `s`.`name` AS `service_name`, count(`a`.`id`) AS `total_appointments`, avg(`p`.`age`) AS `avg_patient_age` FROM ((`services` `s` left join `appointments` `a` on(`s`.`id` = `a`.`service_id`)) left join `patients` `p` on(`a`.`patient_id` = `p`.`id`)) GROUP BY `s`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contact_no` (`contact_no`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
