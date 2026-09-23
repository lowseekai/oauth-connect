<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Repositories\AuditRepository;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class WithdrawApplicationController implements RequestHandlerInterface
{
    public function __construct(
        private ApplicationRepository $applications,
        private AuditRepository $audit
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $id = $this->id($request);
        $application = $this->applications->findForUser($id, (int) $actor->id);

        if (! $application) {
            return new JsonResponse(['error' => 'Application not found.'], 404);
        }

        if ($application->status !== 'pending') {
            return new JsonResponse(['error' => 'Only pending applications can be withdrawn.'], 422);
        }

        $before = ['status' => $application->status];
        try {
            $application = $this->applications->transition($application, 'withdrawn', (int) $actor->id);
        } catch (\InvalidArgumentException $error) {
            return new JsonResponse(['error' => $error->getMessage()], 409);
        }
        $this->audit->record((int) $actor->id, 'application.withdrawn', 'application', (string) $application->id, $before, ['status' => 'withdrawn'], $request);

        return new JsonResponse(['data' => $this->applications->serialize($application)]);
    }

    private function id(ServerRequestInterface $request): int
    {
        $parameters = $request->getAttribute('routeParameters', []);

        return (int) ($parameters['applicationId'] ?? $request->getQueryParams()['applicationId'] ?? 0);
    }
}
