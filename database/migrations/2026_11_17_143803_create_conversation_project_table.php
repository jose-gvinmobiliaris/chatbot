
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_project', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->cascadeOnDelete();

            // ID original del CPT proyecto de WordPress.
            $table->unsignedBigInteger('wordpress_project_id');

            $table->timestamps();

            $table->unique(
                ['conversation_id', 'wordpress_project_id'],
                'conversation_wordpress_project_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_project');
    }
};
