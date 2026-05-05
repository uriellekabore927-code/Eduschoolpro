const API_BASE_URL = window.EDUSCHEDULE_API_BASE_URL || '/backend/routes/api.php/api';

const appState = {
  annees: [],
  classes: [],
  matieres: [],
  enseignants: [],
  salles: [],
  roles: [],
  utilisateurs: [],
  roleStats: [],
  signatureRules: [],
  emplois: [],
  currentCahierId: null,
  signaturePads: {},
  edit: {
    annee: null,
    classe: null,
    matiere: null,
    enseignant: null,
    salle: null,
    role: null,
    utilisateur: null,
    signatureRule: null,
  },
};

function getLocalStorageItem(key) {
  try {
    return localStorage.getItem(key);
  } catch (error) {
    return null;
  }
}

function setLocalStorageItem(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch (error) {
    // Ignorer si le navigateur bloque l'accès au stockage
  }
}

function removeLocalStorageItem(key) {
  try {
    localStorage.removeItem(key);
  } catch (error) {
    // Ignorer si le navigateur bloque l'accès au stockage
  }
}

function showErrorPopup(message = 'Erreur de communication avec le serveur.') {
  window.alert(message);
}

const apiClient = {
  token() {
    return getLocalStorageItem('eduschedule_token');
  },

  headers(extra = {}) {
    const headers = {
      'Content-Type': 'application/json',
      ...extra,
    };

    if (this.token()) {
      headers.Authorization = `Bearer ${this.token()}`;
    }

    return headers;
  },

  async request(path, options = {}) {
    try {
      const response = await fetch(`${API_BASE_URL}${path}`, {
        ...options,
        headers: this.headers(options.headers || {}),
      });

      const result = await response.json().catch(() => ({
        success: false,
        message: 'Réponse API invalide.',
        data: null,
      }));

      if (!response.ok || result.success === false) {
        const message = result.message || 'Erreur API.';
        showErrorPopup(message);
        throw new Error(message);
      }

      return result;
    } catch (error) {
      const message = error?.message || 'Erreur réseau ou serveur.';
      showErrorPopup(message);
      throw error;
    }
  },

  get(path) {
    return this.request(path, { method: 'GET' });
  },

  post(path, data) {
    return this.request(path, { method: 'POST', body: JSON.stringify(data || {}) });
  },

  put(path, data) {
    return this.request(path, { method: 'PUT', body: JSON.stringify(data || {}) });
  },

  delete(path) {
    return this.request(path, { method: 'DELETE' });
  },
};

function currentPage() {
  return document.body?.dataset?.page || '';
}

function confirmDeletion(message = 'Voulez-vous vraiment supprimer cet élément ?') {
  return window.confirm(message);
}

function ensureAuthenticated() {
  if (currentPage() && !apiClient.token()) {
    window.location.href = 'index.html';
  }
}

function showAlert(id, message, type = 'info') {
  const el = document.getElementById(id);
  if (!el) return;
  el.className = `alert alert-${type} mb-4`;
  el.classList.remove('d-none');
  const textNode = el.querySelector('.demo-alert-text');
  if (textNode) {
    textNode.textContent = message;
  } else {
    el.textContent = message;
  }
}

function setOptions(select, items, mapper, includeDefault = true) {
  if (!select) return;
  const options = [];
  if (includeDefault) {
    options.push('<option value="">Sélectionner</option>');
  }
  items.forEach((item) => {
    const mapped = mapper(item);
    options.push(`<option value="${mapped.value}">${mapped.label}</option>`);
  });
  select.innerHTML = options.join('');
}

function formatMoney(value) {
  const amount = Number(value || 0);
  return `${amount.toLocaleString('fr-FR')} FCFA`;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('fr-FR');
}

function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value).replace('T', ' ');
  return date.toLocaleString('fr-FR');
}

function getStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('eduschedule_user') || '{}');
  } catch (error) {
    return {};
  }
}

function filterTableRows(inputId, tbodyId) {
  const input = document.getElementById(inputId);
  const tbody = document.getElementById(tbodyId);
  if (!input || !tbody || input.dataset.bound === '1') return;
  input.dataset.bound = '1';
  input.addEventListener('input', () => applyTableFilter(inputId, tbodyId));
}

function applyTableFilter(inputId, tbodyId) {
  const input = document.getElementById(inputId);
  const tbody = document.getElementById(tbodyId);
  if (!input || !tbody) return;
  const query = input.value.trim().toLowerCase();
  [...tbody.querySelectorAll('tr')].forEach((row) => {
    row.classList.toggle('d-none', !!query && !row.textContent.toLowerCase().includes(query));
  });
}

function showPanel(panelId) {
  const panel = document.getElementById(panelId);
  if (!panel || !window.bootstrap?.Collapse) return;
  window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).show();
}

function hideModal(modalId) {
  const node = document.getElementById(modalId);
  if (!node || !window.bootstrap?.Modal) return;
  window.bootstrap.Modal.getInstance(node)?.hide();
}

function resolveLinkedEntityLabel(user) {
  if (!user?.id_lien) return '-';
  if (user.type_lien === 'classe') {
    return appState.classes.find((item) => Number(item.id) === Number(user.id_lien))?.libelle || `Classe #${user.id_lien}`;
  }
  if (user.type_lien === 'enseignant') {
    const teacher = appState.enseignants.find((item) => Number(item.id) === Number(user.id_lien));
    return teacher ? `${teacher.prenom} ${teacher.nom}` : `Enseignant #${user.id_lien}`;
  }
  return user.type_lien;
}

async function loadReferences() {
  const [annees, classes, matieres, enseignants, salles] = await Promise.all([
    apiClient.get('/annees'),
    apiClient.get('/classes'),
    apiClient.get('/matieres'),
    apiClient.get('/enseignants'),
    apiClient.get('/salles'),
  ]);

  appState.annees = annees.data || [];
  appState.classes = classes.data || [];
  appState.matieres = matieres.data || [];
  appState.enseignants = enseignants.data || [];
  appState.salles = salles.data || [];
}

async function loadUsers() {
  const users = await apiClient.get('/utilisateurs');
  appState.utilisateurs = users.data?.items || [];
  appState.roleStats = users.data?.stats || [];
}

async function loadSignatureRules() {
  const rules = await apiClient.get('/parametres-signatures');
  appState.signatureRules = rules.data || [];
}

async function loadRoles() {
  const roles = await apiClient.get('/roles');
  appState.roles = roles.data || [];
}

async function loadUsersAndSignatureRules() {
  await Promise.all([
    loadUsers(),
    loadSignatureRules(),
  ]);
}

function renderAnnees() {
  const tbody = document.getElementById('anneesTableBody');
  if (!tbody) return;
  tbody.innerHTML = appState.annees.map((annee) => `
    <tr>
      <td>${annee.libelle}</td>
      <td>${formatDate(annee.date_debut)}</td>
      <td>${formatDate(annee.date_fin)}</td>
      <td><span class="status-badge ${Number(annee.active) ? 'success' : 'neutral'}">${Number(annee.active) ? 'Active' : 'Inactive'}</span></td>
      <td>
        <div class="table-actions">
          <button class="action-btn" type="button" data-edit-annee="${annee.id}"><i class="ph-light ph-pencil"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  setOptions(document.getElementById('classeAnneeSelect'), appState.annees, (item) => ({
    value: item.id,
    label: item.libelle,
  }), false);
}

function renderClasses() {
  const tbody = document.getElementById('classesTableBody');
  if (!tbody) return;
  tbody.innerHTML = appState.classes.map((classe) => `
    <tr>
      <td>${classe.code}</td>
      <td>${classe.libelle}</td>
      <td>${classe.niveau}</td>
      <td>${classe.annee_libelle || '-'}</td>
      <td><span class="status-badge ${Number(classe.actif) ? 'success' : 'neutral'}">${Number(classe.actif) ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <div class="table-actions">
          <button class="action-btn" type="button" data-edit-classe="${classe.id}"><i class="ph-light ph-pencil"></i></button>
          <button class="action-btn" type="button" data-delete-classe="${classe.id}"><i class="ph-light ph-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  setOptions(document.getElementById('matiereClasseSelect'), appState.classes, (item) => ({
    value: item.id,
    label: item.libelle,
  }), false);
}

