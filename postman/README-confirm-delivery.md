# API Confirm Delivery - Postman Collection

## Mô tả
API để xác nhận phiếu giao hàng, cập nhật số lượng thực nhận và xử lý logic liên quan.

## Endpoint
```
POST /customer/inventory/order-request/confirm-delivery
```

---

## Request Body Structure

### Required Fields (Bắt buộc)
- `OutboutRequestId` (integer): ID của phiếu giao hàng
- `Details` (array): Danh sách chi tiết số lượng nhận
  - `OutboutRequestDetailId` (integer): ID chi tiết phiếu giao
  - `ActualQty` (integer): Số lượng thực nhận (>= 0)

---

## Request Examples

### Case 1: Full Received (Nhận đủ)
```json
{
  "OutboutRequestId": 1,
  "Details": [
    {
      "OutboutRequestDetailId": 1,
      "ActualQty": 5
    },
    {
      "OutboutRequestDetailId": 2,
      "ActualQty": 10
    },
    {
      "OutboutRequestDetailId": 3,
      "ActualQty": 8
    }
  ]
}
```

### Case 2: Partial Received (Nhận một phần)
```json
{
  "OutboutRequestId": 2,
  "Details": [
    {
      "OutboutRequestDetailId": 4,
      "ActualQty": 3
    },
    {
      "OutboutRequestDetailId": 5,
      "ActualQty": 7
    },
    {
      "OutboutRequestDetailId": 6,
      "ActualQty": 0
    }
  ]
}
```

### Case 3: All Zero (Không nhận được hàng)
```json
{
  "OutboutRequestId": 3,
  "Details": [
    {
      "OutboutRequestDetailId": 7,
      "ActualQty": 0
    },
    {
      "OutboutRequestDetailId": 8,
      "ActualQty": 0
    }
  ]
}
```

---

## Response Example

### Success Response (200 OK)
```json
{
  "status": "success",
  "data": [
    {
      "name": "DeliveryDetail",
      "data": {
        "OutboutRequestId": 1,
        "OutboutCode": "GH-202401-001",
        "DepartmentId": 1,
        "Status": 2,
        "ActualArrivalDate": "2024-01-20 10:30:00",
        "details": [
          {
            "OutboutRequestDetailId": 1,
            "ProductId": 101,
            "ExpectedQty": 5,
            "ActualQty": 5
          },
          {
            "OutboutRequestDetailId": 2,
            "ProductId": 102,
            "ExpectedQty": 10,
            "ActualQty": 10
          }
        ]
      }
    }
  ],
  "messages": [
    {
      "message": "Xác nhận phiếu giao hàng thành công",
      "code": "SUC001",
      "type": "success"
    }
  ]
}
```

### Error Response - Missing OutboutRequestId (422)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Vui lòng cung cấp OutboutRequestId",
      "code": "ERR001",
      "type": "error"
    }
  ]
}
```

### Error Response - Missing Details (422)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Vui lòng cung cấp chi tiết số lượng nhận",
      "code": "ERR001",
      "type": "error"
    }
  ]
}
```

### Error Response - Not Found (500)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Không tìm thấy phiếu giao hàng",
      "code": "ERR999",
      "type": "error"
    }
  ]
}
```

### Error Response - Already Confirmed (500)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Phiếu giao hàng đã được xác nhận",
      "code": "ERR999",
      "type": "error"
    }
  ]
}
```

---

## Business Logic Flow

Khi API được gọi, hệ thống sẽ thực hiện các bước sau:

### 1. Validation
- Kiểm tra OutboutRequestId có tồn tại không
- Kiểm tra phiếu giao đã được xác nhận chưa (Status = 2)
- Kiểm tra Details array có dữ liệu không

### 2. Cập nhật OutboutRequestDetail
- Cập nhật `ActualQty` cho từng chi tiết
- `ActualQty` = Số lượng thực nhận từ request

### 3. Cập nhật OutboutRequest
- Cập nhật `Status` = 2 (Hoàn thành)
- Cập nhật `ActualArrivalDate` = thời gian hiện tại

### 4. Xử lý từng sản phẩm

