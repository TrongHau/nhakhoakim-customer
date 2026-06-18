-- ============================================================
-- Dashboard Promotion - SQL Script
-- ============================================================

-- 0. PromotionType (danh mục phân loại KM - admin quản lý)
CREATE TABLE IF NOT EXISTS `PromotionType` (
    `PromotionTypeId`  INT AUTO_INCREMENT PRIMARY KEY,
    `Code`             VARCHAR(20) NOT NULL UNIQUE,
    `Name`             VARCHAR(100) NOT NULL,
    `Description`      VARCHAR(255) DEFAULT NULL,
    `Color`            VARCHAR(20) DEFAULT NULL,
    `Priority`         INT DEFAULT 0,
    `Status`           TINYINT DEFAULT 1,
    `CreatedBy`        INT DEFAULT NULL,
    `CreatedDate`      DATETIME DEFAULT NULL,
    `UpdatedBy`        INT DEFAULT NULL,
    `UpdatedDate`      DATETIME DEFAULT NULL
);

INSERT INTO `PromotionType` (`Code`, `Name`, `Color`, `Priority`, `Status`) VALUES
('periodic',   'Mã KM định kỳ',          '#3B82F6', 1, 1),
('retention',  'Mã hỗ trợ chốt khách',   '#8B5CF6', 2, 1),
('warranty',   'Mã bảo hành',            '#10B981', 3, 1),
('complaint',  'Mã khiêu nại',           '#EF4444', 4, 1),
('staff',      'Mã nhân viên',           '#F59E0B', 5, 1),
('other',      'Mã khác',               '#6B7280', 6, 1);

-- 1. DashboardPromotionEfficencyDaily
CREATE TABLE IF NOT EXISTS `DashboardPromotionEfficencyDaily` (
    `SummaryDate`         DATE NOT NULL,
    `BranchId`            INT NOT NULL,
    `TotalUsageCount`     INT DEFAULT 0,
    `TotalDiscountAmount` DECIMAL(18,2) DEFAULT 0,
    `TotalOrderCount`     INT DEFAULT 0,
    `TotalOrderAmount`    DECIMAL(18,2) DEFAULT 0,
    `DependencyRate`      DECIMAL(5,2) DEFAULT 0,
    `CreatedDate`         DATETIME DEFAULT NULL,
    `UpdatedDate`         DATETIME DEFAULT NULL,
    PRIMARY KEY (`SummaryDate`, `BranchId`)
);

-- 2. DashboardPromotionEfficencyByType
CREATE TABLE IF NOT EXISTS `DashboardPromotionEfficencyByType` (
    `SummaryDate`         DATE NOT NULL,
    `BranchId`            INT NOT NULL,
    `PromotionTypeId`     INT NOT NULL,
    `UsageCount`          INT DEFAULT 0,
    `UsagePercent`        DECIMAL(5,2) DEFAULT 0,
    `TotalDiscountAmount` DECIMAL(18,2) DEFAULT 0,
    `CreatedDate`         DATETIME DEFAULT NULL,
    `UpdatedDate`         DATETIME DEFAULT NULL,
    PRIMARY KEY (`SummaryDate`, `BranchId`, `PromotionTypeId`)
);

-- 3. DashboardPromotionEfficencyByService
CREATE TABLE IF NOT EXISTS `DashboardPromotionEfficencyByService` (
    `SummaryDate`         DATE NOT NULL,
    `BranchId`            INT NOT NULL,
    `ServiceCategoryId`   VARCHAR(5) NOT NULL,
    `TotalService`        INT DEFAULT 0,
    `UsageCount`          INT DEFAULT 0,
    `UsagePercent`        DECIMAL(5,2) DEFAULT 0,
    `TotalDiscountAmount` DECIMAL(18,2) DEFAULT 0,
    `CreatedDate`         DATETIME DEFAULT NULL,
    `UpdatedDate`         DATETIME DEFAULT NULL,
    PRIMARY KEY (`SummaryDate`, `BranchId`, `ServiceCategoryId`)
);

-- 4. DashboardPromotionEfficencyByBranch
CREATE TABLE IF NOT EXISTS `DashboardPromotionEfficencyByBranch` (
    `SummaryDate`         DATE NOT NULL,
    `BranchId`            INT NOT NULL,
    `TotalOrderCount`     INT DEFAULT 0,
    `TotalUsageCount`     INT DEFAULT 0,
    `TotalDiscountAmount` DECIMAL(18,2) DEFAULT 0,
    `TotalOrderAmount`    DECIMAL(18,2) DEFAULT 0,
    `DependencyRate`      DECIMAL(5,2) DEFAULT 0,
    `EvaluationLevel`     TINYINT DEFAULT 1,
    `CreatedDate`         DATETIME DEFAULT NULL,
    `UpdatedDate`         DATETIME DEFAULT NULL,
    PRIMARY KEY (`SummaryDate`, `BranchId`)
);
