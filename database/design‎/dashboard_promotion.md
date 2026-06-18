# Thiết kế DB - Dashboard Báo cáo Khuyến mãi

## Phân tích từ UI

**Tab**: Hôm nay | Tháng này | Tổng quan theo tháng | Khách hàng | Trả góp | Tùy chọn | **Khuyến mãi**

**Bộ lọc**:
- Khoảng ngày: `FromDate` - `ToDate`
- Phạm vi: **Hệ thống** (tất cả chi nhánh) hoặc **Chi nhánh cụ thể** (array BranchIds)

**Lưu ý quan trọng**: UI có 2 chế độ xem:
- `BranchIds = []` hoặc `NULL` → xem toàn hệ thống, aggregate tất cả chi nhánh
- `BranchIds = [1,2,3]` → xem các chi nhánh cụ thể

---

## Convention đặt tên (theo các bảng hiện tại)

Tham khảo: `DashboardBranchDaily`, `DashboardStaffEffectiveDaily`, `DashboardCustomerSourceSummary`

- Tên cột: **PascalCase** (`SummaryDate`, `BranchId`, `CreatedDate`, `UpdatedDate`)
- Không có auto-increment primary key riêng → dùng composite key
- Không có `Id` column
- Luôn có: `SummaryDate`, `BranchId`, `CreatedDate`, `UpdatedDate`

---

## Bảng DB

### 0. PromotionType
> Danh mục phân loại mục đích sử dụng khuyến mãi (do admin quản lý)

```sql
CREATE TABLE PromotionType (
    PromotionTypeId   INT AUTO_INCREMENT PRIMARY KEY,
    Code              VARCHAR(20) NOT NULL UNIQUE,  -- periodic|retention|warranty|complaint|staff|other
    Name              VARCHAR(100) NOT NULL,         -- Mã KM định kỳ | Mã hỗ trợ chốt khách | ...
    Description       VARCHAR(255),
    Color             VARCHAR(20),                   -- Màu hiển thị trên UI (hex)
    Priority          INT DEFAULT 0,                 -- Thứ tự hiển thị
    Status            TINYINT DEFAULT 1,             -- 1=Active | 0=Inactive
    CreatedBy         INT,
    CreatedDate       DATETIME,
    UpdatedBy         INT,
    UpdatedDate       DATETIME
);

-- Dữ liệu mặc định
INSERT INTO PromotionType (Code, Name, Color, Priority, Status) VALUES
('periodic',   'Mã KM định kỳ',          '#3B82F6', 1, 1),
('retention',  'Mã hỗ trợ chốt khách',   '#8B5CF6', 2, 1),
('warranty',   'Mã bảo hành',            '#10B981', 3, 1),
('complaint',  'Mã khiêu nại',           '#EF4444', 4, 1),
('staff',      'Mã nhân viên',           '#F59E0B', 5, 1),
('other',      'Mã khác',               '#6B7280', 6, 1);
```

### 1. DashboardPromotionEfficencyDaily
> Tổng hợp KPI khuyến mãi theo ngày + chi nhánh

**Data Sources:**
- `OrderDetail`: Lấy tổng tiền dịch vụ, tổng giảm giá
- `PromotionOrderDetail`: Lấy số lượt dùng mã, số promotion phát sinh

**Filters:**
- `FirstTreatmentTime` BETWEEN [FromDate 00:00:00, ToDate 23:59:59]
- `OrderDetail.Status > 1` (đơn đã xác nhận)
- `PromotionOrderDetail.State = 1` (promotion active)
- `ConsultedBranchId` IN (branchIds)

