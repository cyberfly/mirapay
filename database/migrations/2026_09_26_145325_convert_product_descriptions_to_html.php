<?php

use App\Support\HtmlSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Convert legacy Markdown descriptions to sanitized HTML.
     */
    public function up(): void
    {
        DB::table('products')->whereNotNull('description')->orderBy('id')->each(function ($product) {
            $html = Str::markdown($product->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

            DB::table('products')->where('id', $product->id)->update([
                'description' => HtmlSanitizer::clean($html) ?: null,
            ]);
        });
    }

    public function down(): void
    {
        //
    }
};
