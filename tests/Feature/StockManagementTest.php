<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Menu;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $penjual;
    protected $pembeli;
    protected $menu;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->penjual = User::factory()->create([
            'role' => 'penjual',
            'name' => 'Test Penjual',
            'email' => 'penjual@test.com'
        ]);

        $this->pembeli = User::factory()->create([
            'role' => 'pembeli',
            'name' => 'Test Pembeli',
            'email' => 'pembeli@test.com'
        ]);

        // Create test category
        $this->category = Category::create([
            'name' => 'Test Category'
        ]);

        // Create test menu with initial stock
        $this->menu = Menu::create([
            'name' => 'Test Menu',
            'description' => 'Test menu description',
            'price' => 10000,
            'stock' => 10, // Initial stock
            'category_id' => $this->category->id,
            'user_id' => $this->penjual->id
        ]);
    }

    /** @test */
    public function stock_is_reduced_when_transaction_is_paid()
    {
        // Create transaction with items
        $transaction = Transaction::create([
            'user_id' => $this->pembeli->id,
            'cashier_id' => $this->penjual->id,
            'total_price' => 30000,
            'status' => 'pending'
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'menu_id' => $this->menu->id,
            'quantity' => 3,
            'price' => 10000
        ]);

        // Load items relation
        $transaction->load('items.menu');

        // Initial stock should be 10
        $this->assertEquals(10, $this->menu->fresh()->stock);

        // Change status to paid
        $transaction->handleStockManagement('paid', 'pending');

        // Stock should be reduced by 3
        $this->assertEquals(7, $this->menu->fresh()->stock);
    }

    /** @test */
    public function stock_is_restored_when_transaction_is_cancelled()
    {
        // Create transaction with items
        $transaction = Transaction::create([
            'user_id' => $this->pembeli->id,
            'cashier_id' => $this->penjual->id,
            'total_price' => 30000,
            'status' => 'paid'
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'menu_id' => $this->menu->id,
            'quantity' => 3,
            'price' => 10000
        ]);

        // Load items relation
        $transaction->load('items.menu');

        // Reduce stock first (simulate paid status)
        $transaction->handleStockManagement('paid', 'pending');
        $this->assertEquals(7, $this->menu->fresh()->stock);

        // Cancel transaction
        $transaction->handleStockManagement('cancelled', 'paid');

        // Stock should be restored
        $this->assertEquals(10, $this->menu->fresh()->stock);
    }

    /** @test */
    public function menu_reduce_stock_method_works_correctly()
    {
        $initialStock = $this->menu->stock;

        // Reduce stock by 3
        $result = $this->menu->reduceStock(3);

        $this->assertTrue($result);
        $this->assertEquals($initialStock - 3, $this->menu->fresh()->stock);
    }

    /** @test */
    public function menu_reduce_stock_fails_when_insufficient_stock()
    {
        // Try to reduce stock by more than available
        $result = $this->menu->reduceStock(15);

        $this->assertFalse($result);
        $this->assertEquals(10, $this->menu->fresh()->stock); // Stock should remain unchanged
    }

    /** @test */
    public function menu_restore_stock_method_works_correctly()
    {
        $initialStock = $this->menu->stock;

        // Restore stock by 5
        $result = $this->menu->restoreStock(5);

        $this->assertTrue($result);
        $this->assertEquals($initialStock + 5, $this->menu->fresh()->stock);
    }
}
