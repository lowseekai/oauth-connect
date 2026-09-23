<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Client;
use Lowseekai\OAuthConnect\Repositories\AuditRepository;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ResetMyClientSecretController implements RequestHandlerInterface
{
    public function __construct(
        private ClientRepository $clients,
        private AuditRepository $audit
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $client = $this->client($request, (int) $actor->id);

        if (! $client) {
            return new JsonResponse(['error' => 'Client not found.'], 404);
        }

        $secret = $this->clients->resetSecret($client);
        $this->clients->revokeTokens($client);
        $this->audit->record((int) $actor->id, 'client.secret_reset', 'client', $client->client_id, null, ['tokens_revoked' => true], $request);

        return new JsonResponse(['data' => $this->clients->serialize($client, $secret)]);
    }

    private function client(ServerRequestInterface $request, int $userId): ?Client
    {
        $parameters = $request->getAttribute('routeParameters', []);
        $clientId = (string) ($parameters['clientId'] ?? $request->getQueryParams()['clientId'] ?? '');

        return Client::where('client_id', $clientId)->where('owner_user_id', $userId)->whereNull('deleted_at')->first();
    }
}
