<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Client;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListClientsController implements RequestHandlerInterface
{
    private $clients;

    public function __construct(ClientRepository $clients)
    {
        $this->clients = $clients;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        AuthorizationCenterAccess::assert(RequestUtil::getActor($request), 'oauthConnect.manageClients');

        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page_number'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $query = Client::whereNull('deleted_at');

        $status = (string) ($params['status'] ?? '');

        if ($status === 'enabled') {
            $query->where('is_enabled', true);
        } elseif ($status === 'disabled') {
            $query->where('is_enabled', false);
        }

        $search = trim((string) ($params['q'] ?? ''));

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query
                    ->where('client_id', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('homepage_url', 'like', $like);
            });
        }

        $total = (clone $query)->count();

        $clients = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function (Client $client) {
                return $this->clients->serialize($client);
            })
            ->values()
            ->all();

        return new JsonResponse([
            'data' => $clients,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) max(1, ceil($total / $limit)),
                'has_prev' => $page > 1,
                'has_next' => $offset + $limit < $total,
            ],
        ]);
    }
}