Với mỗi sản phẩm trong OutboutRequestDetail:

#### 4.1. Cập nhật DepartmentDemand
- Tăng `TotalDeliveredQty` theo `ActualQty`
  - `TotalDeliveredQty += ActualQty`
- Giảm `PendingQty` theo `ActualQty`
  - `PendingQty = max(0, PendingQty - ActualQty)`
  - Không cho PendingQty âm

#### 4.2. Insert DepartmentDemandLog
- `ChangeType` = 'RECEIVED'
- `ChangeQty` = ActualQty (số dương)
- `QtyBefore` = TotalDeliveredQty cũ
- `QtyAfter` = TotalDeliveredQty mới
- `Note` = Ghi nhận cả thay đổi PendingQty
- `RefType` = 'OutboutRequest'
- `RefId` = OutboutRequestId

#### 4.3. Phân bổ số lượng vào OrderRequest (FIFO - First In First Out)

**Logic phân bổ:**
1. Lấy tất cả OrderRequest của phòng khám chưa hoàn thành (Status = 1, 2, 4)
2. Sắp xếp theo OrderRequestId ASC (từ cũ đến mới)
3. Với mỗi OrderRequest:
   - Tìm OrderRequestDetail có cùng ProductId và UnitId
   - Tính số lượng còn thiếu: `neededQty = RequestQuantity - ReceivedQuantity`
   - Phân bổ: `allocatedQty = min(remainingQty, neededQty)`
   - Cập nhật: `ReceivedQuantity += allocatedQty`
   - Giảm: `remainingQty -= allocatedQty`
4. Lặp lại cho đến khi hết số lượng hoặc hết OrderRequest

**Cập nhật Status của OrderRequest:**
- Kiểm tra tất cả OrderRequestDetail của OrderRequest đó
- Nếu tất cả đã nhận đủ (`ReceivedQuantity >= RequestQuantity`):
  - Cập nhật `OrderRequest.Status = 5` (Hoàn thành)
- Nếu chưa đủ nhưng đã có hàng về (Status = 1 hoặc 2):
  - Cập nhật `OrderRequest.Status = 4` (Đang vận chuyển)

### 5. Transaction Handling
- Tất cả operations được wrap trong DB transaction
- Nếu có lỗi, toàn bộ thao tác sẽ rollback

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Confirm Delivery API                      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  1. Update OutboutRequestDetail       │
        │     - Set ActualQty                   │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  2. Update OutboutRequest             │
        │     - Status = 2 (Hoàn thành)         │
        │     - ActualArrivalDate = now         │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  3. For each Product in Delivery      │
        └───────────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                │                       │
                ▼                       ▼
    ┌─────────────────────┐   ┌─────────────────────┐
    │ Update              │   │ Insert              │
    │ DepartmentDemand    │   │ DepartmentDemandLog │
    │ - TotalDeliveredQty │   │ - ChangeType:       │
    │   += ActualQty      │   │   RECEIVED          │
    └─────────────────────┘   └─────────────────────┘
                │
                ▼
    ┌─────────────────────────────────────────────┐
    │ 4. Allocate to OrderRequests (FIFO)         │
    │    - Get OrderRequests of Department        │
    │    - Status IN (1, 2, 4)                    │
    │    - Order by OrderRequestId ASC (old→new)  │
    └─────────────────────────────────────────────┘
                │
                ▼
    ┌─────────────────────────────────────────────┐
    │ For each OrderRequest (oldest first):       │
    │                                             │
    │ 1. Find OrderRequestDetail                  │
    │    (same ProductId, UnitId)                 │
    │                                             │
    │ 2. Calculate needed:                        │
    │    neededQty = RequestQty - ReceivedQty     │
    │                                             │
    │ 3. Allocate:                                │
    │    allocatedQty = min(remainingQty, needed) │
    │                                             │
    │ 4. Update:                                  │
    │    ReceivedQuantity += allocatedQty         │
    │    remainingQty -= allocatedQty             │
    └─────────────────────────────────────────────┘
                │
                ▼
    ┌─────────────────────────────────────────────┐
    │ 5. Check if OrderRequest is complete:       │
    │                                             │
    │ - All products received?                    │
    │   (ReceivedQty >= RequestQty for all)       │
    │                                             │
    │   YES → Status = 5 (Hoàn thành)             │
    │   NO  → Status = 4 (Đang vận chuyển)        │
    │         (if was Status 1 or 2)              │
    └─────────────────────────────────────────────┘
