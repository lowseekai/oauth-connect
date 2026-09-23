<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Client;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListMyClientsController implements RequestHandlerInterface
{
    public function __construct(private ClientRepository $clients)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $clients = Client::where('owner_user_id', $actor->id)->whereNull('deleted_at')->orderByDesc('created_at')->get()
            ->map(fn (Client $client) => $this->clients->serialize($client))->values()->all();

        return new JsonResponse(['data' => $clients]);
    }
}