function renderMatieres() {
  const tbody = document.getElementById('matieresTableBody');
  if (!tbody) return;
  const classMap = Object.fromEntries(appState.classes.map((item) => [String(item.id), item.libelle]));
  tbody.innerHTML = appState.matieres.map((matiere) => {
    const classesAssociees = String(matiere.classes_associees || '')
      .split(',')
      .filter(Boolean)
      .map((id) => classMap[id] || id)
      .join(', ');
    return `
      <tr>
        <td>${matiere.code}</td>
        <td>${matiere.libelle}</td>
        <td>${matiere.volume_horaire_total} h</td>
        <td>${matiere.coefficient}</td>
        <td>${classesAssociees || '-'}</td>
        <td>
          <div class="table-actions">
            <button class="action-btn" type="button" data-edit-matiere="${matiere.id}"><i class="ph-light ph-pencil"></i></button>
            <button class="action-btn" type="button" data-delete-matiere="${matiere.id}"><i class="ph-light ph-trash"></i></button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function renderEnseignants() {
  const tbody = document.getElementById('enseignantsTableBody');
  if (!tbody) return;
  tbody.innerHTML = appState.enseignants.map((item) => `
    <tr>
      <td>${item.matricule}</td>
      <td>${item.prenom} ${item.nom}</td>
      <td>${item.specialite || '-'}</td>
      <td><span class="status-badge ${item.statut === 'permanent' ? 'success' : 'warning'}">${item.statut}</span></td>
      <td>${formatMoney(item.taux_horaire)}</td>
      <td>
        <div class="table-actions">
          <button class="action-btn" type="button" data-edit-enseignant="${item.id}"><i class="ph-light ph-pencil"></i></button>
          <button class="action-btn" type="button" data-delete-enseignant="${item.id}"><i class="ph-light ph-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

function renderSalles() {
  const tbody = document.getElementById('sallesTableBody');
  if (!tbody) return;
  tbody.innerHTML = appState.salles.map((item) => `
    <tr>
      <td>${item.code}</td>
      <td>${item.libelle}</td>
      <td>${item.capacite}</td>
      <td>${item.batiment || '-'}</td>
      <td>${item.equipements || '-'}</td>
      <td>
        <div class="table-actions">
          <button class="action-btn" type="button" data-edit-salle="${item.id}"><i class="ph-light ph-pencil"></i></button>
          <button class="action-btn" type="button" data-delete-salle="${item.id}"><i class="ph-light ph-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

function parsePermissions(value) {
  if (Array.isArray(value)) return value;
  try {
    return JSON.parse(value || '[]') || [];
  } catch (error) {
    return [];
  }
}

const permissionLabelMap = {
  dashboard: 'Dashboard',
  utilisateurs: 'Utilisateurs',
  parametres: 'Paramètres',
  emploi_temps: 'Emploi du temps',
  pointage: 'Pointage QR',
  cahiers: 'Cahier de texte',
  vacations: 'Vacations',
  rapports: 'Rapports',
};

function formatPermissionsText(value) {
  const permissions = parsePermissions(value);
  return permissions.map((item) => permissionLabelMap[item] || item).join(', ');
}

function collectPermissions(selector) {
  return [...document.querySelectorAll(selector)]
    .filter((input) => input.checked)
    .map((input) => input.value);
}

function setPermissionChecks(selector, permissions = []) {
  document.querySelectorAll(selector).forEach((input) => {
    input.checked = permissions.includes(input.value);
  });
}

function renderUsersTable(tbodyId, statsId = null) {
  const tbody = document.getElementById(tbodyId);
  if (tbody) {
    tbody.innerHTML = appState.utilisateurs.map((user) => {
      const permissions = formatPermissionsText(user.role_permissions_json || user.permissions_json);
      return `
        <tr>
          <td>
            <div class="fw-semibold">${user.prenom} ${user.nom}</div>
            <div class="small text-muted-soft">${user.email}</div>
          </td>
          <td>${user.role_libelle || user.role}</td>
          <td>${resolveLinkedEntityLabel(user)}</td>
          <td class="small text-muted-soft">${permissions || 'Aucun accès défini'}</td>
          <td><span class="status-badge ${Number(user.actif) ? 'success' : 'neutral'}">${Number(user.actif) ? 'Actif' : 'Inactif'}</span></td>
          <td>${formatDateTime(user.derniere_connexion)}</td>
          <td>
            <div class="table-actions">
              <button class="action-btn" type="button" data-edit-user="${user.id}"><i class="ph-light ph-pencil"></i></button>
              <button class="action-btn" type="button" data-delete-user="${user.id}"><i class="ph-light ph-trash"></i></button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  if (statsId) {
    const statsBox = document.getElementById(statsId);
    if (statsBox) {
      statsBox.innerHTML = appState.roleStats.map((item) => `
        <li><span>${item.role_libelle || item.role}</span><strong>${item.total}</strong></li>
      `).join('');
    }
  }
}

function renderRolesTable() {
  const tbody = document.getElementById('rolesTableBody');
  if (!tbody) return;
  tbody.innerHTML = appState.roles.map((role) => `
    <tr>
      <td>${role.code}</td>
      <td>${role.libelle}</td>
      <td class="small text-muted-soft">${formatPermissionsText(role.permissions_json) || 'Aucun accès'}</td>
      <td>${role.users_count || 0}</td>
      <td><span class="status-badge ${Number(role.actif) ? 'success' : 'neutral'}">${Number(role.actif) ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <div class="table-actions">
          <button class="action-btn" type="button" data-edit-role="${role.id}"><i class="ph-light ph-pencil"></i></button>
          <button class="action-btn" type="button" data-delete-role="${role.id}"><i class="ph-light ph-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

function renderSignatureRules() {
  const tbody = document.getElementById('signatureRulesBody');
  if (!tbody) return;
  tbody.innerHTML = appState.signatureRules.map((rule) => `
    <tr>
      <td>${rule.document_type}</td>
      <td>${rule.role_signataire}</td>
      <td>${rule.ordre_validation}</td>
      <td><span class="status-badge ${Number(rule.obligatoire) ? 'success' : 'neutral'}">${Number(rule.obligatoire) ? 'Oui' : 'Non'}</span></td>
      <td><span class="status-badge ${Number(rule.actif) ? 'success' : 'neutral'}">${Number(rule.actif) ? 'Actif' : 'Inactif'}</span></td>
      <td>
        <div class="table-actions">
          <button class="action-btn" type="button" data-edit-signature-rule="${rule.id}"><i class="ph-light ph-pencil"></i></button>
          <button class="action-btn" type="button" data-delete-signature-rule="${rule.id}"><i class="ph-light ph-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

function getEntityOptionsForType(typeLien) {
  const classOptions = appState.classes.map((item) => ({ value: item.id, label: item.libelle }));
  const teacherOptions = appState.enseignants.map((item) => ({ value: item.id, label: `${item.prenom} ${item.nom}` }));

  if (typeLien === 'enseignant') {
    return teacherOptions;
  }
  if (typeLien === 'classe') {
    return classOptions;
  }
  return [];
}

function updateUserEntityOptions(prefix = 'modal', typeLien = null) {
  const typeSelect = document.getElementById(prefix === 'modal' ? 'modalUserTypeLien' : 'userTypeLienSelect');
  const entitySelect = document.getElementById(prefix === 'modal' ? 'modalUserEntity' : 'userEntitySelect');
  if (!entitySelect) return;
  const selectedType = typeLien || (typeSelect?.value || 'aucun');
  const options = getEntityOptionsForType(selectedType);
  setOptions(entitySelect, options, (item) => item, true);
}

function populateEntitySelectors() {
  const userRoleSelect = document.getElementById('userRoleSelect');
  const modalUserRole = document.getElementById('modalUserRole');
  [userRoleSelect, modalUserRole].forEach((select) => {
    if (select) {
      setOptions(select, appState.roles, (item) => ({
        value: item.code,
        label: `${item.libelle}${Number(item.actif) === 1 ? '' : ' (inactif)'}`,
      }), false);
    }
  });
  updateUserEntityOptions('modal', document.getElementById('modalUserTypeLien')?.value || 'aucun');
  updateUserEntityOptions('user', document.getElementById('userTypeLienSelect')?.value || 'aucun');
}

function updateRoleAccessPreview(prefix = 'modal', roleCode = '') {
  const previewId = prefix === 'modal' ? 'modalRoleAccessPreview' : 'userRoleAccessPreview';
  const preview = document.getElementById(previewId);
  if (!preview) return;
  const role = appState.roles.find((item) => item.code === roleCode);
  preview.textContent = role
    ? (formatPermissionsText(role.permissions_json) || 'Aucun accès défini pour ce rôle.')
    : 'Sélectionnez un rôle pour afficher ses accès.';
}

function clearRoleForm() {
  appState.edit.role = null;
  const codeInput = document.getElementById('roleCodeInput');
  const libelleInput = document.getElementById('roleLibelleInput');
  const activeInput = document.getElementById('roleActiveInput');
  if (codeInput) codeInput.value = '';
  if (libelleInput) libelleInput.value = '';
  if (activeInput) activeInput.value = '1';
  setPermissionChecks('.role-permission-check', []);
}

function syncUsersRoleAvailability() {
  const alert = document.getElementById('usersRoleAlert');
  const openBtn = document.getElementById('openUserModalBtn');
  const activeRoles = appState.roles.filter((item) => Number(item.actif) === 1);
  const noRoleAvailable = activeRoles.length === 0;

  if (openBtn) {
    openBtn.disabled = noRoleAvailable;
    openBtn.title = noRoleAvailable ? "Créez d'abord un rôle actif dans Paramètres > Rôles & accès." : '';
  }

  if (!alert) return;
  if (noRoleAvailable) {
    alert.classList.remove('d-none');
    alert.textContent = "Créez d'abord un rôle actif dans Paramètres > Rôles & accès avant de créer un utilisateur.";
  } else {
    alert.classList.add('d-none');
    alert.textContent = '';
  }
}

function fillFormFromEntity(type, entity) {
  if (!entity) return;
  if (type === 'annee') {
    appState.edit.annee = entity.id;
    document.getElementById('anneeLibelleInput').value = entity.libelle;
    document.getElementById('anneeDebutInput').value = entity.date_debut;
    document.getElementById('anneeFinInput').value = entity.date_fin;
    document.getElementById('anneeActiveInput').value = Number(entity.active);
  }
  if (type === 'classe') {
    appState.edit.classe = entity.id;
    document.getElementById('classeCodeInput').value = entity.code;
    document.getElementById('classeLibelleInput').value = entity.libelle;
    document.getElementById('classeNiveauInput').value = entity.niveau;
    document.getElementById('classeAnneeSelect').value = entity.id_annee_academique;
  }
  if (type === 'matiere') {
    appState.edit.matiere = entity.id;
    document.getElementById('matiereCodeInput').value = entity.code;
    document.getElementById('matiereLibelleInput').value = entity.libelle;
    document.getElementById('matiereVolumeInput').value = entity.volume_horaire_total;
    document.getElementById('matiereCoeffInput').value = entity.coefficient;
    const firstClass = String(entity.classes_associees || '').split(',').filter(Boolean)[0] || '';
    document.getElementById('matiereClasseSelect').value = firstClass;
  }
  if (type === 'enseignant') {
    appState.edit.enseignant = entity.id;
    document.getElementById('enseignantMatriculeInput').value = entity.matricule;
    document.getElementById('enseignantNomInput').value = entity.nom;
    document.getElementById('enseignantPrenomInput').value = entity.prenom;
    document.getElementById('enseignantEmailInput').value = entity.email || '';
    document.getElementById('enseignantTelephoneInput').value = entity.telephone || '';
    document.getElementById('enseignantSpecialiteInput').value = entity.specialite || '';
    document.getElementById('enseignantStatutInput').value = entity.statut;
    document.getElementById('enseignantTauxInput').value = entity.taux_horaire;
  }
  if (type === 'salle') {
    appState.edit.salle = entity.id;
    document.getElementById('salleCodeInput').value = entity.code;
    document.getElementById('salleLibelleInput').value = entity.libelle;
    document.getElementById('salleCapaciteInput').value = entity.capacite;
    document.getElementById('salleBatimentInput').value = entity.batiment || '';
    document.getElementById('salleEquipementsInput').value = entity.equipements || '';
  }
  if (type === 'utilisateur') {
    appState.edit.utilisateur = entity.id;
    const fieldPrefix = entity._targetPrefix || 'user';
    const nomId = fieldPrefix === 'modal' ? 'modalUserNom' : 'userNomInput';
    const prenomId = fieldPrefix === 'modal' ? 'modalUserPrenom' : 'userPrenomInput';
    const emailId = fieldPrefix === 'modal' ? 'modalUserEmail' : 'userEmailInput';
    const roleId = fieldPrefix === 'modal' ? 'modalUserRole' : 'userRoleSelect';
    const typeLienId = fieldPrefix === 'modal' ? 'modalUserTypeLien' : 'userTypeLienSelect';
    const entityId = fieldPrefix === 'modal' ? 'modalUserEntity' : 'userEntitySelect';
    document.getElementById(nomId).value = entity.nom;
    document.getElementById(prenomId).value = entity.prenom;
    document.getElementById(emailId).value = entity.email;
    document.getElementById(roleId).value = entity.role;
    document.getElementById(typeLienId).value = entity.type_lien || 'aucun';
    updateUserEntityOptions(fieldPrefix === 'modal' ? 'modal' : 'user', entity.type_lien || 'aucun');
    document.getElementById(entityId).value = entity.id_lien || '';
    updateRoleAccessPreview(fieldPrefix === 'modal' ? 'modal' : 'user', entity.role);
  }
  if (type === 'role') {
    appState.edit.role = entity.id;
    document.getElementById('roleCodeInput').value = entity.code;
    document.getElementById('roleLibelleInput').value = entity.libelle;
    document.getElementById('roleActiveInput').value = Number(entity.actif);
    setPermissionChecks('.role-permission-check', parsePermissions(entity.permissions_json));
  }
  if (type === 'signatureRule') {
    appState.edit.signatureRule = entity.id;
    document.getElementById('signatureDocumentSelect').value = entity.document_type;
    document.getElementById('signatureRoleSelect').value = entity.role_signataire;
    document.getElementById('signatureOrderInput').value = entity.ordre_validation;
    document.getElementById('signatureRequiredInput').value = Number(entity.obligatoire);
    document.getElementById('signatureActiveInput').value = Number(entity.actif);
  }
}

function resetEditState(type) {
  appState.edit[type] = null;
}

async function refreshSettings() {
  await loadReferences();
  await loadUsersAndSignatureRules();
  await loadRoles();
  renderAnnees();
  renderClasses();
  renderMatieres();
  renderEnseignants();
  renderSalles();
  renderRolesTable();
  renderSignatureRules();
  populateEntitySelectors();
  applyTableFilter('anneesFilterInput', 'anneesTableBody');
  applyTableFilter('classesFilterInput', 'classesTableBody');
  applyTableFilter('matieresFilterInput', 'matieresTableBody');
  applyTableFilter('enseignantsFilterInput', 'enseignantsTableBody');
  applyTableFilter('sallesFilterInput', 'sallesTableBody');
  applyTableFilter('rolesFilterInput', 'rolesTableBody');
  applyTableFilter('signatureRulesFilterInput', 'signatureRulesBody');
}

function bindSettingsActions() {
  document.getElementById('openRoleFormBtn')?.addEventListener('click', () => {
    clearRoleForm();
  });

  const saveAnneeBtn = document.getElementById('saveAnneeBtn');
  if (saveAnneeBtn) {
    saveAnneeBtn.addEventListener('click', async () => {
      const payload = {
        libelle: document.getElementById('anneeLibelleInput').value,
        date_debut: document.getElementById('anneeDebutInput').value,
        date_fin: document.getElementById('anneeFinInput').value,
        active: Number(document.getElementById('anneeActiveInput').value),
      };
      if (appState.edit.annee) {
        await apiClient.put(`/annees/${appState.edit.annee}`, payload);
      } else {
        await apiClient.post('/annees', payload);
      }
      resetEditState('annee');
      await refreshSettings();
    });
  }

  const saveClasseBtn = document.getElementById('saveClasseBtn');
  if (saveClasseBtn) {
    saveClasseBtn.addEventListener('click', async () => {
      const payload = {
        code: document.getElementById('classeCodeInput').value,
        libelle: document.getElementById('classeLibelleInput').value,
        niveau: document.getElementById('classeNiveauInput').value,
        id_annee_academique: Number(document.getElementById('classeAnneeSelect').value),
        actif: 1,
      };
      if (appState.edit.classe) {
        await apiClient.put(`/classes/${appState.edit.classe}`, payload);
      } else {
        await apiClient.post('/classes', payload);
      }
      resetEditState('classe');
      await refreshSettings();
    });
  }

  const saveMatiereBtn = document.getElementById('saveMatiereBtn');
  if (saveMatiereBtn) {
    saveMatiereBtn.addEventListener('click', async () => {
      const payload = {
        code: document.getElementById('matiereCodeInput').value,
        libelle: document.getElementById('matiereLibelleInput').value,
        volume_horaire_total: Number(document.getElementById('matiereVolumeInput').value),
        coefficient: Number(document.getElementById('matiereCoeffInput').value),
        actif: 1,
        classes: [Number(document.getElementById('matiereClasseSelect').value)],
      };
      if (appState.edit.matiere) {
        await apiClient.put(`/matieres/${appState.edit.matiere}`, payload);
      } else {
        await apiClient.post('/matieres', payload);
      }
      resetEditState('matiere');
      await refreshSettings();
    });
  }

  const saveEnseignantBtn = document.getElementById('saveEnseignantBtn');
  if (saveEnseignantBtn) {
    saveEnseignantBtn.addEventListener('click', async () => {
      const payload = {
        matricule: document.getElementById('enseignantMatriculeInput').value,
        nom: document.getElementById('enseignantNomInput').value,
        prenom: document.getElementById('enseignantPrenomInput').value,
        email: document.getElementById('enseignantEmailInput').value,
        telephone: document.getElementById('enseignantTelephoneInput').value,
        specialite: document.getElementById('enseignantSpecialiteInput').value,
        statut: document.getElementById('enseignantStatutInput').value,
        taux_horaire: Number(document.getElementById('enseignantTauxInput').value),
        actif: 1,
      };
      if (appState.edit.enseignant) {
        await apiClient.put(`/enseignants/${appState.edit.enseignant}`, payload);
      } else {
        await apiClient.post('/enseignants', payload);
      }
      resetEditState('enseignant');
      await refreshSettings();
    });
  }

  const saveSalleBtn = document.getElementById('saveSalleBtn');
  if (saveSalleBtn) {
    saveSalleBtn.addEventListener('click', async () => {
      const payload = {
        code: document.getElementById('salleCodeInput').value,
        libelle: document.getElementById('salleLibelleInput').value,
        capacite: Number(document.getElementById('salleCapaciteInput').value),
        batiment: document.getElementById('salleBatimentInput').value,
        equipements: document.getElementById('salleEquipementsInput').value,
        actif: 1,
      };
      if (appState.edit.salle) {
        await apiClient.put(`/salles/${appState.edit.salle}`, payload);
      } else {
        await apiClient.post('/salles', payload);
      }
      resetEditState('salle');
      await refreshSettings();
    });
  }

  const saveRoleBtn = document.getElementById('saveRoleBtn');
  if (saveRoleBtn) {
    saveRoleBtn.addEventListener('click', async () => {
      try {
        const payload = {
          code: document.getElementById('roleCodeInput').value.trim(),
          libelle: document.getElementById('roleLibelleInput').value.trim(),
          permissions: collectPermissions('.role-permission-check'),
          actif: Number(document.getElementById('roleActiveInput').value),
        };
        if (appState.edit.role) {
          await apiClient.put(`/roles/${appState.edit.role}`, payload);
        } else {
          await apiClient.post('/roles', payload);
        }
        clearRoleForm();
        await refreshSettings();
      } catch (error) {
        window.alert(error.message);
      }
    });
  }

  const saveSignatureRuleBtn = document.getElementById('saveSignatureRuleBtn');
  if (saveSignatureRuleBtn) {
    saveSignatureRuleBtn.addEventListener('click', async () => {
      const payload = {
        document_type: document.getElementById('signatureDocumentSelect').value,
        role_signataire: document.getElementById('signatureRoleSelect').value,
        ordre_validation: Number(document.getElementById('signatureOrderInput').value),
        obligatoire: Number(document.getElementById('signatureRequiredInput').value),
        actif: Number(document.getElementById('signatureActiveInput').value),
      };
      if (appState.edit.signatureRule) {
        await apiClient.put(`/parametres-signatures/${appState.edit.signatureRule}`, payload);
      } else {
        await apiClient.post('/parametres-signatures', payload);
      }
      resetEditState('signatureRule');
      await refreshSettings();
    });
  }

  document.addEventListener('click', async (event) => {
    const editAnnee = event.target.closest('[data-edit-annee]');
    if (editAnnee) {
      fillFormFromEntity('annee', appState.annees.find((item) => Number(item.id) === Number(editAnnee.dataset.editAnnee)));
      showPanel('anneeFormPanel');
    }
    const editClasse = event.target.closest('[data-edit-classe]');
    if (editClasse) {
      fillFormFromEntity('classe', appState.classes.find((item) => Number(item.id) === Number(editClasse.dataset.editClasse)));
      showPanel('classeFormPanel');
    }
    const deleteClasse = event.target.closest('[data-delete-classe]');
    if (deleteClasse) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer cette classe ?')) {
        return;
      }
      await apiClient.delete(`/classes/${deleteClasse.dataset.deleteClasse}`);
      await refreshSettings();
    }
    const editMatiere = event.target.closest('[data-edit-matiere]');
    if (editMatiere) {
      fillFormFromEntity('matiere', appState.matieres.find((item) => Number(item.id) === Number(editMatiere.dataset.editMatiere)));
      showPanel('matiereFormPanel');
    }
    const deleteMatiere = event.target.closest('[data-delete-matiere]');
    if (deleteMatiere) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer cette matière ?')) {
        return;
      }
      await apiClient.delete(`/matieres/${deleteMatiere.dataset.deleteMatiere}`);
      await refreshSettings();
    }
    const editEnseignant = event.target.closest('[data-edit-enseignant]');
    if (editEnseignant) {
      fillFormFromEntity('enseignant', appState.enseignants.find((item) => Number(item.id) === Number(editEnseignant.dataset.editEnseignant)));
      showPanel('enseignantFormPanel');
    }
    const deleteEnseignant = event.target.closest('[data-delete-enseignant]');
    if (deleteEnseignant) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer cet enseignant ?')) {
        return;
      }
      await apiClient.delete(`/enseignants/${deleteEnseignant.dataset.deleteEnseignant}`);
      await refreshSettings();
    }
    const editSalle = event.target.closest('[data-edit-salle]');
    if (editSalle) {
      fillFormFromEntity('salle', appState.salles.find((item) => Number(item.id) === Number(editSalle.dataset.editSalle)));
      showPanel('salleFormPanel');
    }
    const deleteSalle = event.target.closest('[data-delete-salle]');
    if (deleteSalle) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer cette salle ?')) {
        return;
      }
      await apiClient.delete(`/salles/${deleteSalle.dataset.deleteSalle}`);
      await refreshSettings();
    }
    const editRole = event.target.closest('[data-edit-role]');
    if (editRole) {
      fillFormFromEntity('role', appState.roles.find((item) => Number(item.id) === Number(editRole.dataset.editRole)));
      showPanel('roleFormPanel');
    }
    const deleteRole = event.target.closest('[data-delete-role]');
    if (deleteRole) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer ce rôle ?')) {
        return;
      }
      try {
        await apiClient.delete(`/roles/${deleteRole.dataset.deleteRole}`);
        if (Number(appState.edit.role) === Number(deleteRole.dataset.deleteRole)) {
          clearRoleForm();
        }
        await refreshSettings();
      } catch (error) {
        window.alert(error.message);
      }
    }
    const editSignatureRule = event.target.closest('[data-edit-signature-rule]');
    if (editSignatureRule) {
      fillFormFromEntity('signatureRule', appState.signatureRules.find((item) => Number(item.id) === Number(editSignatureRule.dataset.editSignatureRule)));
      showPanel('signatureFormPanel');
    }
    const deleteSignatureRule = event.target.closest('[data-delete-signature-rule]');
    if (deleteSignatureRule) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer cette règle de signature ?')) {
        return;
      }
      await apiClient.delete(`/parametres-signatures/${deleteSignatureRule.dataset.deleteSignatureRule}`);
      await refreshSettings();
    }
  });
}

async function initSettingsPage() {
  [
    ['anneesFilterInput', 'anneesTableBody'],
    ['classesFilterInput', 'classesTableBody'],
    ['matieresFilterInput', 'matieresTableBody'],
    ['enseignantsFilterInput', 'enseignantsTableBody'],
    ['sallesFilterInput', 'sallesTableBody'],
    ['rolesFilterInput', 'rolesTableBody'],
    ['signatureRulesFilterInput', 'signatureRulesBody'],
  ].forEach(([inputId, tbodyId]) => filterTableRows(inputId, tbodyId));
  await refreshSettings();
  bindSettingsActions();
}

