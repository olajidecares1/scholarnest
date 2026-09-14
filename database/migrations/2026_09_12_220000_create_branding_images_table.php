<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform logo and favicon, kept in the database.
 *
 * They used to live only on the "public" disk. In production that disk is a
 * directory on a filesystem Laravel Cloud wipes on every deploy, gives each
 * replica its own copy of, and does not serve at /storage at all, so an upload
 * reported success and every page asked for a file that was never reachable.
 * The database is the one store every replica shares and every deploy keeps.
 *
 * A TABLE OF ITS OWN, not columns on settings: Setting::current() is read on
 * every page, and two images of up to 2MB each have no business riding along
 * on that query. This row is read only when a browser asks for the image, and
 * the address it asks at changes with every upload, so it asks once.
 *
 * BASE64 IN A LONGTEXT rather than a binary column, because "binary" is a 64KB
 * BLOB on MySQL, a real favicon set does not fit, and longText means the
 * same, unlimited, thing on MySQL, Postgres and SQLite alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_images', function (Blueprint $table) {
            $table->id();

            // The same "branding/logo-XXXXXXXX.png" stored in settings, so the
            // setting needs no second column to find its bytes.
            $table->string('path')->unique();

            $table->string('mime_type', 100);
            $table->longText('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_images');
    }
};
