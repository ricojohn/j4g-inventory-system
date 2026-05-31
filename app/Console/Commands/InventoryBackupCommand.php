<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Export inventory-related tables to a JSON backup file.
 *
 * Manual restore procedure:
 * 1. Stop the application or put it in maintenance mode: `php artisan down`
 * 2. Locate the backup file under `storage/app/backups/` (or your custom `--path`)
 * 3. Parse the JSON and verify `exported_at` and table row counts
 * 4. Truncate target tables in dependency order (children first) or run `migrate:fresh`
 * 5. Insert rows per table in dependency order (parents first): users, roles, permissions,
 *    role_has_permissions, model_has_roles, model_has_permissions, product_categories, sizes,
 *    category_size, products, product_variants, stock_movements
 * 6. Bring the application back up: `php artisan up` and clear caches: `php artisan optimize:clear`
 */
class InventoryBackupCommand extends Command
{
    protected $signature = 'inventory:backup {--path= : Custom output file path relative to storage/app or absolute}';

    protected $description = 'Export inventory tables to a timestamped JSON backup file';

    /**
     * @var list<string>
     */
    private array $tables = [
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'product_categories',
        'sizes',
        'category_size',
        'products',
        'product_variants',
        'stock_movements',
    ];

    public function handle(): int
    {
        $path = $this->resolveOutputPath();
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        $counts = [];

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("Skipping missing table: {$table}");
                $payload['tables'][$table] = [];
                $counts[$table] = 0;

                continue;
            }

            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $payload['tables'][$table] = $rows;
            $counts[$table] = count($rows);
        }

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Backup written to: '.$path);
        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());

        return self::SUCCESS;
    }

    private function resolveOutputPath(): string
    {
        $custom = $this->option('path');

        if (is_string($custom) && $custom !== '') {
            if (str_starts_with($custom, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $custom)) {
                return $custom;
            }

            return storage_path('app/'.ltrim($custom, '/'));
        }

        $filename = 'inventory-'.now()->format('Y-m-d-His').'.json';

        return storage_path('app/backups/'.$filename);
    }
}
