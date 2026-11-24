-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2026 at 12:47 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `erp_p2p`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_sequences`
--

CREATE TABLE `document_sequences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(30) NOT NULL,
  `last_number` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_sequences`
--

INSERT INTO `document_sequences` (`id`, `document_type`, `last_number`, `created_at`, `updated_at`) VALUES
(1, 'PO', 4, '2026-11-19 08:44:16', '2026-11-19 14:19:21'),
(2, 'GRN', 2, '2026-11-19 14:28:35', '2026-11-19 15:05:54');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipts`
--

CREATE TABLE `goods_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `grn_number` varchar(40) NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `received_by` bigint(20) UNSIGNED NOT NULL,
  `receipt_date` date NOT NULL,
  `delivery_reference` varchar(100) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'received',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `goods_receipts`
--

INSERT INTO `goods_receipts` (`id`, `grn_number`, `purchase_order_id`, `supplier_id`, `warehouse_id`, `received_by`, `receipt_date`, `delivery_reference`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'GRN-2026-000001', 1, 1, 1, 4, '2026-11-19', 'DC-STEEL-001', 'received', 'First delivery received', '2026-11-19 15:04:42', '2026-11-19 15:04:42'),
(2, 'GRN-2026-000002', 1, 1, 1, 4, '2026-11-19', 'DC-STEEL-002', 'received', '2nd delivery', '2026-11-19 15:05:54', '2026-11-19 15:05:54');

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipt_items`
--

CREATE TABLE `goods_receipt_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `goods_receipt_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_item_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `received_quantity` decimal(15,3) NOT NULL,
  `qc_status` varchar(30) NOT NULL DEFAULT 'pending_qc',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `goods_receipt_items`
--