async function initUsersPage() {
  await loadReferences();
  await loadUsersAndSignatureRules();
  await loadRoles();
  renderUsersTable('utilisateursPageBody');
  populateEntitySelectors();
  syncUsersRoleAvailability();
  filterTableRows('usersFilterInput', 'utilisateursPageBody');
  applyTableFilter('usersFilterInput', 'utilisateursPageBody');

  document.getElementById('openUserModalBtn')?.addEventListener('click', () => {
    if (!appState.roles.some((item) => Number(item.actif) === 1)) {
      showAlert('usersRoleAlert', "Créez d'abord un rôle actif avant de créer un utilisateur.", 'warning');
      return;
    }
    appState.edit.utilisateur = null;
    document.getElementById('modalUserNom').value = '';
    document.getElementById('modalUserPrenom').value = '';
    document.getElementById('modalUserEmail').value = '';
    document.getElementById('modalUserPassword').value = 'Campus@2026';
    document.getElementById('modalUserRole').value = appState.roles[0]?.code || '';
    document.getElementById('modalUserTypeLien').value = 'aucun';
    updateUserEntityOptions('modal', 'aucun');
    document.getElementById('modalUserEntity').value = '';
    updateRoleAccessPreview('modal', document.getElementById('modalUserRole').value);
    const title = document.querySelector('#userModal .modal-title');
    if (title) title.textContent = 'Créer un utilisateur';
    const saveBtn = document.getElementById('saveUserFromPageBtn');
    if (saveBtn) saveBtn.textContent = 'Créer le compte';
  });

  document.getElementById('modalUserRole')?.addEventListener('change', (event) => {
    updateRoleAccessPreview('modal', event.target.value);
  });

  document.getElementById('modalUserTypeLien')?.addEventListener('change', () => {
    updateUserEntityOptions('modal');
  });

  document.getElementById('saveUserFromPageBtn')?.addEventListener('click', async () => {
    if (!document.getElementById('modalUserRole').value) {
      showAlert('usersRoleAlert', "Sélectionnez un rôle avant d'enregistrer l'utilisateur.", 'warning');
      return;
    }
    try {
      const payload = {
        nom: document.getElementById('modalUserNom').value,
        prenom: document.getElementById('modalUserPrenom').value,
        email: document.getElementById('modalUserEmail').value,
        mot_de_passe: document.getElementById('modalUserPassword').value,
        role: document.getElementById('modalUserRole').value,
        type_lien: document.getElementById('modalUserTypeLien').value,
        id_lien: Number(document.getElementById('modalUserEntity').value) || null,
        actif: 1,
      };
      if (appState.edit.utilisateur) {
        await apiClient.put(`/utilisateurs/${appState.edit.utilisateur}`, payload);
      } else {
        await apiClient.post('/utilisateurs', payload);
      }
      resetEditState('utilisateur');
      await loadUsersAndSignatureRules();
      await loadRoles();
      renderUsersTable('utilisateursPageBody');
      syncUsersRoleAvailability();
      applyTableFilter('usersFilterInput', 'utilisateursPageBody');
      hideModal('userModal');
      showAlert('usersRoleAlert', 'Utilisateur enregistré avec succès.', 'success');
    } catch (error) {
      showAlert('usersRoleAlert', error.message, 'danger');
    }
  });

  document.addEventListener('click', async (event) => {
    const editUser = event.target.closest('[data-edit-user]');
    if (editUser && document.getElementById('modalUserNom')) {
      const user = appState.utilisateurs.find((item) => Number(item.id) === Number(editUser.dataset.editUser));
      if (user) {
        user._targetPrefix = 'modal';
        fillFormFromEntity('utilisateur', user);
        const title = document.querySelector('#userModal .modal-title');
        if (title) title.textContent = 'Modifier un utilisateur';
        const saveBtn = document.getElementById('saveUserFromPageBtn');
        if (saveBtn) saveBtn.textContent = 'Enregistrer les modifications';
        updateRoleAccessPreview('modal', user.role);
        window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
      }
    }
    const deleteUser = event.target.closest('[data-delete-user]');
    if (deleteUser && document.getElementById('utilisateursPageBody')) {
      if (!confirmDeletion('Voulez-vous vraiment supprimer cet utilisateur ?')) {
        return;
      }
      await apiClient.delete(`/utilisateurs/${deleteUser.dataset.deleteUser}`);
      await loadUsersAndSignatureRules();
      await loadRoles();
      renderUsersTable('utilisateursPageBody');
      syncUsersRoleAvailability();
      applyTableFilter('usersFilterInput', 'utilisateursPageBody');
    }
  });
}

function formatDashboardValue(item) {
  if (item?.format === 'money') return formatMoney(item.value || 0);
  return item?.value ?? 0;
}

function renderDashboardSections(sections = []) {
  const container = document.getElementById('dashboardSections');
  if (!container) return;
  container.innerHTML = sections.map((section) => {
    if (section.type === 'comparison') {
      const maxValue = Math.max(1, ...section.items.map((item) => Math.max(Number(item.planned || 0), Number(item.realized || 0))));
      return `
        <div class="dashboard-section-card">
          <div class="section-header">
            <div>
              <h2>${section.title}</h2>
              <p>${section.subtitle || ''}</p>
            </div>
          </div>
          <div class="comparison-list">
            ${section.items.map((item) => `
              <div class="comparison-item">
                <div class="comparison-head">
                  <div>
                    <strong>${item.label}</strong>
                    <div class="comparison-meta">Planifié ${item.planned} h · Réalisé ${item.realized} h</div>
                  </div>
                </div>
                <div class="comparison-bars">
                  <div class="bar-track"><div class="bar-fill" style="width:${Math.min((Number(item.planned || 0) / maxValue) * 100, 100)}%"></div></div>
                  <div class="bar-track"><div class="bar-fill success" style="width:${Math.min((Number(item.realized || 0) / maxValue) * 100, 100)}%"></div></div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }

    if (section.type === 'progress') {
      const maxValue = Math.max(1, ...section.items.map((item) => Number(item.value || 0)));
      return `
        <div class="dashboard-section-card">
          <div class="section-header">
            <div>
              <h2>${section.title}</h2>
              <p>${section.subtitle || ''}</p>
            </div>
          </div>
          <div class="progress-list">
            ${section.items.map((item) => `
              <div class="progress-item">
                <div class="progress-head">
                  <strong>${item.label}</strong>
                  <span>${formatDashboardValue(item)}</span>
                </div>
                <div class="progress-line"><span style="width:${Math.min((Number(item.value || 0) / maxValue) * 100, 100)}%"></span></div>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }

    return `
      <div class="dashboard-section-card">
        <div class="section-header">
          <div>
            <h2>${section.title}</h2>
            <p>${section.subtitle || ''}</p>
          </div>
        </div>
        <div class="plain-list">
          ${(section.items || []).map((item) => `
            <div class="plain-list-item">
              <div class="plain-list-head">
                <strong>${item.label}</strong>
                ${item.status ? `<span class="status-badge ${item.status === 'cloture' || item.status === 'validee' ? 'success' : (item.status === 'a_faire' || item.status === 'generee' ? 'warning' : 'neutral')}">${item.status}</span>` : ''}
              </div>
              <div class="plain-list-detail">${item.detail || ''}</div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }).join('');
}

function renderDashboardAlerts(alerts = []) {
  const container = document.getElementById('dashboardAlerts');
  if (!container) return;
  container.innerHTML = alerts.length ? alerts.map((item) => `
    <div class="alert-item ${item.tone || 'neutral'}">
      <div class="alert-head"><strong>${item.label}</strong></div>
      <div class="alert-detail">${item.detail || ''}</div>
    </div>
  `).join('') : '<div class="text-muted-soft">Aucune alerte pour le moment.</div>';
}

function renderDashboardQuickActions(actions = []) {
  const container = document.getElementById('dashboardQuickActions');
  if (!container) return;
  container.innerHTML = actions.map((item) => `
    <a class="btn btn-${item.variant || 'outline-primary'}" href="${item.href || '#'}">${item.label}</a>
  `).join('');
}

function dashboardToneClass(tone) {
  if (tone === 'danger') return 'danger';
  if (tone === 'warning') return 'warning';
  if (tone === 'success') return 'success';
  return 'primary';
}

function dashboardKpiIcon(label = '') {
  const text = String(label).toLowerCase();
  if (text.includes('séance')) return 'ph-calendar';
  if (text.includes('présence') || text.includes('present')) return 'ph-user-check';
  if (text.includes('retard')) return 'ph-clock-clockwise';
  if (text.includes('absence') || text.includes('absent')) return 'ph-user-minus';
  if (text.includes('cahier')) return 'ph-notebook';
  if (text.includes('vacation') || text.includes('paiement')) return 'ph-coins';
  if (text.includes('heure')) return 'ph-hourglass-medium';
  if (text.includes('cours')) return 'ph-book-open';
  if (text.includes('classe')) return 'ph-users';
  if (text.includes('enseignant')) return 'ph-user-gear';
  return 'ph-chart-line';
}

function dashboardKpiColor(label = '', tone = '') {
  const t = String(tone).toLowerCase();
  const l = String(label).toLowerCase();
  if (t === 'danger'  || l.includes('absent') || l.includes('retard')) return 'kpi-red';
  if (t === 'warning' || l.includes('en attente'))                       return 'kpi-amber';
  if (t === 'success' || l.includes('valid') || l.includes('réalis'))    return 'kpi-green';
  if (l.includes('séance') || l.includes('cours'))  return 'kpi-blue';
  if (l.includes('heure'))                          return 'kpi-cyan';
  if (l.includes('vacation') || l.includes('paiement')) return 'kpi-purple';
  if (l.includes('présence'))                       return 'kpi-green';
  return 'kpi-navy';
}

function renderAdminDashboard(data) {
  const titleNode = document.getElementById('dashboardRoleTitle');
  const subtitleNode = document.getElementById('dashboardRoleSubtitle');
  if (titleNode) titleNode.textContent = 'Tableau de bord';
  if (subtitleNode) subtitleNode.textContent = data.headline?.subtitle || "Vue d'ensemble des activités de l'établissement.";

  const kpiBox = document.getElementById('dashboardKpis');
  if (kpiBox) {
    kpiBox.innerHTML = (data.kpis || []).map((item) => `
      <div class="kpi-card ${dashboardKpiColor(item.label, item.tone)}">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
          <div class="kpi-card-icon">
            <i class="ph-light ${dashboardKpiIcon(item.label)}" style="font-size:1.15rem;"></i>
          </div>
        </div>
        <div class="kpi-card-value">${formatDashboardValue(item)}</div>
        <div class="kpi-card-label">${item.label}</div>
        ${item.hint ? `<div class="kpi-card-delta"><i class="ph-light ph-arrow-up"></i>${item.hint}</div>` : ''}
      </div>
    `).join('');
  }

  const priorityBox = document.getElementById('dashboardPriorityActions');
  if (priorityBox) {
    const actions = data.priority_actions || [];
    if (actions.length === 0) {
      priorityBox.innerHTML = `<div class="text-center py-4" style="color:#6b7280;font-size:.88rem;"><i class="ph-light ph-check-circle d-block mb-2" style="font-size:1.8rem;color:#10b981;opacity:.7;"></i>Aucune action prioritaire</div>`;
    } else {
      priorityBox.innerHTML = actions.map((item) => {
        const iconMap = { danger: 'ph-warning', warning: 'ph-warning-circle', success: 'ph-check-circle', primary: 'ph-info' };
        const colorMap = { danger: '#ef4444', warning: '#f59e0b', success: '#10b981', primary: '#3b82f6' };
        const tone = dashboardToneClass(item.tone);
        return `
          <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;">
            <i class="ph-light ${iconMap[tone] || 'ph-info'}" style="color:${colorMap[tone] || '#3b82f6'};font-size:1rem;margin-top:2px;flex-shrink:0;"></i>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.88rem;font-weight:600;color:#1e3a5f;margin-bottom:2px;">${item.label}</div>
              ${item.detail ? `<div style="font-size:.8rem;color:#6b7280;">${item.detail}</div>` : ''}
            </div>
          </div>
        `;
      }).join('');
    }
  }

  window.EduScheduleProShell?.setNotifications((data.priority_actions || []).map((item) => ({
    ...item,
    href: item.href || 'rapports.html',
  })));

  const comparison = (data.sections || []).find((section) => section.type === 'comparison');
  const hoursChart = document.getElementById('dashboardHoursChart');
  if (hoursChart && comparison) {
    const items = comparison.items || [];
    const maxValue = Math.max(1, ...items.map((item) => Math.max(Number(item.planned || 0), Number(item.realized || 0))));
    const allZero = items.every((item) => !Number(item.planned) && !Number(item.realized));

    if (allZero) {
      hoursChart.innerHTML = '<div class="hours-chart-empty">Aucune donnée pour la semaine en cours</div>';
    } else {
      hoursChart.innerHTML = `
        <div class="hours-chart">${items.map((item) => `
          <div class="hours-chart-col">
            <div class="hours-chart-bars">
              <span class="hours-bar" style="height:${Math.max(4, (Number(item.planned || 0) / maxValue) * 138)}px" title="Planifiées : ${item.planned || 0}h"></span>
              <span class="hours-bar realized" style="height:${Math.max(4, (Number(item.realized || 0) / maxValue) * 138)}px" title="Réalisées : ${item.realized || 0}h"></span>
            </div>
          </div>
        `).join('')}</div>
        <div class="hours-chart-labels">${items.map((item) => `
          <div class="hours-chart-label">${item.label}</div>
        `).join('')}</div>
      `;
    }

    const legendWrap = document.getElementById('dashboardHoursLegend');
    if (legendWrap) {
      legendWrap.innerHTML = `
        <div class="hours-chart-legend-item"><span class="hours-chart-legend-dot" style="background:#bfdbfe"></span>Planifiées</div>
        <div class="hours-chart-legend-item"><span class="hours-chart-legend-dot" style="background:#34d399"></span>Réalisées</div>
      `;
    }
  }

  const programValueNode = document.getElementById('dashboardProgramDonutValue');
  if (programValueNode) programValueNode.textContent = `${data.program_average || 0}%`;
  const programSummary = document.getElementById('dashboardProgramSummary');
  if (programSummary) {
    programSummary.innerHTML = (data.program_summary || []).map((item) => `
      <div class="donut-legend-item">
        <div class="donut-legend-main">
          <span class="donut-dot" style="background:${item.color || '#5b9cf6'}"></span>
          <span>${item.label}</span>
        </div>
        <strong>${item.value}</strong>
      </div>
    `).join('');
  }

  const delayItems = data.delay_distribution || [];
  const delayTotal = delayItems.reduce((sum, item) => sum + Number(item.value || 0), 0);
  const delayValueNode = document.getElementById('dashboardDelayDonutValue');
  if (delayValueNode) delayValueNode.textContent = `${delayTotal}`;
  const delaySummary = document.getElementById('dashboardDelaySummary');
  const colors = ['#2563eb', '#33c1b9', '#fbbf24', '#ef4444'];
  if (delaySummary) {
    delaySummary.innerHTML = delayItems.map((item, index) => `
      <div class="donut-legend-item">
        <div class="donut-legend-main">
          <span class="donut-dot" style="background:${colors[index] || '#cbd5e1'}"></span>
          <span>${item.label}</span>
        </div>
        <strong>${item.value}</strong>
      </div>
    `).join('');
  }

  renderDashboardQuickActions(data.quick_actions || []);
}

async function initDashboardPage() {
  const result = await apiClient.get('/dashboard/stats');
  const data = result.data || {};
  const user = getStoredUser();
  if (user.role === 'administrateur') {
    renderAdminDashboard(data);
    return;
  }

  window.EduScheduleProShell?.setNotifications((data.priority_actions || data.alerts || []).map((item) => ({
    ...item,
    href: item.href || 'rapports.html',
  })));

  const titleNode = document.getElementById('dashboardRoleTitle');
  const subtitleNode = document.getElementById('dashboardRoleSubtitle');
  if (titleNode) titleNode.textContent = data.headline?.title || 'Dashboard';
  if (subtitleNode) subtitleNode.textContent = data.headline?.subtitle || 'Vue adaptée au rôle connecté.';
  renderAdminDashboard(data);
}

function getJourOrder() {
  return ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
}

function getWeekDateLabel(weekStart, offset) {
  const date = new Date(`${weekStart}T00:00:00`);
  date.setDate(date.getDate() + offset);
  return date.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: 'short' });
}

