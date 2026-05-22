-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 10:51 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mediflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `registration_fee` decimal(10,2) DEFAULT 0.00,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `lab_fee` decimal(10,2) DEFAULT 0.00,
  `scan_fee` decimal(10,2) DEFAULT 0.00,
  `medicine_fee` decimal(10,2) DEFAULT 0.00,
  `other_fee` decimal(10,2) DEFAULT 0.00,
  `other_description` varchar(255) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cash','card','upi','online','insurance') DEFAULT 'cash',
  `payment_status` enum('paid','pending','partial') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `bill_number`, `visit_id`, `registration_fee`, `consultation_fee`, `lab_fee`, `scan_fee`, `medicine_fee`, `other_fee`, `other_description`, `subtotal`, `discount`, `total_amount`, `payment_method`, `payment_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'BILL-20260504-0001', 17, 100.00, 500.00, 200.00, 0.00, 50.00, 0.00, '0', 850.00, 0.00, 850.00, 'cash', 'paid', '', '2026-05-04 12:31:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `history` text DEFAULT NULL,
  `examination` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_notes` text DEFAULT NULL,
  `consulted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `visit_id`, `doctor_id`, `chief_complaint`, `history`, `examination`, `diagnosis`, `notes`, `follow_up_date`, `follow_up_notes`, `consulted_at`) VALUES
(1, 7, 2, '', '', '', 'hgfhjk', '', '2026-04-30', 'hhs', '2026-04-29 09:48:39'),
(2, 9, 8, 'as', 'hgfgh', 'f', 'gfhgjk', 'hgfhjk', '2026-04-30', 'hhs', '2026-04-30 08:08:13'),
(3, 10, 2, 'aa', 'asa', 'sasa', 'sa', 'asa', '2026-05-08', 'hhs', '2026-04-30 08:51:40'),
(4, 11, 7, 'sdds', 'asas', 'asasas', 'asas', 'asas', '2026-05-01', 'hhs', '2026-04-30 12:50:32'),
(5, 17, 2, 'ddsd', 'sdsd', 'sdsd', 'fdsfdsf', 'dfsds', '2026-05-05', 'hhs', '2026-05-04 12:05:48');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `status`, `created_at`) VALUES
(1, 'General Medicine', 'General outpatient care', 1, '2026-04-28 10:40:06'),
(2, 'Orthopedics', 'Bone and joint issues', 1, '2026-04-28 10:40:06'),
(3, 'Pediatrics', 'Child healthcare', 1, '2026-04-28 10:40:06'),
(4, 'ENT', 'Ear, Nose, Throat', 1, '2026-04-28 10:40:06'),
(5, 'Ophthalmology', 'Eye care', 1, '2026-04-28 10:40:06'),
(6, 'Dermatology', 'Skin conditions', 1, '2026-04-28 10:40:06'),
(7, 'Gynecology', 'Women health', 1, '2026-04-28 10:40:06'),
(8, 'Cardiology', 'Heart conditions', 1, '2026-04-28 10:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `dispensing_log`
--

CREATE TABLE `dispensing_log` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL,
  `quantity_dispensed` int(11) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `dispensed_by_staff_id` int(11) DEFAULT NULL,
  `dispensed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispensing_log`
--

INSERT INTO `dispensing_log` (`id`, `visit_id`, `prescription_id`, `drug_id`, `quantity_dispensed`, `batch_number`, `expiry_date`, `notes`, `dispensed_by_staff_id`, `dispensed_at`) VALUES
(1, 17, 4, 1, 1, NULL, NULL, 'always take', NULL, '2026-05-04 12:09:34');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `staff_id`, `name`, `department_id`, `phone`, `email`, `qualification`, `specialization`, `status`, `created_at`) VALUES
(1, NULL, 'Dr. Sarah Ahmed', 1, '01712345678', 'sarah@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(2, 4, 'Dr. Karim Hossain', 2, '01712345679', 'karim@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(3, NULL, 'Dr. Fatima Begum', 3, '01712345680', 'fatima@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(4, NULL, 'Dr. Rahman Miah', 4, '01712345681', 'rahman@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(5, NULL, 'Dr. Nasreen Akter', 5, '01712345682', 'nasreen@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(6, NULL, 'Dr. Tanvir Islam', 6, '01712345683', 'tanvir@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(7, NULL, 'Dr. Salma Khatun', 7, '01712345684', 'salma@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(8, NULL, 'Dr. Imran Khan', 8, '01712345685', 'imran@mediflow.com', NULL, NULL, 1, '2026-04-28 10:58:33'),
(9, NULL, 'Alex Extensions', 4, '256777777861', '', 'sdsd', 'aaaaa', 1, '2026-05-03 19:17:07');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_fees`
--

