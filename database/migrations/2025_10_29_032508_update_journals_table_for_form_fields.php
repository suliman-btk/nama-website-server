<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('journals', function (Blueprint $table) {
            // Remove author column
            $table->dropColumn('author');

            // Add new columns based on the form
            $table->string('publication_date')->nullable(); // Publication date
            $table->string('category')->nullable(); // Category field
            $table->text('description')->nullable(); // Description field
            $table->string('journal_pdf')->nullable(); // Journal PDF file
            $table->string('cover_image')->nullable(); // Cover image file

            // Rename content to description if needed, or keep both
            // $table->renameColumn('content', 'description'); // Uncomment if you want to rename
        });
    }

    public function down()
    {
        Schema::table('journals', function (Blueprint $table) {
            // Add back author column
            $table->string('author');

            // Remove the new columns
            $table->dropColumn([
                'publication_date',
                'category',
                'description',
                'journal_pdf',
                'cover_image'
            ]);
        });
    }
};
