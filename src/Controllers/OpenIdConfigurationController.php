<?php

namespace ISeekUp\OAuthConnect\Controllers;

use ISeekUp\OAuthConnect\Support\OpenIdConnect;
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