CREATE TABLE `doctor_fees` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `type` enum('opd','ipd','emergency') DEFAULT 'opd',
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_fees`
--

INSERT INTO `doctor_fees` (`id`, `doctor_id`, `type`, `fee`) VALUES
(1, 9, 'opd', 500.00);

-- --------------------------------------------------------

--
-- Table structure for table `drugs`
--

CREATE TABLE `drugs` (
  `id` int(11) NOT NULL,
  `drug_name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `dosage_form` enum('tablet','capsule','syrup','injection','cream','drops','inhaler','other') DEFAULT 'tablet',
  `strength` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'unit',
  `current_stock` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 20,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `manufacturer` varchar(200) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `storage_instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drugs`
--

INSERT INTO `drugs` (`id`, `drug_name`, `generic_name`, `category`, `dosage_form`, `strength`, `unit`, `current_stock`, `reorder_level`, `unit_cost`, `unit_price`, `manufacturer`, `expiry_date`, `batch_number`, `storage_instructions`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol 500mg', NULL, NULL, 'tablet', NULL, 'unit', 5000, 20, 0.00, 0.00, NULL, NULL, NULL, NULL, 1, '2026-05-04 12:09:34', '2026-05-04 12:09:34');

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `test_name` varchar(200) NOT NULL,
  `status` enum('ordered','collected','processing','completed') DEFAULT 'ordered',
  `ordered_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_orders`
--

INSERT INTO `lab_orders` (`id`, `visit_id`, `test_name`, `status`, `ordered_at`, `completed_at`) VALUES
(12, 10, 'Kidney Function Test (KFT)', 'completed', '2026-04-30 08:42:39', NULL),
(13, 10, 'Kidney Function Test (KFT)', 'completed', '2026-04-30 08:42:41', NULL),
(14, 11, 'Blood Sugar (Fasting)', 'completed', '2026-04-30 12:49:20', NULL),
(15, 11, 'X-Ray Chest PA', 'completed', '2026-04-30 12:49:24', NULL),
(16, 17, 'Liver Function Test (LFT)', 'completed', '2026-05-04 12:04:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `id` int(11) NOT NULL,
  `lab_order_id` int(11) NOT NULL,
  `results` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `entered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_results`
--

INSERT INTO `lab_results` (`id`, `lab_order_id`, `results`, `remarks`, `entered_at`) VALUES
(1, 12, 'ssadsds', 'sds', '2026-04-30 10:11:58'),
(2, 14, 'fdfghjkljhgfdsxfcvgbhjn', 'nbvgfcdtyfguih', '2026-04-30 12:52:10'),
(3, 13, 'hghjhhj', '', '2026-05-01 18:10:26'),
(4, 15, 'ready', '', '2026-05-01 18:54:03'),
(5, 16, 'ssaad', 'asdsd', '2026-05-04 12:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(20) NOT NULL,
  `patient_nin` varchar(15) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `age` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_id`, `patient_nin`, `name`, `phone`, `email`, `dob`, `age`, `gender`, `blood_group`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES
(1, 'PAT-00001', '', 'John Okello', '0772123456', 'okello.john@gmail.com', '1985-03-15', '39', 'Male', 'B+', 'Kampala, Uganda', NULL, NULL, '2026-04-28 10:38:31', '2026-04-28 10:38:31'),
(2, 'PAT-00002', '', 'Sarah Nakato', '0701123457', 'nakato.s@gmail.com', '1990-07-22', '34', 'Female', 'A+', 'Entebbe, Uganda', NULL, NULL, '2026-04-28 10:38:31', '2026-04-28 10:38:31'),
(3, 'PAT-00003', '', 'Musa Mukasa', '0782123458', NULL, '1978-11-10', '46', 'Male', 'O+', 'Jinja, Uganda', NULL, NULL, '2026-04-28 10:38:31', '2026-04-28 10:38:31'),
(4, 'PAT-00004', '', 'Grace Akot', '0753123459', 'g_akot@yahoo.com', '1995-01-05', '29', 'Female', 'AB+', 'Gulu, Uganda', NULL, NULL, '2026-04-28 10:38:31', '2026-04-28 10:38:31'),
(5, 'PAT-00005', '', 'Patrick Mugisha', '0774123460', NULL, '1982-09-18', '42', 'Male', 'B-', 'Mbarara, Uganda', NULL, NULL, '2026-04-28 10:38:31', '2026-04-28 10:38:31'),
(6, 'PAT-00006', 'CM27632738jjsh', 'alex pro', '0777777861', '', NULL, '67', 'Male', 'O+', '', '', '', '2026-04-29 08:06:38', '2026-05-15 12:54:53'),
(7, 'PAT-00007', '', 'alex pro', '0777777861', '', NULL, '67', 'Male', NULL, '', '', '', '2026-04-29 09:29:22', '2026-04-29 09:29:22'),
(8, 'PAT-00008', '', 'acen', '0776255242', '', NULL, '30', 'Female', 'A+', '', '', '', '2026-04-29 12:13:58', '2026-04-29 12:13:58'),
(9, 'PAT-00009', '', 'akello', '0788888888', '', '1991-01-17', '35 years', 'Female', 'B+', '', '', '', '2026-04-30 12:43:23', '2026-04-30 12:43:23'),
(10, 'PAT-00010', '', 'jane akot', '0700765387', '', '2026-05-04', '0 years', 'Female', 'A+', '', '', '', '2026-05-04 11:36:32', '2026-05-04 11:36:32'),
(11, 'PAT-00011', '', 'Jamie', '799999999', '', NULL, '35 years', 'Male', 'O-', '', '', '', '2026-05-04 11:59:05', '2026-05-04 11:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_dispense`
--

CREATE TABLE `pharmacy_dispense` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dispensed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_stock`
--

CREATE TABLE `pharmacy_stock` (
  `id` int(11) NOT NULL,
  `medicine_name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'Tablet',
  `stock_qty` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 10,
  `price` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pharmacy_stock`
--

INSERT INTO `pharmacy_stock` (`id`, `medicine_name`, `generic_name`, `category`, `unit`, `stock_qty`, `min_stock`, `price`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol 500mg', 'Paracetamol', 'Analgesic', 'Tablet', 4999, 100, 2.00, 1, '2026-04-28 11:01:09', '2026-05-04 12:09:34'),
(2, 'Amoxicillin 500mg', 'Amoxicillin', 'Antibiotic', 'Capsule', 3000, 100, 8.00, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(3, 'Omeprazole 20mg', 'Omeprazole', 'Antacid', 'Capsule', 2000, 100, 5.00, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(4, 'Cetirizine 10mg', 'Cetirizine', 'Antihistamine', 'Tablet', 4000, 100, 3.00, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(5, 'Azithromycin 500mg', 'Azithromycin', 'Antibiotic', 'Tablet', 1500, 100, 15.00, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(6, 'Metformin 500mg', 'Metformin', 'Antidiabetic', 'Tablet', 3000, 100, 4.00, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(7, 'Ibuprofen 400mg', 'Ibuprofen', 'Analgesic', 'Tablet', 2500, 100, 3.50, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(8, 'Cough Syrup', 'Dextromethorphan', 'Cough', 'Bottle', 800, 50, 45.00, 1, '2026-04-28 11:01:09', '2026-04-28 11:01:09'),
(9, 'Clopidogrel 75mg', 'Clopidogrel', 'Antiplatelet', 'Tablet', 180, 20, 2.75, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(10, 'Rosuvastatin 20mg', 'Rosuvastatin', 'Statin', 'Tablet', 150, 15, 5.50, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(11, 'Losartan 50mg', 'Losartan', 'Antihypertensive', 'Tablet', 250, 25, 1.20, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(12, 'Warfarin 5mg', 'Warfarin', 'Anticoagulant', 'Tablet', 100, 10, 0.85, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(13, 'Montelukast 10mg', 'Montelukast', 'Leukotriene Antagonist', 'Tablet', 200, 20, 3.20, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(14, 'Fluticasone Nasal Spray', 'Fluticasone', 'Corticosteroid', 'Bottle', 40, 5, 18.50, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(15, 'Loratadine 10mg', 'Loratadine', 'Antihistamine', 'Tablet', 500, 50, 0.40, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(16, 'Loperamide 2mg', 'Loperamide', 'Antidiarrheal', 'Capsule', 300, 30, 0.60, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(17, 'Ranitidine 150mg', 'Ranitidine', 'H2 Blocker', 'Tablet', 200, 20, 1.10, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(18, 'Gaviscon Liquid', 'Alginate/Antacid', 'Antacid', 'Bottle', 60, 10, 12.00, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(19, 'Ciprofloxacin 500mg', 'Ciprofloxacin', 'Antibiotic', 'Tablet', 150, 15, 4.25, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(20, 'Acyclovir 400mg', 'Acyclovir', 'Antiviral', 'Tablet', 120, 15, 2.10, 1, '2026-04-29 10:16:51', '2026-05-16 10:23:48'),
(21, 'Clarithromycin 500mg', 'Clarithromycin', 'Antibiotic', 'Tablet', 80, 10, 7.50, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(22, 'Naproxen 500mg', 'Naproxen', 'NSAID', 'Tablet', 250, 25, 1.80, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(23, 'Gabapentin 300mg', 'Gabapentin', 'Anticonvulsant', 'Capsule', 180, 20, 3.75, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(24, 'Tramadol 50mg', 'Tramadol', 'Opioid Analgesic', 'Capsule', 100, 10, 2.50, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(25, 'Hydrocortisone 1% Cream', 'Hydrocortisone', 'Corticosteroid', 'Tube', 50, 10, 6.50, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(26, 'Mupirocin Ointment', 'Mupirocin', 'Antibacterial', 'Tube', 30, 5, 14.00, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(27, 'Clotrimazole Cream', 'Clotrimazole', 'Antifungal', 'Tube', 100, 15, 5.00, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51'),
(28, 'Insulin Glargine', 'Insulin', 'Hormone', 'Vial', 20, 5, 45.00, 1, '2026-04-29 10:16:51', '2026-04-29 10:16:51');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `consultation_id`, `notes`, `created_at`) VALUES
(1, 3, 'para', '2026-04-30 08:51:40'),
(2, 4, '', '2026-04-30 12:50:33'),
(4, 5, '', '2026-05-04 12:05:48');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicine_name` varchar(200) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_items`
--

INSERT INTO `prescription_items` (`id`, `prescription_id`, `medicine_name`, `dosage`, `frequency`, `duration`, `instructions`) VALUES
(1, 1, 'asas', '500mg', '2+1', '6', 'after food'),
(2, 2, 'paracet', '500mg', '1+1', '6', 'ddsf'),
(4, 4, 'Paracetamol', '500mg', '1+1', '5  days', '');

-- --------------------------------------------------------

--
-- Table structure for table `scans`
--

CREATE TABLE `scans` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `scan_type` varchar(100) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `status` enum('ordered','completed') DEFAULT 'ordered',
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scan_results`
--

CREATE TABLE `scan_results` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `scan_type` varchar(100) NOT NULL,
  `findings` text DEFAULT NULL,
  `impression` text DEFAULT NULL,
  `image_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_paths`)),
  `report_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `performed_by_staff_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `category`, `key`, `value`) VALUES