function capitalizeFirst(text = '') {
  if (!text) return '';
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function getWeekRangeTitle(weekStart) {
  const startDate = new Date(`${weekStart}T00:00:00`);
  const endDate = new Date(`${weekStart}T00:00:00`);
  endDate.setDate(endDate.getDate() + 5);
  const startDay = String(startDate.getDate()).padStart(2, '0');
  const endDay = String(endDate.getDate()).padStart(2, '0');
  const month = endDate.toLocaleDateString('fr-FR', { month: 'long' }).toUpperCase();
  return `EMPLOI DU TEMPS DU ${startDay} AU ${endDay} ${month} ${endDate.getFullYear()}`;
}

function formatPlanningDate(dateValue, jour, fallbackWeekStart) {
  if (dateValue) {
    const date = new Date(`${dateValue}T00:00:00`);
    return capitalizeFirst(date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
  }
  if (!fallbackWeekStart || !jour) return '';
  return capitalizeFirst(getWeekDateLabel(fallbackWeekStart, getJourOrder().indexOf(jour)).replace('.', ''));
}

function getSelectedClasseMeta() {
  const classeId = Number(document.getElementById('emploiClasseSelect')?.value || 0);
  return appState.classes.find((item) => Number(item.id) === classeId) || null;
}

function isEvaluationCreneau(creneau) {
  const label = `${creneau?.matiere_libelle || ''}`.toUpperCase();
  return creneau?.type_seance === 'devoir' || label.startsWith('DS') || label.includes('EXAM');
}

function timeToMinutes(time = '') {
  const [hours = 0, minutes = 0] = `${time}`.split(':').map(Number);
  return (hours * 60) + minutes;
}

function slotOverlapsCreneau(slot, creneau) {
  const slotStart = timeToMinutes(slot.start);
  const slotEnd = timeToMinutes(slot.end);
  const creneauStart = timeToMinutes(creneau?.heure_debut);
  const creneauEnd = timeToMinutes(creneau?.heure_fin);
  return creneauStart < slotEnd && creneauEnd > slotStart;
}

function buildDaySlotCoverage(creneaux = [], slots = []) {
  const coverage = Array(slots.length).fill(null);
  const sorted = [...creneaux].sort((a, b) => timeToMinutes(a.heure_debut) - timeToMinutes(b.heure_debut));

  sorted.forEach((creneau) => {
    const coveredSlots = slots
      .map((slot, index) => (slotOverlapsCreneau(slot, creneau) ? index : -1))
      .filter((index) => index >= 0);

    if (!coveredSlots.length) {
      return;
    }

    const startIndex = coveredSlots[0];
    coverage[startIndex] = { type: 'start', span: coveredSlots.length, creneau };
    coveredSlots.slice(1).forEach((index) => {
      coverage[index] = { type: 'skip', creneau };
    });
  });

  return coverage;
}

function getEmploiSlots() {
  return [
    { label: ['07h30', 'à', '09h30'], start: '07:30:00', end: '09:30:00' },
    { label: ['10h00', 'à', '12h15'], start: '10:00:00', end: '12:15:00' },
    { label: ['14h00', 'à', '17h00'], start: '14:00:00', end: '17:00:00' },
  ];
}

function findCreneauForSlot(creneaux, jour, slot) {
  return (creneaux || []).find((item) => item.jour === jour && item.heure_debut >= slot.start && item.heure_debut <= slot.end);
}

function getFilteredEmploiCreneaux(creneaux = []) {
  const teacherId = document.getElementById('emploiTeacherSelect')?.value || '';
  const roomId = document.getElementById('emploiRoomSelect')?.value || '';
  return creneaux.filter((item) => {
    if (teacherId && Number(item.id_enseignant) !== Number(teacherId)) return false;
    if (roomId && Number(item.id_salle) !== Number(roomId)) return false;
    return true;
  });
}

function renderTimetable(creneaux) {
  const grid = document.getElementById('emploiTimetableGrid');
  if (!grid) return;
  const jours = getJourOrder();
  const slots = getEmploiSlots();
  const weekStart = document.getElementById('emploiSemaineInput')?.value || new Date().toISOString().slice(0, 10);
  const classeMeta = getSelectedClasseMeta();
  const filtered = getFilteredEmploiCreneaux(creneaux);
  const coverageByDay = Object.fromEntries(
    jours.map((jour) => [jour, buildDaySlotCoverage(filtered.filter((item) => item.jour === jour), slots)]),
  );

  const headerCells = jours
    .map((jour, index) => `<th>${capitalizeFirst(getWeekDateLabel(weekStart, index).replace('.', ''))}</th>`)
    .join('');

  const rows = slots
    .map((slot, rowIndex) => `
        <tr>
          <td class="emploi-time-col">${slot.label.map((line) => `<span>${line}</span>`).join('')}</td>
          ${jours
            .map((jour) => {
              const coverage = coverageByDay[jour][rowIndex];
              if (coverage?.type === 'skip') {
                return '';
              }

              const creneau = coverage?.creneau || null;
              const isEvaluation = isEvaluationCreneau(creneau);
              const showExactRange = creneau && (creneau.heure_debut !== slot.start || creneau.heure_fin !== slot.end);
              const rowspan = coverage?.span || 1;
              return `
                <td class="emploi-slot-cell ${isEvaluation ? 'emploi-exam-cell' : ''}"${rowspan > 1 ? ` rowspan="${rowspan}"` : ''}>
                  ${
                    creneau
                      ? `
                        <div class="emploi-course">
                          ${showExactRange ? `<span class="course-time-badge">[${creneau.heure_debut.slice(0, 5).replace(':', 'H')} : ${creneau.heure_fin.slice(0, 5).replace(':', 'H')}]</span>` : ''}
                          <strong>${creneau.matiere_libelle}</strong>
                          <em>${creneau.enseignant_nom}</em>
                          <button class="emploi-qr-btn" type="button" data-qr-id="${creneau.id}" title="Afficher le QR code"><i class="ph-light ph-qr-code"></i></button>
                        </div>
                      `
                      : ''
                  }
                  <div class="emploi-room-footer ${creneau ? '' : 'emploi-empty-room'}">${creneau?.salle_libelle || ''}</div>
                </td>
              `;
            })
            .join('')}
        </tr>
      `)
    .join('');

  grid.innerHTML = `
    <div class="emploi-sheet">
      <div class="emploi-sheet-header">
        <div class="emploi-sheet-heading">Emploi du temps</div>
        <div class="emploi-sheet-title">${getWeekRangeTitle(weekStart)}</div>
        <div class="emploi-sheet-program">${classeMeta?.code || classeMeta?.libelle || 'CLASSE'}</div>
      </div>

      <table class="emploi-table">
        <thead>
          <tr>
            <th>Horaire</th>
            ${headerCells}
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
}

function buildQrExpire(weekStart, jour, startTime) {
  const dayMap = { lundi: 0, mardi: 1, mercredi: 2, jeudi: 3, vendredi: 4, samedi: 5 };
  const base = new Date(`${weekStart}T00:00:00`);
  base.setDate(base.getDate() + (dayMap[jour] || 0));
  const [hours, minutes] = startTime.split(':').map(Number);
  base.setHours(hours || 0, (minutes || 0) + 30, 0, 0);
  return base.toISOString().slice(0, 19).replace('T', ' ');
}

async function initEmploiPage() {
  await loadReferences();
  const pageUser = getStoredUser();
  const canManageTimetable = pageUser.role === 'administrateur';
  const visibleClasses = pageUser.role === 'delegue' && pageUser.type_lien === 'classe'
    ? appState.classes.filter((item) => Number(item.id) === Number(pageUser.id_lien))
    : appState.classes;
  const visibleTeachers = pageUser.role === 'enseignant' && pageUser.type_lien === 'enseignant'
    ? appState.enseignants.filter((item) => Number(item.id) === Number(pageUser.id_lien))
    : appState.enseignants;

  setOptions(document.getElementById('emploiClasseSelect'), visibleClasses, (item) => ({ value: item.id, label: item.libelle }), false);
  setOptions(document.getElementById('creneauClasseSelect'), visibleClasses, (item) => ({ value: item.id, label: item.libelle }), false);
  setOptions(document.getElementById('emploiTeacherSelect'), [{ id: '', prenom: 'Tous', nom: '' }, ...appState.enseignants], (item) => ({ value: item.id, label: `${item.prenom} ${item.nom}`.trim() }), false);
  setOptions(document.getElementById('emploiRoomSelect'), [{ id: '', libelle: 'Toutes les salles' }, ...appState.salles], (item) => ({ value: item.id, label: item.libelle }), false);
  setOptions(document.getElementById('creneauMatiereSelect'), appState.matieres, (item) => ({ value: item.id, label: item.libelle }), false);
  setOptions(document.getElementById('creneauEnseignantSelect'), visibleTeachers, (item) => ({ value: item.id, label: `${item.prenom} ${item.nom}` }), false);
  setOptions(document.getElementById('creneauSalleSelect'), appState.salles, (item) => ({ value: item.id, label: item.libelle }), false);
  setOptions(document.getElementById('exportClasseSelect'), visibleClasses, (item) => ({ value: item.id, label: item.libelle }), false);

  const classeSelect = document.getElementById('emploiClasseSelect');
  const modalClasseSelect = document.getElementById('creneauClasseSelect');
  const semaineInput = document.getElementById('emploiSemaineInput');
  const exportSemaineInput = document.getElementById('exportSemaineInput');
  const exportScopeSelect = document.getElementById('exportScopeSelect');
  const classSearchInput = document.getElementById('emploiClassSearchInput');
  const referencesPanel = document.getElementById('emploiReferencesPanel');
  const teacherFilter = document.getElementById('emploiTeacherSelect');
  const roomFilter = document.getElementById('emploiRoomSelect');
  const addCreneauBtn = document.querySelector('[data-bs-target="#coursModal"]');
  addCreneauBtn?.classList.toggle('d-none', !canManageTimetable);
  document.getElementById('emploiImportBtn')?.classList.toggle('d-none', !canManageTimetable);
  document.getElementById('saveCreneauBtn')?.classList.toggle('d-none', !canManageTimetable);
  if (pageUser.role === 'delegue' && classeSelect) {
    classeSelect.disabled = true;
    if (modalClasseSelect) modalClasseSelect.disabled = true;
  }
  if (pageUser.role === 'enseignant' && teacherFilter && pageUser.type_lien === 'enseignant') {
    teacherFilter.value = String(pageUser.id_lien || '');
    teacherFilter.disabled = true;
  }

  function renderReferenceList(containerId, items, formatter) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = items.map(formatter).join('');
  }

  renderTimetable([]);
  renderEmploiFooters();

  if (modalClasseSelect && classeSelect?.value) {
    modalClasseSelect.value = classeSelect.value;
  }

  function renderClassReferenceList(filterText = '') {
    const value = filterText.trim().toLowerCase();
    const currentClassId = Number(classeSelect.value || appState.classes[0]?.id || 0);
    const items = visibleClasses.filter((item) => {
      if (!value) return true;
      return `${item.libelle} ${item.code}`.toLowerCase().includes(value);
    });

    renderReferenceList('emploiClassesRefList', items, (item) => `
    <div class="emploi-ref-item ${Number(item.id) === currentClassId ? 'active' : ''}" data-select-classe="${item.id}">
      <div>
        <strong>${item.libelle}</strong>
        <span>${item.code}</span>
      </div>
      <div class="emploi-ref-actions ${canManageTimetable ? '' : 'd-none'}">
        <button class="action-btn" type="button"><i class="ph-light ph-pencil"></i></button>
        <button class="action-btn" type="button"><i class="ph-light ph-trash"></i></button>
      </div>
    </div>
  `);
  }

  renderClassReferenceList();
  renderReferenceList('emploiMatieresRefList', appState.matieres, (item) => `
    <div class="emploi-ref-item">
      <div>
        <strong>${item.libelle}</strong>
        <span>${item.code} · ${item.volume_horaire_total}h</span>
      </div>
    </div>
  `);
  renderReferenceList('emploiEnseignantsRefList', pageUser.role === 'enseignant' ? visibleTeachers : appState.enseignants, (item) => `
    <div class="emploi-ref-item">
      <div>
        <strong>${item.prenom} ${item.nom}</strong>
        <span>${item.specialite || item.statut}</span>
      </div>
    </div>
  `);
  renderReferenceList('emploiSallesRefList', appState.salles, (item) => `
    <div class="emploi-ref-item">
      <div>
        <strong>${item.libelle}</strong>
        <span>${item.code} · ${item.capacite} places</span>
      </div>
    </div>
  `);

  async function loadEmplois() {
    const classeId = classeSelect.value || appState.classes[0]?.id;
    if (!classeSelect.value && classeId) classeSelect.value = classeId;
    if (document.getElementById('exportClasseSelect')) {
      document.getElementById('exportClasseSelect').value = classeId || '';
    }
    if (exportSemaineInput) {
      exportSemaineInput.value = semaineInput.value;
    }
    if (!classeId) {
      appState.emplois = [];
      renderTimetable([]);
      renderEmploiFooters();
      const badge = document.getElementById('emploiMetaBadge');
      if (badge) {
        badge.textContent = 'Aucune classe disponible';
      }
      return;
    }
    const result = await apiClient.get(`/emploi-temps?id_classe=${classeId}&semaine=${encodeURIComponent(semaineInput.value)}`);
    appState.emplois = result.data || [];
    const emploi = appState.emplois[0];
    renderTimetable(emploi?.creneaux || []);
    renderEmploiFooters();
    renderClassReferenceList(classSearchInput?.value || '');
    const badge = document.getElementById('emploiMetaBadge');
    if (badge) {
      const loadedCount = getFilteredEmploiCreneaux(emploi?.creneaux || []).length;
      badge.textContent = `${loadedCount} séance(s) affichée(s)`;
    }
  }

  function renderEmploiFooters() {
    const container = document.getElementById('emploiFooterTables');
    const emploi = appState.emplois[0];
    if (!container) return;
    const devoirs = getFilteredEmploiCreneaux(emploi?.creneaux || [])
      .filter((item) => item.devoir_prevu)
      .sort((a, b) => {
        const left = `${a.devoir_date || ''} ${a.jour || ''}`;
        const right = `${b.devoir_date || ''} ${b.jour || ''}`;
        return left.localeCompare(right);
      })
      .slice(0, 4);
    const cells = [0, 1].map((groupIndex) => {
      const items = devoirs.slice(groupIndex * 2, groupIndex * 2 + 2);
      const rows = [0, 1]
        .map((rowIndex) => {
          const item = items[rowIndex];
          return `
            <tr>
              <td>${item ? item.devoir_prevu : ''}</td>
              <td>${item ? formatPlanningDate(item.devoir_date, item.jour, semaineInput.value) : ''}</td>
            </tr>
          `;
        })
        .join('');

      return `
        <table class="emploi-footer-table">
          <thead><tr><th>Devoir prévu</th><th>Date</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      `;
    });
    container.innerHTML = `
      ${cells.join('')}
      <div class="emploi-sheet-footer">Eduschoolpro</div>
    `;
  }

  classeSelect.addEventListener('change', loadEmplois);
  classeSelect.addEventListener('change', () => {
    if (modalClasseSelect) {
      modalClasseSelect.value = classeSelect.value;
    }
  });
  modalClasseSelect?.addEventListener('change', () => {
    if (classeSelect) {
      classeSelect.value = modalClasseSelect.value;
    }
  });
  semaineInput.addEventListener('change', loadEmplois);
  teacherFilter?.addEventListener('change', () => {
    const emploi = appState.emplois[0];
    renderTimetable(emploi?.creneaux || []);
    renderEmploiFooters();
    const badge = document.getElementById('emploiMetaBadge');
    if (badge) {
      badge.textContent = `${getFilteredEmploiCreneaux(emploi?.creneaux || []).length} séance(s) affichée(s)`;
    }
  });
  roomFilter?.addEventListener('change', () => {
    const emploi = appState.emplois[0];
    renderTimetable(emploi?.creneaux || []);
    renderEmploiFooters();
    const badge = document.getElementById('emploiMetaBadge');
    if (badge) {
      badge.textContent = `${getFilteredEmploiCreneaux(emploi?.creneaux || []).length} séance(s) affichée(s)`;
    }
  });
  classSearchInput?.addEventListener('input', () => {
    renderClassReferenceList(classSearchInput.value);
  });
  document.getElementById('emploiReferencesCloseBtn')?.addEventListener('click', () => {
    referencesPanel?.classList.toggle('is-collapsed');
  });
  document.getElementById('emploiFilterBtn')?.addEventListener('click', () => {
    referencesPanel?.classList.toggle('is-collapsed');
  });
  document.addEventListener('click', (event) => {
    const selectClassBtn = event.target.closest('[data-select-classe]');
    if (!selectClassBtn) return;
    classeSelect.value = selectClassBtn.dataset.selectClasse;
    loadEmplois();
  });
  document.addEventListener('click', async (event) => {
    const qrBtn = event.target.closest('[data-qr-id]');
    if (!qrBtn) return;
    const creneauId = qrBtn.dataset.qrId;
    const qrModalBody = document.getElementById('qrModalBody');
    const qrModalExpiry = document.getElementById('qrModalExpiry');
    if (!qrModalBody) return;
    qrModalBody.innerHTML = '<div class="qr-modal-loading"><i class="ph-light ph-circle-notch"></i> Chargement...</div>';
    if (qrModalExpiry) qrModalExpiry.textContent = '';
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('qrModal'));
    modal.show();
    try {
      const result = await apiClient.get(`/creneaux/${creneauId}/qr`);
      const data = result.data || {};
      const token = data.qr_token || '';
      if (!token) {
        throw new Error('QR code indisponible pour cette séance.');
      }
      const expiry = data.qr_expire ? new Date(data.qr_expire).toLocaleString('fr-FR') : '';
      const matiere = data.matiere || '';
      const enseignant = data.enseignant || '';
      qrModalBody.innerHTML = `<div class="qr-modal-meta mb-3"><strong>${matiere}</strong>${enseignant ? ` — ${enseignant}` : ''}</div>`;
      const qrContainer = document.createElement('div');
      qrContainer.style.cssText = 'display:inline-block;margin:0 auto;';
      qrModalBody.appendChild(qrContainer);
      if (typeof QRCode !== 'undefined') {
        new QRCode(qrContainer, { text: token, width: 200, height: 200, colorDark: '#0b2a5b', colorLight: '#ffffff' });
      } else {
        qrContainer.innerHTML = `<div class="text-danger">Bibliothèque QR non chargée.</div>`;
      }
      const tokenDiv = document.createElement('div');
      tokenDiv.className = 'qr-modal-token mt-2';
      tokenDiv.innerHTML = `<code>${token}</code>`;
      qrModalBody.appendChild(tokenDiv);
      if (qrModalExpiry && expiry) qrModalExpiry.textContent = `Expire le ${expiry}`;
    } catch (e) {
      qrModalBody.innerHTML = '<div class="text-danger">Impossible de charger le QR code.</div>';
    }
  });

  exportScopeSelect?.addEventListener('change', () => {
    document.getElementById('exportClasseField')?.classList.toggle('d-none', exportScopeSelect.value === 'all');
  });

  document.getElementById('emploiImportBtn')?.addEventListener('click', () => {
    if (!canManageTimetable) return;
    showAlert('emploiAlert', "Import standardisé bientôt disponible. Utilise pour l'instant l'ajout manuel des créneaux.", 'info');
  });

  document.getElementById('coursModal')?.addEventListener('show.bs.modal', () => {
    if (modalClasseSelect && classeSelect?.value) {
      modalClasseSelect.value = classeSelect.value;
    }
    const typeSelect = document.getElementById('creneauTypeSelect');
    const devoirInput = document.getElementById('creneauDevoirInput');
    const devoirDateInput = document.getElementById('creneauDevoirDateInput');
    if (typeSelect) typeSelect.value = 'cours_magistral';
    if (devoirInput) devoirInput.value = '';
    if (devoirDateInput) devoirDateInput.value = '';
  });

  document.getElementById('saveCreneauBtn')?.addEventListener('click', async () => {
    if (!canManageTimetable) {
      showAlert('emploiAlert', "Seul l'administrateur peut ajouter des créneaux.", 'warning');
      return;
    }
    const saveBtn = document.getElementById('saveCreneauBtn');
    if (saveBtn?.dataset.loading === '1') return;
    if (saveBtn) {
      saveBtn.dataset.loading = '1';
      saveBtn.disabled = true;
    }

    try {
      let emploi = appState.emplois[0];
      const classeId = Number(document.getElementById('creneauClasseSelect')?.value || classeSelect.value);
      const week = semaineInput.value;
      const authUser = getStoredUser();
      const typeSeance = document.getElementById('creneauTypeSelect')?.value || 'cours_magistral';
      const devoirPrevu = document.getElementById('creneauDevoirInput')?.value?.trim() || '';
      const devoirDate = document.getElementById('creneauDevoirDateInput')?.value || '';

      if (classeSelect) {
        classeSelect.value = String(classeId || '');
      }

      if (emploi && Number(emploi.id_classe) !== classeId) {
        emploi = null;
      }

      if (!emploi) {
        await apiClient.post('/emploi-temps', {
          id_classe: classeId,
          semaine_debut: week,
          statut_publication: 'brouillon',
          cree_par: authUser.id || 1,
        });
        const refreshed = await apiClient.get(`/emploi-temps?id_classe=${classeId}&semaine=${encodeURIComponent(week)}`);
        appState.emplois = refreshed.data || [];
        emploi = appState.emplois[0];
      }

      const jour = document.getElementById('creneauJourSelect').value;
      const heureDebut = document.getElementById('creneauDebutInput').value;
      const heureFin = document.getElementById('creneauFinInput').value;

      if (!classeId || !emploi || !heureDebut || !heureFin) {
        showAlert('emploiAlert', "Renseigne la classe, le jour et les horaires avant d'enregistrer.", 'warning');
        return;
      }

      await apiClient.post('/creneaux', {
        id_emploi_temps: emploi.id,
        id_classe: classeId,
        id_matiere: Number(document.getElementById('creneauMatiereSelect').value),
        id_enseignant: Number(document.getElementById('creneauEnseignantSelect').value),
        id_salle: Number(document.getElementById('creneauSalleSelect').value),
        jour,
        heure_debut: `${heureDebut}:00`,
        heure_fin: `${heureFin}:00`,
        type_seance: typeSeance,
        devoir_prevu: devoirPrevu || null,
        devoir_date: devoirDate || null,
        qr_expire: buildQrExpire(week, jour, heureDebut),
        statut: 'planifie',
      });

      await loadEmplois();
      showAlert('emploiAlert', 'Créneau enregistré avec succès.', 'success');
      window.bootstrap?.Modal.getInstance(document.getElementById('coursModal'))?.hide();
    } catch (error) {
      showAlert('emploiAlert', error.message, 'danger');
    } finally {
      if (saveBtn) {
        saveBtn.dataset.loading = '0';
        saveBtn.disabled = false;
      }
    }
  });

  document.getElementById('exportEmploiBtn')?.addEventListener('click', async () => {
    const scope = exportScopeSelect?.value || 'single';
    const classId = document.getElementById('exportClasseSelect')?.value || '';
    const week = exportSemaineInput?.value || semaineInput.value;
    const query = new URLSearchParams({
      scope,
      semaine: week,
    });
    if (scope === 'single') {
      query.set('id_classe', classId);
    }
    const result = await apiClient.get(`/emploi-temps/export?${query.toString()}`);
    if (result.data?.url) {
      const exportUrl = result.data.url;
      const exportWindow = window.open(exportUrl, '_blank', 'noopener');
      if (!exportWindow) {
        window.location.href = exportUrl;
      }
    }
  });

  await loadEmplois();
}

function getRecentPointages() {
  try {
    return JSON.parse(localStorage.getItem('eduschedule_recent_pointages') || '[]');
  } catch (error) {
    return [];
  }
}

function saveRecentPointage(item) {
  const items = [item, ...getRecentPointages()].slice(0, 5);
  localStorage.setItem('eduschedule_recent_pointages', JSON.stringify(items));
}

function renderRecentPointages() {
  const body = document.getElementById('pointageRecentBody');
  if (!body) return;
  body.innerHTML = getRecentPointages().map((item) => `
    <tr>
      <td>${item.time}</td>
      <td>${item.enseignant}</td>
      <td>${item.matiere} · ${item.salle}</td>
      <td><span class="status-badge ${item.status === 'a_l_heure' ? 'success' : 'warning'}">${item.status}</span></td>
      <td><button class="btn btn-sm btn-outline-primary">OK</button></td>
    </tr>
  `).join('');
}

function formatPointageDayLabel(jour) {
  const labels = {
    lundi: 'Lundi',
    mardi: 'Mardi',
    mercredi: 'Mercredi',
    jeudi: 'Jeudi',
    vendredi: 'Vendredi',
    samedi: 'Samedi',
  };
  return labels[jour] || jour || '-';
}

function showPointageResultPanel() {
  const scanCol   = document.getElementById('scanCol');
  const resultCol = document.getElementById('resultCol');
  if (scanCol)   { scanCol.className   = 'col-lg-6'; }
  if (resultCol) { resultCol.className = 'col-lg-6'; }
}

function hidePointageResultPanel() {
  const scanCol   = document.getElementById('scanCol');
  const resultCol = document.getElementById('resultCol');
  if (scanCol)   { scanCol.className   = 'col-12'; }
  if (resultCol) { resultCol.className = 'col-lg-6 d-none'; }
}

function renderPointageResult(data, token) {
  const now = new Date();
  const statusBadge = document.getElementById('pointageStatusBadge');
  const statusText = data.status === 'a_l_heure' ? 'Présent' : 'Retard';
  showPointageResultPanel();

  document.getElementById('pointageResultTitle').textContent = 'Pointage validé avec succès';
  document.getElementById('pointageResultText').textContent = 'La présence a été enregistrée.';
  document.getElementById('pointageEnseignant').textContent = data.creneau.enseignant || '-';
  document.getElementById('pointageClasse').textContent = data.creneau.classe || '-';
  document.getElementById('pointageMatiere').textContent = data.creneau.matiere || '-';
  document.getElementById('pointageSalle').textContent = data.creneau.salle || '-';
  document.getElementById('pointageDate').textContent = formatPointageDayLabel(data.creneau.jour);
  document.getElementById('pointageHoraire').textContent = `${data.creneau.heure_debut.slice(0, 5)} - ${data.creneau.heure_fin.slice(0, 5)}`;
  statusBadge.textContent = statusText;
  statusBadge.className = `status-badge ${data.status === 'a_l_heure' ? 'success' : 'warning'}`;
  document.getElementById('pointageInfoBox').innerHTML = `
    <i class="ph-light ph-clock-clockwise"></i>
    <span>Pointé le ${now.toLocaleDateString('fr-FR')} à ${now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</span>
  `;
}

function resetPointageResult() {
  hidePointageResultPanel();
  document.getElementById('pointageResultTitle').textContent = 'En attente de scan';
  document.getElementById('pointageResultText').textContent = 'Scannez un QR code pour afficher les détails de la présence.';
  document.getElementById('pointageEnseignant').textContent = '-';
  document.getElementById('pointageClasse').textContent = '-';
  document.getElementById('pointageMatiere').textContent = '-';
  document.getElementById('pointageSalle').textContent = '-';
  document.getElementById('pointageDate').textContent = '-';
  document.getElementById('pointageHoraire').textContent = '-';
  const badge = document.getElementById('pointageStatusBadge');
  badge.textContent = 'En attente';
  badge.className = 'status-badge neutral';
  document.getElementById('pointageInfoBox').innerHTML = '<i class="ph-light ph-clock-clockwise"></i><span>Aucun pointage enregistré pour le moment.</span>';
}

async function initPointagePage() {
  renderRecentPointages();
  resetPointageResult();
  const video = document.getElementById('pointageCameraVideo');
  const cameraEmpty = document.getElementById('pointageCameraEmpty');
  const cameraStatus = document.getElementById('pointageCameraStatus');
  const startCameraBtn = document.getElementById('pointageStartCameraBtn');
  const startCameraLabel = document.getElementById('pointageStartCameraLabel');
  const openLocalBtn = document.getElementById('pointageOpenLocalBtn');
  let cameraStream = null;
  let detector = null;
  let scanInterval = null;
  let lastScannedValue = '';
  let isSubmitting = false;
  const isLocalHost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
  const hasBarcodeDetector = 'BarcodeDetector' in window;

  const setCameraMessage = (message) => {
    if (cameraStatus) cameraStatus.textContent = message;
    const text = cameraEmpty?.querySelector('span');
    if (text) text.textContent = message;
  };

  const setCameraButtonLabel = (label) => {
    if (startCameraLabel) {
      startCameraLabel.textContent = label;
    }
  };

  const toggleOpenLocalButton = (visible) => {
    if (!openLocalBtn) return;
    openLocalBtn.classList.toggle('d-none', !visible);
    if (visible) {
      const targetUrl = `${window.location.protocol}//localhost:${window.location.port || '8000'}${window.location.pathname}${window.location.search}${window.location.hash}`;
      openLocalBtn.href = targetUrl;
    } else {
      openLocalBtn.removeAttribute('href');
    }
  };

  let jsqrCanvas = null;
  let jsqrCtx = null;

  const stopScannerLoop = () => {
    if (scanInterval) {
      clearInterval(scanInterval);
      scanInterval = null;
    }
  };

  const stopCamera = () => {
    stopScannerLoop();
    if (cameraStream) {
      cameraStream.getTracks().forEach((track) => track.stop());
      cameraStream = null;
    }
    if (video) {
      video.srcObject = null;
    }
    setCameraButtonLabel('Activer la caméra');
  };

  const startJsQRLoop = () => {
    stopScannerLoop();
    if (!jsqrCanvas) {
      jsqrCanvas = document.createElement('canvas');
      jsqrCtx = jsqrCanvas.getContext('2d', { willReadFrequently: true });
    }
    scanInterval = window.setInterval(() => {
      if (!video || !video.videoWidth || isSubmitting) return;
      jsqrCanvas.width = video.videoWidth;
      jsqrCanvas.height = video.videoHeight;
      jsqrCtx.drawImage(video, 0, 0, jsqrCanvas.width, jsqrCanvas.height);
      const imgData = jsqrCtx.getImageData(0, 0, jsqrCanvas.width, jsqrCanvas.height);
      const code = window.jsQR && window.jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'dontInvert' });
      if (code && code.data) {
        processTokenScan(code.data);
      }
    }, 250);
  };

  const mapCameraError = (error) => {
    const name = error?.name || '';
    if (!window.isSecureContext && !isLocalHost) {
      return 'Caméra bloquée sur cette adresse. Ouvrez cette page via localhost ou HTTPS.';
    }
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
      return 'Accès caméra refusé. Autorisez la caméra dans le navigateur.';
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      return 'Aucune caméra détectée sur cet appareil.';
    }
    if (name === 'NotReadableError' || name === 'TrackStartError') {
      return 'La caméra est déjà utilisée par une autre application.';
    }
    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
      return "Caméra trouvée, mais le mode demandé n'est pas disponible.";
    }
    if (!navigator.mediaDevices?.getUserMedia) {
      return 'Ce navigateur ne donne pas accès à la caméra sur cette page.';
    }
    return 'Accès caméra indisponible pour le moment.';
  };

  const processTokenScan = async (token) => {
    if (!token || isSubmitting) return;
    if (token === lastScannedValue) return;
    lastScannedValue = token;
    isSubmitting = true;
    try {
      const user = getStoredUser();
      const result = await apiClient.post('/pointages/scan', {
        token,
        id_enseignant: Number(user.id_lien || 1),
      });
      const data = result.data;
      renderPointageResult(data, token);
      showAlert('pointageAlert', result.message, 'success');
      saveRecentPointage({
        time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
        enseignant: data.creneau.enseignant,
        matiere: data.creneau.matiere,
        salle: data.creneau.salle,
        status: data.status,
      });
      renderRecentPointages();
    } catch (error) {
      resetPointageResult();
      document.getElementById('pointageResultTitle').textContent = 'Échec du pointage';
      document.getElementById('pointageResultText').textContent = error.message;
      showAlert('pointageAlert', error.message, 'danger');
    } finally {
      window.setTimeout(() => {
        isSubmitting = false;
        lastScannedValue = '';
      }, 1800);
    }
  };

  const startDetectionLoop = () => {
    stopScannerLoop();
    if (!video || !detector) return;
    scanInterval = window.setInterval(async () => {
      if (!video.videoWidth || isSubmitting) return;
      try {
        const codes = await detector.detect(video);
        if (!codes?.length) return;
        const rawValue = codes[0].rawValue?.trim();
        if (rawValue) {
          processTokenScan(rawValue);
        }
      } catch (error) {
        setCameraMessage('Analyse du QR indisponible sur ce navigateur.');
      }
    }, 900);
  };

  const startCamera = async () => {
    if (!window.isSecureContext && !isLocalHost) {
      setCameraMessage('Caméra bloquée sur cette adresse. Ouvrez via localhost ou HTTPS.');
      toggleOpenLocalButton(true);
      setCameraButtonLabel('Réessayer la caméra');
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      setCameraMessage('Ce navigateur ne donne pas accès à la caméra sur cette page.');
      toggleOpenLocalButton(false);
      setCameraButtonLabel('Réessayer la caméra');
      return;
    }

    try {
      toggleOpenLocalButton(false);
      setCameraButtonLabel('Activation...');
      const attempts = [
        { video: { facingMode: { ideal: 'environment' } }, audio: false },
        { video: true, audio: false },
      ];
      let lastError = null;

      for (const constraints of attempts) {
        try {
          cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
          break;
        } catch (error) {
          lastError = error;
        }
      }

      if (!cameraStream) {
        throw lastError || new Error('camera_unavailable');
      }

      video.srcObject = cameraStream;
      await video.play();
      cameraEmpty?.classList.add('d-none');

      if (hasBarcodeDetector) {
        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        setCameraMessage('Caméra active');
        setCameraButtonLabel('Redémarrer la caméra');
        startDetectionLoop();
      } else if (window.jsQR) {
        detector = null;
        setCameraMessage('Caméra active');
        setCameraButtonLabel('Redémarrer la caméra');
        startJsQRLoop();
      } else {
        detector = null;
        setCameraMessage('Caméra active. Scan QR automatique non pris en charge par ce navigateur.');
        setCameraButtonLabel('Redémarrer la caméra');
      }
    } catch (error) {
      cameraEmpty?.classList.remove('d-none');
      setCameraMessage(mapCameraError(error));
      toggleOpenLocalButton(!window.isSecureContext && !isLocalHost);
      setCameraButtonLabel('Réessayer la caméra');
    }
  };

  document.getElementById('pointageValidateBtn')?.addEventListener('click', () => {
    window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('pointageHistoryModal')).show();
  });
  document.getElementById('pointageResetBtn')?.addEventListener('click', () => {
    resetPointageResult();
    lastScannedValue = '';
    setCameraMessage(cameraStream ? 'Caméra active' : 'Activation de la caméra en cours...');
    if (!cameraStream) {
      startCamera();
    }
  });
  startCameraBtn?.addEventListener('click', async () => {
    stopCamera();
    cameraEmpty?.classList.remove('d-none');
    setCameraMessage('Redémarrage de la caméra...');
    setCameraButtonLabel('Activation...');
    await startCamera();
  });
  window.addEventListener('beforeunload', stopCamera, { once: true });
  await startCamera();
}

