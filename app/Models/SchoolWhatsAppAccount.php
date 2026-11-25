<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school's own registered WhatsApp Business number (Meta's Cloud API),
 * from which its result details go out. See App\Services\WhatsApp.
 */
class SchoolWhatsAppAccount extends Model
{
    protected $table = 'school_whatsapp_accounts';

    protected $fillable = [
        'school_id', 'display_number', 'verified_name', 'phone_number_id',
        'access_token', 'result_template', 'template_language', 'verified_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * The last few characters, for the settings page to show which token is
     * saved without showing the token.
     */
    public function maskedToken(): string
    {
        return '••••••••'.substr((string) $this->access_token, -4);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
