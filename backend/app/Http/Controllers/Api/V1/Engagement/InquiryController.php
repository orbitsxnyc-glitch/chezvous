<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Enums\InquiryStatus;
use App\Enums\PreferredChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\BodyRequest;
use App\Http\Requests\Engagement\StoreInquiryRequest;
use App\Http\Requests\Engagement\UpdateInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Services\Inquiries\InquiryAccess;
use App\Services\Inquiries\InquiryService;
use App\Services\Inquiries\InquiryStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    private const RELATIONS = ['listing', 'requester', 'agency', 'assignee'];

    public function __construct(private readonly InquiryService $inquiries, private readonly InquiryStateService $states, private readonly InquiryAccess $access) {}

    /** Demande de contact sur une annonce publique (DEM-01). */
    public function store(StoreInquiryRequest $request, string $listing): JsonResponse
    {
        [$inquiry, $created] = $this->inquiries->open(
            $listing, $request->user(), $request->validated('message'), $request->validated('phone'),
            PreferredChannel::from($request->validated('preferred_channel') ?? 'message'),
        );

        return (new InquiryResource($inquiry->load(self::RELATIONS), true))->response()->setStatusCode($created ? 201 : 200);
    }

    /** Mes demandes envoyées. */
    public function sent(Request $request): AnonymousResourceCollection
    {
        $this->validateStatus($request);
        $query = Inquiry::with(self::RELATIONS)->where('requester_id', $request->user()->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('last_activity_at')->orderByDesc('id');

        return InquiryResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    /** Boîte de réception côté annonceur. */
    public function inbox(Request $request): AnonymousResourceCollection
    {
        $this->validateStatus($request);
        $query = Inquiry::with(self::RELATIONS)->receivedBy($request->user())
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('last_activity_at')->orderByDesc('id');

        return InquiryResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    public function show(Request $request, Inquiry $inquiry): InquiryResource
    {
        Gate::authorize('view', $inquiry);
        $this->inquiries->markRead($inquiry, $request->user());

        return new InquiryResource($this->detail($inquiry), true);
    }

    /** Statut (côté annonceur ; le demandeur ne peut que fermer) et attribution (gérant). */
    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): InquiryResource
    {
        Gate::authorize('view', $inquiry);
        $data = $request->validated();

        if (isset($data['member'])) {
            Gate::authorize('assign', $inquiry);
            $member = AgencyMember::where('ulid', $data['member'])->where('agency_id', $inquiry->agency_id)->firstOrFail();
            $this->inquiries->assign($inquiry, $member, $request->user());
        }
        if (isset($data['status'])) {
            if (! $this->access->isRequester($request->user(), $inquiry)) {
                Gate::authorize('respond', $inquiry);
            }
            $this->states->change($inquiry, $request->user(), InquiryStatus::from($data['status']));
        }

        return new InquiryResource($this->detail($inquiry->refresh()), true);
    }

    public function message(BodyRequest $request, Inquiry $inquiry): JsonResponse
    {
        Gate::authorize('view', $inquiry);
        if (! $this->access->isRequester($request->user(), $inquiry)) {
            Gate::authorize('respond', $inquiry);
        }
        $message = $this->inquiries->addMessage($inquiry, $request->user(), $request->validated('body'));

        return response()->json(['data' => ['id' => $message->id, 'created_at' => $message->created_at]], 201);
    }

    /** Note interne : côté annonceur seulement (DEM-05). Le demandeur ne sait même pas qu'elle existe (404). */
    public function note(BodyRequest $request, Inquiry $inquiry): JsonResponse
    {
        if ($this->access->isRequester($request->user(), $inquiry)) {
            abort(404);
        }
        Gate::authorize('respond', $inquiry);
        $note = $this->inquiries->addNote($inquiry, $request->user(), $request->validated('body'));

        return response()->json(['data' => ['id' => $note->id]], 201);
    }

    private function validateStatus(Request $request): void
    {
        $request->validate(['status' => ['sometimes', Rule::enum(InquiryStatus::class)]]);
    }

    private function detail(Inquiry $inquiry): Inquiry
    {
        return $inquiry->load([...self::RELATIONS, 'messages.sender', 'visits', 'notes.author']);
    }
}