(1, 'hospital', 'name', 'MediFlow Hospital'),
(2, 'hospital', 'address', ''),
(3, 'hospital', 'phone', ''),
(4, 'hospital', 'email', ''),
(5, 'hospital', 'reg_number', '');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_code` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` enum('receptionist','triage_nurse','doctor','lab_technician','radiologist','pharmacist','admin','billing') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `module` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_code`, `first_name`, `last_name`, `role`, `phone`, `email`, `password`, `is_active`, `last_login`, `created_at`, `module`) VALUES
(1, 'ADM001', 'System', 'Admin', 'admin', NULL, 'admin@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(2, 'REC001', 'Akello', 'Everlyn', 'receptionist', NULL, 'reception@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(3, 'NUR001', 'Nurse', 'Salma', 'triage_nurse', NULL, 'nurse@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(4, 'DOC001', 'Kyomugisha', 'Success', 'doctor', NULL, 'doctor@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(5, 'LAB001', 'Karim', 'Labtech', 'lab_technician', NULL, 'lab@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(6, 'RAD001', 'Nadia', 'Radiology', 'radiologist', NULL, 'scan@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(7, 'PHM001', 'Jamil', 'Pharmacist', 'pharmacist', NULL, 'pharmacy@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL),
(8, 'BIL001', 'Hasan', 'Billing', 'billing', NULL, 'billing@mediflow.com', '$2y$10$iSWN6XwzAnSrLSrQfcMlbumDy3xPWNGKBhEPXk4WOC5YSaFzQ6avm', 1, NULL, '2026-04-28 14:10:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `drug_id` int(11) NOT NULL,
  `movement_type` enum('stock_in','stock_out','adjustment','expired','returned') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reference_type` enum('purchase','dispensing','adjustment','expiry','return') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by_staff_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `triage`
