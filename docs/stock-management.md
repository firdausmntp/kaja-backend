# Stock Management System

## Overview

Sistem pengelolaan stok otomatis yang mengurangi stok menu ketika order dibayar dan mengembalikan stok ketika order dibatalkan.

## Features

### 1. Automatic Stock Reduction

Stok menu akan otomatis berkurang ketika:

-   Status transaksi berubah menjadi `paid`
-   Pembayaran dibuat melalui PaymentController
-   Status diupdate melalui PenjualController, TransactionController, atau PembeliController

### 2. Automatic Stock Restoration

Stok menu akan otomatis dikembalikan ketika:

-   Status transaksi berubah menjadi `cancelled`
-   Status berubah dari `paid` ke `cancelled`

### 3. Stock Validation

Validasi stok dilakukan pada:

-   Menambah item ke cart
-   Update quantity item di cart
-   Checkout cart ke transaction

## Implementation Details

### Model Methods

#### Menu Model

```php
// Mengurangi stok
public function reduceStock($quantity): bool

// Mengembalikan stok
public function restoreStock($quantity): bool
```

#### Transaction Model

```php
// Handle stock management berdasarkan perubahan status
public function handleStockManagement($newStatus, $oldStatus = null): void

// Private methods
private function reduceStock(): void
private function restoreStock(): void
```

### Controller Updates

#### PaymentController

-   Menambah stock management ketika pembayaran dibuat
-   Status otomatis berubah ke `paid` dan stok berkurang

#### PenjualController

-   Menambah stock management ketika penjual mengubah status transaksi
-   Support untuk semua status: `pending`, `paid`, `confirmed`, `ready`, `completed`, `cancelled`

#### TransactionController

-   Menambah stock management ketika customer mengubah status transaksi
-   Support untuk status: `pending`, `paid`, `cancelled`

#### PembeliController

-   Menambah stock management ketika pembeli menandai transaksi sebagai paid

#### CartController

-   Validasi stok ketika menambah item ke cart
-   Validasi stok ketika update quantity di cart
-   Validasi stok ketika checkout

## Stock Management Rules

### Status Flow and Stock Impact

1. `pending` → `paid`: **Reduce stock**
2. `paid` → `cancelled`: **Restore stock**
3. `pending` → `cancelled`: **No change** (stock wasn't reduced)
4. Any status → `cancelled`: **Restore stock** (if previously paid)

### Validation Rules

-   Tidak dapat menambah item ke cart jika stok tidak mencukupi
-   Tidak dapat checkout jika ada item yang stoknya tidak mencukupi
-   Stok hanya dikurangi ketika pembayaran confirmed (status = paid)

## Testing

File test tersedia di `tests/Feature/StockManagementTest.php` yang mencakup:

-   Stock reduction ketika transaction paid
-   Stock restoration ketika transaction cancelled
-   Menu model methods (reduceStock, restoreStock)
-   Edge cases untuk insufficient stock

## Usage Examples

### 1. Manual Stock Management

```php
$menu = Menu::find(1);

// Reduce stock
if ($menu->reduceStock(5)) {
    echo "Stock reduced successfully";
} else {
    echo "Insufficient stock";
}

// Restore stock
$menu->restoreStock(3);
```

### 2. Transaction Stock Management

```php
$transaction = Transaction::find(1);
$transaction->load('items.menu');

// Handle status change
$transaction->handleStockManagement('paid', 'pending'); // Reduces stock
$transaction->handleStockManagement('cancelled', 'paid'); // Restores stock
```

## API Response Changes

### Error Responses for Insufficient Stock

#### Add to Cart

```json
{
    "message": "Insufficient stock. Available: 5, Requested: 10"
}
```

#### Update Cart Item

```json
{
    "message": "Insufficient stock. Available: 3, In cart: 2, Requested: 5"
}
```

#### Checkout

```json
{
    "message": "Insufficient stock for menu 'Nasi Gudeg'. Available: 2, Requested: 5"
}
```

## Database Considerations

### Performance

-   Stock updates menggunakan `increment()` dan `decrement()` methods untuk atomic operations
-   Semua stock management operations di-wrap dalam database transactions

### Concurrency

-   Race conditions diminimalisir dengan database-level atomic operations
-   Recommended untuk menggunakan database row locking jika diperlukan untuk high-concurrency scenarios

## Future Enhancements

1. **Stock History Tracking**: Log semua perubahan stok dengan timestamp dan reason
2. **Low Stock Alerts**: Notifikasi ketika stok mencapai threshold tertentu
3. **Stock Reservation**: Reserve stok sementara ketika di cart sebelum checkout
4. **Batch Stock Updates**: Update stok multiple items sekaligus untuk performance
