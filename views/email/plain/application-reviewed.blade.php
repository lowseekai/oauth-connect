@include('mail::plain.notification', [
    'title' => $translator->trans('lowseekai-oauth-connect.email.application_reviewed_title'),
    'body' => $translator->trans('lowseekai-oauth-connect.email.application_reviewed_body', [
        'application' => $blueprint->getData()['applicationName'],
        'status' => $translator->trans('lowseekai-oauth-connect.forum.status.'.$blueprint->getData()['status']),
    ]),
])
