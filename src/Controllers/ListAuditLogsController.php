<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\AuditLog;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListAuditLogsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        AuthorizationCenterAccess::assert($actor, 'oauthConnect.viewAuditLog');
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? $params['page_number'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $query = AuditLog::with('actor');

        if (! empty($params['action'])) {
            $query->where('action', (string) $params['action']);
        }

        foreach (['target_type', 'target_id', 'actor_user_id'] as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $query->where($field, (string) $params[$field]);
            }
        }

        $total = (clone $query)->count();

        $items = $query->orderByDesc('created_at')->offset(($page - 1) * $limit)->limit($limit)->get()->map(function (AuditLog $log) {
            return [
                'id' => (int) $log->id,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'actor_user_id' => $log->actor_user_id ? (int) $log->actor_user_id : null,
                'actor_username' => $log->actor ? $log->actor->username : null,
                'before' => $this->decode($log->before_data),
                'after' => $this->decode($log->after_data),
                'created_at' => $log->created_at ? Carbon::parse($log->created_at)->toIso8601String() : null,
            ];
        })->values()->all();

        return new JsonResponse(['data' => $items, 'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) max(1, ceil($total / $limit)),
            'has_prev' => $page > 1,
            'has_next' => $page * $limit < $total,
        ]]);
    }

    private function decode(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
