<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use InvalidArgumentException;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Repositories\AuditRepository;
use Lowseekai\OAuthConnect\Repositories\ClientRepository;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Lowseekai\OAuthConnect\Support\RequestData;
use Lowseekai\OAuthConnect\Support\ApplicationNotifier;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApproveApplicationController implements RequestHandlerInterface
{
    public function __construct(
        private ApplicationRepository $applications,
        private ClientRepository $clients,
        private AuditRepository $audit,
        private RequestData $data,
        private ApplicationNotifier $notifier
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        AuthorizationCenterAccess::assert($actor, 'oauthConnect.manageApplications');
        $application = $this->applications->find($this->id($request));

        if (! $application) {
            return new JsonResponse(['error' => 'Application not found.'], 404);
        }

        try {
            [$application, $client, $secret] = $this->applications->approve($application, (int) $actor->id, $this->data->all($request));
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $this->audit->record((int) $actor->id, 'application.approved', 'application', (string) $application->id, ['status' => 'pending'], [
            'status' => 'approved',
            'client_id' => $client->client_id,
        ], $request);
        $this->notifier->reviewed($application, $actor);

        return new JsonResponse([
            'data' => [
                'application' => $this->applications->serialize($application),
                'client' => $this->clients->serialize($client, $secret),
            ],
        ]);
    }

    private function id(ServerRequestInterface $request): int
    {
        $parameters = $request->getAttribute('routeParameters', []);

        return (int) ($parameters['applicationId'] ?? $request->getQueryParams()['applicationId'] ?? 0);
    }
}
