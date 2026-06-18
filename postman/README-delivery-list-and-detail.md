# API Delivery List and Detail - Postman Collection

## Mô tả
API để lấy danh sách và chi tiết phiếu giao hàng từ DepartmentDemand.

## Endpoints

### 1. Danh sách phiếu giao hàng
```
POST /customer/inventory/order-request/delivery-list
```

### 2. Chi tiết phiếu giao hàng
```
POST /customer/inventory/order-request/delivery-detail
```

---

## API 1: Delivery List

### Request Body Structure

#### Optional Fields (Tùy chọn)
- `DepartmentId` (integer): Lọc theo phòng khám/chi nhánh
- `Status` (integer): Lọc theo trạng thái
  - `1`: Đang giao
  - `2`: Hoàn thành
- `Keyword` (string): Tìm kiếm theo mã phiếu xuất
- `limit` (integer): Số bản ghi trên 1 trang (default: 20, max: 100)
- `lmstart` (integer): Vị trí bắt đầu (default: 0)

### Request Examples

#### Case 1: Lấy tất cả (All)
```json
{
  "limit": 20,
  "lmstart": 0
}
```

#### Case 2: Lọc theo phòng khám
```json
{
  "DepartmentId": 1,
  "limit": 20,
  "lmstart": 0
}
```

#### Case 3: Lọc theo trạng thái
```json
{
  "Status": 1,
  "limit": 20,
  "lmstart": 0
}
```

#### Case 4: Tìm kiếm theo keyword
```json
{
  "Keyword": "GH-202401",
  "limit": 20,
  "lmstart": 0
}
```

#### Case 5: Nhiều bộ lọc
```json
{
  "DepartmentId": 1,
  "Status": 1,
  "Keyword": "GH",
  "limit": 20,
  "lmstart": 0
}
```

### Response Example (Success - 200)

```json
{
  "status": "success",
  "data": [
    {
      "name": "DeliveryList",
      "type": "Grid",
      "data": [
        {
          "OutboutRequestId": 1,
          "OutboutCode": "GH-202401-001",
          "DepartmentId": 1,
          "DeliveryDate": "2024-01-15",
          "ExpectedReceiptDate": "2024-01-16",
          "DeliveryStaff": "Nguyễn Văn A",
          "Status": 2,
          "TotalSKU": 23,
          "CreatedDate": "2024-01-14 10:30:00",
          "Branch": {
            "BranchId": 1,
            "BranchCode": "33DTH.003",
            "Name": "Chi nhánh Thủ Đức"
          },
          "details": [
            {
              "OutboutRequestId": 1,
              "OutboutRequestDetailId": 1
            },
            {
              "OutboutRequestId": 1,
              "OutboutRequestDetailId": 2
            }
          ]
        },
        {
          "OutboutRequestId": 2,
          "OutboutCode": "GH-202401-002",
          "DepartmentId": 2,
          "DeliveryDate": "2024-01-18",
          "ExpectedReceiptDate": "2024-01-19",
          "DeliveryStaff": "Trần Thị B",
          "Status": 1,
          "TotalSKU": 32,
          "CreatedDate": "2024-01-17 14:20:00",
          "Branch": {
            "BranchId": 2,
            "BranchCode": "2NO.008",
            "Name": "Chi nhánh Quận 2"
          },
          "details": [
            {
              "OutboutRequestId": 2,
              "OutboutRequestDetailId": 3
            }
          ]
        }
      ],
      "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total": 50,
        "last_page": 3
      }
    }
  ]
}
```

### Response Fields Mapping (Theo hình)

| Field trong Response | Cột trong UI | Mô tả |
|---------------------|--------------|-------|
| `OutboutCode` | Mã phiếu xuất | Mã phiếu giao hàng |
| `Branch.BranchCode` | Phòng khám | Mã chi nhánh |
| `DeliveryDate` | Ngày giao | Ngày giao hàng |
| `ExpectedReceiptDate` | Ngày dự kiến nhận | Ngày dự kiến nhận hàng |
| `DeliveryStaff` | Người giao | Tên nhân viên giao hàng |
| `Status` | Trạng thái | 1=Đang giao, 2=Hoàn thành |
| `TotalSKU` | Tổng vật tư | Tổng số loại vật tư |

---

## API 2: Delivery Detail

### Request Body Structure

#### Required Fields (Bắt buộc)
- `OutboutRequestId` (integer): ID của phiếu giao hàng

### Request Example

```json
{
  "OutboutRequestId": 1
}
```

