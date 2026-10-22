<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('participants')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $normalized = mb_strtolower(
                    preg_replace('/\s+/u', ' ', trim($row->suffix ?? '')),
                    'UTF-8',
                );
                DB::table('participants')->where('id', $row->id)->update([
                    'suffix_normalized' => str_replace('.', '', $normalized),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('participants')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('participants')->where('id', $row->id)->update([
                    'suffix_normalized' => mb_strtolower(
                        preg_replace('/\s+/u', ' ', trim($row->suffix ?? '')),
                        'UTF-8',
                    ),
                ]);
            }
        });
    }
};
