# Stock Management API Examples

## Scenarios untuk Testing Stock Management

### 1. Scenario: Order Menu dengan Stok Terbatas

#### Step 1: Cek stok menu

```bash
GET /api/menus/1
```

Response:

```json
{
    "id": 1,
    "name": "Nasi Gudeg",
    "price": 15000,
    "stock": 5,
    "is_available": true
}
```

#### Step 2: Tambah ke cart (normal quantity)

```bash
POST /api/cart/add-item
Content-Type: application/json
Authorization: Bearer {pembeli_token}

{
    "menu_id": 1,
    "quantity": 2
}
```

Response:

```json
{
    "message": "Item added to cart successfully",
    "data": {...}
}
```

#### Step 3: Coba tambah lagi dengan quantity berlebihan

```bash
POST /api/cart/add-item
Content-Type: application/json
Authorization: Bearer {pembeli_token}

{
    "menu_id": 1,
    "quantity": 10
}
```

Response:

```json
{
    "message": "Insufficient stock. Available: 5, In cart: 2, Requested: 10"
}
```

#### Step 4: Checkout cart

```bash
POST /api/cart/checkout/1
Content-Type: application/json
Authorization: Bearer {pembeli_token}

{
    "payment_method_id": 1,
    "notes": "Test order"
}
```

Response:

```json
{
    "message": "Checkout successful",
    "transaction": {
        "id": 123,
        "status": "unpaid",
        "total_price": 30000,
        "items": [...]
    }
}
```

### 2. Scenario: Pembayaran Order (Stock Reduction)

#### Step 1: Bayar order

```bash
POST /api/payments
Content-Type: application/json
Authorization: Bearer {pembeli_token}

{
    "transaction_id": 123,
    "amount": 30000,
    "method": 1,
    "proof": {file_upload}
}
```

Response:

```json
{
    "message": "Pembayaran berhasil",
    "payment": {...}
}
```

#### Step 2: Cek stok menu setelah dibayar

```bash
GET /api/menus/1
```

Response:

```json
{
    "id": 1,
    "name": "Nasi Gudeg",
    "price": 15000,
    "stock": 3, // Berkurang dari 5 menjadi 3
    "is_available": true
}
```

### 3. Scenario: Cancel Order (Stock Restoration)

#### Step 1: Penjual cancel order

```bash
PUT /api/penjual/transactions/123/status
Content-Type: application/json
Authorization: Bearer {penjual_token}

{
    "status": "cancelled"
}
```

Response:

```json
{
    "message": "Status transaksi berhasil diupdate",
    "transaction": {
        "id": 123,
        "status": "cancelled",
        ...
    }
}
```

#### Step 2: Cek stok menu setelah dicancel

```bash
GET /api/menus/1
```

Response:

```json
{
    "id": 1,
    "name": "Nasi Gudeg",
    "price": 15000,
    "stock": 5, // Kembali ke 5 (restored)
    "is_available": true
}
```

### 4. Scenario: Error Cases

#### Error 1: Insufficient Stock saat Add to Cart

```bash
POST /api/cart/add-item
Content-Type: application/json

{
    "menu_id": 1,
    "quantity": 10  // Lebih dari stock
}
```

Response:

```json
{
    "message": "Insufficient stock. Available: 5, Requested: 10"
}
```

#### Error 2: Insufficient Stock saat Update Cart

```bash
PUT /api/cart/items/1
Content-Type: application/json

{
    "quantity": 20  // Lebih dari stock
}
```

Response:

```json
{
    "message": "Insufficient stock. Available: 5, Requested: 20"
}
```

#### Error 3: Insufficient Stock saat Checkout

Jika ada perubahan stok di antara add to cart dan checkout:

```bash
POST /api/cart/checkout/1
```

Response:

```json
{
    "message": "Insufficient stock for menu 'Nasi Gudeg'. Available: 2, Requested: 5"
}
```

## Testing Flow

### Complete Testing Scenario

1. **Setup**: Create menu with stock = 10
2. **Add to cart**: Add 3 items (stock masih 10, belum berkurang)
3. **Checkout**: Create transaction (stock masih 10)
4. **Payment**: Pay transaction → **stock berkurang menjadi 7**
5. **Cancel**: Cancel transaction → **stock kembali menjadi 10**

### Status Flow dan Stock Impact

-   `pending` → `paid`: **Stock berkurang**
-   `paid` → `cancelled`: **Stock dikembalikan**
-   `pending` → `cancelled`: **Tidak ada perubahan stock**
-   `confirmed/ready/completed` → `cancelled`: **Stock dikembalikan** (jika sebelumnya paid)

### Validation Points

1. **Cart Level**: Validasi stock ketika add/update cart items
2. **Checkout Level**: Validasi stock ketika checkout
3. **Payment Level**: Stock reduction ketika paid
4. **Status Change Level**: Stock management ketika status berubah
