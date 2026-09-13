<?php

namespace App\Models;

use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\SchoolWebsiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolWebsite extends Model
{
    /** @use HasFactory<SchoolWebsiteFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'hero_title',
        'hero_subtitle',
        'hero_image_path',
        'about_text',

        // The About section - see the migration for why mission, vision and
        // values are three columns rather than one.
        'about_headline',
        'about_image_path',

        // Backgrounds for two cards on the public site. News and Events share
        // the first one - they are one card, not two.
        'news_events_card_image_path',
        'academics_card_image_path',
        'about_card_image_path',
        'mission',
        'vision',
        'values',

        'slogan',
        'slogan_tagline',
        'footer_text',
        'topbar_announcement',
        'topbar_badge_text',
        'topbar_link_text',
        'topbar_link_url',
        // cta_text and cta_url are the BUTTON; the two below are the band's
        // own words - see the migration.
        'cta_text',
        'cta_url',
        'cta_title',
        'cta_subtitle',
        'hero_secondary_text',
        'hero_secondary_url',
        'whats_happening_title',
        'show_whats_happening',
        'brand_primary_color',
        'brand_secondary_color',
        'font_family',
        'font_weight',
        'navbar_bg_color',
        'principal_name',
        'principal_title',
        'principal_message',
        'principal_photo_path',
        'quote_text',
        'quote_author',
        'quote_author_role',
        'campus_video_url',
        'stats',
        'admissions_intro',
        'admissions_steps',
        'admissions_requirements',
        'contact_email',
        'contact_phone',
        'contact_address',
        'facebook_url',
        'twitter_url',
        'instagram_url',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'admissions_steps' => 'array',
            'admissions_requirements' => 'array',
            'show_whats_happening' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function heroImageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->hero_image_path);
    }

    public function aboutImageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->about_image_path);
    }

    /**
     * The background behind the Latest News AND Upcoming Events card.
     *
     * One image for both, deliberately. They are one card as far as a school
     * is concerned, and two settings would say otherwise.
     */
    public function newsEventsCardImageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->news_events_card_image_path);
    }

    /**
     * The background behind the Academic Excellence card, which is its own
     * card and so has its own setting.
     */
    public function academicsCardImageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->academics_card_image_path);
    }

    /**
     * The background behind the About band.
     *
     * Distinct from aboutImageUrl(), which is the photograph in the frame
     * BESIDE the prose. A school can set either, both or neither.
     */
    public function aboutCardImageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->about_card_image_path);
    }

    public function principalPhotoUrl(): ?string
    {
        return UploadStorage::publicUrl($this->principal_photo_path);
    }

    public function campusVideoEmbedUrl(): ?string
    {
        if (! $this->campus_video_url) {
            return null;
        }

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $this->campus_video_url, $matches)) {
            return "https://www.youtube.com/embed/{$matches[1]}";
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $this->campus_video_url, $matches)) {
            return "https://player.vimeo.com/video/{$matches[1]}";
        }

        return $this->campus_video_url;
    }
}