### Response Example (Success - 200)

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
        "DepartmentType": 1,
        "RelatedType": 2,
        "RelatedId": null,
        "DeliveryDate": "2024-01-15",
        "ExpectedReceiptDate": "2024-01-16",
        "DeliveryStaff": "Nguyễn Văn A",
        "Status": 2,
        "TotalSKU": 3,
        "Note": "Giao hàng khẩn cấp",
        "CreatedBy": 123,
        "CreatedDate": "2024-01-14 10:30:00",
        "Branch": {
          "BranchId": 1,
          "BranchCode": "33DTH.003",
          "Name": "Chi nhánh Thủ Đức"
        },
        "details": [
          {
            "OutboutRequestDetailId": 1,
            "OutboutRequestId": 1,
            "ProductId": 101,
            "UnitId": 1,
            "ExpectedQty": 5,
            "ActualQty": 5,
            "product": {
              "ProductId": 101,
              "ProductCode": "VT001",
              "ProductName": "Composite trám răng",
              "Specification": "Loại A"
            },
            "unit": {
              "UnitId": 1,
              "Code": "Tuyp",
              "Name": "Tuýp"
            }
          },
          {
            "OutboutRequestDetailId": 2,
            "OutboutRequestId": 1,
            "ProductId": 102,
            "UnitId": 2,
            "ExpectedQty": 10,
            "ActualQty": 10,
            "product": {
              "ProductId": 102,
              "ProductCode": "VT002",
              "ProductName": "Găng tay y tế",
              "Specification": "100 cái/hộp"
            },
            "unit": {
              "UnitId": 2,
              "Code": "Hộp",
              "Name": "Hộp"
            }
          },
          {
            "OutboutRequestDetailId": 3,
            "OutboutRequestId": 1,
            "ProductId": 103,
            "UnitId": 2,
            "ExpectedQty": 8,
            "ActualQty": 8,
            "product": {
              "ProductId": 103,
              "ProductCode": "VT003",
              "ProductName": "Khẩu trang y tế 4 lớp",
              "Specification": "50 cái/hộp"
            },
            "unit": {
              "UnitId": 2,
              "Code": "Hộp",
              "Name": "Hộp"
            }
          }
        ]
      }
    }
  ]
}
```

### Response Fields Mapping (Theo hình popup)

#### Header Information
| Field trong Response | Label trong UI | Mô tả |
|---------------------|----------------|-------|
| `Branch.BranchCode` | Phòng khám | Mã chi nhánh |
| `DeliveryDate` | Ngày giao | Ngày giao hàng |
| `ExpectedReceiptDate` | Ngày dự kiến nhận | Ngày dự kiến nhận hàng |
| `DeliveryStaff` | Người giao | Tên nhân viên giao hàng |
| `Status` | Trạng thái | 1=Đang giao, 2=Hoàn thành |

#### Detail Table
| Field trong Response | Cột trong UI | Mô tả |
|---------------------|--------------|-------|
| `product.ProductCode` | Mã VT | Mã vật tư |
| `product.ProductName` | Tên vật tư | Tên vật tư |
| `unit.Name` | ĐVT | Đơn vị tính |
| `product.Specification` | Quy cách | Quy cách sản phẩm |
| `ExpectedQty` | SL | Số lượng giao |

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

### Error Response - Not Found (404)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Không tìm thấy phiếu giao hàng",
      "code": "ERR003",
      "type": "error"
    }
  ]
}
```

---

## Import vào Postman

1. Mở Postman
2. Click **Import** ở góc trên bên trái
3. Chọn file `delivery-list-and-detail.json`
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

## Test Cases Included

### Delivery List (5 cases)
1. **Get All**: Lấy tất cả phiếu giao hàng
2. **Filter by DepartmentId**: Lọc theo phòng khám
3. **Filter by Status**: Lọc theo trạng thái
4. **Search by Keyword**: Tìm kiếm theo mã phiếu
5. **Multiple Filters**: Kết hợp nhiều bộ lọc

### Delivery Detail (3 cases)
1. **Success**: Lấy chi tiết thành công
2. **Missing ID**: Test validation thiếu ID
3. **Not Found**: Test ID không tồn tại

## Business Logic

### Delivery List
- Chỉ lấy phiếu giao từ DepartmentDemand (RelatedType = 2)
- Sắp xếp theo OutboutRequestId DESC (mới nhất trước)
- Hỗ trợ pagination với limit max = 100
- Load thông tin Branch cho mỗi record

### Delivery Detail
- Load đầy đủ thông tin phiếu giao
- Load chi tiết sản phẩm với Product và Unit info
- Load thông tin Branch
- Trả về 404 nếu không tìm thấy

## Status Mapping

| Status Code | Tên trạng thái | Màu hiển thị | Mô tả |
|-------------|----------------|--------------|-------|
| 1 | Đang giao | Vàng (warning) | Phiếu đang trong quá trình giao hàng |
| 2 | Hoàn thành | Xanh (success) | Phiếu đã giao hàng thành công |

## Notes

- API chỉ lấy phiếu giao từ DepartmentDemand (RelatedType = 2)
- Phiếu giao từ OrderRequest (RelatedType = 1) không được hiển thị
- TotalSKU = Tổng số loại vật tư (không phải tổng số lượng)
- ExpectedQty = Số lượng dự kiến giao
- ActualQty = Số lượng thực tế đã giao (0 nếu chưa xác nhận)
