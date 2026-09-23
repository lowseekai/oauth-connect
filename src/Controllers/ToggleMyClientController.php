<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\Client;
use Lowseekai\OAuthConnect\Repositories\AuditRepository;
use Lowseekai\OAuthConnect\Support\RequestData;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ToggleMyClientController implements RequestHandlerInterface
{
    public function __construct(
        private AuditRepository $audit,
        private RequestData $data
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $parameters = $request->getAttribute('routeParameters', []);
        $clientId = (string) ($parameters['clientId'] ?? $request->getQueryParams()['clientId'] ?? '');
        $client = Client::where('client_id', $clientId)->where('owner_user_id', $actor->id)->whereNull('deleted_at')->first();

        if (! $client) {
            return new JsonResponse(['error' => 'Client not found.'], 404);
        }

        $enabled = filter_var($this->data->all($request)['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($enabled && ($client->approval_status !== null && $client->approval_status !== 'approved')) {
            return new JsonResponse(['error' => 'Only approved clients can be enabled.'], 422);
        }

        $before = ['is_enabled' => (bool) $client->is_enabled];
        $client->is_enabled = $enabled;
        $client->save();
        $this->audit->record((int) $actor->id, $enabled ? 'client.enabled' : 'client.disabled', 'client', $client->client_id, $before, ['is_enabled' => $enabled], $request);

        return new JsonResponse(['data' => [
            'client_id' => $client->client_id,
            'is_enabled' => $enabled,
        ]]);
    }
}
