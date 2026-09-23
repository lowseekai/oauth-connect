<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Repositories\ApplicationRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShowMyApplicationController implements RequestHandlerInterface
{
    public function __construct(private ApplicationRepository $applications)
    {
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

        $secret = $this->applications->consumeSecret($application);
        $data = $this->applications->serialize($application);

        if ($secret !== null) {
            $data['client_secret'] = $secret;
        }

        return new JsonResponse(['data' => $data], 200, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    private function id(ServerRequestInterface $request): int
    {
        $parameters = $request->getAttribute('routeParameters', []);

        return (int) ($parameters['applicationId'] ?? $request->getQueryParams()['applicationId'] ?? 0);
    }
}
