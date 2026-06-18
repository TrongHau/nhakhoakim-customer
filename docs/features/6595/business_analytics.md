# Business Analytics: Quản lý vật tư nha khoa

> **Issue:** #6595
> **Nguồn thiết kế:** [Magic Patterns - NHA KHOA ERP](https://project-smooth-anchovy-446.magicpatterns.app/)
> **Artifact ID:** 79635fce-d7bb-48f6-8732-3ba187c48ac8

---

## 1. Tổng quan hệ thống

Hệ thống **Quản lý vật tư nha khoa** là module ERP quản lý toàn bộ quy trình cung ứng vật tư y tế từ kho trung tâm đến các phòng khám chi nhánh. Hệ thống hỗ trợ 2 vai trò chính với các luồng nghiệp vụ riêng biệt.

### 1.1 Vai trò hệ thống (Roles)

| Vai trò | Mã | Mô tả | Số màn hình |
|---------|-----|--------|-------------|
| **Phòng khám** | `clinic` | Quản lý phòng khám chi nhánh — tạo đơn đặt hàng, theo dõi và xác nhận nhận hàng | 1 |
| **Kho trung tâm** | `warehouse` | Thủ kho — quản lý danh mục, nhà cung cấp, xử lý yêu cầu, đặt hàng NCC, nhập kho, quản lý tồn kho | 6 |

### 1.2 Tổng quan màn hình

| # | Vai trò | Màn hình | Mô tả chức năng |
|---|---------|----------|------------------|
| 1 | Phòng khám | Danh sách đơn hàng | Tạo đơn đặt hàng vật tư, theo dõi trạng thái, xác nhận nhận hàng |
| 2 | Kho trung tâm | Danh mục vật tư | Quản lý master data vật tư (CRUD, import Excel) |
| 3 | Kho trung tâm | Nhà cung cấp | Quản lý master data nhà cung cấp (CRUD) |
| 4 | Kho trung tâm | Quản lý yêu cầu | Xem yêu cầu từ phòng khám, tạo phiếu xuất kho giao hàng |
| 5 | Kho trung tâm | Đơn đặt hàng NCC | Tạo PO cho nhà cung cấp, gửi đơn, chuyển NCC |
| 6 | Kho trung tâm | Nhập kho | Tạo phiếu nhập kho từ PO, kiểm kê chênh lệch |
| 7 | Kho trung tâm | Quản lý tồn kho | Theo dõi tồn kho đa kho, cảnh báo, lịch sử biến động |

---

## 2. Luồng nghiệp vụ tổng thể (End-to-End Flow)

```
Phòng khám tạo đơn đặt hàng (Yêu cầu cấp phát vật tư)
    ↓ Trạng thái: "Chờ duyệt"
Kho trung tâm tiếp nhận yêu cầu (Quản lý yêu cầu)
    ↓ Duyệt và tạo phiếu xuất kho
Kho trung tâm giao hàng cho phòng khám
    ↓ Trạng thái: "Đang giao"
Phòng khám xác nhận nhận hàng
    ↓ Trạng thái: "Đã xác nhận"
    ↓
Song song: Kho trung tâm kiểm tra tồn kho
    ↓ Nếu cần bổ sung → Tạo đơn đặt hàng NCC (PO)
Gửi PO cho nhà cung cấp
    ↓ Trạng thái: "Đã gửi nhà cung cấp"
NCC giao hàng → Tạo phiếu nhập kho
    ↓ Kiểm kê số lượng thực nhận vs đặt
Cập nhật tồn kho
```

---

## 3. Chi tiết từng màn hình

---

### 3.1 Danh sách đơn hàng (Phòng khám)

**Vai trò:** Phòng khám
**Mục đích:** Cho phép phòng khám tạo yêu cầu cấp phát vật tư và theo dõi quá trình giao nhận.

#### 3.1.1 Danh sách yêu cầu

**Bộ lọc:**
- Tìm mã yêu cầu (text search)
- Loại yêu cầu: `Tự động` | `Bổ sung` | `Đặc biệt`
- Trạng thái: `Chờ duyệt` | `Đã duyệt` | `Đang soạn hàng` | `Đang vận chuyển` | `Đã hoàn tất`

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã yêu cầu | Định dạng: `YC-YYYYMM-XX` (vd: YC-202310-01) |
| Ngày yêu cầu | dd/mm/yyyy |
| Người yêu cầu | Tên nhân viên tạo yêu cầu |
| Trạng thái | Badge màu theo trạng thái |
| Tổng vật tư | Tổng số lượng vật tư trong đơn |
| Thao tác | Xem chi tiết |

**Thao tác:**
- **Đặt hàng** (nút chính): Mở modal tạo đơn đặt hàng mới
- **Xem chi tiết** (icon mắt): Mở modal chi tiết yêu cầu

#### 3.1.2 Modal: Tạo đơn đặt hàng

**Thông tin nhập:**
- **Loại yêu cầu** (dropdown): `Tự động` | `Bổ sung` | `Đặc biệt`
- **Danh mục đề nghị** (Excel Grid — bảng nhập liệu kiểu spreadsheet):

| Cột | Kiểu | Mô tả |
|-----|------|--------|
| Mã vật tư | Text | Mã định danh vật tư |
| Tên vật tư | Select | Chọn từ danh mục vật tư có sẵn |
| Đơn vị tính | Select | Hộp, Tuýp, Thùng, Vỉ, Chai, Gói, Cái, Bộ, Cuộn |
| Quy cách | Text | Quy cách đóng gói (vd: Tuýp 4g) |
| Số lượng yêu cầu | Number | Số lượng cần đặt |
| Ghi chú | Text | Ghi chú bổ sung |

**Quy tắc:**
- Ít nhất 1 dòng vật tư phải có tên và số lượng
- Mã yêu cầu tự động sinh theo format `YC-YYYYMM-XX`
- Trạng thái mặc định khi tạo: `Chờ duyệt`
- Phòng khám và người yêu cầu tự động gán theo user đang đăng nhập

#### 3.1.3 Modal: Chi tiết yêu cầu

**Thông tin header:**
- Mã yêu cầu, Ngày yêu cầu, Người yêu cầu, Loại yêu cầu, Trạng thái

**Bảng "Danh mục đề nghị":**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã vật tư | Mã vật tư |
| Tên vật tư | Tên vật tư |
| Đơn vị tính | ĐVT |
| Quy cách | Quy cách đóng gói |
| Số lượng yêu cầu | Số lượng đã yêu cầu |
| Ghi chú | Ghi chú |
| **Số lượng đã nhận** | Số lượng phòng khám đã xác nhận nhận (highlight xanh khi đủ) |

**Bảng "Phiếu xuất kho" (liên kết):**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã phiếu xuất | Định dạng: `GH-YYMM-XXX` |
| Ngày gửi | dd/mm/yyyy |
| Trạng thái | `Đang giao` | `Đã xác nhận` |
| Tổng vật tư | Tổng số lượng trong phiếu |
| Thao tác | Xem chi tiết, Xác nhận nhận hàng (chỉ khi "Đang giao") |

#### 3.1.4 Modal: Chi tiết phiếu xuất kho

**Thông tin header:**
- Mã phiếu xuất, Kho xuất (luôn là "Kho trung tâm"), Ngày gửi, Trạng thái

**Bảng danh mục vật tư giao:**
- STT, Mã vật tư, Tên vật tư, Đơn vị tính, Quy cách, Số lượng giao, Ghi chú

**Thao tác:** Nút "Xác nhận đã nhận" (chỉ hiện khi trạng thái "Đang giao")

#### 3.1.5 Modal: Xác nhận nhận hàng

**Thông tin nhập:**
- **Thời gian nhận hàng** (datetime-local): Mặc định thời gian hiện tại
- **Người nhận** (text): Tên người nhận hàng
- **Ghi chú** (textarea): Ghi chú tình trạng hàng hóa

**Quy tắc:**
- Sau xác nhận, trạng thái phiếu xuất chuyển thành `Đã xác nhận`
- Số lượng đã nhận trong bảng danh mục đề nghị được cập nhật

---

### 3.2 Danh mục vật tư (Kho trung tâm)

**Vai trò:** Kho trung tâm
**Mục đích:** Quản lý master data danh mục vật tư y tế toàn hệ thống.

#### 3.2.1 Danh sách vật tư

**Bộ lọc:**
- Tìm tên, mã vật tư, mã vạch (text search)
- Nhóm vật tư: `Vật tư tiêu hao` | `Thuốc` | `Dụng cụ` | `Vật liệu nha khoa`
- Trạng thái: `Đang sử dụng` | `Ngưng`

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã vật tư | Định dạng: `VTXXX` (vd: VT001) |
| Tên vật tư | Tên đầy đủ |
| Nhóm vật tư | Phân loại nhóm |
| Đơn vị tính | Có thể nhiều ĐVT (vd: "Tuýp, Hộp") |
| Quy cách | Quy cách đóng gói |
| Nhà cung cấp ưu tiên | NCC mặc định |
| Trạng thái | Badge màu |
| Thao tác | Xem, Sửa, Ngưng sử dụng |

**Thao tác header:**
- **Import** (nút outline): Import vật tư từ Excel
- **Thêm vật tư** (nút chính): Tạo vật tư mới

#### 3.2.2 Modal: Thêm / Chỉnh sửa vật tư

**Các trường nhập liệu:**

| Trường | Kiểu | Bắt buộc | Ghi chú |
|--------|------|----------|---------|
| Mã vật tư | Text | Có | Tự động tạo hoặc nhập thủ công. Khi sửa: disabled |
| Tên vật tư | Text | Có | |
| Nhóm vật tư | Select | Có | 4 nhóm cố định |
| Đơn vị tính | Text | Có | Nhập thủ công (vd: "Hộp, Cái") |
| Quy cách | Text | Không | |
| Nhà cung cấp ưu tiên | Select | Không | Chọn từ danh sách NCC |
| Mã vạch | Text | Không | Barcode |
| Trạng thái | Select | Có | `Đang sử dụng` | `Ngưng` |
| Ghi chú | Textarea | Không | |

#### 3.2.3 Modal: Chi tiết vật tư

Hiển thị read-only toàn bộ thông tin vật tư bao gồm: Mã, Tên, Nhóm, Mã vạch, Đơn vị tính, Quy cách, Nhà cung cấp ưu tiên, Ngày tạo, Trạng thái, Ghi chú.

#### 3.2.4 Modal: Import vật tư từ Excel

**Chức năng:**
- **Tải file mẫu** (link): Download template Excel
- **Upload file**: Kéo thả hoặc chọn file (.xlsx, .xls)
- **Lưu ý cột bắt buộc**: Mã vật tư, Tên vật tư, Nhóm vật tư, Quy cách, Đơn vị tính, Mã vạch, Trạng thái

---

### 3.3 Nhà cung cấp (Kho trung tâm)

**Vai trò:** Kho trung tâm
**Mục đích:** Quản lý master data nhà cung cấp vật tư.

#### 3.3.1 Danh sách nhà cung cấp

**Bộ lọc:**
- Tìm tên, mã nhà cung cấp, MST (text search)
- Trạng thái: `Hoạt động` | `Ngưng`

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã nhà cung cấp | Định dạng: `NCCXXX` (vd: NCC001) |
| Tên nhà cung cấp | Tên đầy đủ công ty |
| Mã số thuế | MST |
| Điện thoại | Số điện thoại liên hệ |
| Trạng thái | Badge màu |
| Thao tác | Xem, Sửa, Ngưng hợp tác |

#### 3.3.2 Modal: Thêm / Chỉnh sửa nhà cung cấp

**Các trường nhập liệu:**

| Trường | Kiểu | Bắt buộc | Ghi chú |
|--------|------|----------|---------|
| Mã nhà cung cấp | Text | Có | Tự động tạo hoặc nhập. Khi sửa: disabled |
| Tên nhà cung cấp | Text | Có | |
| Mã số thuế | Text | Có | |
| Điện thoại | Text | Có | |
| Email | Email | Không | |
| Trạng thái | Select | Có | `Hoạt động` | `Ngưng` |
| Địa chỉ | Textarea | Không | |
| Ghi chú | Textarea | Không | |

#### 3.3.3 Modal: Chi tiết nhà cung cấp

Hiển thị read-only: Mã NCC, Tên, MST, Trạng thái, Điện thoại, Email, Địa chỉ, Ghi chú.

---

### 3.4 Quản lý yêu cầu (Kho trung tâm)

**Vai trò:** Kho trung tâm
**Mục đích:** Tiếp nhận yêu cầu cấp phát từ các phòng khám, xử lý và tạo phiếu xuất kho giao hàng.

#### 3.4.1 Danh sách yêu cầu

**Bộ lọc:**
- Tìm mã yêu cầu (text search)
- Phòng khám: `Phòng khám Quận 1` | `Phòng khám Quận 3` | `Phòng khám Quận 7` | `Phòng khám Thủ Đức`
- Loại yêu cầu: `Tự động` | `Bổ sung` | `Đặc biệt`
- Trạng thái: `Chờ duyệt` | `Đã duyệt` | `Đang soạn hàng` | `Đang vận chuyển` | `Đã hoàn tất`

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã yêu cầu | Mã yêu cầu từ phòng khám |
| Phòng khám | Tên phòng khám gửi yêu cầu |
| Ngày yêu cầu | dd/mm/yyyy |
| Người yêu cầu | Tên người tạo yêu cầu |
| Trạng thái | Badge màu |
| Tổng vật tư | Tổng số lượng |
| Thao tác | Xem chi tiết, Giao hàng (chỉ khi "Chờ duyệt" hoặc "Đang xử lý") |

#### 3.4.2 Modal: Chi tiết yêu cầu (phía kho)

**Thông tin header:**
- Mã yêu cầu, Phòng khám, Ngày yêu cầu, Người yêu cầu, Loại yêu cầu, Trạng thái

**Bảng "Danh mục đề nghị cấp phát":**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã vật tư | Mã vật tư |
| Tên vật tư | Tên vật tư |
| Đơn vị tính | ĐVT |
| Quy cách | Quy cách |
| Số lượng yêu cầu | Số lượng phòng khám yêu cầu |
| **Tồn kho** | Số lượng tồn kho hiện tại (highlight đỏ nếu < số lượng yêu cầu) |
| Ghi chú | Ghi chú |
| **Số lượng đã giao** | Số lượng đã xuất kho (highlight xanh khi đủ) |

**Bảng "Phiếu xuất kho" (nếu có):**
- STT, Mã phiếu xuất, Ngày gửi, Trạng thái, Tổng vật tư, Thao tác (Xem chi tiết)

#### 3.4.3 Modal: Tạo phiếu xuất kho

**Thông tin header (một phần auto-fill):**

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| Kho xuất | Text (disabled) | Luôn là "Kho trung tâm" |
| Phòng khám nhận | Text (disabled) | Tự động từ yêu cầu |
| Ngày giao | Date | Ngày xuất kho |
| Ngày dự kiến nhận | Date | Ngày dự kiến phòng khám nhận |
| Người giao | Text | Tên người giao hàng |
| Ghi chú | Textarea | Ghi chú xuất kho |

**Bảng "Danh mục đề nghị cấp phát":**

| Cột | Mô tả |
|-----|--------|
| STT | Số thứ tự |
| Mã vật tư | Mã vật tư |
| Vật tư | Tên vật tư |
| Quy cách | Quy cách |
| Số lượng yêu cầu | Số lượng phòng khám yêu cầu (read-only) |
| Tồn kho | Số lượng tồn kho (đỏ nếu thiếu) |
| **Số lượng xuất** | Input number — thủ kho nhập, max = tồn kho |

**Quy tắc:**
- Số lượng xuất không được vượt quá tồn kho
- Mã phiếu xuất tự sinh: `GH-YYMM-XXX`
- Trạng thái phiếu xuất mặc định: `Đang giao`
- Có thể giao nhiều đợt (partial delivery) cho 1 yêu cầu

---

### 3.5 Đơn đặt hàng NCC (Kho trung tâm)

**Vai trò:** Kho trung tâm
**Mục đích:** Tạo và quản lý đơn đặt hàng (Purchase Order) gửi nhà cung cấp để bổ sung tồn kho.

#### 3.5.1 Danh sách đơn đặt hàng

**Bộ lọc:**
- Nhà cung cấp (dropdown)
- Trạng thái: `Đang tạo` | `Đã gửi nhà cung cấp` | `Đã nhận một phần` | `Đã nhận đủ`

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| Mã đơn hàng | Định dạng: `PO-YYMM-XXX` (vd: PO-2311-001) |
| Nhà cung cấp | Tên nhà cung cấp |
| Ngày đặt | dd/mm/yyyy |
| Ngày dự kiến giao | dd/mm/yyyy |
| Tổng giá trị (VNĐ) | Tổng giá trị đơn hàng, format số |
| Trạng thái | Badge màu |
| Thao tác | Tùy trạng thái (xem bên dưới) |

**Thao tác theo trạng thái:**
- `Đang tạo`: Chỉnh sửa, Gửi nhà cung cấp, Xem chi tiết
- `Đã gửi nhà cung cấp`: Tạo phiếu nhập, Xem chi tiết
- `Đã nhận một phần`: Tạo phiếu nhập, Xem chi tiết
- `Đã nhận đủ`: Xem chi tiết

#### 3.5.2 Modal: Tạo đơn đặt hàng (PO)

**Thông tin header:**

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| Nhà cung cấp | Select | Chọn từ danh sách NCC |
| Ngày đặt | Date | |
| Ngày dự kiến giao | Date | |
| Ghi chú | Textarea | |

**Danh sách vật tư (ExcelGrid):**

| Cột | Kiểu | Mô tả |
|-----|------|--------|
| Mã vật tư | Text | |
| Tên vật tư | Select | Chọn từ danh mục |
| Quy cách | Text | |
| ĐVT | Text | |
| Số lượng | Number | |
| Đơn giá (VNĐ) | Number | |
| Ghi chú | Text | |

#### 3.5.3 Modal: Chỉnh sửa PO (Edit mode)

Giống modal tạo nhưng:
- NCC không thể đổi (disabled)
- Bảng vật tư dạng editable table (không phải ExcelGrid)
- Mỗi dòng có thêm cột **Thành tiền** (auto-tính = Số lượng x Đơn giá)
- **Thao tác mỗi dòng:**
  - **Thay đổi NCC** (icon ArrowRightLeft): Chuyển sản phẩm sang PO của NCC khác
  - **Xóa dòng** (icon Trash): Xóa sản phẩm khỏi PO
  - **Thêm dòng mới**: Nút ở footer bảng

#### 3.5.4 Modal: Thay đổi nhà cung cấp (cho 1 sản phẩm)

**Thông tin hiển thị:**
- Mã vật tư, Tên vật tư, Số lượng, Thành tiền, NCC hiện tại

**Thông tin nhập:**
- **Chọn NCC mới** (Select): Hiển thị kèm PO hiện có (vd: "NCC ABC – PO-2312-001") hoặc "Chưa có PO"

**Preview trước khi xác nhận:**
- NCC đích, PO đích (hoặc "Sẽ tạo PO mới"), Giá trị chuyển

**Quy tắc:**
- Nếu NCC đích đã có PO ở trạng thái "Đang tạo" → gộp vào PO đó
- Nếu NCC đích chưa có PO → tự động tạo PO mới
- Giá trị PO nguồn giảm, PO đích tăng tương ứng
- Sản phẩm bị xóa khỏi PO hiện tại sau khi chuyển

#### 3.5.5 Modal: Chi tiết PO

**Thông tin header:**
- Nhà cung cấp, Ngày đặt, Ngày dự kiến giao, Trạng thái, Ghi chú

**Bảng vật tư (read-only):**
- STT, Mã vật tư, Tên vật tư, Quy cách, ĐVT, Số lượng, Đơn giá, Thành tiền, Ghi chú
- **Footer:** Tổng giá trị (highlight xanh đậm)

**Thao tác:**
- `Đang tạo` → Nút "Gửi nhà cung cấp"
- `Đã gửi NCC` / `Đã nhận một phần` → Nút "Tạo phiếu nhập kho"

#### 3.5.6 Modal: Xác nhận gửi đơn hàng

- Xác nhận chuyển trạng thái thành "Đã gửi nhà cung cấp"
- Ghi chú bổ sung (textarea)

---

### 3.6 Nhập kho (Kho trung tâm)

**Vai trò:** Kho trung tâm
**Mục đích:** Ghi nhận hàng hóa nhận được từ NCC, kiểm kê chênh lệch giữa đặt và thực nhận.

#### 3.6.1 Danh sách phiếu nhập

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| Mã phiếu nhập | Định dạng: `NK-YYMM-XXX` (vd: NK-2311-001) |
| Nhà cung cấp | Tên NCC |
| Ngày nhập | dd/mm/yyyy |
| Tổng số lượng | Tổng số lượng thực nhận |
| Trạng thái | `Đang kiểm tra` | `Đã nhập` |
| Thao tác | Xem chi tiết |

#### 3.6.2 Modal: Tạo phiếu nhập kho

**Thông tin header:**

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| Mã đơn đặt hàng (PO) | Select | Chọn PO cần nhập |
| Ngày nhận hàng | Date | |
| Nhà cung cấp | Text (disabled) | Tự động fill từ PO |

**Bảng kiểm kê vật tư (ExcelGrid):**

| Cột | Kiểu | Mô tả |
|-----|------|--------|
| Vật tư | Text (readonly) | Tên vật tư từ PO |
| Tồn kho | Number (readonly) | Tồn kho hiện tại |
| Số lượng đặt | Number (readonly) | Số lượng trong PO |
| **Số lượng nhận** | Number (editable) | Số lượng thực tế nhận được |
| **Chênh lệch** | Number (auto) | = Số lượng nhận - Số lượng đặt |

**Ghi chú:** Textarea bổ sung

**Quy tắc:**
- Chênh lệch tự động tính = Nhận - Đặt
- Chênh lệch âm: thiếu hàng (highlight đỏ)
- Chênh lệch 0: đủ hàng (highlight xanh)

#### 3.6.3 Modal: Chi tiết phiếu nhập

**Thông tin header:**
- Mã phiếu nhập, Mã PO, Nhà cung cấp, Ngày nhập, Người nhập, Trạng thái

**Bảng sản phẩm đã nhập (read-only):**
- Vật tư, Đơn vị tính, Số lượng đặt, Số lượng nhận, Chênh lệch (màu đỏ/xanh)

---

### 3.7 Quản lý tồn kho (Kho trung tâm)

**Vai trò:** Kho trung tâm
**Mục đích:** Theo dõi tồn kho toàn hệ thống (kho trung tâm + các phòng khám), cảnh báo sắp hết, và xem lịch sử biến động.

#### 3.7.1 Dashboard tổng hợp (Summary Cards)

| Card | Mô tả | Icon | Màu |
|------|--------|------|-----|
| Tổng số vật tư | Tổng SKU toàn hệ thống | Package | Xanh dương |
| Tồn kho trung tâm | Tổng tồn kho chính | Server | Xanh lá |
| Tồn kho toàn hệ thống | Tổng tồn tất cả kho + PK | Layers | Tím |
| Sắp hết hàng | Số SKU có stock <= minStock | AlertTriangle | Đỏ |

#### 3.7.2 Danh sách tồn kho

**Bộ lọc:**
- Tìm tên vật tư (text search)
- Kho / Phòng khám: `Kho trung tâm` | `Kho tạm` | `Phòng khám Quận 1/3/7` | `Phòng khám Thủ Đức`
- Nhóm vật tư: `Vật liệu nha khoa` | `Thuốc` | `Vật tư tiêu hao` | `Dụng cụ`

**Bảng hiển thị:**

| Cột | Mô tả |
|-----|--------|
| Kho / Phòng khám | Tên kho hoặc phòng khám |
| Vật tư | Tên vật tư |
| Nhóm vật tư | Phân loại |
| Số lượng | Số lượng tồn (bold) |
| Cập nhật lần cuối | Timestamp dd/mm/yyyy HH:mm |
| Thao tác | Xem chi tiết |

**Đặc điểm:**
- Mỗi vật tư xuất hiện nhiều dòng (1 dòng / kho-phòng khám)
- Trạng thái tồn kho: `Đủ hàng` (stock > minStock), `Sắp hết` (stock <= minStock), `Hết hàng` (stock = 0)

#### 3.7.3 Modal: Chi tiết tồn kho

**Thông tin header:**
- Vật tư, Nhóm vật tư, Kho / Phòng khám, Số lượng

**Bảng "Lịch sử biến động tồn kho":**

| Cột | Mô tả |
|-----|--------|
| Thời gian | Timestamp dd/mm/yyyy HH:mm |
| Loại giao dịch | `Nhập kho` (xanh lá) | `Giao hàng` (xanh dương) | `Xuất kho` (đỏ) | `Điều chỉnh tồn kho` (tím) |
| Số lượng | +/- số lượng (xanh/đỏ) |
| Kho / Phòng khám | Nơi biến động |
| Ghi chú | Mô tả lý do |

---

## 4. Quy tắc nghiệp vụ tổng hợp

### 4.1 Quy ước mã số

| Đối tượng | Format | Ví dụ |
|-----------|--------|-------|
| Yêu cầu cấp phát | `YC-YYYYMM-XX` | YC-202310-01 |
| Phiếu xuất kho | `GH-YYMM-XXX` | GH-2310-001 |
| Đơn đặt hàng NCC | `PO-YYMM-XXX` | PO-2311-001 |
| Phiếu nhập kho | `NK-YYMM-XXX` | NK-2311-001 |
| Mã vật tư | `VTXXX` | VT001 |
| Mã nhà cung cấp | `NCCXXX` | NCC001 |

### 4.2 Trạng thái yêu cầu cấp phát (Flow)

```
Chờ duyệt → Đã duyệt → Đang soạn hàng → Đang vận chuyển → Đã hoàn tất
```

### 4.3 Trạng thái phiếu xuất kho

```
Đang giao → Đã xác nhận
```

### 4.4 Trạng thái đơn đặt hàng NCC (PO)

```
Đang tạo → Đã gửi nhà cung cấp → Đã nhận một phần → Đã nhận đủ
```

### 4.5 Trạng thái phiếu nhập kho

```
Đang kiểm tra → Đã nhập
```

### 4.6 Trạng thái vật tư

```
Đang sử dụng | Ngưng
```

### 4.7 Trạng thái nhà cung cấp

```
Hoạt động | Ngưng
```

### 4.8 Trạng thái tồn kho

```
Đủ hàng (stock > minStock) | Sắp hết (0 < stock <= minStock) | Hết hàng (stock = 0)
```

### 4.9 Quy tắc nghiệp vụ quan trọng

1. **Giao hàng nhiều đợt (Partial Delivery):** Một yêu cầu cấp phát có thể có nhiều phiếu xuất kho, cho phép giao hàng từng phần.
2. **Số lượng xuất ≤ tồn kho:** Khi tạo phiếu xuất kho, số lượng xuất cho mỗi vật tư không được vượt quá tồn kho hiện có.
3. **Chuyển NCC trên PO:** Có thể chuyển từng sản phẩm từ PO này sang PO của NCC khác. Nếu NCC đích chưa có PO "Đang tạo", hệ thống tự tạo PO mới.
4. **Kiểm kê chênh lệch:** Khi nhập kho, hệ thống tự động tính chênh lệch giữa số lượng đặt và thực nhận.
5. **Cảnh báo tồn kho:** Hệ thống highlight đỏ khi tồn kho < mức tối thiểu (minStock).
6. **Đa kho:** Tồn kho được quản lý theo từng kho/phòng khám riêng biệt.
7. **Loại yêu cầu:** 3 loại — `Tự động` (định kỳ), `Bổ sung` (bổ sung ngoài kế hoạch), `Đặc biệt` (yêu cầu đặc biệt).
8. **Nhóm vật tư:** 4 nhóm — `Vật tư tiêu hao`, `Thuốc`, `Dụng cụ`, `Vật liệu nha khoa`.

---

## 5. Danh sách entities chính

| Entity | Mô tả | Quan hệ chính |
|--------|--------|---------------|
| Material | Vật tư y tế | Thuộc MaterialGroup, liên kết ưu tiên Supplier |
| MaterialGroup | Nhóm vật tư | 1-N Material |
| Supplier | Nhà cung cấp | 1-N PurchaseOrder |
| ClinicRequest | Yêu cầu cấp phát từ PK | 1-N ClinicRequestItem, 1-N Transfer |
| ClinicRequestItem | Chi tiết vật tư trong yêu cầu | N-1 ClinicRequest, N-1 Material |
| Transfer (Phiếu xuất kho) | Phiếu xuất kho giao PK | 1-N TransferItem, N-1 ClinicRequest |
| TransferItem | Chi tiết vật tư xuất kho | N-1 Transfer, N-1 Material |
| PurchaseOrder | Đơn đặt hàng NCC | 1-N PurchaseOrderItem, N-1 Supplier |
| PurchaseOrderItem | Chi tiết vật tư đặt mua | N-1 PurchaseOrder, N-1 Material |
| WarehouseReceipt | Phiếu nhập kho | 1-N ReceiptItem, N-1 PurchaseOrder |
| ReceiptItem | Chi tiết vật tư nhập kho | N-1 WarehouseReceipt, N-1 Material |
| Inventory | Tồn kho theo kho/PK | N-1 Material, N-1 Warehouse |
| InventoryHistory | Lịch sử biến động tồn kho | N-1 Inventory |
| Warehouse | Kho / Phòng khám | 1-N Inventory |

---

## 6. Mô tả ngắn để đưa vào ticket

Xây dựng module **Quản lý vật tư nha khoa** cho hệ thống ERP, bao gồm 7 màn hình phục vụ 2 vai trò (Phòng khám và Kho trung tâm). Module quản lý toàn bộ quy trình cung ứng vật tư: từ phòng khám tạo yêu cầu cấp phát → kho trung tâm xử lý, xuất kho, giao hàng → phòng khám xác nhận nhận hàng. Song song đó, kho trung tâm quản lý danh mục vật tư, nhà cung cấp, tạo PO đặt hàng NCC, nhập kho kiểm kê, và theo dõi tồn kho đa kho với cảnh báo sắp hết. Hỗ trợ giao hàng nhiều đợt, chuyển NCC trên PO, và import vật tư từ Excel.
