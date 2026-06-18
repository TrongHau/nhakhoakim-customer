# Database Design: Quản lý vật tư nha khoa

> **Issue:** #6595
> **Schema:** `inventory` (schema mới)
> **Tham chiếu:** [business_analytics.md](./business_analytics.md)

---

## 1. Tổng quan

Module **Quản lý vật tư nha khoa** sử dụng schema mới `inventory` chứa toàn bộ **15 tables mới**.

> **Lưu ý:** Bảng `pos.Material` hiện có được sử dụng cho mục đích khác (dịch vụ/điều trị), không dùng cho module này. Bảng `Material` mới trong schema `inventory` là bảng riêng biệt cho vật tư nha khoa.

Tham chiếu cross-schema:
- `in.Branch` — phòng khám chi nhánh
- `in.Staff` — nhân viên (CreatedBy, UpdatedBy)

---

## 2. Danh sách tables

| # | Table | Schema | Loại | Mô tả |
|---|-------|--------|------|--------|
| 1 | `InboutRequest` | `inventory` | NEW | Phiếu nhập kho |
| 2 | `InboutRequestDetail` | `inventory` | NEW | Chi tiết phiếu nhập kho |
| 3 | `Inventory` | `inventory` | NEW | Tồn kho theo kho/phòng khám |
| 4 | `InventoryTransaction` | `inventory` | NEW | Lịch sử nhập kho xuất kho |
| 5 | `OrderRequest` | `inventory` | NEW | Yêu cầu cấp phát vật tư từ phòng khám |
| 6 | `OrderRequestDetail` | `inventory` | NEW | Chi tiết vật tư trong yêu cầu cấp phát |
| 7 | `OutboutRequest` | `inventory` | NEW | Phiếu xuất kho giao phòng khám |
| 8 | `OutboutRequestDetail` | `inventory` | NEW | Chi tiết vật tư xuất kho |
| 9 | `Product` | `inventory` | NEW | Sản phẩm | Vật tư |
| 10 | `ProductCategory` | `inventory` | NEW | Danh mục sản phẩm | Danh mục vật tư |
| 11 | `PurchaseOrder` | `inventory` | NEW | Đơn hàng gửi NCC |
| 12 | `PurchaseOrderDetail` | `inventory` | NEW | Chi tiết đơn hàng gửi NCC |
| 13 | `Supplier` | `inventory` | NEW | Danh sách NCC |
| 14 | `Unit` | `inventory` | NEW | Đơn vị tính |
| 15 | `Warehouse` | `inventory` | NEW | Kho | phòng khám |

---

## 3. Quan hệ giữa các tables (ERD)

```

```

### Quan hệ chi tiết

---

## 4. Quy ước mã tự sinh

| Đối tượng | Format | Ví dụ | Cột |
|-----------|--------|-------|-----|
| Vật tư | `VTXXX` | VT001 | `Product.SKU` |
| Nhà cung cấp | `NCCXXX` | NCC001 | `Supplier.SupplierCode` |
| Yêu cầu cấp phát | `YC-YYYYMM-XX` | YC-202310-01 | `OrderRequest.OrderRequestCode` |
| Đơn đặt hàng NCC | `PO-YYMM-XXX` | PO-2311-001 | `PurchaseOrder.PurchaseOrderCode` |

---

## 5. Định nghĩa trạng thái

### 5.1 Product.Status

| Giá trị | Mô tả |
|---------|--------|
| 1 | Đang sử dụng |
| 0 | Ngưng |

### 5.2 Supplier.Status

| Giá trị | Mô tả |
|---------|--------|
| 1 | Hoạt động |
| 0 | Ngưng |

### 5.3 Warehouse.WarehouseType

| Giá trị | Mô tả |
|---------|--------|
| 1 | Công ty |
| 5 | Kho tạm |
| 10 | Phòng khám |

### 5.4 OrderRequest.RequestType

| Giá trị | Mô tả |
|---------|--------|
| 1 | Manual |
| 2 | Auto |

### 5.5 OrderRequest.Status