```

---

## Import vào Postman

1. Mở Postman
2. Click **Import** ở góc trên bên trái
3. Chọn file `confirm-delivery.json`
4. Collection sẽ được import với 8 test cases

## Cấu hình Variables

Sau khi import, cần cấu hình 2 variables:

1. `base_url`: URL của API server
   - Development: `http://localhost:8000`
   - Staging: `https://staging-api.example.com`
   - Production: `https://api.example.com`

2. `access_token`: Token xác thực
   - Lấy từ API login
   - Format: Bearer token

---

## Test Cases Included

1. **Success Case**: Xác nhận với số lượng nhận đầy đủ
2. **Partial Received**: Xác nhận với số lượng nhận một phần
3. **All Zero**: Xác nhận với tất cả sản phẩm nhận 0
4. **Missing OutboutRequestId**: Test validation thiếu ID
5. **Missing Details**: Test validation thiếu Details
6. **Empty Details**: Test validation Details rỗng
7. **Not Found**: Test ID không tồn tại
8. **Already Confirmed**: Test xác nhận lại phiếu đã xác nhận

---

## Important Notes

### Status Mapping
| Status | Tên trạng thái | Mô tả |
|--------|----------------|-------|
| 1 | Đang giao | Phiếu đang trong quá trình giao hàng |
| 2 | Hoàn thành | Phiếu đã được xác nhận nhận hàng |

### OrderRequest Status Mapping
| Status | Tên trạng thái | Mô tả |
|--------|----------------|-------|
| 1 | Mới | Yêu cầu mới tạo |
| 2 | Đang xử lý | Đang xử lý yêu cầu |
| 3 | Huỷ | Yêu cầu bị huỷ |
| 4 | Đang vận chuyển | Đang giao hàng (chưa nhận đủ) |
| 5 | Hoàn thành | Đã nhận đủ hàng |

### Key Points
- `ActualQty` có thể = 0 (không nhận được hàng)
- `ActualQty` có thể < `ExpectedQty` (nhận thiếu)
- `ActualQty` có thể > `ExpectedQty` (nhận thừa)
- Phiếu giao chỉ có thể xác nhận 1 lần (Status = 2)
- Sau khi xác nhận, không thể sửa lại
- Transaction đảm bảo tính toàn vẹn dữ liệu

### DepartmentDemandLog ChangeType
| ChangeType | Mô tả |
|------------|-------|
| REQUEST | Tạo yêu cầu cấp phát (tăng PendingQty) |
| DELIVERY | Tạo phiếu giao hàng (giảm PendingQty) |
| RECEIVED | Xác nhận nhận hàng (tăng TotalDeliveredQty) |

---

## FIFO Allocation Logic (Phân bổ theo thứ tự cũ đến mới)

### DepartmentDemand Flow

**Trạng thái ban đầu:**
- `TotalRequestedQty`: 100 (tổng đã yêu cầu)
- `PendingQty`: 50 (đang chờ giao)
- `TotalDeliveredQty`: 50 (đã nhận)

**Khi tạo phiếu giao 30 cái:**
- `PendingQty`: 50 → 20 (giảm 30)
- `TotalDeliveredQty`: 50 (chưa đổi)

**Khi xác nhận nhận 30 cái:**
- `PendingQty`: 20 (không đổi - đã giảm khi tạo phiếu)
- `TotalDeliveredQty`: 50 → 80 (tăng 30)

**Lưu ý:** Logic hiện tại giảm PendingQty khi xác nhận, nên:
- Khi tạo phiếu giao: `PendingQty -= DeliveryQty`
- Khi xác nhận: `PendingQty -= ActualQty` (giảm thêm nếu cần)
- `TotalDeliveredQty += ActualQty`

---

