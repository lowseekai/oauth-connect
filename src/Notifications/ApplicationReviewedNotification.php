<?php

namespace Lowseekai\OAuthConnect\Notifications;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Lowseekai\OAuthConnect\Models\Application;

class ApplicationReviewedNotification implements BlueprintInterface, MailableInterface, AlertableInterface
{
    public function __construct(private Application $application, private User $reviewer)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->reviewer;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->application->user;
    }

    public function getData(): array
    {
        return [
            'applicationId' => (int) $this->application->id,
            'applicationName' => $this->application->name,
            'status' => $this->application->status,
        ];
    }

    public static function getType(): string
    {
        return 'oauthApplicationReviewed';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'lowseekai-oauth-connect::email.plain.application-reviewed', 'html' => 'lowseekai-oauth-connect::email.html.application-reviewed'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('lowseekai-oauth-connect.email.application_reviewed_subject');
    }
}