--

CREATE TABLE `triage` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `priority` enum('green','yellow','orange','red') DEFAULT 'green',
  `bp_systolic` int(11) DEFAULT NULL,
  `bp_diastolic` int(11) DEFAULT NULL,
  `pulse` int(11) DEFAULT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `spo2` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_emergency` tinyint(1) DEFAULT 0,
  `triaged_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `triage`
--

INSERT INTO `triage` (`id`, `visit_id`, `priority`, `bp_systolic`, `bp_diastolic`, `pulse`, `temperature`, `spo2`, `weight`, `chief_complaint`, `notes`, `is_emergency`, `triaged_at`) VALUES
(2, 7, 'green', 120, 80, 70, 35.00, 88, 77.00, 'hh', 'hs', 0, '2026-04-29 09:44:39'),
(3, 9, 'red', 112, 70, 67, 35.00, 98, 77.00, '555', '555', 1, '2026-04-30 07:37:57'),
(4, 10, 'green', 119, 55, 76, 35.00, 55, 55.00, '55', '55', 0, '2026-04-30 07:39:16'),
(5, 11, 'green', 120, 79, 68, 35.00, 95, 80.00, 'big som', 'asa', 0, '2026-04-30 12:47:34'),
(6, 13, 'green', 120, 70, 72, 35.00, 95, 78.00, 'jtko9i', 'kjlkg', 0, '2026-05-04 11:16:55'),
(7, 17, 'green', 110, 79, 70, 35.00, 95, 77.00, 'bad', 'bad', 0, '2026-05-04 12:03:09'),
(8, 18, 'yellow', 120, 78, 60, 40.00, 95, NULL, 'www', 'ww', 0, '2026-05-15 14:07:07'),
(9, 19, 'red', 120, 80, 64, 40.00, 95, NULL, '', '', 1, '2026-05-15 14:17:05');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` int(11) NOT NULL,
  `visit_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `token_number` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `status` enum('registered','triage','consulting','lab','pharmacy','completed','closed') DEFAULT 'registered',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `visit_id`, `patient_id`, `department_id`, `doctor_id`, `token_number`, `visit_date`, `status`, `created_at`, `updated_at`, `closed_at`) VALUES
