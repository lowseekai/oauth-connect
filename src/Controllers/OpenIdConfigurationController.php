<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Lowseekai\OAuthConnect\Support\OpenIdConnect;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OpenIdConfigurationController implements RequestHandlerInterface
{
    private $openid;

    public function __construct(OpenIdConnect $openid)
    {
        $this->openid = $openid;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse($this->openid->configuration());
    }
}