INSERT INTO `goods_receipt_items` (`id`, `goods_receipt_id`, `purchase_order_item_id`, `item_id`, `received_quantity`, `qc_status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 600.000, 'pending_qc', '2026-11-19 15:04:42', '2026-11-19 15:04:42'),
(2, 2, 1, 1, 250.000, 'pending_qc', '2026-11-19 15:05:54', '2026-11-19 15:05:54');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `available_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `rejected_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_balances`
--

INSERT INTO `inventory_balances` (`id`, `warehouse_id`, `item_id`, `available_quantity`, `rejected_quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 600.000, 0.000, '2026-11-20 13:21:39', '2026-11-20 13:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_postings`
--

CREATE TABLE `inventory_postings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `posting_number` varchar(40) NOT NULL,
  `goods_receipt_id` bigint(20) UNSIGNED NOT NULL,
  `qc_inspection_id` bigint(20) UNSIGNED DEFAULT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `posted_by` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `posted_at` timestamp NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_postings`
--

INSERT INTO `inventory_postings` (`id`, `posting_number`, `goods_receipt_id`, `qc_inspection_id`, `warehouse_id`, `posted_by`, `status`, `posted_at`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'IP-2026-000001', 1, 1, 1, 4, 'posted', '2026-11-20 13:21:39', 'Posted from Inventory module', '2026-11-20 13:21:39', '2026-11-20 13:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_posting_lines`
--

CREATE TABLE `inventory_posting_lines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inventory_posting_id` bigint(20) UNSIGNED NOT NULL,
  `goods_receipt_item_id` bigint(20) UNSIGNED NOT NULL,
  `accepted_quantity` decimal(15,3) NOT NULL,
  `rejected_quantity` decimal(15,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_posting_lines`
--

INSERT INTO `inventory_posting_lines` (`id`, `inventory_posting_id`, `goods_receipt_item_id`, `accepted_quantity`, `rejected_quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 600.000, 0.000, '2026-11-20 13:21:39', '2026-11-20 13:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `uom` varchar(20) NOT NULL DEFAULT 'PCS',
  `requires_qc` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `code`, `name`, `category`, `uom`, `requires_qc`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ITM001', 'Steel Plate', 'Raw Material', 'PCS', 1, 1, '2026-11-19 08:35:07', '2026-11-19 08:35:07');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_11_19_133524_create_personal_access_tokens_table', 2),
(5, '2026_11_19_134452_create_roles_table', 3),
(6, '2026_11_19_134453_create_permissions_table', 3),
(7, '2026_11_19_134454_create_role_user_table', 3),
(8, '2026_11_19_134455_create_permission_role_table', 3),
(9, '2026_11_19_134456_add_is_active_to_users_table', 3),
(10, '2026_11_19_140152_create_items_table', 4),
(11, '2026_11_19_140153_create_suppliers_table', 4),
(12, '2026_11_19_140154_create_purchase_orders_table', 4),
(13, '2026_11_19_140155_create_purchase_order_items_table', 4),
(14, '2026_11_19_140156_create_purchase_order_approvals_table', 4),
(15, '2026_11_19_141250_create_document_sequences_table', 5),
(16, '2026_11_19_193048_create_purchase_order_activities_table', 6),
(17, '2026_11_19_195443_create_warehouses_table', 7),
(18, '2026_11_19_195444_create_goods_receipts_table', 7),
(19, '2026_11_19_195445_create_goods_receipt_items_table', 7),
(20, '2026_11_20_091007_create_qc_inspections_table', 8),
(21, '2026_11_20_091008_create_qc_inspection_items_table', 8),
(22, '2026_11_20_091009_create_qc_inspection_activities_table', 8),
(23, '2026_11_20_104628_create_inventory_balances_table', 9),
(24, '2026_11_20_104629_create_inventory_postings_table', 9),
(25, '2026_11_20_104630_create_stock_movements_table', 9),
(26, '2026_11_20_182245_create_inventory_posting_lines_table', 10),
(27, '2026_11_20_185845_create_supplier_invoices_table', 11),
(28, '2026_11_20_185846_create_supplier_invoice_items_table', 11),
(29, '2026_11_20_185847_create_supplier_invoice_activities_table', 11),
(30, '2026_11_21_082018_create_supplier_payments_table', 12),
(31, '2026_11_21_082019_create_supplier_payment_activities_table', 12);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
(1, 'Requisition Create', 'requisition.create', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(2, 'Requisition View', 'requisition.view', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(3, 'Po Create', 'po.create', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(4, 'Po View', 'po.view', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(5, 'Po Approve', 'po.approve', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(6, 'Grn Create', 'grn.create', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(7, 'Grn View', 'grn.view', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(8, 'Qc Inspect', 'qc.inspect', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(9, 'Qc Approve', 'qc.approve', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(10, 'Stock View', 'stock.view', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(11, 'Stock Adjust', 'stock.adjust', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(12, 'Invoice Create', 'invoice.create', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(13, 'Invoice Approve', 'invoice.approve', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(14, 'Payment Record', 'payment.record', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(15, 'Users Manage', 'users.manage', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(16, 'Po Edit', 'po.edit', '2026-11-19 08:42:42', '2026-11-19 08:42:42'),
(17, 'Po Submit', 'po.submit', '2026-11-19 08:42:42', '2026-11-19 08:42:42'),
(18, 'Po Reject', 'po.reject', '2026-11-19 08:42:42', '2026-11-19 08:42:42'),
(19, 'Po Cancel', 'po.cancel', '2026-11-19 14:04:03', '2026-11-19 14:04:03'),
(20, 'Qc View', 'qc.view', '2026-11-20 03:43:53', '2026-11-20 03:43:53'),
(21, 'Qc Create', 'qc.create', '2026-11-20 03:43:53', '2026-11-20 03:43:53'),
(22, 'Qc Submit', 'qc.submit', '2026-11-20 03:43:53', '2026-11-20 03:43:53'),
(23, 'Qc Reject', 'qc.reject', '2026-11-20 03:43:53', '2026-11-20 03:43:53'),
(24, 'Stock Post', 'stock.post', '2026-11-20 12:49:43', '2026-11-20 12:49:43'),
(25, 'Invoice View', 'invoice.view', '2026-11-20 14:08:48', '2026-11-20 14:08:48'),
(26, 'View ERP Dashboard', 'dashboard.view', '2026-11-21 03:56:10', '2026-11-21 03:56:10'),
(27, 'View ERP Reports', 'reports.view', '2026-11-21 04:21:38', '2026-11-21 04:21:38');

-- --------------------------------------------------------

--
-- Table structure for table `permission_role`
--

CREATE TABLE `permission_role` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permission_role`
--

INSERT INTO `permission_role` (`id`, `role_id`, `permission_id`) VALUES
(60, 1, 1),
(61, 1, 2),
(54, 1, 3),
(58, 1, 4),
(52, 1, 5),
(47, 1, 6),
(48, 1, 7),
(59, 1, 8),
(34, 1, 9),
(63, 1, 10),
(62, 1, 11),
(50, 1, 12),
(49, 1, 13),
(51, 1, 14),
(64, 1, 15),
(55, 1, 16),
(57, 1, 17),
(56, 1, 18),
(53, 1, 19),
(38, 1, 20),
(35, 1, 21),
(37, 1, 22),
(36, 1, 23),
(67, 1, 24),
(69, 1, 25),
(73, 1, 26),
(77, 1, 27),
(1, 2, 1),
(2, 2, 2),
(5, 3, 2),
(3, 3, 3),
(4, 3, 4),
(39, 3, 7),
(22, 3, 16),
(23, 3, 17),
(25, 3, 19),
(40, 3, 20),
(7, 4, 4),
(6, 4, 5),
(41, 4, 7),
(24, 4, 18),
(42, 4, 20),
(76, 4, 26),
(80, 4, 27),
(10, 5, 4),
(8, 5, 6),
(9, 5, 7),
(68, 5, 10),
(43, 5, 20),
(65, 5, 24),
(11, 6, 7),
(12, 6, 8),
(44, 6, 20),
(26, 6, 21),
(27, 6, 22),
(13, 7, 7),
(14, 7, 9),
(46, 7, 20),
(45, 7, 23),
(16, 8, 10),
(15, 8, 11),
(66, 8, 24),
(75, 8, 26),
(79, 8, 27),
(21, 9, 4),
(17, 9, 7),
(19, 9, 12),
(20, 9, 14),
(72, 9, 25),
(70, 12, 13),
(71, 12, 25),
(74, 12, 26),
(78, 12, 27);

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `po_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `created_by`, `po_date`, `expected_delivery_date`, `status`, `total_amount`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'PO-2026-0001', 1, 2, '2026-11-19', NULL, 'approved', 250000.00, NULL, '2026-11-19 08:35:32', '2026-11-19 09:03:56'),
(2, 'PO-2026-000002', 1, 2, '2026-11-19', '2026-11-21', 'draft', 100000.00, 'test', '2026-11-19 14:14:32', '2026-11-19 14:15:05'),
(3, 'PO-2026-000003', 1, 2, '2026-11-19', '2026-11-26', 'cancelled', 9998.60, 'test', '2026-11-19 14:15:37', '2026-11-19 14:15:57'),
(4, 'PO-2026-000004', 1, 2, '2026-11-19', '2026-11-24', 'approved', 160800.00, 'test', '2026-11-19 14:19:21', '2026-11-19 14:20:48');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_activities`
--

CREATE TABLE `purchase_order_activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(30) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_order_activities`
--

INSERT INTO `purchase_order_activities` (`id`, `purchase_order_id`, `user_id`, `action`, `from_status`, `to_status`, `reason`, `old_values`, `new_values`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'updated', 'draft', 'draft', NULL, '{\"supplier_id\":1,\"status\":\"draft\",\"po_date\":\"2026-11-19\",\"expected_delivery_date\":\"2026-11-21\",\"total_amount\":\"10000.00\",\"remarks\":\"test\",\"items\":[{\"item_id\":1,\"quantity\":\"100.000\",\"unit_price\":\"100.00\",\"line_total\":\"10000.00\"}]}', '{\"supplier_id\":1,\"status\":\"draft\",\"po_date\":\"2026-11-19\",\"expected_delivery_date\":\"2026-11-21\",\"total_amount\":100000,\"remarks\":\"test\",\"items\":[{\"item_id\":1,\"quantity\":\"100.000\",\"unit_price\":\"1000.00\",\"line_total\":\"100000.00\"}]}', '2026-11-19 14:15:05', '2026-11-19 14:15:05'),
(2, 3, 2, 'cancelled', 'draft', 'cancelled', 'Production requirement was withdrawn', '{\"supplier_id\":1,\"status\":\"draft\",\"po_date\":\"2026-11-19\",\"expected_delivery_date\":\"2026-11-26\",\"total_amount\":\"9998.60\",\"remarks\":\"test\",\"items\":[{\"item_id\":1,\"quantity\":\"10.000\",\"unit_price\":\"999.86\",\"line_total\":\"9998.60\"}]}', '{\"supplier_id\":1,\"status\":\"cancelled\",\"po_date\":\"2026-11-19\",\"expected_delivery_date\":\"2026-11-26\",\"total_amount\":\"9998.60\",\"remarks\":\"test\",\"items\":[{\"item_id\":1,\"quantity\":\"10.000\",\"unit_price\":\"999.86\",\"line_total\":\"9998.60\"}]}', '2026-11-19 14:15:57', '2026-11-19 14:15:57');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_approvals`
--

CREATE TABLE `purchase_order_approvals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `approver_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `action_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_order_approvals`
--

INSERT INTO `purchase_order_approvals` (`id`, `purchase_order_id`, `approver_id`, `action`, `remarks`, `action_at`, `created_at`, `updated_at`) VALUES
(1, 1, 3, 'approved', NULL, '2026-11-19 09:03:56', '2026-11-19 09:03:56', '2026-11-19 09:03:56'),
(2, 4, 3, 'approved', NULL, '2026-11-19 14:20:48', '2026-11-19 14:20:48', '2026-11-19 14:20:48');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `item_id`, `quantity`, `unit_price`, `line_total`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1000.000, 250.00, 250000.00, '2026-11-19 08:35:32', '2026-11-19 08:35:32'),
(3, 2, 1, 100.000, 1000.00, 100000.00, '2026-11-19 14:15:05', '2026-11-19 14:15:05'),
(4, 3, 1, 10.000, 999.86, 9998.60, '2026-11-19 14:15:37', '2026-11-19 14:15:37'),
(5, 4, 1, 134.000, 1200.00, 160800.00, '2026-11-19 14:19:21', '2026-11-19 14:19:21');

-- --------------------------------------------------------

--
-- Table structure for table `qc_inspections`
--

CREATE TABLE `qc_inspections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inspection_number` varchar(40) NOT NULL,
  `goods_receipt_id` bigint(20) UNSIGNED NOT NULL,
  `inspected_by` bigint(20) UNSIGNED NOT NULL,
  `decided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `inspection_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `decision_reason` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `qc_inspections`
--

INSERT INTO `qc_inspections` (`id`, `inspection_number`, `goods_receipt_id`, `inspected_by`, `decided_by`, `status`, `inspection_date`, `remarks`, `decision_reason`, `submitted_at`, `decided_at`, `created_at`, `updated_at`) VALUES
(1, 'QC-2026-000001', 1, 5, 6, 'approved', '2026-11-19', 'test quality by praveenkumar', NULL, '2026-11-20 04:23:33', '2026-11-20 04:30:49', '2026-11-20 04:23:23', '2026-11-20 04:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `qc_inspection_activities`
--

CREATE TABLE `qc_inspection_activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `qc_inspection_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(40) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `reason` text DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `qc_inspection_activities`
--

INSERT INTO `qc_inspection_activities` (`id`, `qc_inspection_id`, `user_id`, `action`, `from_status`, `to_status`, `reason`, `snapshot`, `created_at`, `updated_at`) VALUES
(1, 1, 5, 'created', NULL, 'draft', NULL, '{\"inspection_number\":\"QC-2026-000001\",\"goods_receipt_id\":1,\"status\":\"draft\",\"items\":[{\"goods_receipt_item_id\":1,\"accepted_quantity\":\"600.000\",\"rejected_quantity\":\"0.000\"}]}', '2026-11-20 04:23:24', '2026-11-20 04:23:24'),
(2, 1, 5, 'submitted', 'draft', 'pending_approval', NULL, '{\"inspection_number\":\"QC-2026-000001\",\"goods_receipt_id\":1,\"status\":\"pending_approval\",\"items\":[{\"goods_receipt_item_id\":1,\"accepted_quantity\":\"600.000\",\"rejected_quantity\":\"0.000\"}]}', '2026-11-20 04:23:33', '2026-11-20 04:23:33'),
(3, 1, 6, 'approved', 'pending_approval', 'approved', NULL, '{\"inspection_number\":\"QC-2026-000001\",\"goods_receipt_id\":1,\"status\":\"approved\",\"items\":[{\"goods_receipt_item_id\":1,\"accepted_quantity\":\"600.000\",\"rejected_quantity\":\"0.000\"}]}', '2026-11-20 04:30:49', '2026-11-20 04:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `qc_inspection_items`
--

CREATE TABLE `qc_inspection_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `qc_inspection_id` bigint(20) UNSIGNED NOT NULL,
  `goods_receipt_item_id` bigint(20) UNSIGNED NOT NULL,
  `accepted_quantity` decimal(15,3) NOT NULL,
  `rejected_quantity` decimal(15,3) NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `qc_inspection_items`
--

INSERT INTO `qc_inspection_items` (`id`, `qc_inspection_id`, `goods_receipt_item_id`, `accepted_quantity`, `rejected_quantity`, `rejection_reason`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 600.000, 0.000, NULL, NULL, '2026-11-20 04:23:24', '2026-11-20 04:23:24');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(2, 'Requester', 'requester', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(3, 'Buyer', 'buyer', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(4, 'Purchase Manager', 'purchase-manager', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(5, 'Warehouse Officer', 'warehouse-officer', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(6, 'QC Inspector', 'qc-inspector', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(7, 'QC Manager', 'qc-manager', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(8, 'Inventory Manager', 'inventory-manager', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(9, 'Accounts Officer', 'accounts-officer', '2026-11-19 08:18:51', '2026-11-19 08:18:51'),
(12, 'Finance Manager', 'finance-manager', '2026-11-20 14:23:13', '2026-11-20 14:23:13');

-- --------------------------------------------------------

--
-- Table structure for table `role_user`
--

CREATE TABLE `role_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_user`
--

INSERT INTO `role_user` (`id`, `user_id`, `role_id`) VALUES
(1, 1, 1),
(2, 2, 3),
(3, 3, 4),
(4, 4, 5),
(5, 5, 6),
(6, 6, 7),
(7, 7, 9),
(8, 8, 12);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('Ub9XullcEdWRr28KkQmCB6QXRPGDrNNVUD8x9Jnp', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJWc1V3NlBUcndqbXNHNU9wNGlDVWRIM2FtYTA5NHBUWW5RTjVWcU82IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9lcnBcL3JlcG9ydHM/cmVwb3J0PWludm9pY2VzJnV0bV9zb3VyY2U9Y2hhdGdwdC5jb20iLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1795254748),
('ZGVLRQHt9m9i7tMqyXrEqt8AbhhoEtY3nOLieAf2', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiI0SE9zMnpCcFdIeDNSdE1FaDJ0Tm52TEVWRURWQjlzaGZCMXM2ZGZzIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2VycFwvcmVwb3J0cz9yZXBvcnQ9aW52ZW50b3J5Iiwicm91dGUiOm51bGx9LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OCwicGFzc3dvcmRfaGFzaF93ZWIiOiIzZjY1MmFlNzY3YjdlMzE1ZDE3MTJlZjhlMzRkNmMwMGI5MzMzYjlmN2VlNmY0YjY5ZjM0MGQ2NzY4ZmUwMDhlIn0=', 1795255604);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inventory_posting_id` bigint(20) UNSIGNED NOT NULL,
  `goods_receipt_item_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` varchar(30) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `balance_before` decimal(15,3) NOT NULL,
  `balance_after` decimal(15,3) NOT NULL,
  `movement_date` timestamp NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `inventory_posting_id`, `goods_receipt_item_id`, `warehouse_id`, `item_id`, `movement_type`, `quantity`, `balance_before`, `balance_after`, `movement_date`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 'receipt_accepted', 600.000, 0.000, 600.000, '2026-11-20 13:21:39', NULL, '2026-11-20 13:21:39', '2026-11-20 13:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `gst_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `code`, `name`, `email`, `phone`, `gst_number`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SUP001', 'ABC Steel Suppliers', 'supplier@example.test', '9876543210', NULL, NULL, 1, '2026-11-19 08:35:14', '2026-11-19 08:35:14');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoices`
--

CREATE TABLE `supplier_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(40) NOT NULL,
  `supplier_invoice_number` varchar(100) NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_invoices`
--

INSERT INTO `supplier_invoices` (`id`, `invoice_number`, `supplier_invoice_number`, `supplier_id`, `purchase_order_id`, `invoice_date`, `due_date`, `status`, `subtotal`, `tax_amount`, `total_amount`, `currency`, `created_by`, `approved_by`, `submitted_at`, `approved_at`, `remarks`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 'SI-2026-000001', 'ABC-INV-2026-001', 1, 1, '2026-11-21', '2026-11-22', 'approved', 150000.00, 0.00, 150000.00, 'INR', 7, 8, '2026-11-20 14:59:06', '2026-11-20 15:00:00', 'test invoice', NULL, '2026-11-20 13:53:51', '2026-11-20 15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoice_activities`
--

CREATE TABLE `supplier_invoice_activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_invoice_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_invoice_activities`
--

INSERT INTO `supplier_invoice_activities` (`id`, `supplier_invoice_id`, `user_id`, `action`, `from_status`, `to_status`, `reason`, `snapshot`, `created_at`, `updated_at`) VALUES
(1, 1, 7, 'created', NULL, 'draft', NULL, '{\"invoice_number\":\"SI-2026-000001\",\"supplier_invoice_number\":\"ABC-INV-2026-001\",\"subtotal\":\"150000.00\",\"tax_amount\":\"0.00\",\"total_amount\":\"150000.00\",\"items\":[{\"purchase_order_item_id\":1,\"item_id\":1,\"invoiced_quantity\":\"600.000\",\"unit_price\":\"250.00\",\"line_amount\":\"150000.00\",\"matched_quantity\":\"0.000\",\"match_status\":\"not_checked\"}]}', '2026-11-20 13:53:51', '2026-11-20 13:53:51'),
(2, 1, 7, 'three_way_match', 'draft', 'matched', 'All invoice lines passed matching.', '{\"results\":[{\"purchase_order_item_id\":1,\"invoiced_quantity\":\"600.000\",\"accepted_posted_quantity\":\"600.000\",\"previously_reserved_quantity\":\"0.000\",\"match_status\":\"matched\",\"reasons\":[]}]}', '2026-11-20 14:03:15', '2026-11-20 14:03:15'),
(3, 1, 7, 'submitted_for_approval', 'matched', 'pending_approval', NULL, '{\"invoice_number\":\"SI-2026-000001\",\"supplier_invoice_number\":\"ABC-INV-2026-001\",\"total_amount\":\"150000.00\",\"currency\":\"INR\",\"actor_id\":7}', '2026-11-20 14:59:06', '2026-11-20 14:59:06'),
(4, 1, 8, 'approved', 'pending_approval', 'approved', NULL, '{\"invoice_number\":\"SI-2026-000001\",\"supplier_invoice_number\":\"ABC-INV-2026-001\",\"total_amount\":\"150000.00\",\"currency\":\"INR\",\"actor_id\":8}', '2026-11-20 15:00:00', '2026-11-20 15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoice_items`
--

CREATE TABLE `supplier_invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_invoice_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_item_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `invoiced_quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `line_amount` decimal(18,2) NOT NULL,
  `matched_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `match_status` varchar(30) NOT NULL DEFAULT 'not_checked',
  `mismatch_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_invoice_items`
--

INSERT INTO `supplier_invoice_items` (`id`, `supplier_invoice_id`, `purchase_order_item_id`, `item_id`, `invoiced_quantity`, `unit_price`, `line_amount`, `matched_quantity`, `match_status`, `mismatch_reason`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 600.000, 250.00, 150000.00, 600.000, 'matched', NULL, '2026-11-20 13:53:51', '2026-11-20 14:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_number` varchar(255) NOT NULL,
  `supplier_invoice_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `payment_method` enum('bank_transfer','upi','cheque','cash') NOT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'recorded',
  `recorded_by` bigint(20) UNSIGNED NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `payment_number`, `supplier_invoice_id`, `supplier_id`, `payment_date`, `amount`, `currency`, `payment_method`, `payment_reference`, `status`, `recorded_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'PAY-2026-000001', 1, 1, '2026-11-21', 100000.00, 'INR', 'bank_transfer', '1', 'recorded', 7, NULL, '2026-11-21 03:38:18', '2026-11-21 03:38:18'),
(2, 'PAY-2026-000002', 1, 1, '2026-11-21', 50000.00, 'INR', 'bank_transfer', 'UTR-ERP-TEST-003', 'recorded', 7, 'Final payment — development test', '2026-11-21 03:52:25', '2026-11-21 03:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payment_activities`
--

CREATE TABLE `supplier_payment_activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_payment_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payment_activities`
--

INSERT INTO `supplier_payment_activities` (`id`, `supplier_payment_id`, `user_id`, `action`, `from_status`, `to_status`, `reason`, `snapshot`, `created_at`, `updated_at`) VALUES
(1, 1, 7, 'recorded', NULL, 'recorded', NULL, '{\"payment_number\":\"PAY-2026-000001\",\"invoice_number\":\"SI-2026-000001\",\"supplier_invoice_id\":1,\"amount\":\"100000.00\",\"currency\":\"INR\",\"payment_method\":\"bank_transfer\",\"payment_reference\":\"1\",\"outstanding_before\":\"150000.00\",\"outstanding_after\":\"50000.00\"}', '2026-11-21 03:38:18', '2026-11-21 03:38:18'),
(2, 2, 7, 'recorded', NULL, 'recorded', NULL, '{\"payment_number\":\"PAY-2026-000002\",\"invoice_number\":\"SI-2026-000001\",\"supplier_invoice_id\":1,\"amount\":\"50000.00\",\"currency\":\"INR\",\"payment_method\":\"bank_transfer\",\"payment_reference\":\"UTR-ERP-TEST-003\",\"outstanding_before\":\"50000.00\",\"outstanding_after\":\"0.00\"}', '2026-11-21 03:52:25', '2026-11-21 03:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'ERP Administrator', 'admin@erp.test', NULL, '$2y$12$U7vdPwf6Yb/tOcYc5EqdjupBAFv1eiDhzv0iZCGw0Ar42kq.6kE8a', NULL, '2026-11-19 08:21:13', '2026-11-19 08:21:13', 1),
(2, 'Purchase Executive', 'buyer@erp.test', NULL, '$2y$12$wqxQzAOE295PBNAzkJGvKOCFD7oNE5jGZp9q37XO/G9X2wVMmXK4W', NULL, '2026-11-19 08:21:13', '2026-11-19 08:21:13', 1),
(3, 'Purchase Manager', 'manager@erp.test', NULL, '$2y$12$lVKiKpxmQnsC4gyVcSRIAOuNPvXCZa6AC7iQNp5Cuf7DIpp4ESyra', NULL, '2026-11-19 08:21:13', '2026-11-19 08:21:13', 1),
(4, 'Warehouse Officer', 'warehouse@erp.test', NULL, '$2y$12$lVKiKpxmQnsC4gyVcSRIAOuNPvXCZa6AC7iQNp5Cuf7DIpp4ESyra', NULL, '2026-11-19 14:33:35', '2026-11-19 14:33:35', 1),
(5, 'QC Inspector', 'qcinspector@erp.test', NULL, '$2y$12$lVKiKpxmQnsC4gyVcSRIAOuNPvXCZa6AC7iQNp5Cuf7DIpp4ESyra', NULL, '2026-11-20 03:44:07', '2026-11-20 03:44:07', 1),
(6, 'QC Manager', 'qcmanager@erp.test', NULL, '$2y$12$lVKiKpxmQnsC4gyVcSRIAOuNPvXCZa6AC7iQNp5Cuf7DIpp4ESyra', NULL, '2026-11-20 03:44:40', '2026-11-20 03:44:40', 1),
(7, 'Accounts Officer', 'accounts@erp.test', NULL, '$2y$12$lVKiKpxmQnsC4gyVcSRIAOuNPvXCZa6AC7iQNp5Cuf7DIpp4ESyra', NULL, '2026-11-20 13:48:50', '2026-11-20 13:48:50', 1),
(8, 'Finance Manager', 'finance@erp.test', NULL, '$2y$12$lVKiKpxmQnsC4gyVcSRIAOuNPvXCZa6AC7iQNp5Cuf7DIpp4ESyra', NULL, '2026-11-20 14:20:31', '2026-11-20 14:20:31', 1);

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `code`, `name`, `location`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'WH-HYD-01', 'Hyderabad Main Warehouse', 'Hyderabad', 1, '2026-11-19 14:28:35', '2026-11-19 14:28:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `document_sequences`
--
ALTER TABLE `document_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_sequences_document_type_unique` (`document_type`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `goods_receipts`
--
ALTER TABLE `goods_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grn_supplier_delivery_unique` (`supplier_id`,`delivery_reference`),
  ADD UNIQUE KEY `goods_receipts_grn_number_unique` (`grn_number`),
  ADD KEY `goods_receipts_warehouse_id_foreign` (`warehouse_id`),
  ADD KEY `goods_receipts_received_by_foreign` (`received_by`),
  ADD KEY `goods_receipts_purchase_order_id_status_index` (`purchase_order_id`,`status`);

--
-- Indexes for table `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grn_po_line_unique` (`goods_receipt_id`,`purchase_order_item_id`),
  ADD KEY `goods_receipt_items_item_id_foreign` (`item_id`),
  ADD KEY `goods_receipt_items_purchase_order_item_id_index` (`purchase_order_item_id`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_balance_warehouse_item_unique` (`warehouse_id`,`item_id`),
  ADD KEY `inventory_balances_item_id_foreign` (`item_id`);

--
-- Indexes for table `inventory_postings`
--
ALTER TABLE `inventory_postings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_postings_posting_number_unique` (`posting_number`),
  ADD KEY `inventory_postings_warehouse_id_foreign` (`warehouse_id`),
  ADD KEY `inventory_postings_posted_by_foreign` (`posted_by`),
  ADD KEY `inventory_posting_grn_status_index` (`goods_receipt_id`,`status`),
  ADD KEY `inventory_posting_qc_status_index` (`qc_inspection_id`,`status`);

--
-- Indexes for table `inventory_posting_lines`
--
ALTER TABLE `inventory_posting_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_posting_line_grn_item_unique` (`goods_receipt_item_id`),
  ADD KEY `inventory_posting_lines_inventory_posting_id_foreign` (`inventory_posting_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `items_code_unique` (`code`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_slug_unique` (`slug`);

--
-- Indexes for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_role_role_id_permission_id_unique` (`role_id`,`permission_id`),
  ADD KEY `permission_role_permission_id_foreign` (`permission_id`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_orders_po_number_unique` (`po_number`),
  ADD KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  ADD KEY `purchase_orders_created_by_foreign` (`created_by`);

--
-- Indexes for table `purchase_order_activities`
--
ALTER TABLE `purchase_order_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_activities_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_order_activities_user_id_foreign` (`user_id`);

--
-- Indexes for table `purchase_order_approvals`
--
ALTER TABLE `purchase_order_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_approvals_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_order_approvals_approver_id_foreign` (`approver_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_order_items_item_id_foreign` (`item_id`);

--
-- Indexes for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qc_inspections_inspection_number_unique` (`inspection_number`),
  ADD KEY `qc_inspections_inspected_by_foreign` (`inspected_by`),
  ADD KEY `qc_inspections_decided_by_foreign` (`decided_by`),
  ADD KEY `qc_inspections_goods_receipt_id_status_index` (`goods_receipt_id`,`status`);

--
-- Indexes for table `qc_inspection_activities`
--
ALTER TABLE `qc_inspection_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `qc_inspection_activities_qc_inspection_id_foreign` (`qc_inspection_id`),
  ADD KEY `qc_inspection_activities_user_id_foreign` (`user_id`);

--
-- Indexes for table `qc_inspection_items`
--
ALTER TABLE `qc_inspection_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qc_inspection_grn_line_unique` (`qc_inspection_id`,`goods_receipt_item_id`),
  ADD KEY `qc_inspection_items_goods_receipt_item_id_foreign` (`goods_receipt_item_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_slug_unique` (`slug`);

--
-- Indexes for table `role_user`
--
ALTER TABLE `role_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_user_user_id_role_id_unique` (`user_id`,`role_id`),
  ADD KEY `role_user_role_id_foreign` (`role_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_movement_receipt_line_type_unique` (`goods_receipt_item_id`,`movement_type`),
  ADD KEY `stock_movements_inventory_posting_id_foreign` (`inventory_posting_id`),
  ADD KEY `stock_movements_item_id_foreign` (`item_id`),
  ADD KEY `stock_movement_warehouse_item_date_index` (`warehouse_id`,`item_id`,`movement_date`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `suppliers_code_unique` (`code`);

--
-- Indexes for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_invoice_supplier_reference_unique` (`supplier_id`,`supplier_invoice_number`),
  ADD UNIQUE KEY `supplier_invoices_invoice_number_unique` (`invoice_number`),
  ADD KEY `supplier_invoices_created_by_foreign` (`created_by`),
  ADD KEY `supplier_invoices_approved_by_foreign` (`approved_by`),
  ADD KEY `supplier_invoices_purchase_order_id_status_index` (`purchase_order_id`,`status`),
  ADD KEY `supplier_invoices_supplier_id_invoice_date_index` (`supplier_id`,`invoice_date`);

--
-- Indexes for table `supplier_invoice_activities`
--
ALTER TABLE `supplier_invoice_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_invoice_activities_user_id_foreign` (`user_id`),
  ADD KEY `supplier_invoice_activities_supplier_invoice_id_created_at_index` (`supplier_invoice_id`,`created_at`);

--
-- Indexes for table `supplier_invoice_items`
--
ALTER TABLE `supplier_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_invoice_po_line_unique` (`supplier_invoice_id`,`purchase_order_item_id`),
  ADD KEY `supplier_invoice_items_item_id_foreign` (`item_id`),
  ADD KEY `supplier_invoice_items_purchase_order_item_id_match_status_index` (`purchase_order_item_id`,`match_status`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_payments_supplier_reference_unique` (`supplier_id`,`payment_reference`),
  ADD UNIQUE KEY `supplier_payments_payment_number_unique` (`payment_number`),
  ADD KEY `supplier_payments_recorded_by_foreign` (`recorded_by`),
  ADD KEY `supplier_payments_supplier_invoice_id_status_index` (`supplier_invoice_id`,`status`);

--
-- Indexes for table `supplier_payment_activities`
--
ALTER TABLE `supplier_payment_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_payment_activities_user_id_foreign` (`user_id`),
  ADD KEY `supplier_payment_activities_history_index` (`supplier_payment_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouses_code_unique` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `document_sequences`
--
ALTER TABLE `document_sequences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goods_receipts`
--
ALTER TABLE `goods_receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_postings`
--
ALTER TABLE `inventory_postings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_posting_lines`
--
ALTER TABLE `inventory_posting_lines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `permission_role`
--
ALTER TABLE `permission_role`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchase_order_activities`
--
ALTER TABLE `purchase_order_activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_order_approvals`
--
ALTER TABLE `purchase_order_approvals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qc_inspection_activities`
--
ALTER TABLE `qc_inspection_activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `qc_inspection_items`
--
ALTER TABLE `qc_inspection_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `role_user`
--
ALTER TABLE `role_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_invoice_activities`
--
ALTER TABLE `supplier_invoice_activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplier_invoice_items`
--
ALTER TABLE `supplier_invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_payment_activities`
--
ALTER TABLE `supplier_payment_activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `goods_receipts`
--
ALTER TABLE `goods_receipts`
  ADD CONSTRAINT `goods_receipts_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `goods_receipts_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `goods_receipts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `goods_receipts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `goods_receipt_items`
--
ALTER TABLE `goods_receipt_items`
  ADD CONSTRAINT `goods_receipt_items_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`),
  ADD CONSTRAINT `goods_receipt_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `goods_receipt_items_purchase_order_item_id_foreign` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`);

--
-- Constraints for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD CONSTRAINT `inventory_balances_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `inventory_balances_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `inventory_postings`
--
ALTER TABLE `inventory_postings`
  ADD CONSTRAINT `inventory_postings_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`),
  ADD CONSTRAINT `inventory_postings_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_postings_qc_inspection_id_foreign` FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections` (`id`),
  ADD CONSTRAINT `inventory_postings_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `inventory_posting_lines`
--
ALTER TABLE `inventory_posting_lines`
  ADD CONSTRAINT `inventory_posting_lines_goods_receipt_item_id_foreign` FOREIGN KEY (`goods_receipt_item_id`) REFERENCES `goods_receipt_items` (`id`),
  ADD CONSTRAINT `inventory_posting_lines_inventory_posting_id_foreign` FOREIGN KEY (`inventory_posting_id`) REFERENCES `inventory_postings` (`id`);

--
-- Constraints for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `purchase_order_activities`
--
ALTER TABLE `purchase_order_activities`
  ADD CONSTRAINT `purchase_order_activities_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `purchase_order_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_approvals`
--
ALTER TABLE `purchase_order_approvals`
  ADD CONSTRAINT `purchase_order_approvals_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_order_approvals_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`);

--
-- Constraints for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  ADD CONSTRAINT `qc_inspections_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `qc_inspections_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`),
  ADD CONSTRAINT `qc_inspections_inspected_by_foreign` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `qc_inspection_activities`
--
ALTER TABLE `qc_inspection_activities`
  ADD CONSTRAINT `qc_inspection_activities_qc_inspection_id_foreign` FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections` (`id`),
  ADD CONSTRAINT `qc_inspection_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `qc_inspection_items`
--
ALTER TABLE `qc_inspection_items`
  ADD CONSTRAINT `qc_inspection_items_goods_receipt_item_id_foreign` FOREIGN KEY (`goods_receipt_item_id`) REFERENCES `goods_receipt_items` (`id`),
  ADD CONSTRAINT `qc_inspection_items_qc_inspection_id_foreign` FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections` (`id`);

--
-- Constraints for table `role_user`
--
ALTER TABLE `role_user`
  ADD CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_goods_receipt_item_id_foreign` FOREIGN KEY (`goods_receipt_item_id`) REFERENCES `goods_receipt_items` (`id`),
  ADD CONSTRAINT `stock_movements_inventory_posting_id_foreign` FOREIGN KEY (`inventory_posting_id`) REFERENCES `inventory_postings` (`id`),
  ADD CONSTRAINT `stock_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `stock_movements_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD CONSTRAINT `supplier_invoices_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supplier_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supplier_invoices_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `supplier_invoices_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `supplier_invoice_activities`
--
ALTER TABLE `supplier_invoice_activities`
  ADD CONSTRAINT `supplier_invoice_activities_supplier_invoice_id_foreign` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`),
  ADD CONSTRAINT `supplier_invoice_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `supplier_invoice_items`
--
ALTER TABLE `supplier_invoice_items`
  ADD CONSTRAINT `supplier_invoice_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `supplier_invoice_items_purchase_order_item_id_foreign` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`),
  ADD CONSTRAINT `supplier_invoice_items_supplier_invoice_id_foreign` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`);

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `supplier_payments_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supplier_payments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `supplier_payments_supplier_invoice_id_foreign` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`);

--
-- Constraints for table `supplier_payment_activities`
--
ALTER TABLE `supplier_payment_activities`
  ADD CONSTRAINT `supplier_payment_activities_supplier_payment_id_foreign` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`),
  ADD CONSTRAINT `supplier_payment_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
