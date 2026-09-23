<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Support\ApplicationDirectorySerializer;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListApplicationDirectoryController implements RequestHandlerInterface
{
    public function __construct(private ApplicationDirectorySerializer $serializer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        AuthorizationCenterAccess::assertCanManageApplications($actor);

        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $limit = min(100, max(1, (int) ($request->getQueryParams()['limit'] ?? 50)));
        $status = $request->getQueryParams()['status'] ?? null;
        $query = Application::with('user')->whereIn('status', ['pending', 'approved']);

        if (in_array($status, ['pending', 'approved'], true)) {
            $query->where('status', $status);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (Application $application) => $this->serializer->serialize($application))
            ->values()
            ->all();

        return new JsonResponse(['data' => $items, 'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) max(1, ceil($total / $limit)),
        ]]);
    }
}
