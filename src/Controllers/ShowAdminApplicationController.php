<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use InvalidArgumentException;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Models\AuditLog;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShowAdminApplicationController implements RequestHandlerInterface
{
    public function __construct(private ApplicationRepository $applications)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        AuthorizationCenterAccess::assert($actor, 'oauthConnect.manageApplications');
        $application = Application::with(['user', 'client'])->find($this->id($request));

        if (! $application) {
            return new JsonResponse(['error' => 'Application not found.'], 404);
        }

        $logs = AuditLog::with('actor')
            ->where('target_type', 'application')
            ->where('target_id', (string) $application->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => (int) $log->id,
                'action' => $log->action,
                'actor_user_id' => $log->actor_user_id ? (int) $log->actor_user_id : null,
                'actor_username' => $log->actor ? $log->actor->username : null,
                'before' => $this->decode($log->before_data),
                'after' => $this->decode($log->after_data),
                'created_at' => $log->created_at ? Carbon::parse($log->created_at)->toIso8601String() : null,
            ])->values()->all();

        return new JsonResponse([
            'data' => [
                'application' => $this->applications->serialize($application),
                'audit_logs' => $logs,
            ],
        ]);
    }

    private function id(ServerRequestInterface $request): int
    {
        $parameters = $request->getAttribute('routeParameters', []);

        return (int) ($parameters['applicationId'] ?? $request->getQueryParams()['applicationId'] ?? 0);
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