| Giá trị | Mô tả | Flow |
|---------|--------|------|
| 1 | Chờ duyệt | → 2 |
| 2 | Đã duyệt | → 3 |
| 3 | Đang soạn hàng | → 4 |
| 4 | Đang vận chuyển | → 5 |
| 5 | Đã hoàn tất | (kết thúc) |

### 5.6 OutboutRequest.Status

| Giá trị | Mô tả | Flow |
|---------|--------|------|
| 1 | Đang giao | → 2 |
| 2 | Đã xác nhận | (kết thúc) |

### 5.7 PurchaseOrder.Status

| Giá trị | Mô tả | Flow |
|---------|--------|------|
| 1 | Đang tạo | → 2 |
| 2 | Đã gửi nhà cung cấp | → 3 hoặc 4 |
| 3 | Đã nhận một phần | → 4 |
| 4 | Đã nhận đủ | (kết thúc) |

### 5.8 InboutRequest.Status

| Giá trị | Mô tả | Flow |
|---------|--------|------|
| 1 | Đang kiểm tra | → 2 |
| 2 | Đã nhập | (kết thúc) |

### 5.9 InventoryTransaction.TransactionType

| Giá trị | Mô tả | Dấu |
|---------|--------|-----|
| 1 | Nhập kho (từ NCC) | + |
| 2 | Xuất kho (giao cho phòng khám) | - |

---

## 6. Chi tiết DDL

---

### 6.1 InboutRequest (Phiếu nhập vật tư) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `InboutRequest`;

