-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 20, 2026 at 01:18 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `earist_coffeeshop`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'bcrypt hash',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `username`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Kapehan Admin', 'admin', '$2y$12$JY3BEyGYLerY2Oeb.88EEeOW5Nb1xaMpcpd6T8yOnRwv0wJsywFg2', '2026-03-03 06:39:29', '2026-04-10 21:48:50'),
(2, 'Jhulmar Bregonia', 'bregonia@kapehan.com', '$2y$10$tNagHObFDpQ4sfDRcSYFBub7HBsd4EoVZnKE1cfgfhcSpGzJVzRSu', '2026-04-07 09:23:39', '2026-04-07 09:23:39');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int UNSIGNED NOT NULL,
  `actor_type` enum('admin','cashier','student','faculty') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` int UNSIGNED NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. orders, products',
  `target_id` int UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashiers`
--

CREATE TABLE `cashiers` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'bcrypt hash',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL COMMENT 'admin.id who created this account',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cashiers`
--

INSERT INTO `cashiers` (`id`, `full_name`, `username`, `email`, `email_verified`, `email_verified_at`, `password`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Waren', 'waren_cash', NULL, 0, NULL, '$2y$12$ZQZ6Jq.srqY5QfEBncR96uHLEvSROpwYo6lyyWgmAEh24OQq7nvBa', 0, 1, '2026-03-03 20:59:56', '2026-03-08 10:17:33'),
(2, 'Cashier Jhulmar', 'cashier', NULL, 0, NULL, '$2y$12$xJtIz2IpOIK6mP85AuOyBOS/0smtrbBNhy7gP7aWNxTkho8P.e4Qi', 1, 1, '2026-03-08 10:18:17', '2026-04-20 17:12:43'),
(3, 'Ara Harina', 'Ara01', NULL, 0, NULL, '$2y$12$Wl5PI/2Vppmu9Ld54O5Wk.aphpp6RshWEB0KlIKQtPpHZCWAdEd9K', 1, 1, '2026-03-12 18:48:55', '2026-04-20 17:08:07'),
(4, 'Ara Harina', 'Ara00', 'araharina26@gmail.com', 0, NULL, '$2y$12$fuJjWo2xh32Q64c64HETdOQPXDid8a3/gWCH96KhYmHrDoXPyC8Ia', 1, 1, '2026-04-20 17:09:37', '2026-04-20 17:09:37');

-- --------------------------------------------------------

--
-- Table structure for table `cashier_sessions`
--

CREATE TABLE `cashier_sessions` (
  `id` int UNSIGNED NOT NULL,
  `cashier_id` int UNSIGNED NOT NULL,
  `login_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logout_at` datetime DEFAULT NULL COMMENT 'NULL = still logged in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `icon` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `sort_order`, `icon`, `created_at`) VALUES
(5, NULL, 'Coffees', 10, 'fa-mug-hot', '2026-03-13 15:44:20'),
(6, NULL, 'Other Drinks', 20, 'fa-glass-water', '2026-03-13 15:44:20'),
(7, NULL, 'Food', 30, 'fa-utensils', '2026-03-13 15:44:20'),
(8, NULL, 'Retail', 40, 'fa-bag-shopping', '2026-03-13 15:44:20'),
(9, 5, 'Hot Coffees', 11, 'fa-fire', '2026-03-13 15:51:25'),
(10, 5, 'Iced Coffees', 12, 'fa-ice-cream', '2026-03-13 15:51:25'),
(11, 5, 'Frappes & Blended', 13, 'fa-blender', '2026-03-13 15:51:25'),
(12, 6, 'Teas & Refreshers', 21, 'fa-leaf', '2026-03-13 15:51:25'),
(13, 6, 'Non-Coffee Drinks', 22, 'fa-mug-saucer', '2026-03-13 15:51:25'),
(14, 7, 'Pastries & Sweets', 31, 'fa-cake-candles', '2026-03-13 15:51:25'),
(15, 7, 'Hot Food & Savory', 32, 'fa-sandwich', '2026-03-13 15:51:25'),
(22, NULL, 'Add-ons', 1, NULL, '2026-04-11 14:48:58');

-- --------------------------------------------------------

--
-- Table structure for table `email_otps`
--

CREATE TABLE `email_otps` (
  `id` int UNSIGNED NOT NULL,
  `user_type` enum('student','faculty','cashier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` enum('verification','password_reset') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `attempts` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `faculty_id_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'bcrypt hash',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `id_declaration` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Faculty agreed to ID declaration',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int UNSIGNED NOT NULL,
  `order_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable e.g. ORD-20240101-0001',
  `order_type` enum('walk-in','pre-order') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','preparing','ready','claimed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `student_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL for walk-in, can also reference faculty',
  `cashier_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL until cashier processes',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `locked_by` int UNSIGNED DEFAULT NULL COMMENT 'Cashier ID who locked this order for preparation',
  `locked_at` datetime DEFAULT NULL COMMENT 'When the order was locked',
  `lock_expire_at` datetime DEFAULT NULL COMMENT 'When the lock expires (auto-unlock)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` tinyint NOT NULL,
  `price_at_time` decimal(8,2) NOT NULL COMMENT 'Snapshot price at time of order',
  `subtotal` decimal(10,2) NOT NULL COMMENT 'quantity * price_at_time',
  `customization_note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. Large · Less Sugar · +Oat Milk, +Extra Shot'
) ;

-- --------------------------------------------------------

--
-- Table structure for table `order_feedback`
--

CREATE TABLE `order_feedback` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL COMMENT 'One feedback per order',
  `student_id` int UNSIGNED NOT NULL,
  `cashier_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL for walk-in orders processed by unknown cashier',
  `rating` tinyint NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL COMMENT 'One payment per order',
  `payment_method` enum('cash','online','GCash','PayMaya','Online Banking') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_given` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','paid','refunded','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'For online payments (GCash, PayMaya, etc.)',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_denominations`
