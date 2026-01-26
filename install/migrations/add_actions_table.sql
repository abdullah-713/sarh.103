-- =====================================================
-- نظام صرح الإتقان - Sarh Al-Itqan System
-- =====================================================
-- Migration: Add Actions Management Tables
-- Version: 1.0.0
-- Date: 2026-01-26
-- =====================================================
-- جداول نظام إدارة الإجراءات والمهام
-- Action/Task Management System Tables
-- =====================================================

-- جدول الإجراءات الرئيسي
-- Main Actions Table
CREATE TABLE IF NOT EXISTS `actions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'كود فريد مثل ACT-2026-00001',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `type` ENUM('request', 'task', 'approval', 'complaint', 'suggestion', 'other') NOT NULL DEFAULT 'request',
    `category` VARCHAR(100) NULL,
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `status` ENUM('draft', 'pending', 'in_progress', 'waiting_approval', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `requester_id` INT UNSIGNED NOT NULL,
    `requester_branch_id` INT UNSIGNED NULL,
    `assigned_to` INT UNSIGNED NULL,
    `assigned_by` INT UNSIGNED NULL,
    `assigned_at` DATETIME NULL,
    `due_date` DATE NULL,
    `start_date` DATE NULL,
    `completed_at` DATETIME NULL,
    `approval_level` TINYINT UNSIGNED DEFAULT 0,
    `max_approval_level` TINYINT UNSIGNED DEFAULT 1,
    `current_approver_id` INT UNSIGNED NULL,
    `final_approver_id` INT UNSIGNED NULL,
    `approval_notes` TEXT NULL,
    `attachments` JSON NULL,
    `metadata` JSON NULL,
    `leave_request_id` INT UNSIGNED NULL,
    `related_entity_type` VARCHAR(50) NULL,
    `related_entity_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_requester` (`requester_id`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_due_date` (`due_date`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_deleted` (`deleted_at`),
    CONSTRAINT `fk_action_requester` FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_action_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_action_branch` FOREIGN KEY (`requester_branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- جدول التعليقات والتحديثات
-- Action Comments and Timeline Table
CREATE TABLE IF NOT EXISTS `action_comments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `comment_type` ENUM('comment', 'status_change', 'assignment', 'approval', 'system') NOT NULL DEFAULT 'comment',
    `content` TEXT NOT NULL,
    `old_value` VARCHAR(100) NULL,
    `new_value` VARCHAR(100) NULL,
    `attachments` JSON NULL,
    `is_internal` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_action_id` (`action_id`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_comment_action` FOREIGN KEY (`action_id`) REFERENCES `actions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- جدول سلسلة الموافقات
-- Approval Chain Table
CREATE TABLE IF NOT EXISTS `action_approvals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action_id` INT UNSIGNED NOT NULL,
    `level` TINYINT UNSIGNED NOT NULL,
    `approver_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'skipped') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `decided_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_action_level` (`action_id`, `level`),
    INDEX `idx_approver` (`approver_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_approval_action` FOREIGN KEY (`action_id`) REFERENCES `actions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_approval_user` FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- جدول قوالب الإجراءات
-- Action Templates Table
CREATE TABLE IF NOT EXISTS `action_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('request', 'task', 'approval', 'complaint', 'suggestion', 'other') NOT NULL,
    `category` VARCHAR(100) NULL,
    `description_template` TEXT NULL,
    `default_priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `approval_levels` TINYINT UNSIGNED DEFAULT 1,
    `required_fields` JSON NULL,
    `form_schema` JSON NULL,
    `auto_assign_role_level` TINYINT NULL,
    `sla_hours` INT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- إدراج بيانات تجريبية للقوالب
-- Insert Sample Templates
INSERT INTO `action_templates` (`name`, `type`, `category`, `description_template`, `approval_levels`, `sla_hours`) VALUES
('طلب إجازة', 'request', 'leaves', 'طلب إجازة', 2, 24),
('طلب صيانة', 'request', 'maintenance', 'طلب صيانة', 1, 48),
('شكوى', 'complaint', 'general', 'شكوى', 2, 72),
('اقتراح تطوير', 'suggestion', 'improvement', 'اقتراح', 1, 168),
('مهمة إدارية', 'task', 'admin', 'مهمة', 0, NULL),
('طلب مستلزمات', 'request', 'supplies', 'طلب مستلزمات', 1, 24);

-- =====================================================
-- إضافة عمود action_id لجدول الإجازات
-- Add action_id column to leaves table
-- =====================================================
ALTER TABLE `leaves` 
ADD COLUMN `action_id` INT UNSIGNED NULL AFTER `status`,
ADD INDEX `idx_action_id` (`action_id`),
ADD CONSTRAINT `fk_leave_action` FOREIGN KEY (`action_id`) REFERENCES `actions`(`id`) ON DELETE SET NULL;
