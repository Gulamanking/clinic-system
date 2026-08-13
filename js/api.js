const API = (function () {
  let baseUrl = '';

  function resolveBaseUrl() {
    if (window.API_BASE_URL) {
      return new URL(window.API_BASE_URL, window.location.href).href.replace(/\/$/, '');
    }
    return new URL('backend/index.php', window.location.href).href;
  }

  function getBaseUrl() {
    if (baseUrl) return baseUrl;

    var stored = localStorage.getItem('api_base_url');
    if (stored) {
      try {
        var storedUrl = new URL(stored, window.location.href);
        if (storedUrl.origin === window.location.origin) {
          baseUrl = storedUrl.href.replace(/\/$/, '');
          return baseUrl;
        }
        localStorage.removeItem('api_base_url');
      } catch (_) {
        localStorage.removeItem('api_base_url');
      }
    }

    baseUrl = resolveBaseUrl();
    return baseUrl;
  }

  function networkErrorMessage(err) {
    if (window.location.protocol === 'file:') {
      return 'Cannot reach the server. Open the app through XAMPP at http://localhost/clinic-system/ (do not open the HTML file directly).';
    }
    if (err && err.message === 'Failed to fetch') {
      return 'Cannot reach the server. Make sure Apache and MySQL are running in XAMPP, then reload this page.';
    }
    return (err && err.message) || 'Network request failed.';
  }

  function setBaseUrl(url) {
    baseUrl = url;
    localStorage.setItem('api_base_url', url);
  }

  function getToken() {
    return localStorage.getItem('auth_token');
  }

  function setToken(token) {
    if (token) {
      localStorage.setItem('auth_token', token);
    } else {
      localStorage.removeItem('auth_token');
    }
  }

  function getSession() {
    try {
      const data = localStorage.getItem('session');
      return data ? JSON.parse(data) : null;
    } catch { return null; }
  }

  function setSession(session) {
    try {
      if (session) {
        localStorage.setItem('session', JSON.stringify(session));
      } else {
        localStorage.removeItem('session');
      }
    } catch {}
  }

  async function request(method, route, body) {
    var base = getBaseUrl();
    var sep = base.indexOf('?') !== -1 ? '&' : '?';
    var url = base + sep + 'route=' + encodeURIComponent(route);
    const headers = { 'Content-Type': 'application/json' };
    const token = getToken();
    if (token) {
      headers['Authorization'] = 'Bearer ' + token;
    }
    const opts = { method, headers };
    if (body !== undefined) {
      opts.body = JSON.stringify(body);
    }

    var res;
    try {
      res = await fetch(url, opts);
    } catch (err) {
      throw new Error(networkErrorMessage(err));
    }
    if (!res.ok) {
      var errorMsg = 'Request failed with status ' + res.status;
      try {
        var errData = await res.json();
        errorMsg = errData.message || errData.error || errorMsg;
      } catch (_) {
        try {
          var text = await res.text();
          if (text) errorMsg = text.substring(0, 200);
        } catch (_) {}
      }
      throw new Error(errorMsg);
    }
    var data = await res.json();
    return data;
  }

  function get(path) { return request('GET', path); }
  function post(path, body) { return request('POST', path, body); }
  function put(path, body) { return request('PUT', path, body); }
  function del(path) { return request('DELETE', path); }

  return {
    setBaseUrl,
    getBaseUrl,
    getToken,
    setToken,
    getSession,
    setSession,

    login(username, password) {
      return post('/login', { username, password });
    },

    getDashboard() {
      return get('/dashboard');
    },

    getReports() {
      return get('/reports');
    },

    listRecordFolders() {
      return get('/api/medicalRecords/folders');
    },

    createRecordFolder(payload) {
      if (typeof payload === 'string') payload = { name: payload };
      return post('/api/medicalRecords/folders', payload);
    },

    renameRecordFolder(payload) {
      return put('/api/medicalRecords/folders', payload);
    },
    deleteRecordFolder(name) {
      return request('DELETE', '/api/medicalRecords/folders', { name: name });
    },

    seed(table) {
      if (table) return post('/seed/' + table);
      return post('/seed');
    },

    list(table) {
      return get('/api/' + table);
    },

    getItem(table, id) {
      return get('/api/' + table + '/' + encodeURIComponent(id));
    },

    create(table, data) {
      return post('/api/' + table, data);
    },

    update(table, id, data) {
      return put('/api/' + table + '/' + encodeURIComponent(id), data);
    },

    delete(table, id) {
      return del('/api/' + table + '/' + encodeURIComponent(id));
    },
  };
})();
