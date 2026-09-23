<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListAdminApplicationsController implements RequestHandlerInterface
{
    public function __construct(private ApplicationRepository $applications)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        AuthorizationCenterAccess::assert($actor, 'oauthConnect.manageApplications');
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? $params['page_number'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $query = Application::with('user');
        $status = trim((string) ($params['status'] ?? ''));
        $search = trim((string) ($params['q'] ?? ''));

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('id', ctype_digit($search) ? (int) $search : -1)
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('username', 'like', '%'.$search.'%');
                    });
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn (Application $application) => $this->applications->serialize($application))->values()->all();

        return new JsonResponse(['data' => $items, 'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) max(1, ceil($total / $limit)),
            'has_prev' => $page > 1,
            'has_next' => $page * $limit < $total,
        ]]);
    }
}
