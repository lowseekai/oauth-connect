<?php

namespace Lowseekai\OAuthConnect\Support;

use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Lowseekai\OAuthConnect\Models\Application;
use Lowseekai\OAuthConnect\Notifications\ApplicationReviewedNotification;
use Lowseekai\OAuthConnect\Notifications\ApplicationSubmittedNotification;

class ApplicationNotifier
{
    public function __construct(private NotificationSyncer $notifications)
    {
    }

    public function submitted(Application $application): void
    {
        $application->loadMissing('user');
        $recipients = User::query()
            ->with('groups.permissions')
            ->get()
            ->filter(fn (User $user) => $user->isAdmin() || $user->hasPermission('oauthConnect.manageApplications'))
            ->values();

        $this->notifications->sync(new ApplicationSubmittedNotification($application), $recipients->all());
    }

    public function reviewed(Application $application, User $reviewer): void
    {
        $application->loadMissing('user');

        if ($application->user) {
            $this->notifications->sync(new ApplicationReviewedNotification($application, $reviewer), [$application->user]);
        }
    }
}