async function initCahierPage() {
  await loadReferences();
  await loadSignatureRules();
  const emplois = await apiClient.get('/emploi-temps');
  const cahiers = await apiClient.get('/cahiers');
  const creneaux = (emplois.data || []).flatMap((item) => item.creneaux || []);
  const select = document.getElementById('cahierCreneauSelect');
  const classeFilter = document.getElementById('cahierClasseFilter');
  const matiereFilter = document.getElementById('cahierMatiereFilter');
  const enseignantFilter = document.getElementById('cahierEnseignantFilter');
  const weekFilter = document.getElementById('cahierWeekFilter');
  const sessionList = document.getElementById('cahierSessionList');
  const workspaceTitle = document.getElementById('cahierWorkspaceTitle');
  const workspaceSubtitle = document.getElementById('cahierWorkspaceSubtitle');
  const closeCahierBtn = document.getElementById('closeCahierBtn');
  const saveCahierBtn = document.getElementById('saveCahierBtn');
  const signCahierBtn = document.getElementById('signCahierBtn');
  let cahierRequiredRules = [];
  let cahierSignedMap = {};
  const user = getStoredUser();
  const currentRole = ['delegue', 'enseignant'].includes(user.role) ? user.role : null;
  const canEditCahier = ['delegue', 'enseignant'].includes(user.role);
  const canCloseCahier = ['administrateur', 'admin', 'enseignant'].includes(user.role);
  const visibleCahierClasses = user.role === 'delegue' && user.type_lien === 'classe'
    ? appState.classes.filter((item) => Number(item.id) === Number(user.id_lien))
    : appState.classes;
  const visibleCahierTeachers = user.role === 'enseignant' && user.type_lien === 'enseignant'
    ? appState.enseignants.filter((item) => Number(item.id) === Number(user.id_lien))
    : appState.enseignants;
  const cahierViews = {
    form: {
      title: 'Saisie du cahier',
      subtitle: 'Renseignez les informations pédagogiques et sauvegardez la séance.',
    },
    resume: {
      title: 'Résumé de la séance',
      subtitle: 'Consultez la synthèse pédagogique avant validation.',
    },
    signatures: {
      title: 'Signatures numériques',
      subtitle: 'Capturez la signature et vérifiez la chaîne de validation.',
    },
    history: {
      title: 'Historique du cahier',
      subtitle: 'Suivez les étapes importantes de la séance.',
    },
  };

  setOptions(select, creneaux, (item) => ({
    value: item.id,
    label: `${item.jour} · ${item.matiere_libelle} · ${item.heure_debut.slice(0, 5)}`,
  }), false);
  setOptions(classeFilter, [{ id: '', libelle: 'Classe' }, ...visibleCahierClasses], (item) => ({
    value: item.id,
    label: item.libelle,
  }), false);
  setOptions(matiereFilter, [{ id: '', libelle: 'Matière' }, ...appState.matieres], (item) => ({
    value: item.id,
    label: item.libelle,
  }), false);
  setOptions(enseignantFilter, [{ id: '', prenom: 'Enseignant', nom: '' }, ...visibleCahierTeachers], (item) => ({
    value: item.id,
    label: `${item.prenom} ${item.nom}`.trim(),
  }), false);
  if (user.role === 'delegue' && user.type_lien === 'classe' && classeFilter) {
    classeFilter.value = String(user.id_lien || '');
    classeFilter.disabled = true;
  }
  if (user.role === 'enseignant' && user.type_lien === 'enseignant' && enseignantFilter) {
    enseignantFilter.value = String(user.id_lien || '');
    enseignantFilter.disabled = true;
  }

  const emploiMap = Object.fromEntries((emplois.data || []).map((emploi) => [Number(emploi.id), emploi]));
  const cahiersByCreneau = new Map();
  (cahiers.data || []).forEach((cahier) => {
    const key = Number(cahier.id_creneau);
    if (!cahiersByCreneau.has(key)) {
      cahiersByCreneau.set(key, cahier);
    }
  });

  const defaultCahierDraft = {
    titre_cours: 'Introduction aux réseaux informatiques',
    points_abordes: "Définition d'un réseau informatique\nTypes de réseaux (LAN, MAN, WAN)\nTopologies physiques et logiques\nModèle OSI et modèle TCP/IP",
    travaux_demandes: 'Lire le chapitre 1 du cours\nFaire les exercices 1 à 3\nPréparer un exposé sur le modèle TCP/IP',
    observations: 'RAS',
    niveau_avancement: '20%',
    statut: 'brouillon',
  };

  function formatCahierDate(creneau) {
    return `${formatPointageDayLabel(creneau.jour)} ${weekFilter.value}`;
  }

  function progressFromCahier(cahier) {
    const match = String(cahier?.niveau_avancement || defaultCahierDraft.niveau_avancement).match(/\d+/);
    return match ? Math.max(0, Math.min(100, Number(match[0]))) : 20;
  }

  function applyCahierData(cahier = null) {
    const source = cahier || defaultCahierDraft;
    document.getElementById('cahierTitreInput').value = source.titre_cours || '';
    document.getElementById('cahierPointsInput').value = source.points_abordes || '';
    document.getElementById('cahierTravauxInput').value = source.travaux_demandes || '';
    document.getElementById('cahierObservationsInput').value = source.observations || '';
    document.getElementById('cahierProgressInput').value = progressFromCahier(source);

    const status = source.statut === 'cloture'
      ? 'Validé'
      : source.statut === 'signe'
        ? 'Signé'
        : (cahier ? 'Brouillon enregistré' : 'Brouillon');
    const statusClass = source.statut === 'cloture' || source.statut === 'signe' ? 'success' : 'neutral';
    document.getElementById('cahierSummaryStatus').textContent = status;
    document.getElementById('cahierSummaryStatus').className = `status-badge ${statusClass}`;
    updateCahierPreview();
  }

  function getFilteredCreneaux() {
    return creneaux.filter((item) => {
      if (classeFilter?.value) {
        const emploi = emploiMap[Number(item.id_emploi_temps)];
        if (Number(emploi?.id_classe) !== Number(classeFilter.value)) return false;
      }
      if (matiereFilter?.value && Number(item.id_matiere) !== Number(matiereFilter.value)) return false;
      if (enseignantFilter?.value && Number(item.id_enseignant) !== Number(enseignantFilter.value)) return false;
      return true;
    });
  }

  function updateCahierPreview() {
    const progressValue = document.getElementById('cahierProgressInput').value;
    document.getElementById('cahierProgressValue').textContent = `${progressValue}%`;
    document.getElementById('cahierSummaryProgress').textContent = `${progressValue}%`;
    document.getElementById('cahierPreviewTitre').textContent = document.getElementById('cahierTitreInput').value || '-';
    document.getElementById('cahierPreviewPoints').textContent = document.getElementById('cahierPointsInput').value || '-';
    document.getElementById('cahierPreviewTravaux').textContent = document.getElementById('cahierTravauxInput').value || '-';
    document.getElementById('cahierPreviewObservations').textContent = document.getElementById('cahierObservationsInput').value || '-';
    document.getElementById('cahierResumeMatiere').textContent = document.getElementById('cahierSummaryMatiere').textContent;
    document.getElementById('cahierResumeClasse').textContent = document.getElementById('cahierSummaryClasse').textContent;
    document.getElementById('cahierResumeSalle').textContent = document.getElementById('cahierSummarySalle').textContent;
  }

  function updateCahierCloseState() {
    if (!closeCahierBtn) return;
    const missing = cahierRequiredRules.filter((rule) => !cahierSignedMap[rule.role_signataire]);
    const canClose = Boolean(appState.currentCahierId) && missing.length === 0;
    closeCahierBtn.disabled = !canClose;
    closeCahierBtn.title = canClose
      ? 'Clôturer cette séance'
      : (missing.length
        ? `Signatures manquantes : ${missing.map((rule) => rule.role_signataire).join(', ')}`
        : "Enregistre d'abord le cahier avant clôture");
  }

  function signatureRoleLabel(role) {
    return role === 'delegue' ? 'Délégué de classe' : 'Enseignant';
  }

  function normalizeSignatureRole(role) {
    const value = String(role || '').toLowerCase().trim();
    if (['delegue', 'délégué', 'delegate', 'class_delegate'].includes(value)) return 'delegue';
    if (['enseignant', 'teacher', 'professeur', 'prof'].includes(value)) return 'enseignant';
    return value;
  }

  function savedSignatureMarkup(signature, role) {
    if (!signature?.signature_base64) return '';
    return `
      <div class="cahier-signature-slot">
        <img src="${signature.signature_base64}" alt="Signature ${signatureRoleLabel(role)}" class="cahier-signature-image" />
      </div>
      <div class="cahier-signature-note">
        ${signature.signataire_nom || signatureRoleLabel(role)} · ${formatDateTime(signature.horodatage)}
      </div>
    `;
  }

  function renderResumeSignatures() {
    const resumeNode = document.getElementById('cahierResumeSignatures');
    if (!resumeNode) return;
    const delegue = cahierSignedMap.delegue;
    const enseignant = cahierSignedMap.enseignant;
    if (!delegue && !enseignant) {
      resumeNode.innerHTML = 'Aucune signature enregistrée.';
      return;
    }
    const block = (label, signature) => {
      if (!signature?.signature_base64) {
        return `<div class="mb-3"><strong>${label}</strong><div class="text-muted-soft">En attente.</div></div>`;
      }
      return `
        <div class="mb-3">
          <strong>${label}</strong>
          <div class="cahier-signature-slot mt-2">
            <img src="${signature.signature_base64}" alt="Signature ${label}" class="cahier-signature-image" />
          </div>
          <div class="cahier-signature-note">
            ${signature.signataire_nom || label} · ${formatDateTime(signature.horodatage)}
          </div>
        </div>
      `;
    };
    resumeNode.innerHTML = `${block('Délégué de classe', delegue)}${block('Enseignant', enseignant)}`;
  }

  function renderSignatureRoleArea(role, currentRole) {
    const container = document.getElementById(role === 'delegue' ? 'cahierDelegueSignatureArea' : 'cahierEnseignantSignatureArea');
    const badge = document.getElementById(role === 'delegue' ? 'cahierDelegueStatusBadge' : 'cahierEnseignantStatusBadge');
    if (!container || !badge) return;

    const signature = cahierSignedMap[role];
    badge.textContent = signature ? 'Signé' : 'En attente';
    badge.className = `status-badge ${signature ? 'success' : 'warning'}`;

    if (role === currentRole) {
      container.innerHTML = `
        ${savedSignatureMarkup(signature, role)}
        <canvas class="signature-box w-100" id="cahierSignaturePad" width="640" height="160"></canvas>
        <div class="cahier-signature-actions">
          <button class="btn btn-outline-primary" id="clearSignaturesBtn" type="button">
            <i class="ph-light ph-eraser me-2"></i>Effacer
          </button>
          <button class="btn btn-primary" type="button" data-save-cahier-signature="${role}">
            <i class="ph-light ph-floppy-disk me-2"></i>Enregistrer la signature
          </button>
        </div>
        <div class="cahier-signature-note">
          ${signature ? 'La signature enregistrée reste affichée. Signez à nouveau puis enregistrez pour la remplacer.' : 'Signez ici puis cliquez sur Enregistrer la signature.'}
        </div>
      `;
      appState.signaturePads[currentRole] = setupPad('cahierSignaturePad');
      document.getElementById('clearSignaturesBtn')?.addEventListener('click', () => {
        const canvas = appState.signaturePads[currentRole];
        if (canvas) {
          const ctx = canvas.getContext('2d');
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          canvas.dataset.hasInk = '0';
        }
      });
      return;
    }

    if (signature?.signature_base64) {
      container.innerHTML = savedSignatureMarkup(signature, role);
      return;
    }

    if (!currentRole) {
      container.innerHTML = `
        <div class="cahier-signature-slot">
          <div class="cahier-signature-placeholder">
            <i class="ph-light ph-lock"></i>
            <span>Connectez-vous avec le rôle ${signatureRoleLabel(role).toLowerCase()} pour signer.</span>
          </div>
        </div>
      `;
      return;
    }

    container.innerHTML = `
      <div class="cahier-signature-slot">
        <div class="cahier-signature-placeholder">
          <i class="ph-light ph-vector-pen"></i>
          <span>Aucune signature enregistrée.</span>
        </div>
      </div>
    `;
  }

  function setCahierView(view) {
    document.querySelectorAll('[data-cahier-view-btn]').forEach((button) => {
      button.classList.toggle('active', button.dataset.cahierViewBtn === view);
    });
    document.querySelectorAll('[data-cahier-view]').forEach((panel) => {
      panel.classList.toggle('active', panel.dataset.cahierView === view);
    });
    if (workspaceTitle) workspaceTitle.textContent = cahierViews[view]?.title || 'Cahier de texte';
    if (workspaceSubtitle) workspaceSubtitle.textContent = cahierViews[view]?.subtitle || '';
  }

  function setCahierLayoutMode(mode = 'full') {
    document.body.classList.remove('cahier-view-list-only', 'cahier-view-form-only');
    if (mode === 'list') {
      document.body.classList.add('cahier-view-list-only');
      return;
    }
    if (mode === 'form') {
      document.body.classList.add('cahier-view-form-only');
    }
  }

  function applyCahierHashView() {
    if (window.location.hash === '#detail-cahier') {
      setCahierLayoutMode('form');
      setCahierView('resume');
      return;
    }
    if (window.location.hash === '#formulaire-cahier') {
      setCahierLayoutMode('form');
      setCahierView('form');
      return;
    }
    if (window.location.hash === '#list-seances') {
      setCahierLayoutMode('list');
      return;
    }
    setCahierLayoutMode('full');
  }

  function renderSessionList() {
    const filtered = getFilteredCreneaux();
    const activeId = Number(select.value || filtered[0]?.id || 0);
    if (sessionList) {
      sessionList.innerHTML = filtered.map((item) => {
        const isActive = Number(item.id) === activeId;
        const dayNumber = Number(weekFilter.value.slice(8, 10)) + getJourOrder().indexOf(item.jour);
        const cahier = cahiersByCreneau.get(Number(item.id));
        const statusText = cahier
          ? (cahier.statut === 'cloture' ? 'Validé' : (cahier.statut === 'signe' ? 'Signé' : 'Saisi'))
          : item.statut === 'planifie'
            ? 'Planifiée'
            : 'À saisir';
        const statusClass = statusText === 'Saisi' || statusText === 'Signé'
          ? 'success'
          : (statusText === 'Validé' ? 'success' : (statusText === 'Planifiée' ? 'neutral' : 'warning'));
        return `
          <div class="cahier-session-item ${isActive ? 'active' : ''}" data-cahier-creneau="${item.id}">
            <div class="cahier-session-date">
              <span>${(item.jour || '').slice(0, 3)}.</span>
              <strong>${String(dayNumber).padStart(2, '0')}</strong>
              <small>avr.</small>
            </div>
            <div class="cahier-session-main">
              <span>${item.heure_debut.slice(0, 5)} – ${item.heure_fin.slice(0, 5)}</span>
              <strong>${item.matiere_libelle}</strong>
              <small>${item.salle_libelle}</small>
            </div>
            <span class="status-badge ${statusClass}">${statusText}</span>
            <i class="ph-light ph-caret-right text-muted"></i>
          </div>
        `;
      }).join('');
    }
    document.getElementById('cahierSessionCount').textContent = `${filtered.length} séance(s)`;
  }

  const syncInfo = async () => {
    const creneau = creneaux.find((item) => Number(item.id) === Number(select.value)) || creneaux[0];
    if (!creneau) return;
    const emploi = emploiMap[Number(creneau.id_emploi_temps)];
    const cahier = cahiersByCreneau.get(Number(creneau.id)) || null;
    appState.currentCahierId = cahier?.id || null;
    document.getElementById('cahierDateInfo').value = formatCahierDate(creneau);
    document.getElementById('cahierHeureDebutInfo').value = creneau.heure_debut.slice(0, 5);
    document.getElementById('cahierHeureFinInfo').value = creneau.heure_fin.slice(0, 5);
    document.getElementById('cahierSalleInfo').value = creneau.salle_libelle;
    document.getElementById('cahierSummaryMatiere').textContent = creneau.matiere_libelle;
    document.getElementById('cahierSummaryClasse').textContent = emploi?.classe_libelle || '-';
    document.getElementById('cahierSummaryEnseignant').textContent = creneau.enseignant_nom;
    document.getElementById('cahierSummarySalle').textContent = creneau.salle_libelle;
    document.getElementById('cahierSummaryDate').textContent = formatCahierDate(creneau);
    document.getElementById('cahierSummaryHoraire').textContent = `${creneau.heure_debut.slice(0, 5)} – ${creneau.heure_fin.slice(0, 5)}`;
    document.getElementById('cahierResumeMatiere').textContent = creneau.matiere_libelle;
    document.getElementById('cahierResumeClasse').textContent = emploi?.classe_libelle || '-';
    document.getElementById('cahierResumeSalle').textContent = creneau.salle_libelle;
    applyCahierData(cahier);
    if (appState.currentCahierId) {
      await loadCahierSignatures(appState.currentCahierId);
    } else {
      cahierSignedMap = {};
      renderSignatureRoleArea('delegue', currentRole);
      renderSignatureRoleArea('enseignant', currentRole);
      updateCahierCloseState();
    }
    renderSessionList();
  };

  if (creneaux[0]) {
    select.value = creneaux[0].id;
    await syncInfo();
  }
  select?.addEventListener('change', () => { syncInfo(); });
  document.getElementById('cahierApplyFiltersBtn')?.addEventListener('click', async () => {
    const filtered = getFilteredCreneaux();
    if (filtered[0]) {
      select.value = filtered[0].id;
      await syncInfo();
    }
    renderSessionList();
  });
  const isCahierMobile = () => window.innerWidth < 992;

  document.addEventListener('click', (event) => {
    const row = event.target.closest('[data-cahier-creneau]');
    if (!row) return;
    select.value = row.dataset.cahierCreneau;
    syncInfo();
    setCahierView('form');
    if (isCahierMobile()) {
      setCahierLayoutMode('form');
    }
  });

  document.getElementById('cahierBackBtn')?.addEventListener('click', () => {
    setCahierLayoutMode('full');
  });
  document.querySelectorAll('[data-cahier-view-btn]').forEach((button) => {
    button.addEventListener('click', () => setCahierView(button.dataset.cahierViewBtn));
  });
  window.addEventListener('hashchange', applyCahierHashView);
  ['cahierTitreInput', 'cahierPointsInput', 'cahierTravauxInput', 'cahierObservationsInput', 'cahierProgressInput'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', updateCahierPreview);
  });

  function setupPad(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    canvas.dataset.hasInk = '0';
    let drawing = false;
    const position = (event) => {
      const rect = canvas.getBoundingClientRect();
      const source = event.touches?.[0] || event;
      return {
        x: (source.clientX - rect.left) * (canvas.width / rect.width),
        y: (source.clientY - rect.top) * (canvas.height / rect.height),
      };
    };
    const start = (event) => {
      event.preventDefault();
      drawing = true;
      canvas.dataset.hasInk = '1';
      const p = position(event);
      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
    };
    const move = (event) => {
      if (!drawing) return;
      event.preventDefault();
      const p = position(event);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
    };
    const end = () => { drawing = false; };
    ['mousedown', 'touchstart'].forEach((evt) => canvas.addEventListener(evt, start));
    ['mousemove', 'touchmove'].forEach((evt) => canvas.addEventListener(evt, move));
    ['mouseup', 'mouseleave', 'touchend'].forEach((evt) => canvas.addEventListener(evt, end));
    return canvas;
  }

  const isReadOnly = !canEditCahier && !canCloseCahier;

  // Bandeau + masquage des actions pour le surveillant (lecture seule)
  if (isReadOnly) {
    const alertBox = document.getElementById('cahierAlert');
    if (alertBox) {
      alertBox.className = 'alert alert-info mb-3';
      alertBox.innerHTML = '<i class="bi bi-eye me-2"></i><strong>Mode consultation</strong> — Vous consultez les cahiers en lecture seule. Aucune modification n\'est possible.';
      alertBox.classList.remove('d-none');
    }
    const newBtn = document.getElementById('newCahierSessionBtn');
    if (newBtn) newBtn.style.display = 'none';
  }

  const signButton = document.getElementById('signCahierBtn');
  if (signButton) {
    if (isReadOnly) {
      signButton.style.display = 'none';
    } else {
      signButton.disabled = !currentRole || !canEditCahier;
      signButton.title = currentRole && canEditCahier ? 'Ouvrir la zone de signature' : 'Seuls le délégué et l\'enseignant peuvent signer ce cahier.';
    }
  }
  if (saveCahierBtn) {
    if (isReadOnly) {
      saveCahierBtn.style.display = 'none';
    } else {
      saveCahierBtn.disabled = !canEditCahier;
      saveCahierBtn.title = canEditCahier ? 'Enregistrer les modifications du cahier' : 'Seuls le délégué et l\'enseignant peuvent enregistrer le cahier.';
    }
  }
  if (closeCahierBtn) {
    if (isReadOnly) {
      closeCahierBtn.style.display = 'none';
    } else if (!canCloseCahier) {
      closeCahierBtn.disabled = true;
      closeCahierBtn.title = 'Seuls l\'enseignant et l\'administrateur peuvent clôturer ce cahier.';
    }
  }
  if (!canEditCahier) {
    ['cahierTitreInput', 'cahierPointsInput', 'cahierTravauxInput', 'cahierObservationsInput', 'cahierProgressInput'].forEach((id) => {
      const field = document.getElementById(id);
      if (field) field.disabled = true;
    });
  }

  async function saveCurrentCahierSignature() {
    if (!currentRole) {
      showAlert('cahierAlert', 'Connecte-toi avec un compte délégué ou enseignant pour signer ce cahier.', 'warning');
      return;
    }
    if (!appState.currentCahierId) {
      showAlert('cahierAlert', "Enregistre d'abord le cahier avant signature.", 'warning');
      return;
    }
    const pad = appState.signaturePads[currentRole];
    if (!pad || pad.dataset.hasInk !== '1') {
      setCahierView('signatures');
      showAlert('cahierAlert', 'Trace ta signature puis clique sur Enregistrer la signature.', 'warning');
      return;
    }

    try {
      const signedAt = new Date().toISOString();
      const signatureBase64 = pad.toDataURL('image/png');
      const response = await apiClient.post(`/cahiers/${appState.currentCahierId}/signer`, {
        type_signataire: currentRole,
        signature_base64: signatureBase64,
      });
      const returnedSignature = response?.data?.signature || null;
      cahierSignedMap[currentRole] = {
        type_signataire: currentRole,
        signature_base64: returnedSignature?.signature_base64 || signatureBase64,
        signataire_nom: returnedSignature?.signataire_nom || `${user.prenom || ''} ${user.nom || ''}`.trim() || signatureRoleLabel(currentRole),
        horodatage: returnedSignature?.horodatage || signedAt,
      };
      const summaryStatus = document.getElementById('cahierSummaryStatus');
      if (summaryStatus) {
        summaryStatus.textContent = 'Signé';
        summaryStatus.className = 'status-badge success';
      }
      renderSignatureRoleArea('delegue', currentRole);
      renderSignatureRoleArea('enseignant', currentRole);
      updateCahierCloseState();
      showAlert('cahierAlert', 'Signature enregistrée.', 'success');
      await loadCahierSignatures(appState.currentCahierId);
      setCahierView('signatures');
    } catch (error) {
      showAlert('cahierAlert', error.message, 'danger');
    }
  }

  document.getElementById('saveCahierBtn')?.addEventListener('click', async () => {
    if (!canEditCahier) {
      showAlert('cahierAlert', "Seuls le délégué et l'enseignant peuvent enregistrer ce cahier.", 'warning');
      return;
    }
    try {
      const payload = {
        id_creneau: Number(select.value),
        titre_cours: document.getElementById('cahierTitreInput').value,
        points_abordes: document.getElementById('cahierPointsInput').value,
        niveau_avancement: `${document.getElementById('cahierProgressInput').value}%`,
        travaux_demandes: document.getElementById('cahierTravauxInput').value,
        observations: document.getElementById('cahierObservationsInput').value,
        statut: 'brouillon',
      };
      const result = appState.currentCahierId
        ? await apiClient.put(`/cahiers/${appState.currentCahierId}`, payload)
        : await apiClient.post('/cahiers', payload);
      appState.currentCahierId = result.data.id;
      cahiersByCreneau.set(Number(select.value), {
        ...(cahiersByCreneau.get(Number(select.value)) || {}),
        ...payload,
        id: appState.currentCahierId,
      });
      document.getElementById('cahierSummaryStatus').textContent = 'Brouillon';
      document.getElementById('cahierSummaryStatus').className = 'status-badge neutral';
      showAlert('cahierAlert', 'Le cahier de texte a été enregistré en base.', 'info');
      await loadCahierSignatures(appState.currentCahierId);
      renderSessionList();
      setCahierView('resume');
    } catch (error) {
      showAlert('cahierAlert', error.message, 'danger');
    }
  });

  document.getElementById('signCahierBtn')?.addEventListener('click', async () => {
    setCahierView('signatures');
    await saveCurrentCahierSignature();
  });

  document.addEventListener('click', async (event) => {
    const saveSignatureBtn = event.target.closest('[data-save-cahier-signature]');
    if (!saveSignatureBtn) return;
    await saveCurrentCahierSignature();
  });

  closeCahierBtn?.addEventListener('click', async () => {
    if (!canCloseCahier) {
      showAlert('cahierAlert', 'Vous ne pouvez pas clôturer ce cahier avec ce compte.', 'warning');
      return;
    }
    if (!appState.currentCahierId) {
      showAlert('cahierAlert', "Enregistre d'abord le cahier avant clôture.", 'warning');
      return;
    }
    try {
      await apiClient.post(`/cahiers/${appState.currentCahierId}/cloturer`, {});
      document.getElementById('cahierSummaryStatus').textContent = 'Validé';
      document.getElementById('cahierSummaryStatus').className = 'status-badge success';
      const saved = cahiersByCreneau.get(Number(select.value));
      if (saved) {
        saved.statut = 'cloture';
        cahiersByCreneau.set(Number(select.value), saved);
      }
      showAlert('cahierAlert', 'Séance clôturée en base.', 'success');
      setCahierView('history');
      updateCahierCloseState();
      renderSessionList();
    } catch (error) {
      showAlert('cahierAlert', error.message, 'warning');
      setCahierView('signatures');
    }
  });

  async function loadCahierSignatures(cahierId) {
    let signedRows = [];
    const creneauId = Number(select.value || 0);
    const appendUniqueByRole = (rows = []) => {
      rows.forEach((row) => {
        const role = normalizeSignatureRole(row?.type_signataire);
        if (!['delegue', 'enseignant'].includes(role)) return;
        const already = signedRows.find((item) => normalizeSignatureRole(item?.type_signataire) === role);
        if (!already) {
          signedRows.push(row);
          return;
        }
        const currentDate = new Date(already?.horodatage || 0).getTime();
        const incomingDate = new Date(row?.horodatage || 0).getTime();
        if (incomingDate > currentDate) {
          signedRows = signedRows.map((item) =>
            normalizeSignatureRole(item?.type_signataire) === role ? row : item
          );
        }
      });
    };

    // 1) Source principale : signatures de la fiche.
    try {
      const byCahier = await apiClient.get(`/cahiers/${cahierId}/signatures`);
      appendUniqueByRole(byCahier?.data || []);
    } catch (error) {
      // Continue with fallback sources.
    }

    // 2) Fallback robuste : signatures de toute la séance (créneau).
    if (creneauId) {
      try {
        const byCreneau = await apiClient.get(`/cahiers/creneau/${creneauId}/signatures`);
        appendUniqueByRole(byCreneau?.data || []);
      } catch (error) {
        // Keep any rows already found.
      }
    }

    const body = document.getElementById('cahierSignatureStatusBody');
    cahierSignedMap = Object.fromEntries(
      signedRows
        .map((row) => [normalizeSignatureRole(row.type_signataire), row])
        .filter(([role]) => ['delegue', 'enseignant'].includes(role))
    );
    const hasAnySignature = signedRows.length > 0;
    cahierRequiredRules = appState.signatureRules
      .filter((row) => row.document_type === 'cahier' && Number(row.actif))
      .sort((a, b) => Number(a.ordre_validation) - Number(b.ordre_validation));
    if (body) {
      const html = cahierRequiredRules.map((rule) => {
        const signature = cahierSignedMap[rule.role_signataire];
        return `
        <tr>
          <td>${rule.role_signataire}</td>
          <td><span class="status-badge ${signature ? 'success' : 'warning'}">${signature ? 'Signé' : 'En attente'}</span></td>
          <td>${signature?.signataire_nom || '-'}</td>
          <td>${signature ? formatDateTime(signature.horodatage) : '-'}</td>
        </tr>
      `;
      }).join('');
      body.innerHTML = html;
      const mirror = document.getElementById('cahierSignatureStatusBodyMirror');
      if (mirror) mirror.innerHTML = html;
    }
    if (hasAnySignature) {
      const summaryStatus = document.getElementById('cahierSummaryStatus');
      if (summaryStatus && summaryStatus.textContent !== 'Validé') {
        summaryStatus.textContent = 'Signé';
        summaryStatus.className = 'status-badge success';
      }
      const currentSession = cahiersByCreneau.get(Number(select.value));
      if (currentSession && currentSession.statut !== 'cloture') {
        currentSession.statut = 'signe';
        cahiersByCreneau.set(Number(select.value), currentSession);
      }
      const delegueBadge = document.getElementById('cahierDelegueStatusBadge');
      const enseignantBadge = document.getElementById('cahierEnseignantStatusBadge');
      if (delegueBadge && cahierSignedMap.delegue) {
        delegueBadge.textContent = 'Signé';
        delegueBadge.className = 'status-badge success';
      }
      if (enseignantBadge && cahierSignedMap.enseignant) {
        enseignantBadge.textContent = 'Signé';
        enseignantBadge.className = 'status-badge success';
      }
    }
    renderSignatureRoleArea('delegue', currentRole);
    renderSignatureRoleArea('enseignant', currentRole);
    renderResumeSignatures();
    updateCahierCloseState();
  }

  renderSessionList();
  renderSignatureRoleArea('delegue', currentRole);
  renderSignatureRoleArea('enseignant', currentRole);
  renderResumeSignatures();
  setCahierView('form');
  applyCahierHashView();
  updateCahierCloseState();
}

