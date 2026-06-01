<?php

namespace ISeekUp\OAuthConnect\Controllers;

use Flarum\Http\RequestUtil;
use ISeekUp\OAuthConnect\Repositories\ClientRepository;
use ISeekUp\OAuthConnect\Support\AccessPolicy;
use ISeekUp\OAuthConnect\Support\OAuthErrorResponse;
use ISeekUp\OAuthConnect\Support\UserInfoBuilder;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserInfoController implements RequestHandlerInterface
{
    private $builder;
    private $errors;
    private $clients;
    private $accessPolicy;

    public function __construct(
        UserInfoBuilder $builder,
        OAuthErrorResponse $errors,
        ClientRepository $clients,
        AccessPolicy $accessPolicy
    ) {
        $this->builder = $builder;
        $this->errors = $errors;
        $this->clients = $clients;
        $this->accessPolicy = $accessPolicy;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $request->getAttribute('oauthConnectToken')) {
            return $this->errors->make('invalid_token', 'A valid OAuth2 Bearer token is required.', 401, [
                'WWW-Authenticate' => 'Bearer realm="OAuth2 UserInfo"',
            ]);
        }

        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $token = $request->getAttribute('oauthConnectToken');
        $client = $this->clients->findEnabled((string) $token->client_id);

        if (! $client) {
            return $this->errors->make('invalid_token', 'The OAuth2 client is disabled or no longer exists.', 401, [
                'WWW-Authenticate' => 'Bearer realm="OAuth2 UserInfo"',
            ]);
        }

        $policyFailure = $this->accessPolicy->failureMessage($client, $actor);

        if ($policyFailure !== null) {
            return $this->errors->make('access_denied', $policyFailure, 403, [
                'WWW-Authenticate' => 'Bearer realm="OAuth2 UserInfo"',
            ]);
        }

        return new JsonResponse($this->builder->build($actor, $request->getAttribute('oauthConnectScopes', ['user.read'])));
    }
}
