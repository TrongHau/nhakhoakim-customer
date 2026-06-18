# API Create Delivery From Demand - Postman Collection

## Mô tả
API này dùng để tạo phiếu giao hàng từ DepartmentDemand (nhu cầu vật tư của phòng khám).

## Endpoint
```
POST /customer/inventory/order-request/create-delivery-from-demand
```

## Request Body Structure

### Required Fields (Bắt buộc)
- `DepartmentId` (integer): ID của phòng khám/chi nhánh
- `Products` (array): Danh sách sản phẩm cần giao
  - `ProductId` (integer): ID sản phẩm
  - `UnitId` (integer): ID đơn vị tính
  - `DeliveryQty` (integer): Số lượng giao

### Optional Fields (Tùy chọn)
- `DepartmentType` (integer): Loại phòng ban (default: 1)
- `ExpectedReceiptDate` (date): Ngày dự kiến nhận hàng (format: YYYY-MM-DD)
- `DeliveryStaff` (string): Tên nhân viên giao hàng
- `DeliveryDate` (date): Ngày giao hàng (format: YYYY-MM-DD)
- `Note` (string): Ghi chú

## Request Example

### Case 1: Full Fields (Đầy đủ thông tin)
```json
{
  "DepartmentId": 1,
  "DepartmentType": 1,
  "Products": [
    {
      "ProductId": 101,
      "UnitId": 1,
      "DeliveryQty": 50
    },
    {
      "ProductId": 102,
      "UnitId": 2,
      "DeliveryQty": 30
    }
  ],
  "ExpectedReceiptDate": "2024-12-31",
  "DeliveryStaff": "Nguyễn Văn A",
  "DeliveryDate": "2024-12-25",
  "Note": "Giao hàng khẩn cấp"
}
```

### Case 2: Minimal Fields (Tối thiểu)
```json
{
  "DepartmentId": 2,
  "Products": [
    {
      "ProductId": 201,
      "UnitId": 1,
      "DeliveryQty": 100
    }
  ]
}
```

## Response Example

### Success Response (201 Created)
```json
{
  "status": "success",
  "data": [
    {
      "name": "OutboutRequestId",
      "data": {
        "OutboutRequestId": 123
      }
    }
  ],
  "messages": [
    {
      "message": "Tạo phiếu giao hàng thành công",
      "code": "SUC001",
      "type": "success"
    }
  ]
}
```

### Error Response - Missing DepartmentId (422)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Vui lòng cung cấp DepartmentId",
      "code": "ERR001",
      "type": "error"
    }
  ]
}
```

### Error Response - Missing Products (422)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Vui lòng cung cấp danh sách sản phẩm",
      "code": "ERR001",
      "type": "error"
    }
  ]
}
```

### Error Response - Empty Products (500)
```json
{
  "status": "error",
  "messages": [
    {
      "message": "Vui lòng chọn sản phẩm để giao hàng",
      "code": "ERR999",
      "type": "error"
    }
  ]
}
```

## Business Logic

Khi API được gọi, hệ thống sẽ thực hiện các bước sau:

1. **Tạo OutboutRequest**
   - Tạo mã phiếu giao hàng tự động (format: GH-YYMM-XXX)
   - RelatedType = 2 (từ DepartmentDemand)
   - Status = 1 (Đang giao)

2. **Tạo OutboutRequestDetail**
   - Tạo chi tiết cho từng sản phẩm
   - ExpectedQty = DeliveryQty
   - ActualQty = 0 (chưa nhận)

3. **Update DepartmentDemand**
   - Giảm PendingQty theo số lượng giao
   - PendingQty = max(0, PendingQty - DeliveryQty)

4. **Insert DepartmentDemandLog**
   - ChangeType = 'DELIVERY'
   - ChangeQty = -DeliveryQty (số âm)
   - Ghi nhận QtyBefore và QtyAfter

5. **Trừ tồn kho**
   - Trừ tồn kho tại kho trung tâm (WarehouseType = 1)
   - Inventory.Quantity -= DeliveryQty

6. **Log InventoryTransaction**
   - TransactionType = 2 (Xuất kho)
   - RefType = 'OutboutRequest'
   - RefId = OutboutRequestId

## Import vào Postman

1. Mở Postman
2. Click **Import** ở góc trên bên trái
3. Chọn file `create-delivery-from-demand.json`
4. Collection sẽ được import với 6 test cases

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

1. **Success Case**: Tạo phiếu giao hàng thành công với đầy đủ thông tin
2. **Minimal Fields**: Tạo với các trường bắt buộc tối thiểu
3. **Multiple Products**: Tạo với nhiều sản phẩm (5 items)
4. **Missing DepartmentId**: Test validation thiếu DepartmentId
5. **Missing Products**: Test validation thiếu Products array
6. **Empty Products Array**: Test validation Products array rỗng

## Notes

- Tất cả operations được wrap trong DB transaction
- Nếu có lỗi, toàn bộ thao tác sẽ rollback
- OutboutCode được generate tự động theo format: GH-YYMM-XXX
- Chỉ trừ tồn kho tại kho trung tâm (WarehouseType = 1)
- DeliveryQty phải > 0, nếu <= 0 sẽ bị skip