async function initVacationsPage() {
  await loadReferences();
  const currentUser = getStoredUser();
  const isEnseignant = currentUser?.role === 'enseignant';

  // Enseignant sees only their own fiches; filter by teacher is hidden for them
  if (!isEnseignant) {
    setOptions(document.getElementById('vacationTeacherSelect'), [{ id: '', prenom: 'Tous les enseignants', nom: '' }, ...appState.enseignants], (item) => ({
      value: item.id,
      label: `${item.prenom} ${item.nom}`,
    }), false);
  } else {
    const teacherSelect = document.getElementById('vacationTeacherSelect');
    if (teacherSelect) teacherSelect.style.display = 'none';
  }

  // Modale génération : enseignant génère uniquement pour lui-même (pas de select)
  if (isEnseignant) {
    const selectRow = document.getElementById('vacationTeacherSelectModalRow');
    const selfRow = document.getElementById('vacationTeacherSelfRow');
    const selfName = document.getElementById('vacationTeacherSelfName');
    if (selectRow) selectRow.style.display = 'none';
    if (selfRow) selfRow.style.display = '';
    if (selfName) selfName.textContent = `${currentUser.prenom || ''} ${currentUser.nom || ''}`.trim();
  } else {
    setOptions(document.getElementById('vacationTeacherSelectModal'), appState.enseignants, (item) => ({
      value: item.id,
      label: `${item.prenom} ${item.nom}`,
    }), false);
  }

  setOptions(document.getElementById('vacationsClassFilter'), [{ id: '', libelle: 'Toutes les classes' }, ...appState.classes], (item) => ({
    value: item.id,
    label: item.libelle,
  }), false);

  let vacationItems = [];
  let selectedVacationId = null;

  const statusMap = {
    generee: { label: 'En attente contrôle', cls: 'warning' },
    controlee: { label: 'Contrôlée', cls: 'primary' },
    validee: { label: 'Validée Comptable', cls: 'success' },
    payee: { label: 'Payée', cls: 'success' },
  };

  const formatVacationPeriod = (item) => {
    const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return `${months[Math.max(0, Number(item.mois) - 1)] || item.mois} ${item.annee}`;
  };

  const offsetMap = { lundi: 0, mardi: 1, mercredi: 2, jeudi: 3, vendredi: 4, samedi: 5 };
  const formatVacationLineDate = (line) => {
    if (!line.semaine_debut) return formatPointageDayLabel(line.jour);
    const date = new Date(`${line.semaine_debut}T00:00:00`);
    date.setDate(date.getDate() + (offsetMap[line.jour] || 0));
    return date.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' });
  };

  function applyVacationHashView() {
    document.body.classList.remove('vacations-view-list-only', 'vacations-view-validation-only');
    if (window.location.hash === '#list-fiches') {
      document.body.classList.add('vacations-view-list-only');
      return;
    }
    if (window.location.hash === '#validation-fiche') {
      document.body.classList.add('vacations-view-validation-only');
    }
  }

  function clearVacationDetail() {
    document.getElementById('vacDetailNumber').textContent = '-';
    document.getElementById('vacDetailPeriod').textContent = 'Période : -';
    const statusNode = document.getElementById('vacDetailStatus');
    statusNode.textContent = '-';
    statusNode.className = 'status-badge neutral';
    document.getElementById('vacDetailTeacher').textContent = '-';
    document.getElementById('vacDetailClasses').textContent = '-';
    document.getElementById('vacDetailSubjects').textContent = '-';
    document.getElementById('vacDetailHours').textContent = '-';
    document.getElementById('vacDetailRate').textContent = '-';
    document.getElementById('vacDetailAmount').textContent = '-';
    document.getElementById('vacDetailLinesBody').innerHTML = '<tr><td colspan="5" class="text-muted-soft">Aucune fiche sélectionnée.</td></tr>';
  }

  function renderVacationDetail(item) {
    if (!item) return;
    selectedVacationId = Number(item.id);
    const status = statusMap[item.statut] || { label: item.statut, cls: 'neutral' };
    document.getElementById('vacDetailNumber').textContent = `VAC-${String(item.annee).slice(-2)}-${String(item.id).padStart(3, '0')}`;
    document.getElementById('vacDetailPeriod').textContent = `Période : ${formatVacationPeriod(item)}`;
    const statusNode = document.getElementById('vacDetailStatus');
    statusNode.textContent = status.label;
    statusNode.className = `status-badge ${status.cls}`;
    document.getElementById('vacDetailTeacher').textContent = item.enseignant || '-';
    document.getElementById('vacDetailClasses').textContent = item.classes || '-';
    document.getElementById('vacDetailSubjects').textContent = item.matieres || '-';
    document.getElementById('vacDetailHours').textContent = `${item.total_heures || 0}h00`;
    document.getElementById('vacDetailRate').textContent = formatMoney(item.taux_horaire || 0);
    document.getElementById('vacDetailAmount').textContent = formatMoney(item.montant_net || 0);
    document.getElementById('vacDetailLinesBody').innerHTML = (item.lignes || []).map((line) => `
      <tr>
        <td>${formatVacationLineDate(line)}</td>
        <td>${line.heure_debut.slice(0, 5)} – ${line.heure_fin.slice(0, 5)}</td>
        <td>${line.classe}</td>
        <td>${line.matiere}</td>
        <td>${String(line.duree_heures).replace('.00', '')}h00</td>
      </tr>
    `).join('') || '<tr><td colspan="5" class="text-muted-soft">Aucune ligne disponible.</td></tr>';

    // Boutons d'action selon le statut et le rôle de l'utilisateur connecté
    const validationBox = document.getElementById('validation-fiche');
    if (validationBox) {
      const currentUser = getStoredUser();
      const role = currentUser?.role || '';
      const statut = item.statut;
        const validations = item.validations || [];
        const signedRoles = validations.map((val) => val.role_validateur);
        const teacherSigned = signedRoles.includes('enseignant');
        let actionBtn = '';

        if (statut === 'generee' && role === 'enseignant' && !teacherSigned) {
          actionBtn = `<button class="btn btn-primary w-100 mt-3" type="button" data-vac-action="signer" data-vac-id="${item.id}">
            <i class="bi bi-pen me-2"></i>Signer la fiche
          </button>`;
        } else if (statut === 'generee' && teacherSigned && ['administrateur', 'surveillant'].includes(role) && !signedRoles.includes('surveillant')) {
          actionBtn = `<button class="btn btn-primary w-100 mt-3" type="button" data-vac-action="controler" data-vac-id="${item.id}">
            <i class="bi bi-check2-circle me-2"></i>Contrôler la vacation
          </button>`;
        } else if (statut === 'controlee' && ['administrateur', 'comptable'].includes(role)) {
          actionBtn = `<button class="btn btn-primary w-100 mt-3" type="button" data-vac-action="valider" data-vac-id="${item.id}">
            <i class="bi bi-patch-check me-2"></i>Valider (comptable)
          </button>`;
        } else if (statut === 'validee' && ['administrateur', 'comptable'].includes(role)) {
          actionBtn = `<button class="btn btn-success w-100 mt-3" type="button" data-vac-action="approuver" data-vac-id="${item.id}">
            <i class="bi bi-cash-coin me-2"></i>Marquer comme payée
          </button>`;
        }

        const roleLabelMap = {
          enseignant: 'Enseignant',
          surveillant: 'Surveillant',
          comptable: 'Comptable',
        };

        const validationsHtml = validations.length
          ? `<div class="vacation-validation-list mb-3"><strong>Signatures enregistrées :</strong><ul class="mb-0 ps-3">${validations.map((validation) => `
              <li>${roleLabelMap[validation.role_validateur] || validation.role_validateur} • ${validation.validateur_nom || 'Utilisateur inconnu'} • ${formatDateTime(validation.date_validation)}</li>
            `).join('')}</ul></div>`
          : '<div class="vacation-validation-list mb-3 text-muted-soft">Aucune signature enregistrée.</div>';

        const teacherPendingHtml = statut === 'generee' && !teacherSigned
          ? "<div class=\"alert alert-warning mb-3\">Signature de l'enseignant manquante avant contrôle.</div>"
          : '';

        validationBox.innerHTML = `${teacherPendingHtml}${validationsHtml}${actionBtn}`;
      }
    }

  function getFilteredVacations(items) {
    const teacherId = Number(document.getElementById('vacationTeacherSelect').value || 0);
    const classLabel = document.getElementById('vacationsClassFilter').selectedOptions[0]?.textContent || '';
    const status = document.getElementById('vacationsStatusFilter').value;
    return items.filter((item) => {
      if (teacherId && Number(item.id_enseignant) !== teacherId) return false;
      if (status && item.statut !== status) return false;
      if (document.getElementById('vacationsClassFilter').value && !(item.classes || '').includes(classLabel)) return false;
      return true;
    });
  }

  const loadVacations = async () => {
    const result = await apiClient.get('/vacations');
    const summary = result.data.summary || {};
    document.getElementById('vacSummaryHours').textContent = `${summary.total_heures || 0} h`;
    document.getElementById('vacSummaryBrut').textContent = formatMoney(summary.montant_brut || 0);
    document.getElementById('vacSummaryRetenues').textContent = formatMoney(summary.retenues || 0);
    document.getElementById('vacSummaryNet').textContent = formatMoney(summary.montant_net || 0);

    vacationItems = result.data.items || [];
    const filtered = getFilteredVacations(vacationItems);
    const body = document.getElementById('vacationsBody');
    body.innerHTML = filtered.map((item) => {
      const status = statusMap[item.statut] || { label: item.statut, cls: 'neutral' };
      const ref = `VAC-${String(item.annee).slice(-2)}-${String(item.id).padStart(3, '0')}`;
      return `
      <tr data-vacation-id="${item.id}" class="${Number(item.id) === Number(selectedVacationId || filtered[0]?.id) ? 'active' : ''}">
        <td>${ref}</td>
        <td>${item.enseignant}</td>
        <td>${item.classes || '-'}</td>
        <td>${formatVacationPeriod(item)}</td>
        <td>${item.total_heures}h00</td>
        <td>${formatMoney(item.montant_net)}</td>
        <td><span class="status-badge ${status.cls}">${status.label}</span></td>
        <td class="table-actions">
          <button class="btn btn-sm btn-outline-primary" type="button" data-vacation-view="${item.id}" title="Voir la fiche">
            <i class="bi bi-eye"></i> Voir
          </button>
        </td>
      </tr>
    `;
    }).join('');
    document.getElementById('vacationsListCount').textContent = `${filtered.length} fiche(s)`;
    const selected = filtered.find((item) => Number(item.id) === Number(selectedVacationId)) || filtered[0];
    if (selected) {
      renderVacationDetail(selected);
    } else {
      clearVacationDetail();
    }
  };

  document.getElementById('generateVacationBtn')?.addEventListener('click', async () => {
    const payload = {
      mois: Number(document.getElementById('vacationMonthSelect').value),
      annee: Number(document.getElementById('vacationYearSelect').value),
    };
    if (!isEnseignant) {
      payload.id_enseignant = Number(document.getElementById('vacationTeacherSelectModal').value);
    }
    try {
      await apiClient.post('/vacations/generer', payload);
      bootstrap.Modal.getInstance(document.getElementById('ficheModal'))?.hide();
      await loadVacations();
    } catch (e) {
      showAlert('vacationsAlert', e.message || 'Erreur lors de la génération.', 'danger');
    }
  });

  document.getElementById('vacationsApplyFiltersBtn')?.addEventListener('click', loadVacations);
  const openVacationPdf = async () => {
    if (!selectedVacationId) {
      window.alert("Sélectionne d'abord une fiche de vacation.");
      return;
    }
    const result = await apiClient.get(`/vacations/${selectedVacationId}/pdf`);
    const pdfUrl = result.data?.url;
    if (!pdfUrl) {
      throw new Error("Le fichier PDF n'a pas pu être généré.");
    }
    window.open(pdfUrl, '_blank', 'noopener');
  };

  document.getElementById('vacationsExportBtn')?.addEventListener('click', openVacationPdf);
  document.getElementById('vacDetailMoreBtn')?.addEventListener('click', openVacationPdf);

  document.addEventListener('click', async (event) => {
    const actionBtn = event.target.closest('[data-vac-action]');
    if (!actionBtn) return;
    const action = actionBtn.dataset.vacAction;
    const vacId = Number(actionBtn.dataset.vacId);
    if (!vacId) return;
    actionBtn.disabled = true;
    try {
      const currentUser = getStoredUser();
      const role = currentUser?.role || '';
      if (action === 'signer') {
        await apiClient.post(`/vacations/${vacId}/valider`, { role_validateur: 'enseignant' });
      } else if (action === 'controler') {
        await apiClient.post(`/vacations/${vacId}/valider`, { role_validateur: 'surveillant' });
      } else if (action === 'valider') {
        await apiClient.post(`/vacations/${vacId}/valider`, { role_validateur: 'comptable' });
      } else if (action === 'approuver') {
        await apiClient.post(`/vacations/${vacId}/approuver`, {});
      }
      await loadVacations();
    } catch (e) {
      window.alert(e.message || 'Erreur lors de l\'action.');
      actionBtn.disabled = false;
    }
  });

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-vacation-id], [data-vacation-view]');
    if (!trigger) return;
    const id = Number(trigger.dataset.vacationId || trigger.dataset.vacationView);
    selectedVacationId = id;
    const item = vacationItems.find((row) => Number(row.id) === id);
    if (item) {
      renderVacationDetail(item);
      document.querySelectorAll('#vacationsBody tr').forEach((row) => {
        row.classList.toggle('active', Number(row.dataset.vacationId) === id);
      });
    }
  });

  window.addEventListener('hashchange', applyVacationHashView);

  await loadVacations();
  applyVacationHashView();
}

