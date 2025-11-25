<?php

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArchiveOldInventoryMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Ex: php artisan inventory:archive-old
     */
    protected $signature = 'inventory:archive-old';

    /**
     * The console command description.
     */
    protected $description = 'Marca como arquivadas movimentações de estoque com mais de 90 dias.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays(90);

        $this->info("Arquivando movimentações anteriores a {$cutoff->toDateTimeString()}...");

        $total = 0;

        // Processa em lotes para não travar o banco com muitos registros
        InventoryMovement::query()
            ->whereNull('archived_at')
            ->where('created_at', '<', $cutoff)
            ->chunkById(1000, function ($movements) use (&$total) {
                DB::transaction(function () use ($movements, &$total) {
                    foreach ($movements as $movement) {
                        $movement->archived_at = now();
                        $movement->save();
                        $total++;
                    }
                });
            });

        $this->info("Total de movimentações arquivadas: {$total}");

        return self::SUCCESS;
    }
}