CREATE TABLE `InboutRequest` (
  `InboutRequestId` int(11) NOT NULL AUTO_INCREMENT,
  `PurchaseOrderId` int(11) DEFAULT NULL,
  `RelatedType` int(11) DEFAULT NULL,
  `RelatedId` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `ActualArrivalDate` datetime DEFAULT NULL,
  `TotalSKU` int(11) DEFAULT NULL,
  `IRCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` int(11) DEFAULT NULL,
  `RefOutboutRequestId` int(11) DEFAULT NULL,
  PRIMARY KEY (`InboutRequestId`),
  KEY `PurchaseOrderId` (`PurchaseOrderId`),
  CONSTRAINT `InboutRequest_ibfk_1` FOREIGN KEY (`PurchaseOrderId`) REFERENCES `PurchaseOrder` (`PurchaseOrderId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;
```

---
### 6.2 InboutRequestDetail (Chi tiết phiếu nhập vật tư) (NEW)

> **Lưu ý:** Bảng `pos.Material` hiện có được sử dụng cho mục đích khác (dịch vụ/điều trị).
> Bảng `inventory.InboutRequestDetail` này là bảng riêng biệt, quản lý danh mục vật tư nha khoa.

```sql
USE `inventory`;

DROP TABLE IF EXISTS `InboutRequestDetail`;

CREATE TABLE `InboutRequestDetail` (
  `InboutRequestDetailId` int(11) NOT NULL AUTO_INCREMENT,
  `InboutRequestId` int(11) DEFAULT NULL,
  `ProductId` int(11) DEFAULT NULL,
  `UnitId` int(11) DEFAULT NULL,
  `PartnerSKU` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ExpectedQty` int(11) DEFAULT NULL,
  `RefQty` int(11) DEFAULT NULL,
  `ActualQty` int(11) DEFAULT NULL,
  `ExceptionQty` int(11) DEFAULT NULL,
  `Price` decimal(18,2) DEFAULT NULL,
  `ExpirationDate` datetime DEFAULT NULL,
  `ManufactureDate` datetime DEFAULT NULL,
  `LOT` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`InboutRequestDetailId`),
  KEY `InboutRequestId` (`InboutRequestId`),
  KEY `ProductId` (`ProductId`),
  CONSTRAINT `InboutRequestDetail_ibfk_1` FOREIGN KEY (`InboutRequestId`) REFERENCES `InboutRequest` (`InboutRequestId`),
  CONSTRAINT `InboutRequestDetail_ibfk_2` FOREIGN KEY (`ProductId`) REFERENCES `Product` (`ProductId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;
```

---

### 6.3 Inventory (Kho) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `Inventory`;

CREATE TABLE `Inventory` (
  `InventoryId` int(11) NOT NULL AUTO_INCREMENT,
  `ProductId` int(11) DEFAULT NULL,
  `WarehouseId` int(11) DEFAULT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `MinStock` int(11) DEFAULT NULL,
  `TotalValue` decimal(18,2) DEFAULT NULL,
  `Status` tinyint(4) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`InventoryId`),
  KEY `WarehouseId` (`WarehouseId`),
  CONSTRAINT `Inventory_ibfk_1` FOREIGN KEY (`WarehouseId`) REFERENCES `Warehouse` (`WarehouseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.4 InventoryTransaction (Lịch sử kho) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `InventoryTransaction`;

CREATE TABLE `InventoryTransaction` (
  `InventoryTransactionId` int(11) NOT NULL AUTO_INCREMENT,
  `InventoryId` int(11) DEFAULT NULL,
  `TransactionType` tinyint(4) DEFAULT NULL COMMENT '1 inbout 2 outbout',
  `ProductId` int(11) DEFAULT NULL,
  `UnitId` int(11) DEFAULT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `ReferenceType` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ReferenceId` int(11) DEFAULT NULL,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`InventoryTransactionId`),
  KEY `InventoryId` (`InventoryId`),
  CONSTRAINT `InventoryTransaction_ibfk_1` FOREIGN KEY (`InventoryId`) REFERENCES `Inventory` (`InventoryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.5 OrderRequest (Đơn đặt từ PK) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `OrderRequest`;

CREATE TABLE `OrderRequest` (
  `OrderRequestId` int(11) NOT NULL AUTO_INCREMENT,
  `OrderRequestCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RequesterType` int(11) DEFAULT NULL,
  `RequesterId` int(11) DEFAULT NULL,
  `RequestDate` datetime DEFAULT NULL,
  `RequestType` tinyint(4) DEFAULT NULL COMMENT '1 manual 2 auto',
  `Status` tinyint(4) DEFAULT NULL,
  `RequestedBy` int(11) DEFAULT NULL,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `MaterialGroupId` int(11) DEFAULT NULL,
  `ProcessType` int(11) DEFAULT NULL COMMENT '1 tự đông 2 manual',
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`OrderRequestId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.6 OrderRequestDetail (Chi tiết đơn đặt từ PK) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `OrderRequestDetail`;

CREATE TABLE `OrderRequestDetail` (
  `OrderRequestDetailId` int(11) NOT NULL AUTO_INCREMENT,
  `OrderRequestId` int(11) DEFAULT NULL,
  `ProductId` int(11) DEFAULT NULL,
  `UnitId` int(11) DEFAULT NULL,
  `Specification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RequestQuantity` int(11) DEFAULT NULL,
  `ReceivedQuantity` int(11) DEFAULT NULL,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`OrderRequestDetailId`),
  KEY `OrderRequestId` (`OrderRequestId`),
  KEY `ProductId` (`ProductId`),
  CONSTRAINT `OrderRequestDetail_ibfk_1` FOREIGN KEY (`OrderRequestId`) REFERENCES `OrderRequest` (`OrderRequestId`),
  CONSTRAINT `OrderRequestDetail_ibfk_2` FOREIGN KEY (`ProductId`) REFERENCES `Product` (`ProductId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.7 OutboutRequest (Phiếu xuất kho) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `OutboutRequest`;

CREATE TABLE `OutboutRequest` (
  `OutboutRequestId` int(11) NOT NULL AUTO_INCREMENT,
  `PurchaseOrderId` int(11) DEFAULT NULL,
  `RelatedType` int(11) DEFAULT NULL,
  `RelatedId` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `ActualArrivalDate` datetime DEFAULT NULL,
  `TotalSKU` int(11) DEFAULT NULL,
  `IRCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` int(11) DEFAULT NULL,
  PRIMARY KEY (`OutboutRequestId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.8 OutboutRequestDetail (Chi tiết phiếu xuất kho) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `OutboutRequestDetail`;

CREATE TABLE `OutboutRequestDetail` (
  `OutboutRequestDetailId` int(11) NOT NULL AUTO_INCREMENT,
  `OutboutRequestId` int(11) DEFAULT NULL,
  `ProductId` int(11) DEFAULT NULL,
  `UnitId` int(11) DEFAULT NULL,
  `PartnerSKU` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ExpectedQty` int(11) DEFAULT NULL,
  `RefQty` int(11) DEFAULT NULL,
  `ActualQty` int(11) DEFAULT NULL,
  `ExceptionQty` int(11) DEFAULT NULL,
  `Price` decimal(18,2) DEFAULT NULL,
  `ExpirationDate` datetime DEFAULT NULL,
  `ManufactureDate` datetime DEFAULT NULL,
  `LOT` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`OutboutRequestDetailId`),
  KEY `OutboutRequestId` (`OutboutRequestId`),
  KEY `ProductId` (`ProductId`),
  CONSTRAINT `OutboutRequestDetail_ibfk_1` FOREIGN KEY (`OutboutRequestId`) REFERENCES `OutboutRequest` (`OutboutRequestId`),
  CONSTRAINT `OutboutRequestDetail_ibfk_2` FOREIGN KEY (`ProductId`) REFERENCES `Product` (`ProductId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.9 Product (Sản phẩm) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `Product`;

CREATE TABLE `Product` (
  `ProductId` int(11) NOT NULL AUTO_INCREMENT,
  `SKU` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NameUnsign` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `ProductCategoryId` int(11) DEFAULT NULL,
  `SupplierId` int(11) DEFAULT NULL,
  `UnitId` int(11) DEFAULT NULL,
  `IsTrackingSerial` tinyint(4) DEFAULT NULL,
  `Price` double DEFAULT NULL,
  `PackageType` int(11) DEFAULT NULL,
  `IsExpiryDate` tinyint(4) DEFAULT NULL,
  `Specification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Barcode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `Status` tinyint(4) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`ProductId`),
  KEY `ProductCategoryId` (`ProductCategoryId`),
  KEY `SupplierId` (`SupplierId`),
  KEY `UnitId` (`UnitId`),
  CONSTRAINT `Product_ibfk_1` FOREIGN KEY (`ProductCategoryId`) REFERENCES `ProductCategory` (`ProductCategoryId`),
  CONSTRAINT `Product_ibfk_2` FOREIGN KEY (`SupplierId`) REFERENCES `Supplier` (`SupplierId`),
  CONSTRAINT `Product_ibfk_3` FOREIGN KEY (`UnitId`) REFERENCES `Unit` (`UnitId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.10 ProductCategory (Danh mục sản phẩm) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `ProductCategory`;

CREATE TABLE `ProductCategory` (
  `ProductCategoryId` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` tinyint(4) DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `Type` int(11) DEFAULT NULL,
  `ParentId` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`ProductCategoryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.11 PurchaseOrder (Đơn hàng gửi Nhà cung cấp) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `PurchaseOrder`;

CREATE TABLE `PurchaseOrder` (
  `PurchaseOrderId` int(11) NOT NULL AUTO_INCREMENT,
  `PurchaseOrderCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SupplierId` int(11) DEFAULT NULL,
  `OrderDate` date DEFAULT NULL,
  `ExpectedDeliveryDate` date DEFAULT NULL,
  `TotalAmount` decimal(18,2) DEFAULT NULL,
  `Status` tinyint(4) DEFAULT NULL,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`PurchaseOrderId`),
  KEY `SupplierId` (`SupplierId`),
  CONSTRAINT `PurchaseOrder_ibfk_1` FOREIGN KEY (`SupplierId`) REFERENCES `Supplier` (`SupplierId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.12 PurchaseOrderDetail (Chi tiết đơn hàng gửi Nhà cung cấp) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `PurchaseOrderDetail`;

CREATE TABLE `PurchaseOrderDetail` (
  `PurchaseOrderDetailId` int(11) NOT NULL AUTO_INCREMENT,
  `PurchaseOrderId` int(11) DEFAULT NULL,
  `ProductId` int(11) DEFAULT NULL,
  `UnitId` int(11) DEFAULT NULL,
  `Specification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `UnitPrice` decimal(18,2) DEFAULT NULL,
  `Amount` decimal(18,2) DEFAULT NULL,
  `ReceivedQuantity` int(11) DEFAULT NULL,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `Status` tinyint(4) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`PurchaseOrderDetailId`),
  KEY `PurchaseOrderId` (`PurchaseOrderId`),
  KEY `ProductId` (`ProductId`),
  CONSTRAINT `PurchaseOrderDetail_ibfk_1` FOREIGN KEY (`PurchaseOrderId`) REFERENCES `PurchaseOrder` (`PurchaseOrderId`),
  CONSTRAINT `PurchaseOrderDetail_ibfk_2` FOREIGN KEY (`ProductId`) REFERENCES `Product` (`ProductId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.13 Supplier (Nhà cung cấp) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `Supplier`;

CREATE TABLE `Supplier` (
  `SupplierId` int(11) NOT NULL AUTO_INCREMENT,
  `SupplierCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TaxCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Address` text COLLATE utf8mb4_unicode_ci,
  `Note` text COLLATE utf8mb4_unicode_ci,
  `Status` tinyint(4) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`SupplierId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.14 Unit (Đơn vị tính) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `Unit`;

CREATE TABLE `Unit` (
  `UnitId` int(11) NOT NULL AUTO_INCREMENT,
  `Code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NameUnsign` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IsBaseUnit` tinyint(4) DEFAULT NULL,
  `Status` tinyint(4) DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`UnitId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 6.15 Warehouse (Kho) (NEW)

```sql
USE `inventory`;

DROP TABLE IF EXISTS `Warehouse`;

CREATE TABLE `Warehouse` (
  `WarehouseId` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `WarehouseType` tinyint(4) DEFAULT NULL COMMENT '1 company; 5 area; 10 Clinic ',
  `BranchId` int(11) DEFAULT NULL,
  `Type` int(11) DEFAULT NULL,
  `Address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` tinyint(4) DEFAULT NULL,
  `CreatedBy` int(11) DEFAULT NULL,
  `CreatedDate` datetime DEFAULT NULL,
  `UpdatedBy` int(11) DEFAULT NULL,
  `UpdatedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`WarehouseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 7. Ghi chú quan trọng

### 7.1 Schema mới `inventory`

Cần tạo schema `inventory` trước khi chạy các DDL:

```sql
CREATE SCHEMA IF NOT EXISTS `inventory` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 7.2 Cột denormalized

Các cột sau được lưu trữ denormalized để tối ưu hiệu năng truy vấn:

| Cột | Bảng | Nguồn tính |
|-----|------|------------|


### 7.3 Giao hàng nhiều đợt (Partial Delivery)



### 7.4 Chuyển NCC trên PO

Khi chuyển 1 sản phẩm từ PO này sang PO của NCC khác:
- Nếu NCC đích đã có PO ở Status = 1 (Đang tạo) → gộp `PurchaseOrderId` vào PO đó
- Nếu NCC đích chưa có PO → tự động tạo PO mới
- `PurchaseOrderId` bị xóa mềm (State = 0) ở PO nguồn, tạo mới ở PO đích
- Cập nhật lại `TotalAmount` cho cả 2 PO

### 7.5 Cross-schema references

| Cột | Bảng (inventory) | Tham chiếu |
|-----|-------------------|------------|


### 7.6 Quy tắc tồn kho

- `Inventory` được cập nhật khi:
  - **Xuất kho** (OutboutRequest): Giảm `Quantity` tại kho xuất
  - **Nhận hàng** (InboutRequest Status = 2): Tăng `Quantity` tại kho trung tâm
  - **Điều chỉnh thủ công**: Tăng/giảm trực tiếp
- Mỗi thay đổi tồn kho phải ghi `InventoryTransaction`
- Cảnh báo: `Quantity` <= `MinStock` → trạng thái "Sắp hết"
- `Quantity` = 0 → trạng thái "Hết hàng"
