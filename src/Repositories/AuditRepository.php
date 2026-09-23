<?php

namespace Lowseekai\OAuthConnect\Repositories;

use Carbon\Carbon;
use Lowseekai\OAuthConnect\Models\AuditLog;
use Psr\Http\Message\ServerRequestInterface;

class AuditRepository
{
    public function record(
        ?int $actorUserId,
        string $action,
        string $targetType,
        string $targetId,
        ?array $before = null,
        ?array $after = null,
        ?ServerRequestInterface $request = null
    ): AuditLog {
        $log = new AuditLog();
        $log->actor_user_id = $actorUserId;
        $log->action = $action;
        $log->target_type = $targetType;
        $log->target_id = $targetId;
        $log->before_data = $this->encode($before);
        $log->after_data = $this->encode($after);
        $log->ip_address = $request ? $this->ipAddress($request) : null;
        $log->user_agent = $request ? mb_substr($request->getHeaderLine('user-agent'), 0, 1000) : null;
        $log->created_at = Carbon::now();
        $log->save();

        return $log;
    }

    private function encode(?array $data): ?string
    {
        return $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function ipAddress(ServerRequestInterface $request): ?string
    {
        $params = $request->getServerParams();
        $ip = $params['REMOTE_ADDR'] ?? null;

        return $ip ? mb_substr((string) $ip, 0, 45) : null;
    }
}
