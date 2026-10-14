<?php

namespace App\Http\Resources;

use App\Models\Inquiry;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Visit */
class VisitResource extends JsonResource
{
    public function __construct($resource, private readonly Inquiry $inquiry)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $proposedByRequester = $this->proposed_by === $this->inquiry->requester_id;
        $viewerIsRequester = $viewer->id === $this->inquiry->requester_id;
        $pending = in_array($this->status->value, ['proposed', 'rescheduled'], true);

        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'proposed_slots' => $this->proposed_slots,
            'scheduled_at' => $this->scheduled_at,
            'proposed_by_me' => $this->proposed_by === $viewer->id,
            // C'est à moi de répondre si l'autre côté a fait la dernière proposition.
            'awaiting_me' => $pending && ($proposedByRequester !== $viewerIsRequester),
            'cancel_reason' => $this->cancel_reason,
            'feedback' => $this->feedback_available === null ? null : [
                'available' => $this->feedback_available, 'conform' => $this->feedback_conform, 'comment' => $this->feedback_comment,
            ],
        ];
    }
}
