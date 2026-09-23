<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use InvalidArgumentException;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Lowseekai\OAuthConnect\Repositories\AuditRepository;
use Lowseekai\OAuthConnect\Support\RequestData;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CreateApplicationController implements RequestHandlerInterface
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
        AuthorizationCenterAccess::assertSubmitApplication($actor);

        try {
            $application = $this->applications->create((int) $actor->id, $this->data->all($request));
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $this->audit->record((int) $actor->id, 'application.submitted', 'application', (string) $application->id, null, [
            'status' => $application->status,
            'name' => $application->name,
        ], $request);

        return new JsonResponse(['data' => $this->applications->serialize($application)], 201);
    }
}
