<?php

namespace App\Http\Resources\Api\V1;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The school a token belongs to, as much of it as a portal account may see.
 *
 * Deliberately thin. A mobile client needs to brand itself and show a name;
 * it does not need the plan, the subscription, the contact details of the
 * owner, or anything else the School Admin panel knows.
 *
 * @mixin School
 */
class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logoUrl(),
        ];
    }
}