```sql
CREATE TABLE DashboardPromotionEfficencyDaily (
    SummaryDate       DATE NOT NULL,
    BranchId          INT NOT NULL,
    TotalUsageCount   INT DEFAULT 0,           -- COUNT(DISTINCT CONCAT(CustomerId, '_', PromotionId))
    TotalDiscountAmount DECIMAL(18,2) DEFAULT 0, -- SUM(OrderDetail.DiscountAmount)
    TotalOrderCount   INT DEFAULT 0,           -- COUNT(DISTINCT PromotionId)
    TotalOrderAmount  DECIMAL(18,2) DEFAULT 0, -- SUM(OrderDetail.ServicePrice) [tất cả đơn]
    DependencyRate    DECIMAL(5,2) DEFAULT 0,  -- (TotalDiscountAmount / TotalOrderAmount) * 100
    CreatedDate       DATETIME,
    UpdatedDate       DATETIME,
    PRIMARY KEY (SummaryDate, BranchId),
    INDEX idx_summary_date (SummaryDate),
    INDEX idx_branch_date (BranchId, SummaryDate)
);
```

**Calculated Fields:**
- `TotalUsageCount`: Số lượt khách hàng sử dụng mã (1 khách dùng 1 mã = 1 lượt)
- `TotalOrderCount`: Số promotion phát sinh trong ngày
- `DependencyRate`: Tỷ lệ phụ thuộc khuyến mãi (%)

### 2. DashboardPromotionEfficencyByType
> Phân loại KM theo mục đích sử dụng theo ngày + chi nhánh

**Data Sources:**
- `PromotionOrderDetail` → `OrderDetail` → `promotions` → `PromotionType`

**Filters:**
- `FirstTreatmentTime` BETWEEN [FromDate 00:00:00, ToDate 23:59:59]
- `OrderDetail.Status > 1`
- `PromotionOrderDetail.State = 1`
- `ConsultedBranchId` IN (branchIds)

**Group By:**
- DATE(FirstTreatmentTime)
- ConsultedBranchId
- PromotionTypeId

```sql
CREATE TABLE DashboardPromotionEfficencyByType (
    SummaryDate         DATE NOT NULL,
    BranchId            INT NOT NULL,
    PromotionTypeId     INT NOT NULL,               -- FK → PromotionType.PromotionTypeId
    UsageCount          INT DEFAULT 0,              -- COUNT(DISTINCT CustomerId)
    UsagePercent        DECIMAL(5,2) DEFAULT 0,     -- (TotalDiscountAmount / Tổng) * 100
    TotalDiscountAmount DECIMAL(18,2) DEFAULT 0,    -- SUM(OrderDetail.DiscountAmount)
    CreatedDate         DATETIME,
    UpdatedDate         DATETIME,
    PRIMARY KEY (SummaryDate, BranchId, PromotionTypeId),
    INDEX idx_summary_date (SummaryDate),
    INDEX idx_branch_date (BranchId, SummaryDate),
    FOREIGN KEY (PromotionTypeId) REFERENCES PromotionType(PromotionTypeId)
);
```

**Calculated Fields:**
- `UsageCount`: Số khách hàng sử dụng loại KM này
- `UsagePercent`: Tỷ lệ % giảm giá của loại KM so với tổng trong ngày+chi nhánh

### 3. DashboardPromotionEfficencyByService
> Phân tích KM theo loại dịch vụ theo ngày + chi nhánh

**Data Sources:**
- `PromotionOrderDetail` → `OrderDetail` → `Service`

**Filters:**
- `FirstTreatmentTime` BETWEEN [FromDate 00:00:00, ToDate 23:59:59]
- `OrderDetail.Status > 1`
- `PromotionOrderDetail.State = 1`
- `ConsultedBranchId` IN (branchIds)

**Service Category Mapping:**
- `Service.WarrantyType = 'I'` → `ServiceCategoryId = 'I'` (Implant)
- `Service.WarrantyType = 'O'` → `ServiceCategoryId = 'O'` (Niềng răng/Orthodontic)
- `Service.WarrantyType = 'P'` → `ServiceCategoryId = 'P'` (Phục hình/Prosthetic)
- `Service.WarrantyType = NULL` hoặc khác → `ServiceCategoryId = 'T'` (Tổng quát/Treatment)

