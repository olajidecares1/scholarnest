<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where to find a school online.
 *
 * Three of these - Facebook, X and Instagram - already existed, on the website
 * record, which is a Standard and Exclusive feature. So the schools least
 * likely to have a website of their own were the ones with no way to publish a
 * handle at all, and there was nowhere to put TikTok, WhatsApp, YouTube or
 * LinkedIn regardless of plan.
 *
 * They belong to the school rather than to one of its features, so they sit on
 * the school, as its address now does. What the website already holds is
 * copied across, so nobody re-types a handle they have given us before.
 *
 * WhatsApp is a number rather than a URL: a school knows its telephone number,
 * not its wa.me link, so the link is built from the number (see
 * App\Support\SchoolSocialLinks) instead of asked for.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COPIED_FROM_WEBSITE = ['facebook_url', 'twitter_url', 'instagram_url'];

    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('facebook_url')->nullable()->after('contact_email');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('twitter_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('twitter_url');
            $table->string('youtube_url')->nullable()->after('tiktok_url');
            $table->string('linkedin_url')->nullable()->after('youtube_url');
            $table->string('whatsapp_number', 60)->nullable()->after('linkedin_url');
        });

        if (! Schema::hasTable('school_websites')) {
            return;
        }

        foreach (self::COPIED_FROM_WEBSITE as $column) {
            if (! Schema::hasColumn('school_websites', $column)) {
                continue;
            }

            DB::table('schools')->update([
                $column => DB::raw(
                    "(select {$column} from school_websites where school_websites.school_id = schools.id limit 1)"
                ),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_url', 'instagram_url', 'twitter_url',
                'tiktok_url', 'youtube_url', 'linkedin_url', 'whatsapp_number',
            ]);
        });
    }
};
