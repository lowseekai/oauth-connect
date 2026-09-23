<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use InvalidArgumentException;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Lowseekai\OAuthConnect\Support\RequestData;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CreateClientController implements RequestHandlerInterface
{
    private $clients;
    private $data;

    public function __construct(
        ClientRepository $clients,
        RequestData $data
    ) {
        $this->clients = $clients;
        $this->data = $data;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        AuthorizationCenterAccess::assert(RequestUtil::getActor($request), 'oauthConnect.manageClients');

        try {
            [$client, $secret] = $this->clients->create($this->data->all($request));
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['data' => $this->clients->serialize($client, $secret)], 201);
    }
}
