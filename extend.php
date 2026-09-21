<?php

use Flarum\Extend;
use Lowseekai\OAuthConnect\Controllers\AuthorizeController;
use Lowseekai\OAuthConnect\Controllers\AuthorizePageController;
use Lowseekai\OAuthConnect\Controllers\CreateClientController;
use Lowseekai\OAuthConnect\Controllers\DeleteClientController;
use Lowseekai\OAuthConnect\Controllers\JwksController;
use Lowseekai\OAuthConnect\Controllers\ListAuthorizationsController;
use Lowseekai\OAuthConnect\Controllers\ListClientsController;
use Lowseekai\OAuthConnect\Controllers\OpenIdConfigurationController;
use Lowseekai\OAuthConnect\Controllers\ResetClientSecretController;
use Lowseekai\OAuthConnect\Controllers\RevokeAuthorizationController;
use Lowseekai\OAuthConnect\Controllers\RevokeTokenController;
use Lowseekai\OAuthConnect\Controllers\TokenController;
use Lowseekai\OAuthConnect\Controllers\UpdateClientController;
use Lowseekai\OAuthConnect\Controllers\UserInfoController;
use Lowseekai\OAuthConnect\Middlewares\OAuthBearerMiddleware;

return [
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    (new Extend\Routes('forum'))
        ->get('/.well-known/openid-configuration', 'oauth-connect.openid.configuration', OpenIdConfigurationController::class)
        ->get('/.well-known/jwks.json', 'oauth-connect.openid.jwks', JwksController::class)
        ->get('/oauth2/authorize', 'oauth-connect.authorize', AuthorizePageController::class)
        ->post('/oauth2/authorize', 'oauth-connect.authorize.submit', AuthorizeController::class)
        ->post('/oauth2/token', 'oauth-connect.token', TokenController::class),

    (new Extend\Routes('api'))
        ->get('/oauth/user', 'oauth-connect.user', UserInfoController::class)
        ->get('/user', 'oauth-connect.user.alias', UserInfoController::class)
        ->get('/oauth-connect/clients', 'oauth-connect.clients.index', ListClientsController::class)
        ->post('/oauth-connect/clients', 'oauth-connect.clients.create', CreateClientController::class)
        ->patch('/oauth-connect/clients/{clientId}', 'oauth-connect.clients.update', UpdateClientController::class)
        ->delete('/oauth-connect/clients/{clientId}', 'oauth-connect.clients.delete', DeleteClientController::class)
        ->post('/oauth-connect/clients/{clientId}/reset-secret', 'oauth-connect.clients.reset-secret', ResetClientSecretController::class)
        ->get('/oauth-connect/authorizations', 'oauth-connect.authorizations.index', ListAuthorizationsController::class)
        ->post('/oauth-connect/authorizations/revoke', 'oauth-connect.authorizations.revoke', RevokeAuthorizationController::class)
        ->post('/oauth-connect/tokens/revoke', 'oauth-connect.tokens.revoke', RevokeTokenController::class),

    (new Extend\Csrf())
        ->exemptRoute('oauth-connect.token'),

    (new Extend\Middleware('api'))
        ->insertAfter('flarum.api.route_resolver', OAuthBearerMiddleware::class),

    (new Extend\Settings())
        ->default('iseekup.oauth-connect.access_token_lifetime', '7200')
        ->default('iseekup.oauth-connect.refresh_token_lifetime', '2592000')
        ->default('iseekup.oauth-connect.authorization_code_lifetime', '600')
        ->default('iseekup.oauth-connect.require_state', '1'),
];
