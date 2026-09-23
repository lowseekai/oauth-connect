<?php

namespace Lowseekai\OAuthConnect\Controllers;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Lowseekai\OAuthConnect\Models\AccessToken;
use Lowseekai\OAuthConnect\Models\RefreshToken;
use Lowseekai\OAuthConnect\Support\RequestData;
use Lowseekai\OAuthConnect\Support\Translation;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RevokeTokenController implements RequestHandlerInterface
{
    private $data;
    private $translation;

    public function __construct(RequestData $data, Translation $translation)
    {
        $this->data = $data;
        $this->translation = $translation;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        AuthorizationCenterAccess::assert(RequestUtil::getActor($request), 'oauthConnect.manageAuthorizations');

        $body = $this->data->all($request);
        $token = (string) ($body['token'] ?? '');

        if ($token === '') {
            return new JsonResponse(['error' => $this->translation->trans('admin.errors.token_required', [], 'token is required.')], 422);
        }

        $now = Carbon::now();
        $accessCount = AccessToken::where('token', $token)->whereNull('revoked_at')->update(['revoked_at' => $now]);
        $refreshCount = RefreshToken::where('token', $token)->whereNull('revoked_at')->update(['revoked_at' => $now]);

        return new JsonResponse(['data' => ['revoked' => ($accessCount + $refreshCount) > 0]]);
    }
}