```sql
CREATE TABLE DashboardPromotionEfficencyByService (
    SummaryDate         DATE NOT NULL,
    BranchId            INT NOT NULL,
    ServiceCategoryId   VARCHAR(5) NOT NULL,    -- I|O|P|T
    TotalService        INT DEFAULT 0,          -- COUNT(DISTINCT ServiceId)
    UsageCount          INT DEFAULT 0,          -- COUNT(DISTINCT CONCAT(CustomerId, '_', PromotionId))
    UsagePercent        DECIMAL(5,2) DEFAULT 0, -- (TotalDiscountAmount / Tổng) * 100
    TotalDiscountAmount DECIMAL(18,2) DEFAULT 0, -- SUM(PromotionOrderDetail.DiscountAmount)
    CreatedDate         DATETIME,
    UpdatedDate         DATETIME,
    PRIMARY KEY (SummaryDate, BranchId, ServiceCategoryId),
    INDEX idx_summary_date (SummaryDate),
    INDEX idx_branch_date (BranchId, SummaryDate)
);
```

**Calculated Fields:**
- `TotalService`: Số dịch vụ khác nhau được áp dụng KM
- `UsageCount`: Số lượt sử dụng mã cho loại dịch vụ này
- `UsagePercent`: Tỷ lệ % giảm giá của loại dịch vụ so với tổng

### 4. DashboardPromotionEfficencyByBranch
> Phân tích KM theo phòng khám theo ngày (chỉ dùng khi xem toàn hệ thống)

**Data Sources:**
- `OrderDetail`: Tổng ca điều trị, tổng tiền dịch vụ
- `PromotionOrderDetail`: Số lượt dùng mã, tổng giảm giá

**Filters:**
- `FirstTreatmentTime` BETWEEN [FromDate 00:00:00, ToDate 23:59:59]
- `OrderDetail.Status > 1`
- `PromotionOrderDetail.State = 1`
- `ConsultedBranchId` IN (branchIds)

```sql
CREATE TABLE DashboardPromotionEfficencyByBranch (
    SummaryDate         DATE NOT NULL,
    BranchId            INT NOT NULL,
    TotalOrderCount     INT DEFAULT 0,          -- COUNT(DISTINCT TreatmentId)
    TotalUsageCount     INT DEFAULT 0,          -- COUNT(DISTINCT CONCAT(CustomerId, '_', PromotionId))
    TotalDiscountAmount DECIMAL(18,2) DEFAULT 0, -- SUM(PromotionOrderDetail.DiscountAmount)
    TotalOrderAmount    DECIMAL(18,2) DEFAULT 0, -- SUM(OrderDetail.ServicePrice)
    DependencyRate      DECIMAL(5,2) DEFAULT 0,  -- (TotalDiscountAmount / TotalOrderAmount) * 100
    -- EvaluationLevel: 1=Bình thường (<30%) | 2=Cao (30-45%) | 3=Bất thường (>45%)
    EvaluationLevel     TINYINT DEFAULT 1,
    CreatedDate         DATETIME,
    UpdatedDate         DATETIME,
    PRIMARY KEY (SummaryDate, BranchId),
    INDEX idx_summary_date (SummaryDate),
    INDEX idx_branch_date (BranchId, SummaryDate)
);
```

**Calculated Fields:**
- `TotalOrderCount`: Tổng số ca điều trị
- `TotalUsageCount`: Số lượt sử dụng mã
- `DependencyRate`: Tỷ lệ phụ thuộc khuyến mãi
- `EvaluationLevel`: Mức đánh giá dựa trên DependencyRate

---

## Luồng insert data (cron job hàng ngày)

**Command:** `php artisan dashboard:promotion-refresh`

**Execution Order:**
```
1. insertPromotionDaily()           → DashboardPromotionEfficencyDaily
2. insertPromotionByType()          → DashboardPromotionEfficencyByType
3. insertPromotionByService()       → DashboardPromotionEfficencyByService
4. insertPromotionByBranch()        → DashboardPromotionEfficencyByBranch
```

**Upsert Logic:**
- Mỗi hàm check existing records theo composite key
- Nếu tồn tại: UPDATE
- Nếu chưa có: INSERT (batch 500 records)

**Transaction:**
- Mỗi hàm có transaction riêng
- Sử dụng `READ UNCOMMITTED` isolation level cho performance

