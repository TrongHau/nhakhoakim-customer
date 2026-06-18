# API Specification: Quản lý vật tư nha khoa

> **Issue:** #6595
> **Base URL:** `http://localhost:6801/api/customer-v2/inventory`
> **Auth:** Bearer JWT Token (middleware `auth`)
> **Rate Limit:** `throttle:api` (60/min), `throttle:search` (30/min) cho endpoints tìm kiếm
> **Schema:** `inventory` (database connection `mysql_inventory`)
> **Tham chiếu:** [business_analytics.md](./business_analytics.md) · [database_design.md](./database_design.md)

---

## Mục lục

1. [Tổng quan Route](#1-tổng-quan-route)
2. [Sản phẩm / Vật tư (Product)](#2-sản-phẩm--vật-tư-product)
3. [Danh mục sản phẩm (ProductCategory)](#3-danh-mục-sản-phẩm-productcategory)
4. [Đơn vị tính (Unit)](#4-đơn-vị-tính-unit)
5. [Nhà cung cấp (Supplier)](#5-nhà-cung-cấp-supplier)
6. [Kho / Phòng khám (Warehouse)](#6-kho--phòng-khám-warehouse)
7. [Yêu cầu cấp phát — Phòng khám (OrderRequest)](#7-yêu-cầu-cấp-phát--phòng-khám-orderrequest)
8. [Quản lý yêu cầu — Kho trung tâm (WarehouseOrderRequest)](#8-quản-lý-yêu-cầu--kho-trung-tâm-warehouseorderrequest)
9. [Phiếu xuất kho (OutboutRequest)](#9-phiếu-xuất-kho-outboutrequest)
10. [Đơn đặt hàng NCC (PurchaseOrder)](#10-đơn-đặt-hàng-ncc-purchaseorder)
11. [Phiếu nhập kho (InboutRequest)](#11-phiếu-nhập-kho-inboutrequest)
12. [Tồn kho (Inventory)](#12-tồn-kho-inventory)
13. [Models](#13-models)
14. [Repositories](#14-repositories)
15. [Form Requests](#15-form-requests)

---

## 1. Tổng quan Route

### Route prefix: `/api/customer-v2/inventory`

Tất cả routes nằm trong middleware group: `['auth', 'throttle:api']`

```
── inventory/
   ├── product/
   │   ├── GET    /list              → ProductController@index          (throttle:search)
   │   ├── GET    /detail/{id}       → ProductController@show
   │   ├── POST   /create            → ProductController@store
   │   ├── PUT    /update/{id}       → ProductController@update
   │   └── PUT    /toggle-state/{id} → ProductController@toggleState
   │
   ├── product-category/
   │   └── GET    /list              → ProductCategoryController@index
   │
   ├── unit/
   │   └── GET    /list              → UnitController@index
   │
   ├── supplier/
   │   ├── GET    /list              → SupplierController@index          (throttle:search)
   │   ├── GET    /detail/{id}       → SupplierController@show
   │   ├── POST   /create            → SupplierController@store
   │   ├── PUT    /update/{id}       → SupplierController@update
   │   └── PUT    /toggle-state/{id} → SupplierController@toggleState
   │
   ├── warehouse/
   │   └── GET    /list              → WarehouseController@index
   │
   ├── order-request/
   │   ├── GET    /list              → OrderRequestController@index      (throttle:search)
   │   ├── GET    /detail/{id}       → OrderRequestController@show
   │   └── POST   /create            → OrderRequestController@store
   │
   ├── warehouse-order-request/
   │   ├── GET    /list              → WarehouseOrderRequestController@index (throttle:search)
   │   └── GET    /detail/{id}       → WarehouseOrderRequestController@show
   │
   ├── outbout-request/
   │   ├── GET    /detail/{id}       → OutboutRequestController@show
   │   ├── POST   /create            → OutboutRequestController@store
   │   └── PUT    /confirm/{id}      → OutboutRequestController@confirm
   │
   ├── purchase-order/
   │   ├── GET    /list              → PurchaseOrderController@index     (throttle:search)
   │   ├── GET    /detail/{id}       → PurchaseOrderController@show
   │   ├── POST   /create            → PurchaseOrderController@store
   │   ├── PUT    /update/{id}       → PurchaseOrderController@update
   │   ├── PUT    /send/{id}         → PurchaseOrderController@send
   │   └── POST   /transfer-item     → PurchaseOrderController@transferItem
   │
   ├── inbout-request/
   │   ├── GET    /list              → InboutRequestController@index     (throttle:search)
   │   ├── GET    /detail/{id}       → InboutRequestController@show
   │   └── POST   /create            → InboutRequestController@store
   │
   └── inventory/
       ├── GET    /list              → InventoryController@index         (throttle:search)
       ├── GET    /summary           → InventoryController@summary
       └── GET    /history/{id}      → InventoryController@history
```

---

## 2. Sản phẩm / Vật tư (Product)

### 2.1 GET `/product/list` — Danh sách sản phẩm

**Mô tả:** Tìm kiếm và lọc danh sách vật tư nha khoa.

**Form Request:** `SearchProductRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `Keyword` | string | No | `max:255` | Tìm theo tên, SKU, mã vạch |
| `ProductCategoryId` | integer | No | `exists:mysql_inventory.ProductCategory,ProductCategoryId` | Lọc theo danh mục |
| `SupplierId` | integer | No | `exists:mysql_inventory.Supplier,SupplierId` | Lọc theo NCC |
| `Status` | integer | No | `in:0,1` | 1: Đang sử dụng, 0: Ngưng |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('ProductList', $data)`

```json
{
  "module": {
    "views": [{
      "type": "Grid",
      "name": "ProductList",
      "data": [
        {
          "ProductId": 1,
          "SKU": "VT001",
          "Name": "Composite Z350",
          "ProductCategoryId": 4,
          "ProductCategoryName": "Vật liệu nha khoa",
          "UnitId": 1,
          "UnitName": "Tuýp",
          "Specification": "Tuýp 4g",
          "Barcode": "8934567890123",
          "SupplierName": "NCC ABC",
          "Status": 1,
          "TotalRow": 50
        }
      ],
      "pagination": { "currentPage": 1, "totalRecord": 50, "limit": 20 }
    }]
  }
}
```

**Data Access:** Eloquent `Product::query()->with(['productCategory', 'supplier', 'unit'])->where(...)->paginate()`

---

### 2.2 GET `/product/detail/{id}` — Chi tiết sản phẩm

| Param | Type | Required | Validation |
|-------|------|----------|------------|
| `id` | integer | Yes | URL param, `exists:mysql_inventory.Product,ProductId` |

**Response:** `formatData('ProductDetail', $data)`

```json
{
  "ProductId": 1,
  "SKU": "VT001",
  "Name": "Composite Z350",
  "ProductCategoryId": 4,
  "ProductCategoryName": "Vật liệu nha khoa",
  "SupplierId": 2,
  "SupplierName": "NCC ABC",
  "UnitId": 1,
  "UnitName": "Tuýp",
  "Specification": "Tuýp 4g",
  "Barcode": "8934567890123",
  "Description": "Vật liệu hàn răng thẩm mỹ",
  "Note": "",
  "Price": 150000.00,
  "Status": 1,
  "CreatedDate": "2026-03-12 10:00:00"
}
```

**Data Access:** Eloquent `Product::query()->with(['productCategory', 'supplier', 'unit'])->find($id)`

---

### 2.3 POST `/product/create` — Tạo sản phẩm mới

**Form Request:** `StoreProductRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `SKU` | string | No | `max:100, unique:mysql_inventory.Product,SKU` | Tự động tạo nếu không nhập |
| `Name` | string | Yes | `max:255` | Tên sản phẩm |
| `ProductCategoryId` | integer | Yes | `exists:mysql_inventory.ProductCategory,ProductCategoryId` | Danh mục |
| `SupplierId` | integer | No | `exists:mysql_inventory.Supplier,SupplierId` | NCC ưu tiên |
| `UnitId` | integer | Yes | `exists:mysql_inventory.Unit,UnitId` | Đơn vị tính |
| `Specification` | string | No | `max:255` | Quy cách đóng gói |
| `Barcode` | string | No | `max:100` | Mã vạch |
| `Description` | string | No | `max:500` | Mô tả |
| `Note` | string | No | `max:1000` | Ghi chú |
| `Price` | numeric | No | `min:0` | Giá tham khảo |
| `Status` | integer | Yes | `in:0,1` | Trạng thái |

**Mã tự sinh:** Nếu `SKU` rỗng → sinh theo format `VTXXX` (VT001, VT002,...)

**Response:** `formatData('ProductDetail', $product)` — HTTP 201

**Data Access:** `Product::insertGetId($data)`

---

### 2.4 PUT `/product/update/{id}` — Cập nhật sản phẩm

**Form Request:** `UpdateProductRequest`

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `Name` | string | Yes | `max:255` |
| `ProductCategoryId` | integer | Yes | `exists:mysql_inventory.ProductCategory,ProductCategoryId` |
| `SupplierId` | integer | No | `exists:mysql_inventory.Supplier,SupplierId` |
| `UnitId` | integer | Yes | `exists:mysql_inventory.Unit,UnitId` |
| `Specification` | string | No | `max:255` |
| `Barcode` | string | No | `max:100` |
| `Description` | string | No | `max:500` |
| `Note` | string | No | `max:1000` |
| `Price` | numeric | No | `min:0` |
| `Status` | integer | Yes | `in:0,1` |

> `SKU` không được phép thay đổi khi sửa.

**Response:** `formatData('ProductDetail', $product)` — HTTP 200

---

### 2.5 PUT `/product/toggle-state/{id}` — Ngưng / Kích hoạt sản phẩm

**Response:** `addMessage('Cập nhật trạng thái thành công', null, self::$SUCCESS)` — HTTP 200

---

## 3. Danh mục sản phẩm (ProductCategory)

### 3.1 GET `/product-category/list` — Danh sách danh mục

**Mô tả:** Lấy danh sách danh mục sản phẩm (dùng cho dropdown).

**Response:** `formatData('ProductCategoryList', $data)`

```json
{
  "data": [
    { "ProductCategoryId": 1, "Name": "Vật tư tiêu hao", "Type": 1 },
    { "ProductCategoryId": 2, "Name": "Thuốc", "Type": 1 },
    { "ProductCategoryId": 3, "Name": "Dụng cụ", "Type": 1 },
    { "ProductCategoryId": 4, "Name": "Vật liệu nha khoa", "Type": 1 }
  ]
}
```

**Data Access:** Eloquent `ProductCategory::query()->where('Status', 1)->orderBy('Priority')->get()`

---

## 4. Đơn vị tính (Unit)

### 4.1 GET `/unit/list` — Danh sách đơn vị tính

**Mô tả:** Lấy danh sách đơn vị tính (dùng cho dropdown).

**Response:** `formatData('UnitList', $data)`

```json
{
  "data": [
    { "UnitId": 1, "Code": "TUP", "Name": "Tuýp" },
    { "UnitId": 2, "Code": "HOP", "Name": "Hộp" },
    { "UnitId": 3, "Code": "CHAI", "Name": "Chai" }
  ]
}
```

**Data Access:** Eloquent `Unit::query()->where('Status', 1)->orderBy('Priority')->get()`

---

## 5. Nhà cung cấp (Supplier)

### 5.1 GET `/supplier/list` — Danh sách NCC

**Form Request:** `SearchSupplierRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `Keyword` | string | No | `max:255` | Tìm theo tên, mã NCC, MST |
| `Status` | integer | No | `in:0,1` | 1: Hoạt động, 0: Ngưng |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('SupplierList', $data)`

```json
{
  "data": [
    {
      "SupplierId": 1,
      "SupplierCode": "NCC001",
      "Name": "Công ty TNHH Thiết bị Y tế ABC",
      "TaxCode": "0301234567",
      "Phone": "0281234567",
      "Status": 1,
      "TotalRow": 10
    }
  ]
}
```

**Data Access:** Eloquent `Supplier::query()->where(...)->paginate()`

---

### 5.2 GET `/supplier/detail/{id}` — Chi tiết NCC

**Response:** `formatData('SupplierDetail', $data)`

```json
{
  "SupplierId": 1,
  "SupplierCode": "NCC001",
  "Name": "Công ty TNHH Thiết bị Y tế ABC",
  "TaxCode": "0301234567",
  "Phone": "0281234567",
  "Email": "contact@abc.vn",
  "Address": "123 Nguyễn Văn Linh, Q7, TP.HCM",
  "Note": "",
  "Status": 1,
  "CreatedDate": "2026-03-12 10:00:00"
}
```

**Data Access:** `$supplierRepo->find($id)`

---

### 5.3 POST `/supplier/create` — Tạo NCC mới

**Form Request:** `StoreSupplierRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `SupplierCode` | string | No | `max:100, unique:mysql_inventory.Supplier,SupplierCode` | Tự động tạo nếu không nhập |
| `Name` | string | Yes | `max:255` | Tên nhà cung cấp |
| `TaxCode` | string | No | `max:100` | Mã số thuế |
| `Phone` | string | No | `max:50` | Điện thoại |
| `Email` | string | No | `email, max:255` | Email |
| `Address` | string | No | `max:1000` | Địa chỉ |
| `Note` | string | No | `max:1000` | Ghi chú |
| `Status` | integer | Yes | `in:0,1` | Trạng thái |

**Mã tự sinh:** `NCCXXX` (NCC001, NCC002,...)

**Response:** `formatData('SupplierDetail', $supplier)` — HTTP 201

---

### 5.4 PUT `/supplier/update/{id}` — Cập nhật NCC

**Form Request:** `UpdateSupplierRequest`

Giống `StoreSupplierRequest` nhưng `SupplierCode` không được phép thay đổi.

**Response:** `formatData('SupplierDetail', $supplier)` — HTTP 200

---

### 5.5 PUT `/supplier/toggle-state/{id}` — Ngưng / Kích hoạt NCC

**Response:** `addMessage('Cập nhật trạng thái thành công', null, self::$SUCCESS)` — HTTP 200

---

## 6. Kho / Phòng khám (Warehouse)

### 6.1 GET `/warehouse/list` — Danh sách kho

**Response:** `formatData('WarehouseList', $data)`

```json
{
  "data": [
    { "WarehouseId": 1, "Name": "Kho trung tâm", "WarehouseType": 1 },
    { "WarehouseId": 2, "Name": "Kho tạm", "WarehouseType": 5 },
    { "WarehouseId": 3, "Name": "Phòng khám Quận 1", "WarehouseType": 10, "BranchId": 10 }
  ]
}
```

> `WarehouseType`: 1 = Công ty, 5 = Kho tạm, 10 = Phòng khám

**Data Access:** Eloquent `Warehouse::query()->where('Status', 1)->get()`

---

## 7. Yêu cầu cấp phát — Phòng khám (OrderRequest)

### 7.1 GET `/order-request/list` — Danh sách yêu cầu (phía phòng khám)

**Mô tả:** Phòng khám xem danh sách yêu cầu cấp phát của mình. Tự động lọc theo `RequesterId` của user đang đăng nhập.

**Form Request:** `SearchOrderRequestRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `Keyword` | string | No | `max:255` | Tìm theo mã yêu cầu |
| `RequestType` | integer | No | `in:1,2` | 1: Manual, 2: Auto |
| `Status` | integer | No | `in:1,2,3,4,5` | Trạng thái yêu cầu |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('OrderRequestList', $data)`

```json
{
  "data": [
    {
      "OrderRequestId": 1,
      "OrderRequestCode": "YC-202603-01",
      "RequestDate": "2026-03-12",
      "RequestedByName": "Nguyễn Văn A",
      "RequestType": 1,
      "Status": 1,
      "TotalProduct": 5,
      "TotalRow": 20
    }
  ]
}
```

**Data Access:** Eloquent `OrderRequest::query()->where('RequesterId', $requesterId)->where(...)->paginate()`

Parameters: `$requesterId, $keyword, $requestType, $status, $offset, $limit`

> `$requesterId` lấy từ `Auth::user()['StaffId']` — không cho client truyền.

---

### 7.2 GET `/order-request/detail/{id}` — Chi tiết yêu cầu

**Response:** `formatData('OrderRequestDetail', $data)`

```json
{
  "OrderRequestId": 1,
  "OrderRequestCode": "YC-202603-01",
  "BranchName": "Phòng khám Quận 1",
  "RequestDate": "2026-03-12",
  "RequestedByName": "Nguyễn Văn A",
  "RequestType": 1,
  "Status": 1,
  "Note": "",
  "Details": [
    {
      "OrderRequestDetailId": 1,
      "ProductId": 1,
      "SKU": "VT001",
      "ProductName": "Composite Z350",
      "UnitName": "Tuýp",
      "Specification": "Tuýp 4g",
      "RequestQuantity": 10,
      "ReceivedQuantity": 5,
      "Note": ""
    }
  ],
  "OutboutRequests": [
    {
      "OutboutRequestId": 1,
      "IRCode": "GH-2603-001",
      "ActualArrivalDate": "2026-03-13",
      "Status": 1,
      "TotalSKU": 3
    }
  ]
}
```

---

### 7.3 POST `/order-request/create` — Tạo yêu cầu cấp phát

**Form Request:** `StoreOrderRequestRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `RequestType` | integer | Yes | `in:1,2` | 1: Manual, 2: Auto |
| `Note` | string | No | `max:1000` | Ghi chú |
| `Details` | array | Yes | `min:1` | Ít nhất 1 dòng vật tư |
| `Details.*.ProductId` | integer | Yes | `exists:mysql_inventory.Product,ProductId` | Sản phẩm |
| `Details.*.UnitId` | integer | No | `exists:mysql_inventory.Unit,UnitId` | Đơn vị tính |
| `Details.*.Specification` | string | No | `max:255` | Quy cách |
| `Details.*.RequestQuantity` | integer | Yes | `min:1, max:99999` | Số lượng yêu cầu |
| `Details.*.Note` | string | No | `max:500` | Ghi chú |

**Giá trị tự động gán:**
- `OrderRequestCode`: sinh theo format `YC-YYYYMM-XX`
- `RequesterId`: từ `Auth::user()['StaffId']`
- `RequestDate`: ngày hiện tại
- `Status`: 1 (Chờ duyệt)
- `CreatedBy`: từ `Auth::id()`

**Response:** HTTP 201

**Data Access:**
1. `OrderRequest::insertGetId($requestData)`
2. `OrderRequestDetail::insert($detailsData)`

---

## 8. Quản lý yêu cầu — Kho trung tâm (WarehouseOrderRequest)

### 8.1 GET `/warehouse-order-request/list` — Danh sách yêu cầu (phía kho)

**Form Request:** `SearchWarehouseOrderRequestRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `Keyword` | string | No | `max:255` | Tìm theo mã yêu cầu |
| `RequesterId` | integer | No | `integer` | Lọc theo người yêu cầu |
| `RequestType` | integer | No | `in:1,2` | Loại yêu cầu |
| `Status` | integer | No | `in:1,2,3,4,5` | Trạng thái |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('WarehouseOrderRequestList', $data)`

**Data Access:** Eloquent `OrderRequest::query()->with(['details.product', 'outboutRequests'])->where(...)->paginate()`

---

### 8.2 GET `/warehouse-order-request/detail/{id}` — Chi tiết yêu cầu (phía kho)

**Mô tả:** Xem chi tiết yêu cầu kèm thông tin tồn kho và số lượng đã giao.

**Response:** `formatData('WarehouseOrderRequestDetail', $data)`

```json
{
  "OrderRequestId": 1,
  "OrderRequestCode": "YC-202603-01",
  "BranchName": "Phòng khám Quận 1",
  "RequestDate": "2026-03-12",
  "RequestedByName": "Nguyễn Văn A",
  "RequestType": 1,
  "Status": 2,
  "Details": [
    {
      "OrderRequestDetailId": 1,
      "ProductId": 1,
      "SKU": "VT001",
      "ProductName": "Composite Z350",
      "UnitName": "Tuýp",
      "Specification": "Tuýp 4g",
      "RequestQuantity": 10,
      "CurrentStock": 25,
      "DeliveredQuantity": 5,
      "Note": ""
    }
  ],
  "OutboutRequests": [
    {
      "OutboutRequestId": 1,
      "IRCode": "GH-2603-001",
      "ActualArrivalDate": "2026-03-13",
      "Status": 2,
      "TotalSKU": 3
    }
  ]
}
```

> `CurrentStock`: tồn kho tại kho trung tâm (WarehouseType = 1), lấy từ `Inventory`.
> `DeliveredQuantity`: tổng `ActualQty` từ `OutboutRequestDetail` đã xác nhận.

---

## 9. Phiếu xuất kho (OutboutRequest)

### 9.1 GET `/outbout-request/detail/{id}` — Chi tiết phiếu xuất kho

**Response:** `formatData('OutboutRequestDetail', $data)`

```json
{
  "OutboutRequestId": 1,
  "IRCode": "GH-2603-001",
  "OrderRequestCode": "YC-202603-01",
  "WarehouseName": "Kho trung tâm",
  "BranchName": "Phòng khám Quận 1",
  "ActualArrivalDate": "2026-03-13",
  "Status": 1,
  "Note": "",
  "Details": [
    {
      "OutboutRequestDetailId": 1,
      "SKU": "VT001",
      "ProductName": "Composite Z350",
      "UnitName": "Tuýp",
      "Specification": "Tuýp 4g",
      "ActualQty": 5,
      "Note": ""
    }
  ]
}
```

---

### 9.2 POST `/outbout-request/create` — Tạo phiếu xuất kho

**Form Request:** `StoreOutboutRequestRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `RelatedType` | integer | Yes | `integer` | Loại liên kết (OrderRequest) |
| `RelatedId` | integer | Yes | `exists:mysql_inventory.OrderRequest,OrderRequestId` | ID yêu cầu liên kết |
| `ActualArrivalDate` | datetime | Yes | `date` | Ngày xuất kho |
| `Details` | array | Yes | `min:1` | Danh sách vật tư xuất |
| `Details.*.ProductId` | integer | Yes | `exists:mysql_inventory.Product,ProductId` | Sản phẩm |
| `Details.*.UnitId` | integer | No | `exists:mysql_inventory.Unit,UnitId` | ĐVT |
| `Details.*.Specification` | string | No | `max:255` | Quy cách |
| `Details.*.ActualQty` | integer | Yes | `min:1, max:99999` | Số lượng xuất |
| `Details.*.Note` | string | No | `max:500` | Ghi chú |

**Business Rules:**
- `IRCode` tự sinh: `GH-YYMM-XXX`
- `Status`: 1 (Đang giao)

**Side Effects:**
1. Giảm `Inventory.Quantity` tại kho trung tâm
2. Ghi `InventoryTransaction` (TransactionType = 2: Xuất kho)
3. Cập nhật `OrderRequest.Status` → 3 hoặc 4

**Response:** HTTP 201

---

### 9.3 PUT `/outbout-request/confirm/{id}` — Xác nhận nhận hàng

**Form Request:** `ConfirmOutboutRequestRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `ActualArrivalDate` | datetime | Yes | `date` | Thời gian nhận hàng |
| `Note` | string | No | `max:1000` | Ghi chú tình trạng |

**Business Rules:**
- Chỉ cho phép confirm khi `OutboutRequest.Status = 1`
- Sau confirm: `Status` → 2 (Đã xác nhận)

**Side Effects:**
1. Cập nhật `OutboutRequest.Status = 2`
2. Cộng dồn `OrderRequestDetail.ReceivedQuantity`
3. Tăng `Inventory.Quantity` tại kho phòng khám (WarehouseType = 10)
4. Ghi `InventoryTransaction` (TransactionType = 2)
5. Nếu tất cả details đã nhận đủ → `OrderRequest.Status` → 5

**Response:** HTTP 200

---

## 10. Đơn đặt hàng NCC (PurchaseOrder)

### 10.1 GET `/purchase-order/list` — Danh sách PO

**Form Request:** `SearchPurchaseOrderRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `SupplierId` | integer | No | `exists:mysql_inventory.Supplier,SupplierId` | Lọc theo NCC |
| `Status` | integer | No | `in:1,2,3,4` | Trạng thái PO |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('PurchaseOrderList', $data)`

```json
{
  "data": [
    {
      "PurchaseOrderId": 1,
      "PurchaseOrderCode": "PO-2603-001",
      "SupplierName": "NCC ABC",
      "OrderDate": "2026-03-12",
      "ExpectedDeliveryDate": "2026-03-20",
      "TotalAmount": 15000000.00,
      "Status": 1,
      "TotalRow": 10
    }
  ]
}
```

**Data Access:** Eloquent `PurchaseOrder::query()->with('supplier')->where(...)->paginate()`

---

### 10.2 GET `/purchase-order/detail/{id}` — Chi tiết PO

**Response:** `formatData('PurchaseOrderDetail', $data)`

```json
{
  "PurchaseOrderId": 1,
  "PurchaseOrderCode": "PO-2603-001",
  "SupplierId": 1,
  "SupplierName": "NCC ABC",
  "OrderDate": "2026-03-12",
  "ExpectedDeliveryDate": "2026-03-20",
  "TotalAmount": 15000000.00,
  "Status": 1,
  "Note": "",
  "Details": [
    {
      "PurchaseOrderDetailId": 1,
      "ProductId": 1,
      "SKU": "VT001",
      "ProductName": "Composite Z350",
      "Specification": "Tuýp 4g",
      "UnitName": "Tuýp",
      "Quantity": 100,
      "UnitPrice": 150000.00,
      "Amount": 15000000.00,
      "ReceivedQuantity": 0,
      "Note": "",
      "Status": 1
    }
  ]
}
```

---

### 10.3 POST `/purchase-order/create` — Tạo PO mới

**Form Request:** `StorePurchaseOrderRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `SupplierId` | integer | Yes | `exists:mysql_inventory.Supplier,SupplierId` | Nhà cung cấp |
| `OrderDate` | date | Yes | `date` | Ngày đặt |
| `ExpectedDeliveryDate` | date | No | `date, after_or_equal:OrderDate` | Ngày dự kiến giao |
| `Note` | string | No | `max:1000` | Ghi chú |
| `Details` | array | Yes | `min:1` | Danh sách vật tư |
| `Details.*.ProductId` | integer | Yes | `exists:mysql_inventory.Product,ProductId` | Sản phẩm |
| `Details.*.UnitId` | integer | No | `exists:mysql_inventory.Unit,UnitId` | ĐVT |
| `Details.*.Specification` | string | No | `max:255` | Quy cách |
| `Details.*.Quantity` | integer | Yes | `min:1, max:99999` | Số lượng |
| `Details.*.UnitPrice` | numeric | Yes | `min:0` | Đơn giá VNĐ |
| `Details.*.Note` | string | No | `max:500` | Ghi chú |

**Giá trị tự động:**
- `PurchaseOrderCode`: sinh `PO-YYMM-XXX`
- `Status`: 1 (Đang tạo)
- `Amount` mỗi detail: `Quantity * UnitPrice`
- `TotalAmount`: `SUM(Amount)`

**Response:** HTTP 201

---

### 10.4 PUT `/purchase-order/update/{id}` — Cập nhật PO

**Business Rules:**
- Chỉ cho phép update khi `Status = 1`
- `SupplierId` không được thay đổi
- Details không có trong payload bị xóa mềm (`Status = 0`)

**Response:** HTTP 200

---

### 10.5 PUT `/purchase-order/send/{id}` — Gửi PO cho NCC

**Business Rules:**
- Chỉ cho phép khi `Status = 1`
- Phải có ít nhất 1 detail active (`Status = 1`)
- Sau khi gửi: `Status` → 2

**Response:** HTTP 200

---

### 10.6 POST `/purchase-order/transfer-item` — Chuyển sản phẩm sang NCC khác

**Form Request:** `TransferPurchaseOrderItemRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `PurchaseOrderDetailId` | integer | Yes | `exists:mysql_inventory.PurchaseOrderDetail,PurchaseOrderDetailId` | Detail cần chuyển |
| `TargetSupplierId` | integer | Yes | `exists:mysql_inventory.Supplier,SupplierId` | NCC đích |

**Response:** HTTP 200

```json
{
  "messages": [{ "mes": "Chuyển sản phẩm thành công" }],
  "data": {
    "SourcePurchaseOrderId": 1,
    "TargetPurchaseOrderId": 3,
    "TargetPurchaseOrderCode": "PO-2603-003",
    "IsNewPO": true
  }
}
```

---

## 11. Phiếu nhập kho (InboutRequest)

### 11.1 GET `/inbout-request/list` — Danh sách phiếu nhập

**Form Request:** `SearchInboutRequestRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `PurchaseOrderId` | integer | No | `integer` | Lọc theo PO |
| `Status` | integer | No | `in:1,2` | 1: Đang kiểm tra, 2: Đã nhập |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('InboutRequestList', $data)`

```json
{
  "data": [
    {
      "InboutRequestId": 1,
      "IRCode": "NK-2603-001",
      "SupplierName": "NCC ABC",
      "ActualArrivalDate": "2026-03-20",
      "TotalSKU": 3,
      "Status": 2,
      "TotalRow": 5
    }
  ]
}
```

**Data Access:** Eloquent `InboutRequest::query()->with('purchaseOrder.supplier')->where(...)->paginate()`

---

### 11.2 GET `/inbout-request/detail/{id}` — Chi tiết phiếu nhập

**Response:** `formatData('InboutRequestDetail', $data)`

```json
{
  "InboutRequestId": 1,
  "IRCode": "NK-2603-001",
  "PurchaseOrderId": 1,
  "PurchaseOrderCode": "PO-2603-001",
  "SupplierName": "NCC ABC",
  "ActualArrivalDate": "2026-03-20",
  "Status": 2,
  "Details": [
    {
      "InboutRequestDetailId": 1,
      "ProductName": "Composite Z350",
      "UnitName": "Tuýp",
      "ExpectedQty": 100,
      "ActualQty": 95,
      "ExceptionQty": -5,
      "Price": 150000.00,
      "ExpirationDate": "2028-03-01",
      "LOT": "LOT001",
      "Note": "Thiếu 5 tuýp"
    }
  ]
}
```

---

### 11.3 POST `/inbout-request/create` — Tạo phiếu nhập kho

**Form Request:** `StoreInboutRequestRequest`

| Field | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `PurchaseOrderId` | integer | Yes | `exists:mysql_inventory.PurchaseOrder,PurchaseOrderId` | PO liên kết |
| `ActualArrivalDate` | datetime | Yes | `date` | Ngày nhận hàng |
| `Details` | array | Yes | `min:1` | Danh sách vật tư nhập |
| `Details.*.ProductId` | integer | Yes | `exists:mysql_inventory.Product,ProductId` | Sản phẩm |
| `Details.*.UnitId` | integer | No | `exists:mysql_inventory.Unit,UnitId` | ĐVT |
| `Details.*.ExpectedQty` | integer | Yes | `min:0` | Số lượng đặt (từ PO) |
| `Details.*.ActualQty` | integer | Yes | `min:0, max:99999` | Số lượng thực nhận |
| `Details.*.Price` | numeric | No | `min:0` | Đơn giá |
| `Details.*.ExpirationDate` | datetime | No | `date` | Hạn sử dụng |
| `Details.*.LOT` | string | No | `max:100` | Số lô |
| `Details.*.Note` | string | No | `max:500` | Ghi chú |

**Business Rules:**
- PO phải ở `Status = 2` hoặc `Status = 3`
- `IRCode` tự sinh: `NK-YYMM-XXX`
- `ExceptionQty` tự tính: `ActualQty - ExpectedQty`
- `Status` mặc định: 2 (Đã nhập)

**Side Effects:**
1. Tạo `InboutRequest` + `InboutRequestDetail`
2. Cộng dồn `PurchaseOrderDetail.ReceivedQuantity`
3. Tăng `Inventory.Quantity` tại kho trung tâm
4. Ghi `InventoryTransaction` (TransactionType = 1: Nhập kho)
5. Cập nhật `PurchaseOrder.Status` → 3 hoặc 4

**Response:** HTTP 201

---

## 12. Tồn kho (Inventory)

### 12.1 GET `/inventory/list` — Danh sách tồn kho

**Form Request:** `SearchInventoryRequest`

| Param | Type | Required | Validation | Mô tả |
|-------|------|----------|------------|--------|
| `Keyword` | string | No | `max:255` | Tìm tên sản phẩm, SKU |
| `WarehouseId` | integer | No | `integer` | Lọc theo kho |
| `ProductCategoryId` | integer | No | `exists:mysql_inventory.ProductCategory,ProductCategoryId` | Lọc theo danh mục |
| `limit` | integer | No | `min:1, max:100` | Default: 20 |
| `lmstart` | integer | No | `min:0` | Default: 0 |

**Response:** `formatDataPaginationByStore('InventoryList', $data)`

```json
{
  "data": [
    {
      "InventoryId": 1,
      "WarehouseName": "Kho trung tâm",
      "ProductName": "Composite Z350",
      "ProductCategoryName": "Vật liệu nha khoa",
      "Quantity": 25,
      "MinStock": 10,
      "TotalValue": 3750000.00,
      "UpdatedDate": "2026-03-12 14:30:00",
      "TotalRow": 100
    }
  ]
}
```

**Data Access:** Eloquent `Inventory::query()->with(['product.productCategory', 'warehouse'])->where(...)->paginate()`

---

### 12.2 GET `/inventory/summary` — Dashboard tổng hợp tồn kho

**Response:** `formatData('InventorySummary', $data)`

```json
{
  "TotalProduct": 150,
  "CentralWarehouseStock": 5000,
  "TotalSystemStock": 8500,
  "LowStockCount": 12
}
```

**Data Access:** Eloquent aggregation — `Product::count()`, `Inventory::sum('Quantity')`, `Inventory::where('Quantity', '<=', DB::raw('MinStock'))->count()`

---

### 12.3 GET `/inventory/history/{id}` — Lịch sử biến động tồn kho

**Form Request:** `SearchInventoryHistoryRequest`

| Param | Type | Required | Validation |
|-------|------|----------|------------|
| `id` | integer | Yes | URL param (`InventoryId`) |
| `limit` | integer | No | `min:1, max:100` |
| `lmstart` | integer | No | `min:0` |

**Response:** `formatDataPaginationByStore('InventoryHistory', $data)`

```json
{
  "Inventory": {
    "InventoryId": 1,
    "ProductName": "Composite Z350",
    "ProductCategoryName": "Vật liệu nha khoa",
    "WarehouseName": "Kho trung tâm",
    "Quantity": 25
  },
  "History": [
    {
      "InventoryTransactionId": 1,
      "TransactionType": 1,
      "Quantity": 100,
      "ReferenceType": "InboutRequest",
      "ReferenceId": 1,
      "Note": "Nhập kho từ PO-2603-001",
      "CreatedDate": "2026-03-12 10:00:00"
    }
  ]
}
```

> `TransactionType`: 1 = Nhập kho (+), 2 = Xuất kho (-)

**Data Access:** Eloquent `InventoryTransaction::query()->where('InventoryId', $id)->orderByDesc('CreatedDate')->paginate()`

---

## 13. Models

### Database Connection: `mysql_inventory`

| # | Model | Table | PrimaryKey | Relationships |
|---|-------|-------|------------|---------------|
| 1 | `Product` | `Product` | `ProductId` | belongsTo: ProductCategory, Supplier, Unit |
| 2 | `ProductCategory` | `ProductCategory` | `ProductCategoryId` | hasMany: Product |
| 3 | `Unit` | `Unit` | `UnitId` | hasMany: Product |
| 4 | `Supplier` | `Supplier` | `SupplierId` | hasMany: Product, PurchaseOrder |
| 5 | `Warehouse` | `Warehouse` | `WarehouseId` | hasMany: Inventory |
| 6 | `OrderRequest` | `OrderRequest` | `OrderRequestId` | hasMany: OrderRequestDetail, OutboutRequest |
| 7 | `OrderRequestDetail` | `OrderRequestDetail` | `OrderRequestDetailId` | belongsTo: OrderRequest, Product, Unit |
| 8 | `OutboutRequest` | `OutboutRequest` | `OutboutRequestId` | hasMany: OutboutRequestDetail; belongsTo: OrderRequest |
| 9 | `OutboutRequestDetail` | `OutboutRequestDetail` | `OutboutRequestDetailId` | belongsTo: OutboutRequest, Product, Unit |
| 10 | `PurchaseOrder` | `PurchaseOrder` | `PurchaseOrderId` | belongsTo: Supplier; hasMany: PurchaseOrderDetail, InboutRequest |
| 11 | `PurchaseOrderDetail` | `PurchaseOrderDetail` | `PurchaseOrderDetailId` | belongsTo: PurchaseOrder, Product, Unit |
| 12 | `InboutRequest` | `InboutRequest` | `InboutRequestId` | belongsTo: PurchaseOrder; hasMany: InboutRequestDetail |
| 13 | `InboutRequestDetail` | `InboutRequestDetail` | `InboutRequestDetailId` | belongsTo: InboutRequest, Product, Unit |
| 14 | `Inventory` | `Inventory` | `InventoryId` | belongsTo: Product, Warehouse; hasMany: InventoryTransaction |
| 15 | `InventoryTransaction` | `InventoryTransaction` | `InventoryTransactionId` | belongsTo: Inventory, Product, Unit |

---

## 14. Repositories

| # | Interface | Repository | Mô tả |
|---|-----------|------------|--------|
| 1 | `ProductInterface` | `ProductRepository` | CRUD sản phẩm + search |
| 2 | `ProductCategoryInterface` | `ProductCategoryRepository` | List danh mục |
| 3 | `UnitInterface` | `UnitRepository` | List đơn vị tính |
| 4 | `SupplierInterface` | `SupplierRepository` | CRUD NCC + search |
| 5 | `WarehouseInterface` | `WarehouseRepository` | List kho |
| 6 | `OrderRequestInterface` | `OrderRequestRepository` | Tạo yêu cầu + search + detail |
| 7 | `OutboutRequestInterface` | `OutboutRequestRepository` | Tạo/confirm phiếu xuất + detail |
| 8 | `PurchaseOrderInterface` | `PurchaseOrderRepository` | CRUD PO + send + transfer item |
| 9 | `InboutRequestInterface` | `InboutRequestRepository` | Tạo phiếu nhập + search + detail |
| 10 | `InventoryInterface` | `InventoryRepository` | Tồn kho + summary + history + updateStock |

---

## 15. Form Requests

| # | Form Request | Controller Method | HTTP |
|---|-------------|-------------------|------|
| 1 | `SearchProductRequest` | `ProductController@index` | GET |
| 2 | `StoreProductRequest` | `ProductController@store` | POST |
| 3 | `UpdateProductRequest` | `ProductController@update` | PUT |
| 4 | `SearchSupplierRequest` | `SupplierController@index` | GET |
| 5 | `StoreSupplierRequest` | `SupplierController@store` | POST |
| 6 | `UpdateSupplierRequest` | `SupplierController@update` | PUT |
| 7 | `SearchOrderRequestRequest` | `OrderRequestController@index` | GET |
| 8 | `StoreOrderRequestRequest` | `OrderRequestController@store` | POST |
| 9 | `SearchWarehouseOrderRequestRequest` | `WarehouseOrderRequestController@index` | GET |
| 10 | `StoreOutboutRequestRequest` | `OutboutRequestController@store` | POST |
| 11 | `ConfirmOutboutRequestRequest` | `OutboutRequestController@confirm` | PUT |
| 12 | `SearchPurchaseOrderRequest` | `PurchaseOrderController@index` | GET |
| 13 | `StorePurchaseOrderRequest` | `PurchaseOrderController@store` | POST |
| 14 | `UpdatePurchaseOrderRequest` | `PurchaseOrderController@update` | PUT |
| 15 | `SendPurchaseOrderRequest` | `PurchaseOrderController@send` | PUT |
| 16 | `TransferPurchaseOrderItemRequest` | `PurchaseOrderController@transferItem` | POST |
| 17 | `SearchInboutRequestRequest` | `InboutRequestController@index` | GET |
| 18 | `StoreInboutRequestRequest` | `InboutRequestController@store` | POST |
| 19 | `SearchInventoryRequest` | `InventoryController@index` | GET |
| 20 | `SearchInventoryHistoryRequest` | `InventoryController@history` | GET |

---

## Phụ lục: Controllers cần tạo

| # | Controller | Prefix | Methods |
|---|-----------|--------|---------|
| 1 | `ProductController` | `product/` | index, show, store, update, toggleState |
| 2 | `ProductCategoryController` | `product-category/` | index |
| 3 | `UnitController` | `unit/` | index |
| 4 | `SupplierController` | `supplier/` | index, show, store, update, toggleState |
| 5 | `WarehouseController` | `warehouse/` | index |
| 6 | `OrderRequestController` | `order-request/` | index, show, store |
| 7 | `WarehouseOrderRequestController` | `warehouse-order-request/` | index, show |
| 8 | `OutboutRequestController` | `outbout-request/` | show, store, confirm |
| 9 | `PurchaseOrderController` | `purchase-order/` | index, show, store, update, send, transferItem |
| 10 | `InboutRequestController` | `inbout-request/` | index, show, store |
| 11 | `InventoryController` | `inventory/` | index, summary, history |

---

## Phụ lục: Thứ tự triển khai đề xuất

**Phase 1 — Master Data:**
1. Models (15 models) + Database connection `mysql_inventory`
2. ProductCategory (list)
3. Unit (list)
4. Warehouse (list)
5. Product (CRUD + search)
6. Supplier (CRUD + search)

**Phase 2 — Luồng phòng khám:**
7. OrderRequest (create + list + detail)

**Phase 3 — Luồng kho trung tâm:**
8. WarehouseOrderRequest (list + detail)
9. OutboutRequest (create + confirm + detail)
10. Inventory (list + summary + history)

**Phase 4 — Luồng đặt hàng NCC:**
11. PurchaseOrder (CRUD + send + transfer-item)
12. InboutRequest (create + list + detail)
