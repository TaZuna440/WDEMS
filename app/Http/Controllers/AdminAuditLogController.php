<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    private const ACTIONS = [
        'event.created',
        'event.deleted',
        'user.registered',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'in:'.implode(',', self::ACTIONS)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = AuditLog::query()->with('user:id,name,email');

        if (isset($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('action', 'like', $search)
                    ->orWhere('target_type', 'like', $search)
                    ->orWhere('details', 'like', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return Inertia::render('admin/audit-log/index', [
            'logs' => $query->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'action' => $filters['action'] ?? '',
                'user_id' => $filters['user_id'] ?? '',
            ],
            'actions' => self::ACTIONS,
        ]);
    }
}
