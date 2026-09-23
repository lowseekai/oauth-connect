import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Notification from 'flarum/forum/components/Notification';

(function () {
  'use strict';

  function getDefault(value) {
    return value && value.default ? value.default : value;
  }

  var m = typeof window !== 'undefined' ? window.m : null;

  if (!app || !m || !app.initializers) return;

  function api(path) {
    return app.forum.attribute('apiUrl') + '/oauth-connect' + path;
  }

  function t(key, params, fallback) {
    var value = app.translator.trans('lowseekai-oauth-connect.forum.' + key, params || {});
    return value === 'lowseekai-oauth-connect.forum.' + key ? fallback : value;
  }

  function errorMessage(error) {
    return error && error.errors && error.errors[0] && error.errors[0].detail ? error.errors[0].detail : (error && error.error) || t('request_failed', {}, 'Request failed.');
  }

  class AuthorizationCenterPage extends Page {
    bodyClass = 'App--index';

    oninit(vnode) {
      super.oninit(vnode);
      this.loading = true;
      this.submitting = false;
      this.error = null;
      this.applications = [];
      this.clients = [];
      this.directory = [];
      this.directoryAvailable = false;
      this.directoryStatus = 'all';
      this.directoryPage = 1;
      this.directoryPages = 1;
      this.directoryTotal = 0;
      this.showForm = false;
      this.form = { name: '', description: '', homepage_url: '', redirect_uris: '', scopes: ['user.read'], application_note: '' };
      this.load();
    }
  }

  function statusLabel(status) {
    var labels = {
      pending: t('status.pending', {}, 'Under review'),
      approved: t('status.approved', {}, 'Approved'),
      rejected: t('status.rejected', {}, 'Rejected'),
      withdrawn: t('status.withdrawn', {}, 'Withdrawn'),
      cancelled: t('status.cancelled', {}, 'Cancelled'),
    };

    return labels[status] || status;
  }

  function date(value) {
    if (!value) return '';

    try {
      return new Date(value).toLocaleDateString();
    } catch (error) {
      return value;
    }
  }

  AuthorizationCenterPage.prototype.load = function () {
    var self = this;
    return Promise.all([
      app.request({ method: 'GET', url: api('/applications') }),
      app.request({ method: 'GET', url: api('/my-clients') }),
    ]).then(function (responses) {
      self.applications = responses[0].data || [];
      self.clients = responses[1].data || [];
      self.loading = false;
      m.redraw();
      self.loadDirectory();
    }, function (error) {
      self.error = errorMessage(error);
      self.loading = false;
      m.redraw();
    });
  };

  AuthorizationCenterPage.prototype.submit = function (event) {
    event.preventDefault();
    var self = this;
    self.submitting = true;
    self.error = null;
    app.request({ method: 'POST', url: api('/applications'), body: self.form }).then(function () {
      self.form = { name: '', description: '', homepage_url: '', redirect_uris: '', scopes: ['user.read'], application_note: '' };
      self.showForm = false;
      self.submitting = false;
      return self.load();
    }, function (error) {
      self.error = errorMessage(error);
      self.submitting = false;
      m.redraw();
    });
  };

  AuthorizationCenterPage.prototype.view = function () {
    var self = this;
    var pendingCount = self.applications.filter(function (application) { return application.status === 'pending'; }).length;
    var approvedCount = self.applications.filter(function (application) { return application.status === 'approved'; }).length;

    return m(PageStructure, { className: 'IndexPage', sidebar: function () { return m(IndexSidebar); } },
      m('.OAuthConnectForumPage', [
        m('.OAuthConnectPageTitle', [
          m('div', [
            m('span.OAuthConnectEyebrow', t('eyebrow', {}, 'DEVELOPER ACCESS')),
            m('h2', t('title', {}, 'Authorization center')),
            m('p.helpText', t('description', {}, 'Manage applications that connect to your forum account.')),
          ]),
          m('button.Button.Button--primary', { type: 'button', onclick: function () { self.showForm = !self.showForm; } }, self.showForm ? t('cancel', {}, 'Cancel') : t('new_application', {}, 'Apply for application access')),
        ]),
        m('.OAuthConnectOverview', [
          m('.OAuthConnectOverviewItem', [m('strong', self.loading ? '-' : self.applications.length), m('span', t('overview.applications', {}, 'Applications'))]),
          m('.OAuthConnectOverviewItem', [m('strong', self.loading ? '-' : pendingCount), m('span', t('overview.pending', {}, 'Under review'))]),
          m('.OAuthConnectOverviewItem', [m('strong', self.loading ? '-' : approvedCount), m('span', t('overview.approved', {}, 'Approved'))]),
        ]),
        self.error ? m('.ValidationErrors', self.error) : null,
        self.showForm ? self.formView() : null,
        m('.OAuthConnectSection', [
          m('.OAuthConnectSectionHeading', [m('h3', t('my_applications', {}, 'My applications')), m('span', self.loading ? '' : self.applications.length)]),
          self.loading ? m('p.helpText', t('loading', {}, 'Loading...')) : self.applicationList(),
        ]),
        m('.OAuthConnectSection', [
          m('.OAuthConnectSectionHeading', [m('h3', t('my_clients', {}, 'My clients')), m('span', self.loading ? '' : self.clients.length)]),
          self.loading ? null : self.clientList(),
        ]),
        self.directoryAvailable ? self.directoryView() : null,
      ])
    );
  };

  AuthorizationCenterPage.prototype.formView = function () {
    var self = this;
    return m('form.OAuthConnectForm', { onsubmit: self.submit.bind(self) }, [
      m('.OAuthConnectFormHeading', [m('h3', t('form.title', {}, 'New application')), m('p.helpText', t('form.description_help', {}, 'Add your app details and exact callback URL.'))]),
      m('label', [m('span', t('form.name', {}, 'Application name')), m('input.FormControl', { required: true, maxlength: 120, value: self.form.name, oninput: function (event) { self.form.name = event.target.value; } })]),
      m('label', [m('span', t('form.description', {}, 'Description')), m('textarea.FormControl', { required: true, maxlength: 2000, rows: 4, value: self.form.description, oninput: function (event) { self.form.description = event.target.value; } })]),
      m('.OAuthConnectFormGrid', [
        m('label', [m('span', t('form.homepage_url', {}, 'Homepage URL')), m('input.FormControl', { type: 'url', placeholder: 'https://', value: self.form.homepage_url, oninput: function (event) { self.form.homepage_url = event.target.value; } })]),
        m('label', [m('span', t('form.application_note', {}, 'Application note')), m('input.FormControl', { value: self.form.application_note, oninput: function (event) { self.form.application_note = event.target.value; } })]),
      ]),
      m('label', [m('span', t('form.redirect_uris', {}, 'Redirect URIs')), m('textarea.FormControl', { required: true, rows: 4, placeholder: 'https://app.example.com/oauth/callback', value: self.form.redirect_uris, oninput: function (event) { self.form.redirect_uris = event.target.value; } }), m('p.helpText', t('form.redirect_help', {}, 'Use one exact HTTPS redirect URI per line.'))]),
      m('.OAuthConnectFormActions', [m('button.Button.Button--primary', { type: 'submit', disabled: self.submitting }, self.submitting ? t('submitting', {}, 'Submitting...') : t('submit', {}, 'Submit application'))]),
    ]);
  };

  AuthorizationCenterPage.prototype.applicationList = function () {
    var self = this;
    if (!self.applications.length) return m('p.helpText', t('no_applications', {}, 'You have not submitted an application yet.'));
    return m('.OAuthConnectPanel', self.applications.map(function (application) {
      return m('.OAuthConnectApplicationItem', [
        m('.OAuthConnectApplicationMain', [
          m('.OAuthConnectApplicationIdentity', [m('strong', application.name), m('span.OAuthConnectStatus', { className: 'OAuthConnectStatus--' + application.status }, statusLabel(application.status))]),
          m('p.helpText', application.description),
          m('.OAuthConnectApplicationMeta', [application.created_at ? m('span', date(application.created_at)) : null, application.homepage_url ? m('a', { href: application.homepage_url, target: '_blank', rel: 'noopener noreferrer' }, application.homepage_url) : null]),
          application.review_note ? m('p.OAuthConnectReviewNote', application.review_note) : null,
        ]),
        m('.OAuthConnectActions', [
          application.status === 'approved' ? m('button.Button', { type: 'button', onclick: function () { self.viewApplication(application); } }, t('view_credentials', {}, 'View credentials')) : null,
          application.status === 'pending' ? m('button.Button', { type: 'button', onclick: function () { self.withdraw(application); } }, t('withdraw', {}, 'Withdraw')) : null,
        ]),
      ]);
    }));
  };

  AuthorizationCenterPage.prototype.viewApplication = function (application) {
    var self = this;
    app.request({ method: 'GET', url: api('/applications/' + application.id) }).then(function (response) {
      var data = response.data || {};
      var message = t('client_id', {}, 'Client ID: ') + (data.client_id || application.client_id || '') + '\n';

      if (data.client_secret) {
        message += t('client_secret', {}, 'Client Secret: ') + data.client_secret;
      } else {
        message += t('secret_unavailable', {}, 'The Client Secret has already been viewed. Reset it if you no longer have it.');
      }

      alert(message);
      self.load();
    }, function (error) {
      self.error = errorMessage(error);
      m.redraw();
    });
  };

  AuthorizationCenterPage.prototype.clientList = function () {
    var self = this;

    if (!self.clients.length) return m('p.helpText', t('no_clients', {}, 'No approved clients yet.'));

    return m('.OAuthConnectPanel', self.clients.map(function (client) {
      return m('.OAuthConnectApplicationItem', [
        m('.OAuthConnectApplicationMain', [m('.OAuthConnectApplicationIdentity', [m('strong', client.name), m('span.OAuthConnectStatus', { className: client.is_enabled ? 'OAuthConnectStatus--enabled' : 'OAuthConnectStatus--disabled' }, client.is_enabled ? t('enabled', {}, 'Enabled') : t('disabled', {}, 'Disabled'))]), m('code.OAuthConnectClientId', client.client_id)]),
        m('.OAuthConnectActions', [
          m('button.Button', { type: 'button', onclick: function () { self.toggleClient(client); } }, client.is_enabled ? t('disable', {}, 'Disable') : t('enable', {}, 'Enable')),
          m('button.Button', { type: 'button', onclick: function () { self.resetClient(client); } }, t('reset_secret', {}, 'Reset secret')),
        ]),
      ]);
    }));
  };

  AuthorizationCenterPage.prototype.directoryView = function () {
    var self = this;
    var items = self.directory;

    return m('.OAuthConnectSection.OAuthConnectDirectory', [
      m('.OAuthConnectSectionHeading', [m('h3', t('directory.title', {}, 'Connected applications')), m('span', self.directoryTotal)]),
      m('.OAuthConnectDirectoryFilters', [
        m('button.Button', { type: 'button', className: self.directoryStatus === 'all' ? 'active' : '', onclick: function () { self.directoryStatus = 'all'; self.directoryPage = 1; self.loadDirectory(); } }, t('directory.all', {}, 'All')),
        m('button.Button', { type: 'button', className: self.directoryStatus === 'pending' ? 'active' : '', onclick: function () { self.directoryStatus = 'pending'; self.directoryPage = 1; self.loadDirectory(); } }, statusLabel('pending')),
        m('button.Button', { type: 'button', className: self.directoryStatus === 'approved' ? 'active' : '', onclick: function () { self.directoryStatus = 'approved'; self.directoryPage = 1; self.loadDirectory(); } }, statusLabel('approved')),
      ]),
      items.length ? m('.OAuthConnectPanel', items.map(function (application) {
        return m('.OAuthConnectApplicationItem', [
          m('.OAuthConnectApplicationMain', [
            m('.OAuthConnectApplicationIdentity', [m('strong', application.name), m('span.OAuthConnectStatus', { className: 'OAuthConnectStatus--' + application.status }, statusLabel(application.status))]),
            m('p.helpText', application.description),
            m('.OAuthConnectApplicationMeta', [application.username ? m('span', application.username) : null, application.created_at ? m('span', date(application.created_at)) : null, application.homepage_url ? m('a', { href: application.homepage_url, target: '_blank', rel: 'noopener noreferrer' }, application.homepage_url) : null]),
          ]),
        ]);
      })) : m('p.helpText', t('directory.empty', {}, 'No applications in this view.')),
      self.directoryPages > 1 ? m('.OAuthConnectDirectoryPagination', [
        m('button.Button', { type: 'button', disabled: self.directoryPage <= 1, onclick: function () { self.directoryPage--; self.loadDirectory(); } }, t('directory.previous', {}, 'Previous')),
        m('span', t('directory.page', { page: self.directoryPage, pages: self.directoryPages }, 'Page {page} of {pages}')),
        m('button.Button', { type: 'button', disabled: self.directoryPage >= self.directoryPages, onclick: function () { self.directoryPage++; self.loadDirectory(); } }, t('directory.next', {}, 'Next')),
      ]) : null,
    ]);
  };

  AuthorizationCenterPage.prototype.loadDirectory = function () {
    var self = this;
    var params = { limit: 50, page: self.directoryPage };
    if (self.directoryStatus !== 'all') params.status = self.directoryStatus;
    var query = new URLSearchParams(params).toString();
    window.fetch(api('/admin/directory') + '?' + query, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then(function (response) {
      if (!response.ok) throw new Error('directory_unavailable');
      return response.json();
    }).then(function (response) {
      self.directory = response.data || [];
      self.directoryPages = response.meta && response.meta.total_pages ? response.meta.total_pages : 1;
      self.directoryTotal = response.meta && response.meta.total ? response.meta.total : 0;
      self.directoryAvailable = true;
      m.redraw();
    }, function () {
      self.directory = [];
      self.directoryAvailable = false;
      m.redraw();
    });
  };

  AuthorizationCenterPage.prototype.toggleClient = function (client) {
    var self = this;
    app.request({ method: 'POST', url: api('/my-clients/' + encodeURIComponent(client.client_id) + '/toggle'), body: { enabled: !client.is_enabled } }).then(function () { return self.load(); }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  AuthorizationCenterPage.prototype.resetClient = function (client) {
    var self = this;
    if (!confirm(t('confirm_reset_secret', {}, 'Reset this secret and revoke existing tokens?'))) return;
    app.request({ method: 'POST', url: api('/my-clients/' + encodeURIComponent(client.client_id) + '/reset-secret') }).then(function (response) {
      alert(t('client_secret', {}, 'Client Secret: ') + response.data.client_secret);
      return self.load();
    }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  AuthorizationCenterPage.prototype.withdraw = function (application) {
    var self = this;
    if (!confirm(t('confirm_withdraw', {}, 'Withdraw this application?'))) return;
    app.request({ method: 'POST', url: api('/applications/' + application.id + '/withdraw') }).then(function () { return self.load(); }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  app.initializers.add('lowseekai/oauth-connect-forum', function () {
    app.routes.oauthConnect = { path: '/oauth-connect', component: AuthorizationCenterPage };
    app.notificationComponents.oauthApplicationSubmitted = class extends Notification {
      icon() { return 'fas fa-key'; }
      href() { return app.route('oauthConnect'); }
      content() { return t('notification.submitted', { application: this.attrs.notification.content().applicationName }, 'New OAuth application: {application}'); }
      excerpt() { return this.content(); }
    };
    app.notificationComponents.oauthApplicationReviewed = class extends Notification {
      icon() { return 'fas fa-gavel'; }
      href() { return app.route('oauthConnect'); }
      content() {
        var data = this.attrs.notification.content() || {};
        return t('notification.reviewed', { application: data.applicationName, status: statusLabel(data.status) }, '{application} application {status}.');
      }
      excerpt() { return this.content(); }
    };
    extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items) {
      items.add('oauthApplicationSubmitted', { name: 'oauthApplicationSubmitted', icon: 'fas fa-key', label: t('notification.submitted_setting', {}, 'New OAuth applications') });
      items.add('oauthApplicationReviewed', { name: 'oauthApplicationReviewed', icon: 'fas fa-gavel', label: t('notification.reviewed_setting', {}, 'OAuth application reviews') });
    });

    if (extend && LinkButton) {
      extend('flarum/forum/components/IndexSidebar', 'navItems', function (items) {
        items.add('oauth-connect', m(LinkButton, {
          href: app.route('oauthConnect'),
          icon: 'fas fa-key',
        }, t('nav', {}, 'Authorization center')), 5);
      });
    }
  });
})();
