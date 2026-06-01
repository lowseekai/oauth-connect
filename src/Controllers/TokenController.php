<?php

namespace ISeekUp\OAuthConnect\Controllers;

use Carbon\Carbon;
use ISeekUp\OAuthConnect\Models\AuthorizationCode;
use ISeekUp\OAuthConnect\Models\Client;
use ISeekUp\OAuthConnect\Models\RefreshToken;
use ISeekUp\OAuthConnect\Repositories\ClientRepository;
use ISeekUp\OAuthConnect\Repositories\TokenRepository;
use ISeekUp\OAuthConnect\Support\AccessPolicy;
use ISeekUp\OAuthConnect\Support\OAuthErrorResponse;
use ISeekUp\OAuthConnect\Support\RequestData;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TokenController implements RequestHandlerInterface
{
    private $clients;
    private $tokens;
    private $data;
    private $errors;
    private $accessPolicy;

    public function __construct(
        ClientRepository $clients,
        TokenRepository $tokens,
        RequestData $data,
        OAuthErrorResponse $errors,
        AccessPolicy $accessPolicy
    ) {
        $this->clients = $clients;
        $this->tokens = $tokens;
        $this->data = $data;
        $this->errors = $errors;
        $this->accessPolicy = $accessPolicy;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->data->all($request);
        $grantType = (string) ($body['grant_type'] ?? '');
        [$clientId, $clientSecret] = $this->clientCredentials($request, $body);

        $client = $clientId !== '' ? $this->clients->findEnabled($clientId) : null;

        if (! $client || ! password_verify($clientSecret, $client->client_secret_hash)) {
            return $this->errors->make('invalid_client', 'Invalid client credentials.', 401, [
                'WWW-Authenticate' => 'Basic realm="OAuth2 Token"',
            ]);
        }

        if (! $client->allowsGrantType($grantType)) {
            return $this->errors->make('unsupported_grant_type', 'The requested grant type is not supported for this client.');
        }

        if ($grantType === 'authorization_code') {
            return $this->authorizationCodeGrant($client, $body);
        }

        if ($grantType === 'refresh_token') {
            return $this->refreshTokenGrant($client, $body);
        }

        return $this->errors->make('unsupported_grant_type', 'Only authorization_code and refresh_token are supported.');
    }

    private function authorizationCodeGrant(Client $client, array $body): ResponseInterface
    {
        $codeValue = (string) ($body['code'] ?? '');
        $redirectUri = (string) ($body['redirect_uri'] ?? '');

        if ($codeValue === '' || $redirectUri === '') {
            return $this->errors->make('invalid_request', 'code and redirect_uri are required.');
        }

        $code = AuthorizationCode::findValid($codeValue);

        if (! $code) {
            return $this->errors->make('invalid_grant', 'Authorization code is invalid, expired, or already used.');
        }

        if ($code->client_id !== $client->client_id || $code->redirect_uri !== $redirectUri) {
            return $this->errors->make('invalid_grant', 'Authorization code does not match this client or redirect_uri.');
        }

        if (! $code->user) {
            return $this->errors->make('invalid_grant', 'Authorization code user no longer exists.');
        }

        $policyFailure = $this->accessPolicy->failureMessage($client, $code->user);

        if ($policyFailure !== null) {
            return $this->errors->make('access_denied', $policyFailure, 403);
        }

        $code->used_at = Carbon::now();
        $code->save();

        [$accessToken, $refreshToken] = $this->tokens->issuePair(
            $client->client_id,
            (int) $code->user_id,
            $this->splitScope($code->scope)
        );

        return $this->tokenResponse($accessToken->token, $refreshToken->token, $accessToken->scope, $this->tokens->accessLifetime());
    }

    private function refreshTokenGrant(Client $client, array $body): ResponseInterface
    {
        $tokenValue = (string) ($body['refresh_token'] ?? '');

        if ($tokenValue === '') {
            return $this->errors->make('invalid_request', 'refresh_token is required.');
        }

        $oldRefreshToken = RefreshToken::findValid($tokenValue);

        if (! $oldRefreshToken || $oldRefreshToken->client_id !== $client->client_id) {
            return $this->errors->make('invalid_grant', 'Refresh token is invalid, expired, revoked, or belongs to another client.');
        }

        if (! $oldRefreshToken->user) {
            return $this->errors->make('invalid_grant', 'Refresh token user no longer exists.');
        }

        $policyFailure = $this->accessPolicy->failureMessage($client, $oldRefreshToken->user);

        if ($policyFailure !== null) {
            return $this->errors->make('access_denied', $policyFailure, 403);
        }

        $oldRefreshToken->revoked_at = Carbon::now();
        $oldRefreshToken->save();

        [$accessToken, $refreshToken] = $this->tokens->issuePair(
            $client->client_id,
            (int) $oldRefreshToken->user_id,
            $oldRefreshToken->scopeList()
        );

        return $this->tokenResponse($accessToken->token, $refreshToken->token, $accessToken->scope, $this->tokens->accessLifetime());
    }

    private function tokenResponse(string $accessToken, string $refreshToken, ?string $scope, int $expiresIn): JsonResponse
    {
        return new JsonResponse([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'scope' => $scope ?: 'user.read',
        ], 200, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    private function clientCredentials(ServerRequestInterface $request, array $body): array
    {
        $authorization = $request->getHeaderLine('authorization');

        if (stripos($authorization, 'Basic ') === 0) {
            $decoded = base64_decode(substr($authorization, 6), true);

            if ($decoded !== false && strpos($decoded, ':') !== false) {
                [$clientId, $clientSecret] = explode(':', $decoded, 2);

                return [urldecode($clientId), urldecode($clientSecret)];
            }
        }

        return [
            (string) ($body['client_id'] ?? ''),
            (string) ($body['client_secret'] ?? ''),
        ];
    }

    private function splitScope(?string $scope): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) $scope)) ?: [], 'strlen'));
    }
}
