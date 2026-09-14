<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded files, kept in the database when no object storage is attached.
 *
 * On Laravel Cloud the server's own filesystem is wiped by every deploy and is
 * different on every instance, so an upload written there is lost, which is
 * exactly how every school logo in production came to be a broken image. Object
 * storage buckets are the long-term home for uploads; until they are attached,
 * the database is the one store every instance shares and every deploy keeps.
 * See App\Support\Storage\DatabaseStorageFallback.
 *
 * THE BYTES ARE IN CHUNKS, in a table of their own:
 *
 *  - A single column holding a whole file would have to be sent to the
 *    database in one packet, and MySQL refuses packets over its
 *    max_allowed_packet, a 20MB CBT document or a video would fail to save.
 *    One megabyte at a time never comes near it.
 *  - Reading a file, or part of one for a video seek, fetches only the chunks
 *    needed, rather than loading every byte into memory.
 *  - Listing, checking and sizing files touches only `stored_files`, which
 *    holds no bytes at all.
 *
 * BASE64 IN LONGTEXT, not a binary column: "binary" is a 64KB BLOB on MySQL,
 * and longText means the same, large, thing on MySQL, Postgres and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();

            // Which application disk the file belongs to: "public" or "local".
            $table->string('disk', 32);
            $table->string('path', 512);

            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('visibility', 16)->default('private');
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamps();

            $table->unique(['disk', 'path']);
        });

        Schema::create('stored_file_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->longText('data');

            $table->unique(['stored_file_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_file_chunks');
        Schema::dropIfExists('stored_files');
    }
};