async function initRapportsPage() {
  const result = await apiClient.get('/reports/summary');
  const payload = result.data || {};
  const kpis = payload.kpis || [];

  const triggerDownload = (file) => {
    if (!file?.url) {
      throw new Error('Le fichier à télécharger est introuvable.');
    }
    const link = document.createElement('a');
    link.href = file.url;
    if (file.filename) {
      link.download = file.filename;
    }
    link.target = '_blank';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
  };

  const getCurrentWeekStart = () => {
    const now = new Date();
    const day = now.getDay();
    const offset = day === 0 ? -6 : 1 - day;
    now.setDate(now.getDate() + offset);
    return now.toISOString().slice(0, 10);
  };

  document.getElementById('reportsKpis').innerHTML = kpis.map((item) => `
    <div class="reports-kpi-card">
      <div class="reports-kpi-icon ${item.tone}"><i class="ph-light ${item.icon}"></i></div>
      <div>
        <div class="reports-kpi-label">${item.label}</div>
        <div class="reports-kpi-value">${Number(item.value).toLocaleString('fr-FR')}</div>
        <div class="reports-kpi-trend text-muted">${item.trend}</div>
      </div>
    </div>
  `).join('');

  const lineItems = payload.line || [];
  const totalLine = lineItems.reduce((sum, item) => sum + Number(item.value || 0), 0);
  if (!totalLine) {
    document.getElementById('reportsLineChart').innerHTML = '<div class="text-muted small py-4">Aucune donnée disponible.</div>';
  } else {
    const maxLine = Math.max(...lineItems.map((item) => item.value), 1);
    document.getElementById('reportsLineChart').innerHTML = lineItems.map((item) => `
      <div class="reports-line-point">
        <div class="reports-line-stick" style="--line-height:${Math.max(48, (item.value / maxLine) * 150)}px"></div>
        <div class="reports-line-value">${item.value}</div>
        <div class="reports-line-label">${item.label}</div>
      </div>
    `).join('');
  }

  const donutItems = payload.donut || [];
  const totalLinks = donutItems.reduce((sum, item) => sum + Number(item.value || 0), 0);
  document.getElementById('reportsDonutValue').textContent = totalLinks.toLocaleString('fr-FR');
  document.getElementById('reportsDonutLegend').innerHTML = totalLinks
    ? donutItems.map((item) => `
      <div class="reports-legend-item">
        <span class="reports-legend-dot" style="background:${item.color}"></span>
        <span>${item.label}</span>
        <strong>${item.value.toLocaleString('fr-FR')}</strong>
      </div>
    `).join('')
    : '<div class="text-muted small">Aucune donnée disponible.</div>';

  const barItems = payload.bars || [];
  const totalBar = barItems.reduce((sum, item) => sum + Number(item.value || 0), 0);
  if (!totalBar) {
    document.getElementById('reportsBars').innerHTML = '<div class="text-muted small py-4">Aucune donnée disponible.</div>';
  } else {
    const maxBar = Math.max(...barItems.map((item) => item.value), 1);
    document.getElementById('reportsBars').innerHTML = barItems.map((item) => `
      <div class="reports-hbar-row">
        <span class="reports-hbar-label" title="${item.label}">${item.label}</span>
        <div class="reports-hbar-track">
          <div class="reports-hbar-fill" style="width:${Math.max(2, Math.round((item.value / maxBar) * 100))}%;background:${item.color}"></div>
        </div>
        <span class="reports-hbar-value">${item.value}</span>
      </div>
    `).join('');
  }

  const refreshBtn = document.getElementById('reportsRefreshBtn');
  if (refreshBtn) {
    refreshBtn.onclick = () => initRapportsPage();
  }

  const sessionsBtn = document.getElementById('reportsSessionsPdfBtn');
  if (sessionsBtn) {
    sessionsBtn.onclick = async () => {
      try {
        const week = getCurrentWeekStart();
        const exportResult = await apiClient.get(`/reports/export/sessions?semaine=${encodeURIComponent(week)}`);
        triggerDownload(exportResult.data);
        showAlert('reportsAlert', 'Rapport des séances généré.', 'success');
      } catch (error) {
        showAlert('reportsAlert', error.message, 'warning');
      }
    };
  }

  const vacationsBtn = document.getElementById('reportsVacationsPdfBtn');
  if (vacationsBtn) {
    vacationsBtn.onclick = async () => {
      try {
        const period = document.getElementById('reportsPeriodInput')?.value || 'Période courante';
        const exportResult = await apiClient.get(`/reports/export/vacations?periode=${encodeURIComponent(period)}`);
        triggerDownload(exportResult.data);
        showAlert('reportsAlert', 'Rapport des vacations généré.', 'success');
      } catch (error) {
        showAlert('reportsAlert', error.message, 'warning');
      }
    };
  }

  const excelBtn = document.getElementById('reportsExcelBtn');
  if (excelBtn) {
    excelBtn.onclick = async () => {
      try {
        const exportResult = await apiClient.get('/reports/export/excel');
        triggerDownload(exportResult.data);
        showAlert('reportsAlert', 'Export Excel généré.', 'success');
      } catch (error) {
        showAlert('reportsAlert', error.message, 'warning');
      }
    };
  }

  const referencesBtn = document.getElementById('reportsReferencesPdfBtn');
  if (referencesBtn) {
    referencesBtn.onclick = async () => {
      try {
        const exportResult = await apiClient.get('/reports/export/referentials');
        triggerDownload(exportResult.data);
        showAlert('reportsAlert', 'Rapport des référentiels généré.', 'success');
      } catch (error) {
        showAlert('reportsAlert', error.message, 'warning');
      }
    };
  }
}

async function initPageData() {
  try {
    ensureAuthenticated();
    switch (currentPage()) {
      case 'dashboard':
        await initDashboardPage();
        break;
      case 'parametres':
        await initSettingsPage();
        break;
      case 'emploi':
        await initEmploiPage();
        break;
      case 'pointage':
        await initPointagePage();
        break;
      case 'cahier':
        await initCahierPage();
        break;
      case 'vacations':
        await initVacationsPage();
        break;
      case 'rapports':
        await initRapportsPage();
        break;
      case 'utilisateurs':
        await initUsersPage();
        break;
      default:
        break;
    }
  } catch (error) {
    const alertTargets = ['dashboardAlert', 'emploiAlert', 'pointageAlert', 'cahierAlert', 'vacationsAlert', 'reportsAlert'];
    let shown = false;
    alertTargets.forEach((id) => {
      const node = document.getElementById(id);
      if (node && !shown) {
        showAlert(id, error.message, 'danger');
        shown = true;
      }
    });
    if (!shown && currentPage()) {
      console.error(error);
    }
  }
}

document.addEventListener('eduschedule:ready', initPageData);