(1, 'OPD-250101-001', 1, 1, 1, 1, '2026-04-28', 'completed', '2026-04-28 10:41:18', '2026-04-28 10:41:18', NULL),
(2, 'OPD-250101-002', 2, 3, 3, 1, '2026-04-28', 'consulting', '2026-04-28 10:41:18', '2026-04-28 10:41:18', NULL),
(3, 'OPD-250101-003', 3, 2, 2, 1, '2026-04-28', 'triage', '2026-04-28 10:41:18', '2026-04-28 10:41:18', NULL),
(4, 'OPD-250101-004', 4, 1, 1, 2, '2026-04-28', 'registered', '2026-04-28 10:41:18', '2026-04-28 10:41:18', NULL),
(5, 'OPD-250101-005', 5, 4, 4, 1, '2026-04-28', 'registered', '2026-04-28 10:41:18', '2026-04-28 10:41:18', NULL),
(7, 'OPD-260429-001', 7, 2, 2, 1, '2026-04-29', 'pharmacy', '2026-04-29 09:29:23', '2026-04-29 09:48:39', NULL),
(8, 'OPD-260429-002', 8, 7, 7, 1, '2026-04-29', 'registered', '2026-04-29 12:13:58', '2026-04-29 12:13:58', NULL),
(9, 'OPD-260430-001', 6, 8, 8, 1, '2026-04-30', 'pharmacy', '2026-04-30 07:33:19', '2026-04-30 08:08:13', NULL),
(10, 'OPD-260430-002', 6, 2, 2, 1, '2026-04-30', 'lab', '2026-04-30 07:34:59', '2026-04-30 08:51:40', NULL),
(11, 'OPD-260430-003', 9, 7, 7, 1, '2026-04-30', 'lab', '2026-04-30 12:43:23', '2026-04-30 12:50:33', NULL),
(12, 'OPD-260503-001', 6, 3, 3, 1, '2026-05-03', 'registered', '2026-05-03 19:33:52', '2026-05-03 19:33:52', NULL),
(13, 'OPD-260504-001', 6, 2, NULL, 1, '2026-05-04', 'triage', '2026-05-04 11:06:02', '2026-05-04 11:16:55', NULL),
(14, 'OPD-260504-002', 10, 7, 7, 1, '2026-05-04', 'registered', '2026-05-04 11:36:32', '2026-05-04 11:36:32', NULL),
(15, 'OPD-260504-003', 10, 2, 2, 2, '2026-05-04', 'registered', '2026-05-04 11:38:22', '2026-05-04 11:38:22', NULL),
(16, 'OPD-260504-004', 10, 2, 2, 3, '2026-05-04', 'registered', '2026-05-04 11:48:14', '2026-05-04 11:48:14', NULL),
(17, 'OPD-260504-005', 11, 2, 2, 4, '2026-05-04', 'closed', '2026-05-04 11:59:05', '2026-05-04 12:31:28', NULL),
(18, 'OPD-260515-001', 6, 2, 2, 1, '2026-05-15', 'triage', '2026-05-15 12:54:53', '2026-05-15 14:07:07', NULL),
(19, 'OPD-260515-002', 6, 2, 2, 2, '2026-05-15', 'triage', '2026-05-15 12:55:26', '2026-05-15 14:17:05', NULL),
(20, 'OPD-260516-001', 6, 2, 2, 1, '2026-05-16', 'registered', '2026-05-16 21:16:37', '2026-05-16 21:16:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `visit_log`
--

CREATE TABLE `visit_log` (
  `id` int(11) NOT NULL,
  `visit_id` int(11) NOT NULL,
  `from_station` varchar(50) DEFAULT NULL,
  `to_station` varchar(50) NOT NULL,
  `action` varchar(255) NOT NULL,
  `performed_by_staff_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_number` (`bill_number`),
  ADD KEY `idx_bill_number` (`bill_number`),
  ADD KEY `idx_visit_id` (`visit_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_id` (`visit_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dispensing_log`
--
ALTER TABLE `dispensing_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit` (`visit_id`),
  ADD KEY `idx_prescription` (`prescription_id`),
  ADD KEY `drug_id` (`drug_id`),
  ADD KEY `dispensed_by_staff_id` (`dispensed_by_staff_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `fk_doctor_staff` (`staff_id`);

--
-- Indexes for table `doctor_fees`
--
ALTER TABLE `doctor_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_doctor_type` (`doctor_id`,`type`);

--
-- Indexes for table `drugs`
--
ALTER TABLE `drugs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`drug_name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_stock_alert` (`current_stock`,`reorder_level`,`is_active`),
  ADD KEY `idx_expiry` (`expiry_date`,`is_active`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_order_id` (`lab_order_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`);

--
-- Indexes for table `pharmacy_dispense`
--
ALTER TABLE `pharmacy_dispense`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `pharmacy_stock`
--
ALTER TABLE `pharmacy_stock`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `scans`
--
ALTER TABLE `scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `scan_results`
--
ALTER TABLE `scan_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit_scan` (`visit_id`,`scan_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `performed_by_staff_id` (`performed_by_staff_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`category`,`key`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_code` (`staff_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drug_movement` (`drug_id`,`movement_type`,`created_at`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `performed_by_staff_id` (`performed_by_staff_id`);

--
-- Indexes for table `triage`
--
ALTER TABLE `triage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_id` (`visit_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `idx_visit_date` (`visit_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `visit_log`
--
ALTER TABLE `visit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit` (`visit_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `performed_by_staff_id` (`performed_by_staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `dispensing_log`
--
ALTER TABLE `dispensing_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `doctor_fees`
--
ALTER TABLE `doctor_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drugs`
--
ALTER TABLE `drugs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `pharmacy_dispense`
--
ALTER TABLE `pharmacy_dispense`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacy_stock`
--
ALTER TABLE `pharmacy_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `scans`
--
ALTER TABLE `scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scan_results`
--
ALTER TABLE `scan_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `triage`
--
ALTER TABLE `triage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `visit_log`
--
ALTER TABLE `visit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`),
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `dispensing_log`
--
ALTER TABLE `dispensing_log`
  ADD CONSTRAINT `dispensing_log_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`),
  ADD CONSTRAINT `dispensing_log_ibfk_2` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`),
  ADD CONSTRAINT `dispensing_log_ibfk_3` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `dispensing_log_ibfk_4` FOREIGN KEY (`dispensed_by_staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_doctor_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `doctor_fees`
--
ALTER TABLE `doctor_fees`
  ADD CONSTRAINT `doctor_fees_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD CONSTRAINT `lab_orders_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`);

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`);

--
-- Constraints for table `pharmacy_dispense`
--
ALTER TABLE `pharmacy_dispense`
  ADD CONSTRAINT `pharmacy_dispense_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`),
  ADD CONSTRAINT `pharmacy_dispense_ibfk_2` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`),
  ADD CONSTRAINT `pharmacy_dispense_ibfk_3` FOREIGN KEY (`medicine_id`) REFERENCES `pharmacy_stock` (`id`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`);

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`);

--
-- Constraints for table `scans`
--
ALTER TABLE `scans`
  ADD CONSTRAINT `scans_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`);

--
-- Constraints for table `scan_results`
--
ALTER TABLE `scan_results`
  ADD CONSTRAINT `scan_results_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`),
  ADD CONSTRAINT `scan_results_ibfk_2` FOREIGN KEY (`performed_by_staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`performed_by_staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `triage`
--
ALTER TABLE `triage`
  ADD CONSTRAINT `triage_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`);

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `visit_log`
--
ALTER TABLE `visit_log`
  ADD CONSTRAINT `visit_log_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`),
  ADD CONSTRAINT `visit_log_ibfk_2` FOREIGN KEY (`performed_by_staff_id`) REFERENCES `staff` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