**Cron Schedule:**
```bash
# Chạy lúc 1:00 AM hàng ngày
0 1 * * * cd /path/to/project && php artisan dashboard:promotion-refresh
```

---

## API Endpoints

### 1. Insert/Refresh Data
```
POST /customer/dashboard/insert-promotion-dashboard
```

**Request Body:**
```json
{
  "FromDate": "2026-04-01",  // required, Y-m-d
  "ToDate": "2026-04-07",    // required, Y-m-d, >= FromDate
  "BranchId": [1, 2, 3]      // optional, array, empty = all active branches
}
```

**Response:**
```json
{
  "success": true,
  "message": "Dashboard Promotion data insert successfully",
  "execution_time": "12.5 seconds",
  "from_date": "2026-04-01",
  "to_date": "2026-04-07",
  "branches": 3,
  "details": {
    "insertPromotionDaily": { "success": true, "inserted": 21, "updated": 0 },
    "insertPromotionByType": { "success": true, "inserted": 42, "updated": 0 },
    "insertPromotionByService": { "success": true, "inserted": 28, "updated": 0 },
    "insertPromotionByBranch": { "success": true, "inserted": 21, "updated": 0 }
  }
}
```

### 2. Promotion Summary (KPI Tổng quan)
```
POST /customer/dashboard/promotion-summary
```

**Data Source:** `DashboardPromotionEfficencyDaily`

**Request Body:**
```json
{
  "FromDate": "2026-04-01",  // required
  "ToDate": "2026-04-07",    // required
  "BranchIds": [1, 2, 3]     // optional, empty = all branches
}
```

**Logic:**
- Ngày cũ: Query từ `DashboardPromotionEfficencyDaily`
- Ngày hôm nay: Query real-time từ `OrderDetail` + `PromotionOrderDetail`

**Response:**
```json
{
  "TotalUsageCount": 150,        // Tổng lượt dùng mã
  "TotalDiscountAmount": 45000000, // Tổng giảm giá (VNĐ)
  "TotalBranchCount": 8,         // Số PK phát sinh mã
  "DependencyRate": 27.5         // Tỷ lệ phụ thuộc KM (%)
}
```

### 3. Promotion By Type (Phân loại theo mục đích)
```
POST /customer/dashboard/promotion-by-type
```

**Data Source:** `DashboardPromotionEfficencyByType` + `PromotionType`

**Request Body:**
```json
{
  "FromDate": "2026-04-01",
  "ToDate": "2026-04-07",
  "BranchIds": [1, 2, 3]
}
```

**Logic:**
- Ngày cũ: Query từ `DashboardPromotionEfficencyByType`
- Ngày hôm nay: Query real-time từ `PromotionOrderDetail` → `promotions` → `PromotionType`

**Response:**
```json
[
  {
    "PromotionTypeId": 1,
    "PromotionTypeName": "Mã KM định kỳ",
    "Color": "#3B82F6",
    "UsageCount": 85,
    "TotalDiscountAmount": 25000000,
    "UsagePercent": 55.6
  },
  {
    "PromotionTypeId": 2,
    "PromotionTypeName": "Mã hỗ trợ chốt khách",
    "Color": "#8B5CF6",
    "UsageCount": 42,
    "TotalDiscountAmount": 15000000,
    "UsagePercent": 33.3
  }
]
```

### 4. Promotion By Service (Phân tích theo dịch vụ)
```
POST /customer/dashboard/promotion-by-service
```

**Data Source:** `DashboardPromotionEfficencyByService`

**Request Body:**
```json
{
  "FromDate": "2026-04-01",
  "ToDate": "2026-04-07",
  "BranchIds": [1, 2, 3]
}
```

**Logic:**
- Ngày cũ: Query từ `DashboardPromotionEfficencyByService`
- Ngày hôm nay: Query real-time từ `PromotionOrderDetail` → `OrderDetail` → `Service`

