<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListMyApplicationsController implements RequestHandlerInterface
{
    public function __construct(private ApplicationRepository $applications)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? $params['page_number'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $query = Application::where('user_id', $actor->id);
        $status = trim((string) ($params['status'] ?? ''));

        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn (Application $application) => $this->applications->serialize($application))->values()->all();

        return new JsonResponse(['data' => $items, 'meta' => $this->meta($page, $limit, $total)]);
    }

    private function meta(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) max(1, ceil($total / $limit)),
            'has_prev' => $page > 1,
            'has_next' => $page * $limit < $total,
        ];
    }
}