--

CREATE TABLE `payment_denominations` (
  `id` int UNSIGNED NOT NULL,
  `payment_id` int UNSIGNED NOT NULL,
  `denomination` decimal(8,2) NOT NULL COMMENT 'e.g. 1000, 500, 0.50',
  `quantity` smallint UNSIGNED NOT NULL DEFAULT '0',
  `subtotal` decimal(10,2) GENERATED ALWAYS AS ((`denomination` * `quantity`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bill and coin breakdown for cash payments';

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int UNSIGNED NOT NULL,
  `category_id` int UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `has_sizes` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = show Small/Medium/Large size picker',
  `has_sugar` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = show sugar level picker',
  `has_addons` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = show add-ons checkboxes',
  `price` decimal(8,2) NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `has_sizes`, `has_sugar`, `has_addons`, `price`, `image_path`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 9, 'Cafe Au Lait', '', 1, 1, 1, 80.00, 'c6b11b87d876682295d78df21102eac6.jpg', 1, '2026-03-03 20:55:38', '2026-03-15 17:44:07'),
(2, 9, 'Brewed Coffee', '', 1, 1, 1, 60.00, 'b9345dfc1c49330153cb35e8f9c26a82.webp', 1, '2026-03-03 21:11:23', '2026-03-15 17:44:07'),
(3, 13, 'Iced Chocolate', '', 1, 1, 1, 75.00, '6e9d3f5ced8d5ebfcb18ef85f05eeac8.webp', 1, '2026-03-03 21:11:53', '2026-03-15 17:44:07'),
(4, 12, 'Twinings Tea (English Breakfast)', '', 1, 0, 0, 60.00, '80eb3ee88acd5faf85be9d1973174a3e.webp', 1, '2026-03-03 21:12:03', '2026-03-15 18:45:03'),
(5, 15, 'Burger', '', 0, 0, 0, 35.00, 'a0d403a4da6b119b4ae29154db871479.jpg', 1, '2026-03-03 21:12:43', '2026-03-13 16:00:49'),
(6, 10, 'Iced Coffee', '', 1, 1, 1, 60.00, '158b2fc4d501f0b3eb9615a03bde52b5.jpg', 1, '2026-03-09 11:31:02', '2026-03-15 17:44:07'),
(7, 9, 'Espresso', '', 1, 1, 1, 60.00, 'b9b7b41627133ec686d16b3c27c083ef.jpg', 1, '2026-03-09 11:34:26', '2026-03-15 17:44:07'),
(8, 9, 'Americano', '', 1, 1, 1, 70.00, 'ce821fa64e41478ebc1edb1d09607c40.jpg', 1, '2026-03-09 11:37:04', '2026-04-06 16:48:21'),
(9, 9, 'Cappuccino', '', 1, 1, 1, 110.00, '47edf68a96c342dfe037a8127d3c9fbd.jpg', 1, '2026-03-09 11:40:48', '2026-03-15 17:44:07'),
(10, 9, 'Cafe Latte', '', 1, 1, 1, 110.00, '40a978d1a34ca0a5f619e652e67502bb.jpg', 1, '2026-03-09 11:43:08', '2026-03-15 17:44:07'),
(11, 9, 'Mocha', '', 1, 1, 1, 120.00, '03b7ef709f90a7242b2acaf4e807c69e.jpg', 1, '2026-03-09 11:44:05', '2026-03-15 17:44:07'),
(12, 9, 'Caramel Latte', '', 1, 1, 1, 125.00, 'eda5cd185e97c9cf90775d6dc7a76e8a.jpg', 1, '2026-03-09 11:45:46', '2026-03-15 17:44:07'),
(13, 9, 'Vanilla Latte', '', 1, 1, 1, 125.00, '61ec109d78517e76f65e6c24caa49dcb.jpg', 1, '2026-03-09 11:46:52', '2026-03-15 17:44:07'),
(14, 10, 'Hazelnut Latte', '', 1, 1, 1, 125.00, '1602bbf06cecedae058bb02b21beed2c.jpg', 1, '2026-03-09 11:48:09', '2026-03-15 17:44:07'),
(15, 13, 'Hot Chocolate', '', 1, 1, 1, 75.00, 'f7e8c55546f549a6cc7c8b0e126ed494.jpg', 1, '2026-03-09 11:54:20', '2026-03-15 17:44:07'),
(16, 13, 'Sago at Gulaman', '', 1, 1, 0, 60.00, '6aa750b308dd3481722beed9dc28bee4.jpg', 1, '2026-03-09 11:58:56', '2026-03-15 18:45:18'),
(17, 12, 'Lemongrass & Pandan', '', 1, 0, 0, 60.00, '4de00a9daecf04a3a6989ac82fbcd067.jpg', 1, '2026-03-09 12:00:37', '2026-03-15 18:44:57'),
(18, 15, 'Cheeseburger', '', 0, 0, 0, 40.00, 'fc481d857a8476e42dc871dc5a662214.jpg', 1, '2026-03-09 12:03:55', '2026-03-13 16:01:04'),
(19, 15, 'Burger with Egg', '', 0, 0, 0, 50.00, '4a4fbb5896c1581e66bbb25e1bde386f.jpg', 1, '2026-03-09 12:05:36', '2026-03-13 16:00:56'),
(20, 15, 'Nacho Craze', '', 0, 0, 0, 75.00, 'bc71d3fdba06f71b01744f36e54eec50.jpg', 1, '2026-03-09 12:07:02', '2026-03-13 16:01:09'),
(21, 14, 'Italian Spaghetti', '', 0, 0, 0, 100.00, 'aeee2173916d08aa1c6707d2f11ea6f8.jpg', 1, '2026-03-09 12:10:07', '2026-03-13 16:01:27'),
(22, 14, 'Pasta Bolognese', '', 0, 0, 0, 165.00, 'e4b0ea4cf325f66bceff11971d6c275f.jpg', 1, '2026-03-09 12:11:24', '2026-03-13 16:01:31'),
(23, 14, 'Chicken Cream Al Pesto', '', 0, 0, 0, 150.00, '25a31f59c83f1e6abbae49af2af139dd.jpg', 1, '2026-03-09 12:13:40', '2026-03-13 16:01:22'),
(24, 10, 'Michael Latte', 'Masarap', 1, 1, 1, 1000.00, 'a0afa427023ca8ab5a6420d5ff0deb67.jpg', 1, '2026-03-12 18:42:43', '2026-04-11 12:46:19');

-- --------------------------------------------------------

--
-- Table structure for table `product_ratings`
--

CREATE TABLE `product_ratings` (
  `id` int UNSIGNED NOT NULL,
  `feedback_id` int UNSIGNED NOT NULL COMMENT 'Links to order_feedback.id',
  `order_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `rating` tinyint NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `product_rating_summary`
-- (See below for the actual view)
--
CREATE TABLE `product_rating_summary` (
`avg_rating` decimal(6,2)
,`category_name` varchar(60)
,`five_star` decimal(23,0)
,`four_star` decimal(23,0)
,`image_path` varchar(255)
,`one_star` decimal(23,0)
,`product_id` int unsigned
,`product_name` varchar(150)
,`three_star` decimal(23,0)
,`total_ratings` bigint
,`total_sold` decimal(25,0)
,`two_star` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `refund_requests`
--

CREATE TABLE `refund_requests` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` int UNSIGNED DEFAULT NULL COMMENT 'admin.id',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'bcrypt hash',
  `id_declaration` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Student agreed to ID declaration',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `full_name`, `student_id_no`, `course`, `email`, `email_verified`, `email_verified_at`, `password`, `id_declaration`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 'Gabriel Joseph N. De Ramos', '2316-02173C', 'BS Information Technology', 'gabrielj.deramos09@gmail.com', 1, '2026-04-20 10:06:25', '$2y$12$Osw8SyNZWICYL/7DbQ0l0.6.SZ7gB9QPZJ1byBCWQhsT1MVSULDvi', 1, 1, '2026-04-20 10:05:26', '2026-04-20 10:08:17'),
(7, 'Jhulmar Bregonia', '2316-02097C', 'BS Information Technology', 'zxc.jhulmar@gmail.com', 1, '2026-04-20 12:29:05', '$2y$12$SIwDY0XEuB5vK7PbZ/soi.dTTF4BfOd5J3GU8lgDfL1IqWXXEGX5.', 1, 1, '2026-04-20 12:28:20', '2026-04-20 13:18:23'),
(8, 'Ara', '2316-02169C', 'BS Information Technology', 'araharina26@gmail.com', 1, '2026-04-20 16:42:57', '$2y$12$1dGUA9PRKSMVx97h4YkIAeO.oRIdmkkTlvBltWPD.tbf3RapC1Nwa', 1, 1, '2026-04-20 16:42:16', '2026-04-20 16:42:57'),
(9, 'Ara Harina', '2316-00000C', 'BS Information Technology', 'kylaarabellaharina@gmail.com', 0, NULL, '$2y$12$pM8lxrynzNmywPkNnzJUu.zia/sq77C2JKgXcmrxj9iMrPFox/jhS', 1, 1, '2026-04-20 17:03:12', '2026-04-20 17:03:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_actor` (`actor_type`,`actor_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `cashiers`
--
ALTER TABLE `cashiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_cashier_admin` (`created_by`);

--
-- Indexes for table `cashier_sessions`
--
ALTER TABLE `cashier_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cs_cashier` (`cashier_id`),
  ADD KEY `idx_cs_login_at` (`login_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `fk_cat_parent` (`parent_id`);

--
-- Indexes for table `email_otps`
--
ALTER TABLE `email_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_lookup` (`user_type`,`user_id`,`purpose`),
  ADD KEY `idx_otp_expires` (`expires_at`),
  ADD KEY `idx_otp_email` (`email`,`purpose`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `faculty_id_no` (`faculty_id_no`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `fk_order_cashier` (`cashier_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_type` (`order_type`),
  ADD KEY `idx_orders_created_at` (`created_at`),
  ADD KEY `idx_orders_student` (`student_id`),
  ADD KEY `idx_orders_locked` (`locked_by`,`locked_at`),
  ADD KEY `idx_orders_lock_expire` (`lock_expire_at`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_od_order` (`order_id`),
  ADD KEY `idx_od_product` (`product_id`);

--
-- Indexes for table `order_feedback`
--
ALTER TABLE `order_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `idx_fb_student` (`student_id`),
  ADD KEY `idx_fb_cashier` (`cashier_id`),
  ADD KEY `idx_fb_rating` (`rating`),
  ADD KEY `idx_fb_created` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`);

--
-- Indexes for table `payment_denominations`
--
ALTER TABLE `payment_denominations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_product_category` (`category_id`);

--
-- Indexes for table `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_order` (`product_id`,`order_id`),
  ADD KEY `fk_pr_feedback` (`feedback_id`),
  ADD KEY `fk_pr_order` (`order_id`),
  ADD KEY `idx_pr_product` (`product_id`),
  ADD KEY `idx_pr_student` (`student_id`),
  ADD KEY `idx_pr_rating` (`rating`);

--
-- Indexes for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_refund_order` (`order_id`),
  ADD KEY `fk_refund_student` (`student_id`),
  ADD KEY `fk_refund_admin` (`reviewed_by`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id_no` (`student_id_no`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashiers`
--
ALTER TABLE `cashiers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cashier_sessions`
--
ALTER TABLE `cashier_sessions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `email_otps`
--
ALTER TABLE `email_otps`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_feedback`
--
ALTER TABLE `order_feedback`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_denominations`
--
ALTER TABLE `payment_denominations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `product_ratings`
--
ALTER TABLE `product_ratings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund_requests`
--
ALTER TABLE `refund_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

-- --------------------------------------------------------

--
-- Structure for view `product_rating_summary`
--
DROP TABLE IF EXISTS `product_rating_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_rating_summary`  AS SELECT `p`.`id` AS `product_id`, `p`.`name` AS `product_name`, `p`.`image_path` AS `image_path`, `c`.`name` AS `category_name`, count(`pr`.`id`) AS `total_ratings`, round(avg(`pr`.`rating`),2) AS `avg_rating`, sum((`pr`.`rating` = 5)) AS `five_star`, sum((`pr`.`rating` = 4)) AS `four_star`, sum((`pr`.`rating` = 3)) AS `three_star`, sum((`pr`.`rating` = 2)) AS `two_star`, sum((`pr`.`rating` = 1)) AS `one_star`, coalesce((select sum(`od`.`quantity`) from `order_details` `od` where (`od`.`product_id` = `p`.`id`)),0) AS `total_sold` FROM ((`products` `p` left join `categories` `c` on((`p`.`category_id` = `c`.`id`))) left join `product_ratings` `pr` on((`pr`.`product_id` = `p`.`id`))) GROUP BY `p`.`id`, `p`.`name`, `p`.`image_path`, `c`.`name` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cashiers`
--
ALTER TABLE `cashiers`
  ADD CONSTRAINT `fk_cashier_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `cashier_sessions`
--
ALTER TABLE `cashier_sessions`
  ADD CONSTRAINT `fk_cs_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `cashiers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `cashiers` (`id`),
  ADD CONSTRAINT `fk_order_locked_by` FOREIGN KEY (`locked_by`) REFERENCES `cashiers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `fk_od_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_od_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `order_feedback`
--
ALTER TABLE `order_feedback`
  ADD CONSTRAINT `fk_fb_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `cashiers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fb_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fb_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `payment_denominations`
--
ALTER TABLE `payment_denominations`
  ADD CONSTRAINT `fk_denom_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD CONSTRAINT `fk_pr_feedback` FOREIGN KEY (`feedback_id`) REFERENCES `order_feedback` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pr_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD CONSTRAINT `fk_refund_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_refund_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_refund_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