**Response:**
```json
{
  "Summary": {
    "TotalUsageCount": 150,
    "TotalDiscountAmount": 45000000,
    "TotalService": 4
  },
  "Items": [
    {
      "ServiceCategoryId": "O",
      "ServiceCategoryName": "Niềng răng",
      "Color": "#3B82F6",
      "UsageCount": 65,
      "TotalService": 12,
      "TotalDiscountAmount": 18000000,
      "UsagePercent": 40.0
    },
    {
      "ServiceCategoryId": "I",
      "ServiceCategoryName": "Implant",
      "Color": "#10B981",
      "UsageCount": 45,
      "TotalService": 8,
      "TotalDiscountAmount": 15000000,
      "UsagePercent": 33.3
    }
  ]
}
```

### 5. Promotion By Branch (Phân tích theo phòng khám)
```
POST /customer/dashboard/promotion-by-branch
```

**Data Source:** `DashboardPromotionEfficencyByBranch` + `Branch`

**Request Body:**
```json
{
  "FromDate": "2026-04-01",
  "ToDate": "2026-04-07",
  "BranchIds": [1, 2, 3]
}
```

**Logic:**
- Ngày cũ: Query từ `DashboardPromotionEfficencyByBranch`
- Ngày hôm nay: Query real-time từ `OrderDetail` + `PromotionOrderDetail`
- Sort theo `Branch.Priority` ASC

**Response:**
```json
[
  {
    "BranchId": 1,
    "BranchCode": "33DTH.003",
    "Priority": 1,
    "TotalOrderCount": 120,
    "TotalUsageCount": 85,
    "TotalDiscountAmount": 25000000,
    "TotalOrderAmount": 90000000,
    "DependencyRate": 27.8,
    "EvaluationLevel": "Bình thường"
  },
  {
    "BranchId": 2,
    "BranchCode": "33DTH.005",
    "Priority": 2,
    "TotalOrderCount": 95,
    "TotalUsageCount": 65,
    "TotalDiscountAmount": 20000000,
    "TotalOrderAmount": 45000000,
    "DependencyRate": 44.4,
    "EvaluationLevel": "Cao"
  }
]
```

### 6. Top Customers By Promotion (Danh sách khách hàng)
```
POST /customer/dashboard/promotion-top-customers
```

**Data Source:** `OrderDetail` + `PromotionOrderDetail` + `Customer` (real-time query)

**Request Body:**
```json
{
  "FromDate": "2026-04-01",
  "ToDate": "2026-04-07",
  "BranchIds": [1, 2, 3]
}
```

**Logic:**
- Query real-time từ `OrderDetail` + `PromotionOrderDetail`
- Filter: `FirstTreatmentTime`, `ConsultedBranchId`, `Status > 1`
- Sort: `SUM(ServicePrice)` DESC
- Limit: Top 10

**Response:**
```json
[
  {
    "STT": 1,
    "CustomerId": 12345,
    "CustomerName": "Nguyễn Văn A",
    "CustomerCode": "KH001",
    "PromotionUsageCount": 5,
    "TotalDiscountAmount": 12500000,
    "TotalServicePrice": 45000000,
    "DependencyRate": 27.8
  },
  {
    "STT": 2,
    "CustomerId": 12346,
    "CustomerName": "Trần Thị B",
    "CustomerCode": "KH002",
    "PromotionUsageCount": 4,
    "TotalDiscountAmount": 9800000,
    "TotalServicePrice": 32000000,
    "DependencyRate": 30.6
  }
]
```

---

## Ngưỡng đánh giá EvaluationLevel

| DependencyRate  | EvaluationLevel | Hiển thị    | Màu sắc |
|-----------------|-----------------|-------------|---------|
| < 30%           | 1               | Bình thường | Green   |
| 30% - 45%       | 2               | Cao         | Yellow  |
| > 45%           | 3               | Bất thường  | Red     |

---

## Performance Considerations