### Ví dụ minh họa OrderRequest FIFO:

**Tình huống:**
- Phòng khám có 3 OrderRequest chưa hoàn thành:
  - OrderRequest #1 (cũ nhất): Cần 10 cái găng tay, đã nhận 0
  - OrderRequest #2: Cần 15 cái găng tay, đã nhận 0
  - OrderRequest #3 (mới nhất): Cần 20 cái găng tay, đã nhận 0
- Phiếu giao mới về: 25 cái găng tay

**Quá trình phân bổ:**

1. **OrderRequest #1** (cũ nhất):
   - Cần: 10 cái
   - Phân bổ: 10 cái
   - ReceivedQuantity: 0 → 10
   - Status: 1 → 5 (Hoàn thành - đã đủ)
   - Còn lại: 25 - 10 = 15 cái

2. **OrderRequest #2**:
   - Cần: 15 cái
   - Phân bổ: 15 cái
   - ReceivedQuantity: 0 → 15
   - Status: 1 → 5 (Hoàn thành - đã đủ)
   - Còn lại: 15 - 15 = 0 cái

3. **OrderRequest #3** (mới nhất):
   - Cần: 20 cái
   - Phân bổ: 0 cái (đã hết hàng)
   - ReceivedQuantity: 0 → 0
   - Status: 1 (không đổi - chưa có hàng)
   - Còn lại: 0 cái

**Kết quả:**
- OrderRequest #1: Hoàn thành ✓
- OrderRequest #2: Hoàn thành ✓
- OrderRequest #3: Vẫn chờ hàng

### Ví dụ 2: Phân bổ một phần

**Tình huống:**
- OrderRequest #1: Cần 30 cái, đã nhận 20 (còn thiếu 10)
- OrderRequest #2: Cần 25 cái, đã nhận 0
- Phiếu giao mới về: 15 cái

**Quá trình phân bổ:**

1. **OrderRequest #1**:
   - Cần: 10 cái (30 - 20)
   - Phân bổ: 10 cái
   - ReceivedQuantity: 20 → 30
   - Status: 4 → 5 (Hoàn thành)
   - Còn lại: 15 - 10 = 5 cái

2. **OrderRequest #2**:
   - Cần: 25 cái
   - Phân bổ: 5 cái (chỉ còn 5)
   - ReceivedQuantity: 0 → 5
   - Status: 1 → 4 (Đang vận chuyển - đã có hàng về)
   - Còn lại: 5 - 5 = 0 cái

**Kết quả:**
- OrderRequest #1: Hoàn thành ✓
- OrderRequest #2: Đang vận chuyển (nhận 5/25)

---

## Testing Workflow

### Workflow 1: Test FIFO Allocation
1. Tạo 3 OrderRequest cho cùng 1 phòng khám với cùng sản phẩm
2. Tạo phiếu giao từ DepartmentDemand với số lượng vừa đủ cho 2 OrderRequest đầu
3. Xác nhận phiếu giao
4. Kiểm tra:
   - OrderRequest #1: Status = 5, ReceivedQuantity = RequestQuantity
   - OrderRequest #2: Status = 5, ReceivedQuantity = RequestQuantity
   - OrderRequest #3: Status = 1, ReceivedQuantity = 0

### Workflow 2: Test Partial Allocation
1. Tạo OrderRequest với nhiều sản phẩm
2. Tạo phiếu giao với số lượng không đủ
3. Xác nhận phiếu giao
4. Kiểm tra:
   - OrderRequest: Status = 4 (Đang vận chuyển)
   - Một số sản phẩm đã nhận đủ, một số chưa

### Workflow 3: Test Multiple Products
1. Tạo OrderRequest với 3 sản phẩm khác nhau
2. Tạo phiếu giao với đủ 2 sản phẩm, thiếu 1 sản phẩm
3. Xác nhận phiếu giao
4. Kiểm tra:
   - OrderRequest: Status = 4 (vì chưa đủ tất cả)
   - 2 sản phẩm: ReceivedQuantity = RequestQuantity
   - 1 sản phẩm: ReceivedQuantity < RequestQuantity

---
