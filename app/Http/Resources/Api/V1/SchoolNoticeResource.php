<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SchoolNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolNotice
 */
class SchoolNoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience?->value,
            'class_name' => $this->class_name,
            'published_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