### 1. Indexing Strategy
```sql
-- DashboardPromotionEfficencyDaily
CREATE INDEX idx_summary_date ON DashboardPromotionEfficencyDaily(SummaryDate);
CREATE INDEX idx_branch_date ON DashboardPromotionEfficencyDaily(BranchId, SummaryDate);

-- DashboardPromotionEfficencyByType
CREATE INDEX idx_summary_date ON DashboardPromotionEfficencyByType(SummaryDate);
CREATE INDEX idx_branch_date ON DashboardPromotionEfficencyByType(BranchId, SummaryDate);

-- DashboardPromotionEfficencyByService
CREATE INDEX idx_summary_date ON DashboardPromotionEfficencyByService(SummaryDate);
CREATE INDEX idx_branch_date ON DashboardPromotionEfficencyByService(BranchId, SummaryDate);

-- DashboardPromotionEfficencyByBranch
CREATE INDEX idx_summary_date ON DashboardPromotionEfficencyByBranch(SummaryDate);
CREATE INDEX idx_branch_date ON DashboardPromotionEfficencyByBranch(BranchId, SummaryDate);

CREATE INDEX idx_od_treatment_branch_status 
ON OrderDetail(FirstTreatmentTime, ConsultedBranchId, Status);

-- 2. OrderDetail: Tối ưu cho query chỉ theo ngày và status
CREATE INDEX idx_od_treatment_status 
ON OrderDetail(FirstTreatmentTime, Status);

-- 3. PromotionOrderDetail: Tối ưu join với OrderDetail
CREATE INDEX idx_pod_orderdetail_state 
ON PromotionOrderDetail(OrderDetailId, State);

-- 4. PromotionOrderDetail: Tối ưu đếm DISTINCT (CustomerId, PromotionId)
CREATE INDEX idx_pod_customer_promo_state 
ON PromotionOrderDetail(CustomerId, PromotionId, State);

-- 5. PromotionOrderDetail: Tối ưu join với promotions
CREATE INDEX idx_pod_promotion_state 
ON PromotionOrderDetail(PromotionId, State);

-- 6. Service: Tối ưu join và filter theo WarrantyType
CREATE INDEX idx_service_id_warranty 
ON Service(ServiceId, WarrantyType);

-- 7. promotions: Tối ưu join với PromotionType
CREATE INDEX idx_promotions_id_type 
ON promotions(ID, PromotionType);

-- 8. OrderDetail: Tối ưu cho query ServiceId
CREATE INDEX idx_od_service_treatment 
ON OrderDetail(ServiceId, FirstTreatmentTime, Status);
```

### 2. Query Optimization
- Sử dụng `READ UNCOMMITTED` isolation level cho dashboard queries
- Batch insert 500 records mỗi lần
- Upsert logic: Check existing → UPDATE hoặc INSERT

### 3. Caching Strategy
- Cache API responses 5 phút cho ngày cũ
- Không cache ngày hôm nay (real-time data)

### 4. Data Retention
- Giữ data 2 năm
- Archive data cũ hơn 2 năm vào bảng history

---

## Migration Scripts

```sql
-- Run these in order
1. CREATE TABLE PromotionType
2. INSERT default PromotionType data
3. CREATE TABLE DashboardPromotionEfficencyDaily
4. CREATE TABLE DashboardPromotionEfficencyByType
5. CREATE TABLE DashboardPromotionEfficencyByService
6. CREATE TABLE DashboardPromotionEfficencyByBranch
7. CREATE all indexes
```

---

## Testing Checklist

- [ ] Insert data cho 1 ngày, 1 chi nhánh
- [ ] Insert data cho 7 ngày, nhiều chi nhánh
- [ ] Insert data cho 30 ngày, tất cả chi nhánh
- [ ] Query promotion-summary với BranchIds = []
- [ ] Query promotion-summary với BranchIds = [1,2,3]
- [ ] Query promotion-by-type với ngày cũ
- [ ] Query promotion-by-type với ngày hôm nay
- [ ] Query promotion-by-service với ngày cũ
- [ ] Query promotion-by-service với ngày hôm nay
- [ ] Query promotion-by-branch sort theo Priority
- [ ] Query promotion-top-customers limit 10
- [ ] Test concurrent insert requests
- [ ] Test large date range (90 ngày)
- [ ] Verify EvaluationLevel calculation
- [ ] Verify DependencyRate calculation
