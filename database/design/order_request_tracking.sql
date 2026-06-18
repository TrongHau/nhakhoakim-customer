-- ============================================================
-- OrderRequestTracking - Lưu lịch sử thay đổi OrderRequest
-- ============================================================

CREATE TABLE IF NOT EXISTS `OrderRequestTracking` (
    `OrderRequestTrackingId` INT AUTO_INCREMENT PRIMARY KEY,
    `OrderRequestId`         INT NOT NULL,
    -- ActionType: create|update|update_status|update_expected_delivery_date
    `ActionType`             VARCHAR(50) NOT NULL,
    -- OldStatus / NewStatus khi thay đổi trạng thái
    `OldStatus`              TINYINT DEFAULT NULL,
    `NewStatus`              TINYINT DEFAULT NULL,
    -- Snapshot data trước khi thay đổi (JSON)
    `OldData`                TEXT DEFAULT NULL,
    -- Snapshot data sau khi thay đổi (JSON)
    `NewData`                TEXT DEFAULT NULL,
    `Note`                   VARCHAR(500) DEFAULT NULL,
    `CreatedBy`              INT DEFAULT NULL,
    `CreatedDate`            DATETIME DEFAULT NULL,
    INDEX idx_order_request_id (`OrderRequestId`),
    INDEX idx_created_date (`CreatedDate`)
);
