<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_guru');
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('file_detail')->nullable();
            $table->enum('target', ['siswa', 'kelas']);
            $table->json('id_target');
            $table->enum('tipe_pengumpulan', ['link', 'langsung'])->default('link');
            $table->dateTime('tanggal_mulai')->nullable();
            $table->dateTime('tanggal_deadline')->nullable();
            $table->timestamps();

            $table->foreign('id_guru')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tugas');
    }
};
