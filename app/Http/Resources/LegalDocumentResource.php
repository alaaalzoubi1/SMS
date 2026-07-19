<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Used for the POST/update response, where the caller (super_admin) should
 * see both languages back. The public GET endpoint does NOT use this
 * resource — it returns only the single resolved language (see
 * LegalDocumentController::show()).
 */
class LegalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'version' => $this->version,
            'content' => $this->content,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
