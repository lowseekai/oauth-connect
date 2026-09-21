<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Lowseekai\OAuthConnect\Support\Translation;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ResetClientSecretController implements RequestHandlerInterface
{
    private $clients;
    private $translation;

    public function __construct(ClientRepository $clients, Translation $translation)
    {
        $this->clients = $clients;
        $this->translation = $translation;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $client = $this->clients->find((string) ($request->getQueryParams()['clientId'] ?? ''));

        if (! $client) {
            return new JsonResponse(['error' => $this->translation->trans('admin.errors.client_not_found', [], 'Client not found.')], 404);
        }

        $secret = $this->clients->resetSecret($client);
        $this->clients->revokeTokens($client);

        return new JsonResponse(['data' => $this->clients->serialize($client, $secret)]);
    }
}
