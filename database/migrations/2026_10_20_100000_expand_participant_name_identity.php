<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('middle_name_normalized')->default('');
            $table->string('suffix_normalized', 16)->default('');
        });

        // Backfill from display values, including first/last names created
        // before normalization existed. Never guess missing optional names.
        DB::table('participants')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $values = [];
                foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $part) {
                    $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($row->$part ?? '')), 'UTF-8');
                    $values[$part.'_normalized'] = in_array($part, ['first_name', 'last_name'], true)
                        ? ($normalized === '' ? null : $normalized) : $normalized;
                }
                DB::table('participants')->where('id', $row->id)->update($values);
            }
        });
    }

    public function down(): void
    {
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn(['middle_name_normalized', 'suffix_normalized']));
    }
};
