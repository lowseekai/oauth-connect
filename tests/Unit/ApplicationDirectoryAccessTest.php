<?php

namespace Lowseekai\OAuthConnect\Tests\Unit;

use Flarum\User\User;
use Lowseekai\OAuthConnect\Support\AuthorizationCenterAccess;
use PHPUnit\Framework\TestCase;

class ApplicationDirectoryAccessTest extends TestCase
{
    public function testAdministratorCanManageApplications(): void
    {
        $actor = $this->createMock(User::class);
        $actor->expects(self::once())->method('isAdmin')->willReturn(true);
        $actor->expects(self::never())->method('hasPermission');

        self::assertTrue(AuthorizationCenterAccess::canManageApplications($actor));
    }

    public function testUserWithReviewPermissionCanManageApplications(): void
    {
        $actor = $this->createMock(User::class);
        $actor->expects(self::once())->method('isAdmin')->willReturn(false);
        $actor->expects(self::once())->method('hasPermission')
            ->with('oauthConnect.manageApplications')
            ->willReturn(true);

        self::assertTrue(AuthorizationCenterAccess::canManageApplications($actor));
    }

    public function testUserWithoutReviewPermissionCannotManageApplications(): void
    {
        $actor = $this->createMock(User::class);
        $actor->expects(self::once())->method('isAdmin')->willReturn(false);
        $actor->expects(self::once())->method('hasPermission')
            ->with('oauthConnect.manageApplications')
            ->willReturn(false);

        self::assertFalse(AuthorizationCenterAccess::canManageApplications($actor));
    }
}
