<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;

class FixCashierIdCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:cashier-id {--dry-run : Preview changes without applying them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix cashier_id for existing transactions based on menu owners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be applied');
        }

        // Cari semua transaksi yang cashier_id nya null
        $transactions = Transaction::whereNull('cashier_id')->with('items.menu')->get();

        $this->info("Found {$transactions->count()} transactions with null cashier_id");

        if ($transactions->isEmpty()) {
            $this->info('✅ No transactions to fix!');
            return;
        }

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $this->withProgressBar($transactions, function ($transaction) use (&$updated, &$skipped, &$errors, $dryRun) {
            if ($transaction->items->isEmpty()) {
                $this->newLine();
                $this->warn("⚠️ Transaction ID {$transaction->id} has no items - SKIPPED");
                $skipped++;
                return;
            }

            // Ambil user_id dari menu pertama sebagai cashier_id
            $firstItem = $transaction->items->first();
            if (!$firstItem || !$firstItem->menu) {
                $this->newLine();
                $this->warn("⚠️ Transaction ID {$transaction->id} has no menu data - SKIPPED");
                $skipped++;
                return;
            }

            $merchantId = $firstItem->menu->user_id;

            // Validasi bahwa semua item dalam transaksi ini dari merchant yang sama
            $allFromSameMerchant = $transaction->items->every(function ($item) use ($merchantId) {
                return $item->menu && $item->menu->user_id === $merchantId;
            });

            if (!$allFromSameMerchant) {
                $this->newLine();
                $this->error("❌ Transaction ID {$transaction->id} has items from different merchants - SKIPPED");
                $errors++;
                return;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("🔍 WOULD UPDATE: Transaction ID {$transaction->id} with cashier_id: {$merchantId}");
            } else {
                try {
                    $transaction->update(['cashier_id' => $merchantId]);
                    $this->newLine();
                    $this->info("✅ Updated transaction ID {$transaction->id} with cashier_id: {$merchantId}");
                    $updated++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("❌ Failed to update transaction ID {$transaction->id}: " . $e->getMessage());
                    $errors++;
                }
            }
        });

        $this->newLine(2);
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $dryRun ? 'N/A (dry run)' : $updated],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total', $transactions->count()]
            ]
        );

        if ($dryRun) {
            $this->warn('🔍 This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->info('✅ Update completed!');
        }
    }
}
