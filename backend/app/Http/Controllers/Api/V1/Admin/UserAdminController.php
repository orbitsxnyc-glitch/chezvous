<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use App\Services\Admin\UserAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Gestion des utilisateurs (ADM-03). Les coordonnées ne sont visibles que des administrateurs. */
class UserAdminController extends Controller
{
    public function __construct(private readonly UserAdminService $users) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate([
            'q' => ['sometimes', 'string', 'max:100'], 'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'role' => ['sometimes', Rule::in(['client', 'particulier', 'admin', 'super_admin'])], 'account_type' => ['sometimes', Rule::in(['individual', 'agency'])],
        ]);
        $q = (string) $request->query('q', '');
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';

        $page = User::with(['roles', 'agencyMembership.agency'])
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($term, $digits) {
                $x->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)->orWhere('ulid', 'ilike', $term);
                if (strlen($digits) >= 4) {
                    $x->orWhere('phone', 'like', '%'.$digits.'%');
                }
            }))
            ->when($request->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($request->query('account_type'), fn ($w, $v) => $w->where('account_type', $v))
            ->when($request->query('role'), fn ($w, $v) => $w->role($v))
            ->orderByDesc('id')->cursorPaginate(30)->withQueryString();

        return response()->json(['data' => collect($page->items())->map(fn (User $u) => $this->present($u)), 'meta' => ['next_cursor' => $page->nextCursor()?->encode()]]);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('moderate');
        $user->load(['roles', 'agencyMembership.agency']);

        return response()->json(['data' => $this->present($user) + [
            'listings' => Listing::where('advertiser_user_id', $user->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'inquiries_sent' => Inquiry::where('requester_id', $user->id)->count(),
            'history' => AuditLog::where('subject_type', 'User')->where('subject_id', $user->ulid)->latest('id')->limit(30)->get(['action', 'actor_id', 'after', 'created_at']),
        ]]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json(['data' => $this->present($this->users->suspend($user, $request->user(), $data['reason']))]);
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => $this->present($this->users->reactivate($user, $request->user()))]);
    }

    /** Attribution du rôle administrateur : super administrateur uniquement. */
    public function adminRole(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $data = $request->validate(['admin' => ['required', 'boolean']]);

        return response()->json(['data' => $this->present($this->users->setAdminRole($user, $request->user(), (bool) $data['admin'])->load('roles'))]);
    }

    /** @return array<string, mixed> */
    private function present(User $u): array
    {
        return [
            'id' => $u->ulid, 'name' => $u->name, 'phone' => $u->phone, 'email' => $u->email, 'account_type' => $u->account_type->value,
            'status' => $u->status->value, 'roles' => $u->getRoleNames(), 'phone_verified' => $u->hasVerifiedPhone(), 'created_at' => $u->created_at,
            'agency' => $u->agencyMembership ? ['name' => $u->agencyMembership->agency->name, 'role' => $u->agencyMembership->role->value] : null,
        ];
    }
}
