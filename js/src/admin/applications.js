import app from 'flarum/admin/app';
import Page from 'flarum/common/components/Page';

(function () {
  'use strict';

  var m = typeof window !== 'undefined' ? window.m : null;
  if (!app || !m || !app.initializers) return;

  function api(path) { return app.forum.attribute('apiUrl') + '/oauth-connect' + path; }
  function t(key, params, fallback) {
    var value = app.translator.trans('lowseekai-oauth-connect.admin.' + key, params || {});
    return value === 'lowseekai-oauth-connect.admin.' + key ? fallback : value;
  }
  function errorMessage(error) {
    return error && error.errors && error.errors[0] && error.errors[0].detail
      ? error.errors[0].detail
      : (error && error.error) || t('errors.request_failed', {}, 'Request failed.');
  }
  function date(value) { if (!value) return '-'; try { return new Date(value).toLocaleString(); } catch (error) { return value; } }

  class ApplicationsPage extends Page {}

  ApplicationsPage.prototype.oninit = function (vnode) {
    Page.prototype.oninit.call(this, vnode);
    this.applications = [];
    this.loading = true;
    this.error = null;
    this.status = m.route.param('status') || 'pending';
    this.q = m.route.param('q') || '';
    this.detail = null;
    this.rejecting = null;
    this.rejectNote = '';
    this.secret = null;
    this.load();
  };

  ApplicationsPage.prototype.load = function () {
    var self = this;
    self.loading = true;
    return app.request({ method: 'GET', url: api('/admin/applications'), params: { status: self.status, q: self.q, limit: 50 } }).then(function (response) {
      self.applications = response.data || [];
      self.meta = response.meta || {};
      self.loading = false;
      m.redraw();
    }, function (error) {
      self.error = errorMessage(error);
      self.loading = false;
      m.redraw();
    });
  };

  ApplicationsPage.prototype.filters = function (event) {
    event.preventDefault();
    m.route.set(app.route('oauthConnectApplications', { status: this.status, q: this.q }));
  };

  ApplicationsPage.prototype.show = function (application) {
    var self = this;
    self.detail = null;
    return app.request({ method: 'GET', url: api('/admin/applications/' + application.id) }).then(function (response) {
      self.detail = response.data || {};
      self.approveRedirects = (self.detail.application.redirect_uris || []).join('\n');
      self.approveScopes = (self.detail.application.requested_scopes || []).join(' ');
      self.reviewNote = self.detail.application.review_note || '';
      m.redraw();
    }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  ApplicationsPage.prototype.approve = function (application, overrides) {
    var self = this;
    if (!confirm(t('applications.confirm_approve', {}, 'Approve this application and create a client?'))) return;
    app.request({ method: 'POST', url: api('/admin/applications/' + application.id + '/approve'), body: overrides || {} }).then(function (response) {
      self.secret = response.data && response.data.client ? response.data.client.client_secret : null;
      self.detail = null;
      return self.load();
    }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  ApplicationsPage.prototype.rejectForm = function (event, application) {
    event.preventDefault();
    var self = this;
    if (!self.rejectNote.trim()) return;
    app.request({ method: 'POST', url: api('/admin/applications/' + application.id + '/reject'), body: { review_note: self.rejectNote.trim() } }).then(function () {
      self.rejecting = null;
      self.rejectNote = '';
      return self.load();
    }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  ApplicationsPage.prototype.transition = function (application, action) {
    var self = this;
    var message = action === 'cancel'
      ? t('applications.confirm_cancel', {}, 'Cancel this application?')
      : t('applications.confirm_reopen', {}, 'Reopen this application for review?');
    if (!confirm(message)) return;
    app.request({ method: 'POST', url: api('/admin/applications/' + application.id + '/' + action), body: {} }).then(function () {
      self.detail = null;
      return self.load();
    }, function (error) { self.error = errorMessage(error); m.redraw(); });
  };

  ApplicationsPage.prototype.view = function () {
    var self = this;
    return m('.OAuthConnectPage.OAuthConnectApplicationsPage', m('.container', [
      m('.OAuthConnectPageTitle', [
        m('div', [m('h2', t('applications.title', {}, 'OAuth application approvals')), m('p.helpText', t('applications.description', {}, 'Review user applications before issuing OAuth credentials.'))]),
        m('.OAuthConnectActions', [m('a.Button', { href: app.route('oauthConnectAuditLogs') }, t('applications.audit_logs', {}, 'Audit log')), m('a.Button', { href: app.route('extension', { id: 'lowseekai-oauth-connect' }) }, t('actions.back_to_settings', {}, 'Back to settings'))]),
      ]),
      self.secret ? m('.OAuthConnectSecret', [m('div', [m('strong', t('secret.title', {}, 'Client secret generated')), m('p.helpText', t('secret.help', {}, 'Copy it now. It will not be shown again.'))]), m('input.FormControl', { value: self.secret, readonly: true, onclick: function (event) { event.currentTarget.select(); } }), m('button.Button', { type: 'button', onclick: function () { if (navigator.clipboard) navigator.clipboard.writeText(self.secret); } }, t('secret.copy', {}, 'Copy')), m('button.Button.Button--link', { type: 'button', onclick: function () { self.secret = null; } }, t('secret.dismiss', {}, 'Dismiss'))]) : null,
      self.error ? m('.Alert.Alert--error', self.error) : null,
      m('form.OAuthConnectFilters', { onsubmit: self.filters.bind(self) }, [
        m('label', [m('span', t('filters.status', {}, 'Status')), m('select.FormControl', { value: self.status, onchange: function (event) { self.status = event.target.value; } }, [m('option', { value: '' }, t('filters.all_statuses', {}, 'All statuses')), m('option', { value: 'pending' }, t('applications.pending', {}, 'Pending')), m('option', { value: 'approved' }, t('applications.approved', {}, 'Approved')), m('option', { value: 'rejected' }, t('applications.rejected', {}, 'Rejected')), m('option', { value: 'withdrawn' }, t('applications.withdrawn', {}, 'Withdrawn')), m('option', { value: 'cancelled' }, t('applications.cancelled', {}, 'Cancelled'))])]),
        m('label', [m('span', t('filters.search_client', {}, 'Search')), m('input.FormControl', { value: self.q, placeholder: t('applications.search_placeholder', {}, 'Application or username'), oninput: function (event) { self.q = event.target.value; } })]),
        m('.OAuthConnectFilterActions', [m('button.Button.Button--primary', { type: 'submit' }, t('actions.apply_filters', {}, 'Apply filters')), m('button.Button', { type: 'button', onclick: function () { self.status = 'pending'; self.q = ''; m.route.set(app.route('oauthConnectApplications', { status: 'pending' })); } }, t('actions.reset_filters', {}, 'Reset'))]),
      ]),
      self.loading ? m('p.OAuthConnectLoading', t('loading', {}, 'Loading...')) : self.table(),
      self.detail ? self.detailView() : null,
    ]));
  };

  ApplicationsPage.prototype.table = function () {
    var self = this;
    if (!self.applications.length) return m('p.helpText', t('applications.none', {}, 'No applications match the current filters.'));
    return m('.OAuthConnectTableWrap', m('table.OAuthConnectTable', [
      m('thead', m('tr', [m('th', '#'), m('th', t('table.client', {}, 'Application')), m('th', t('table.user', {}, 'Applicant')), m('th', t('table.status', {}, 'Status')), m('th', t('table.actions', {}, 'Actions'))])),
      m('tbody', self.applications.map(function (application) {
        var pending = application.status === 'pending';
        var rejected = application.status === 'rejected';
        return m('tr', [
          m('td', application.id),
          m('td', [m('strong', application.name), application.description ? m('div.helpText', application.description) : null]),
          m('td', application.username || ('#' + application.user_id)),
          m('td', application.status),
          m('td', [m('.OAuthConnectActions', [m('button.Button', { type: 'button', onclick: function () { self.show(application); } }, t('applications.view', {}, 'View')), pending ? m('button.Button.Button--primary', { type: 'button', onclick: function () { self.approve(application); } }, t('applications.approve', {}, 'Approve')) : null, pending ? m('button.Button.Button--danger', { type: 'button', onclick: function () { self.rejecting = application.id; self.rejectNote = ''; } }, t('applications.reject', {}, 'Reject')) : null, rejected ? m('button.Button', { type: 'button', onclick: function () { self.transition(application, 'reopen'); } }, t('applications.reopen', {}, 'Reopen')) : null, pending ? m('button.Button.Button--danger', { type: 'button', onclick: function () { self.transition(application, 'cancel'); } }, t('applications.cancel', {}, 'Cancel')) : null]), self.rejecting === application.id ? m('form.OAuthConnectRejectForm', { onsubmit: function (event) { self.rejectForm(event, application); } }, [m('textarea.FormControl', { required: true, rows: 2, value: self.rejectNote, placeholder: t('applications.reject_reason', {}, 'Enter a rejection reason.'), oninput: function (event) { self.rejectNote = event.target.value; } }), m('button.Button.Button--danger', { type: 'submit' }, t('applications.confirm_reject', {}, 'Confirm rejection')), m('button.Button', { type: 'button', onclick: function () { self.rejecting = null; } }, t('actions.cancel', {}, 'Cancel'))]) : null]),
        ]);
      })),
    ]));
  };

  ApplicationsPage.prototype.detailView = function () {
    var self = this;
    var application = self.detail.application;
    return m('.OAuthConnectPanel.OAuthConnectApplicationDetail', [
      m('.OAuthConnectPanelHeader', [m('h3', t('applications.detail', {}, 'Application details')), m('button.Button', { type: 'button', onclick: function () { self.detail = null; } }, t('actions.close', {}, 'Close'))]),
      m('.OAuthConnectDetailGrid', [m('div', [m('strong', t('table.client', {}, 'Application')), m('div', application.name)]), m('div', [m('strong', t('table.user', {}, 'Applicant')), m('div', application.username || ('#' + application.user_id))]), m('div', [m('strong', t('table.status', {}, 'Status')), m('div', application.status)]), m('div', [m('strong', t('applications.created_at', {}, 'Submitted')), m('div', date(application.created_at))]), m('div', [m('strong', t('form.homepage_url', {}, 'Homepage')), m('div', application.homepage_url || '-')]), m('div', [m('strong', t('form.redirect_uris', {}, 'Redirect URIs')), m('code.OAuthConnectClientId', (application.redirect_uris || []).join('\n'))]), m('div', [m('strong', t('applications.requested_scopes', {}, 'Requested scopes')), m('code.OAuthConnectClientId', (application.requested_scopes || []).join(' '))]), m('div', [m('strong', t('applications.review_note', {}, 'Review note')), m('div', application.review_note || '-')])]),
      m('h4', t('applications.audit_history', {}, 'Application history')),
      self.detail.audit_logs && self.detail.audit_logs.length ? m('ul.OAuthConnectAuditList', self.detail.audit_logs.map(function (log) { return m('li', [m('strong', log.action), m('span', (log.actor_username || ('#' + log.actor_user_id)) + ' / ' + date(log.created_at))]); })) : m('p.helpText', t('applications.no_history', {}, 'No history yet.')),
      application.status === 'pending' ? m('form.OAuthConnectApprovalForm', { onsubmit: function (event) { event.preventDefault(); self.approve(application, { redirect_uris: self.approveRedirects, approved_scopes: self.approveScopes, review_note: self.reviewNote }); } }, [
        m('h4', t('applications.approval_options', {}, 'Approval options')),
        m('label', [m('span', t('form.redirect_uris', {}, 'Redirect URIs')), m('textarea.FormControl', { rows: 3, value: self.approveRedirects, oninput: function (event) { self.approveRedirects = event.target.value; } })]),
        m('label', [m('span', t('applications.approved_scopes', {}, 'Approved scopes')), m('input.FormControl', { value: self.approveScopes, oninput: function (event) { self.approveScopes = event.target.value; } })]),
        m('label', [m('span', t('applications.review_note', {}, 'Review note')), m('textarea.FormControl', { rows: 2, value: self.reviewNote, oninput: function (event) { self.reviewNote = event.target.value; } })]),
        m('button.Button.Button--primary', { type: 'submit' }, t('applications.approve', {}, 'Approve')),
      ]) : null,
    ]);
  };

  class AuditLogsPage extends Page {}
  AuditLogsPage.prototype.oninit = function (vnode) { Page.prototype.oninit.call(this, vnode); this.loading = true; this.logs = []; this.error = null; this.load(); };
  AuditLogsPage.prototype.load = function () { var self = this; return app.request({ method: 'GET', url: api('/admin/audit-logs'), params: { limit: 50 } }).then(function (response) { self.logs = response.data || []; self.loading = false; m.redraw(); }, function (error) { self.error = errorMessage(error); self.loading = false; m.redraw(); }); };
  AuditLogsPage.prototype.view = function () { var self = this; return m('.OAuthConnectPage', m('.container', [m('.OAuthConnectPageTitle', [m('div', [m('h2', t('audit.title', {}, 'OAuth audit log')), m('p.helpText', t('audit.description', {}, 'Review authorization center operations.'))]), m('a.Button', { href: app.route('oauthConnectApplications') }, t('applications.back_to_applications', {}, 'Applications'))]), self.error ? m('.Alert.Alert--error', self.error) : null, self.loading ? m('p.OAuthConnectLoading', t('loading', {}, 'Loading...')) : m('.OAuthConnectTableWrap', m('table.OAuthConnectTable', [m('thead', m('tr', [m('th', t('audit.time', {}, 'Time')), m('th', t('audit.action', {}, 'Action')), m('th', t('audit.target', {}, 'Target')), m('th', t('audit.actor', {}, 'Actor'))])), m('tbody', self.logs.map(function (log) { return m('tr', [m('td', date(log.created_at)), m('td', log.action), m('td', log.target_type + ' #' + log.target_id), m('td', log.actor_username || ('#' + log.actor_user_id))]); }))]))])); };

  app.initializers.add('lowseekai/oauth-connect-applications', function () {
    app.routes.oauthConnectApplications = { path: '/oauth-connect/applications', component: ApplicationsPage };
    app.routes.oauthConnectAuditLogs = { path: '/oauth-connect/audit-logs', component: AuditLogsPage };
  });
})();
