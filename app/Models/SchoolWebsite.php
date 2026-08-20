<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\SchoolWebsiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'slogan',
        'slogan_tagline',
        'footer_text',
        'topbar_announcement',
        'topbar_badge_text',
        'topbar_link_text',
        'topbar_link_url',
        'cta_text',
        'cta_url',
        'hero_secondary_text',
        'hero_secondary_url',
        'whats_happening_title',
        'show_whats_happening',
        'brand_primary_color',
        'brand_secondary_color',
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
        return $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null;
    }

    public function principalPhotoUrl(): ?string
    {
        return $this->principal_photo_path ? Storage::disk('public')->url($this->principal_photo_path) : null;
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
