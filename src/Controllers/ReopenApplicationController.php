<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use InvalidArgumentException;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Repositories\AuditRepository;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Lowseekai\OAuthConnect\Support\RequestData;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ReopenApplicationController implements RequestHandlerInterface
{
    public function __construct(
        private ApplicationRepository $applications,
        private AuditRepository $audit,
        private RequestData $data
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

        $before = ['status' => $application->status];
        $body = $this->data->all($request);

        try {
            $application = $this->applications->transition($application, 'pending', (int) $actor->id, trim((string) ($body['review_note'] ?? '')) ?: null);
        } catch (InvalidArgumentException $error) {
            return new JsonResponse(['error' => $error->getMessage()], 409);
        }

        $this->audit->record((int) $actor->id, 'application.reopened', 'application', (string) $application->id, $before, ['status' => 'pending'], $request);

        return new JsonResponse(['data' => $this->applications->serialize($application)]);
    }

    private function id(ServerRequestInterface $request): int
    {
        $parameters = $request->getAttribute('routeParameters', []);

        return (int) ($parameters['applicationId'] ?? $request->getQueryParams()['applicationId'] ?? 0);
    }
}
