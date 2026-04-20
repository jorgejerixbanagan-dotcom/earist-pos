-- Migration: Add Order Locking for Pre-orders
-- Date: 2026-04-20
-- Description: Adds locking mechanism to prevent multiple cashiers from working on the same pre-order

-- ============================================================
-- 1. Add locking columns to orders table
-- ============================================================
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `locked_by` int UNSIGNED DEFAULT NULL COMMENT 'Cashier ID who locked this order for preparation',
  ADD COLUMN IF NOT EXISTS `locked_at` datetime DEFAULT NULL COMMENT 'When the order was locked';

-- Add foreign key for locked_by
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_locked_by` FOREIGN KEY IF NOT EXISTS (`locked_by`) REFERENCES `cashiers` (`id`) ON DELETE SET NULL;

-- Add index for faster lock lookups
ALTER TABLE `orders`
  ADD KEY IF NOT EXISTS `idx_orders_locked` (`locked_by`, `locked_at`);

-- ============================================================
-- 2. Add lock_expire_at column (locks expire after 15 minutes of inactivity)
-- ============================================================
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `lock_expire_at` datetime DEFAULT NULL COMMENT 'When the lock expires (auto-unlock)';

-- ============================================================
-- 3. Create index for expired lock cleanup
-- ============================================================
ALTER TABLE `orders`
  ADD KEY IF NOT EXISTS `idx_orders_lock_expire` (`lock_expire_at`);

-- ============================================================
-- 4. Default lock timeout is 15 minutes
-- ============================================================
-- When setting a lock, use: DATE_ADD(NOW(), INTERVAL 15 MINUTE)