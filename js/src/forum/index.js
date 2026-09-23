import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import Page from 'flarum/common/components/Page';

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
    oninit(vnode) {
      super.oninit(vnode);
      this.loading = true;
      this.submitting = false;
      this.error = null;
      this.applications = [];
      this.clients = [];
      this.showForm = false;
      this.form = { name: '', description: '', homepage_url: '', redirect_uris: '', scopes: ['user.read'], application_note: '' };
      this.load();
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
    return m('.OAuthConnectForumPage', m('.container', [
      m('.OAuthConnectPageTitle', [m('div', [m('h2', t('title', {}, 'Authorization center')), m('p.helpText', t('description', {}, 'Manage applications that connect to your forum account.'))]), m('button.Button.Button--primary', { type: 'button', onclick: function () { self.showForm = !self.showForm; } }, self.showForm ? t('cancel', {}, 'Cancel') : t('new_application', {}, 'Apply for application access'))]),
      self.error ? m('.ValidationErrors', self.error) : null,
      self.showForm ? self.formView() : null,
      m('h3', t('my_applications', {}, 'My applications')),
      self.loading ? m('p', t('loading', {}, 'Loading...')) : self.applicationList(),
      m('h3', t('my_clients', {}, 'My clients')),
      self.loading ? null : self.clientList(),
    ]));
  };

  AuthorizationCenterPage.prototype.formView = function () {
    var self = this;
    return m('form.OAuthConnectPanel.OAuthConnectForm', { onsubmit: self.submit.bind(self) }, [
      m('h3', t('form.title', {}, 'New application')),
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
      return m('.OAuthConnectApplicationItem', [m('div', [m('strong', application.name), m('p.helpText', application.description), m('span.OAuthConnectStatus', application.status)]), m('.OAuthConnectActions', [application.status === 'approved' ? m('button.Button', { type: 'button', onclick: function () { self.viewApplication(application); } }, t('view_credentials', {}, 'View credentials')) : null, application.status === 'pending' ? m('button.Button.Button--danger', { type: 'button', onclick: function () { self.withdraw(application); } }, t('withdraw', {}, 'Withdraw')) : null])]);
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
        m('div', [m('strong', client.name), m('code.OAuthConnectClientId', client.client_id), m('span.OAuthConnectStatus', client.is_enabled ? t('enabled', {}, 'Enabled') : t('disabled', {}, 'Disabled'))]),
        m('.OAuthConnectActions', [
          m('button.Button', { type: 'button', onclick: function () { self.toggleClient(client); } }, client.is_enabled ? t('disable', {}, 'Disable') : t('enable', {}, 'Enable')),
          m('button.Button', { type: 'button', onclick: function () { self.resetClient(client); } }, t('reset_secret', {}, 'Reset secret')),
        ]),
      ]);
    }));
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
