const appPages = {
  dashboard: {
    title: "Dashboard",
    subtitle: "Vue adaptée au rôle connecté."
  },
  emploi: {
    title: "Gestion de l'emploi du temps",
    subtitle: "Planification hebdomadaire des cours, salles et intervenants."
  },
  pointage: {
    title: "Pointage des enseignants par QR Code",
    subtitle: "Validation des présences et contrôle de conformité des séances."
  },
  cahier: {
    title: "Cahier de texte numérique",
    subtitle: "Suivi pédagogique, progression des contenus et validation des séances."
  },
  vacations: {
    title: "Fiches de vacation et paiements",
    subtitle: "Synthèse des heures, montants dus et états de règlement."
  },
  rapports: {
    title: "Rapports",
    subtitle: "Indicateurs d'activité, taux d'exécution et tableaux de suivi."
  },
  utilisateurs: {
    title: "Gestion des utilisateurs",
    subtitle: "Administration des rôles, accès et statuts des comptes."
  },
  parametres: {
    title: "Paramètres",
    subtitle: "Configuration institutionnelle, sécurité et options d'intégration."
  }
};

const sidebarSections = [
  {
    key: "dashboard",
    label: "Dashboard",
    href: "dashboard-admin.html",
    icon: "ph-squares-four",
  },
  {
    key: "emploi",
    label: "Emploi du temps",
    href: "emploi-temps.html",
    icon: "ph-calendar",
  },
  {
    key: "pointage",
    label: "Pointage QR",
    href: "pointage-qr.html",
    icon: "ph-qr-code",
  },
  {
    key: "cahier",
    label: "Cahier de texte",
    href: "cahier-texte.html",
    icon: "ph-notebook",
  },
  {
    key: "vacations",
    label: "Vacations",
    href: "vacations.html",
    icon: "ph-coins",
  },
  {
    key: "rapports",
    label: "Rapports",
    href: "rapports.html",
    icon: "ph-chart-bar",
  },
  {
    key: "administration",
    label: "Administration",
    icon: "ph-sliders-horizontal",
    children: [
      { label: "Utilisateurs", href: "utilisateurs.html", pageKey: "utilisateurs" },
      { label: "Paramètres", href: "parametres.html", pageKey: "parametres" },
    ],
  },
];

const pagePermissionMap = {
  dashboard: "dashboard",
  emploi: "emploi_temps",
  pointage: "pointage",
  cahier: "cahiers",
  vacations: "vacations",
  rapports: "rapports",
  utilisateurs: "utilisateurs",
  parametres: "parametres",
};

const phosphorToBootstrapIconMap = {
  "ph-squares-four": "bi-grid",
  "ph-calendar": "bi-calendar3",
  "ph-calendar-blank": "bi-calendar3",
  "ph-qr-code": "bi-qr-code",
  "ph-notebook": "bi-journal-text",
  "ph-coins": "bi-cash-stack",
  "ph-chart-bar": "bi-bar-chart-line",
  "ph-chart-line": "bi-graph-up",
  "ph-sliders-horizontal": "bi-sliders",
  "ph-sliders": "bi-sliders",
  "ph-circle": "bi-circle",
  "ph-caret-down": "bi-chevron-down",
  "ph-caret-right": "bi-chevron-right",
  "ph-bell": "bi-bell",
  "ph-sign-out": "bi-box-arrow-right",
  "ph-pencil": "bi-pencil",
  "ph-trash": "bi-trash",
  "ph-plus": "bi-plus-lg",
  "ph-floppy-disk": "bi-floppy",
  "ph-eraser": "bi-eraser",
  "ph-lock": "bi-lock",
  "ph-vector-pen": "bi-pen",
  "ph-pen": "bi-pen",
  "ph-pen-nib": "bi-pen",
  "ph-check": "bi-check-lg",
  "ph-check-circle": "bi-check-circle",
  "ph-clock-clockwise": "bi-clock-history",
  "ph-user-check": "bi-person-check",
  "ph-user-minus": "bi-person-dash",
  "ph-users": "bi-people",
  "ph-users-three": "bi-people",
  "ph-user-gear": "bi-person-gear",
  "ph-book-open": "bi-book",
  "ph-book-open-text": "bi-book",
  "ph-hourglass-medium": "bi-hourglass-split",
  "ph-warning": "bi-exclamation-triangle",
  "ph-warning-circle": "bi-exclamation-circle",
  "ph-info": "bi-info-circle",
  "ph-eye": "bi-eye",
  "ph-arrow-right": "bi-arrow-right",
  "ph-arrow-up": "bi-arrow-up",
  "ph-file-text": "bi-file-text",
  "ph-file-pdf": "bi-file-earmark-pdf",
  "ph-table": "bi-table",
  "ph-funnel": "bi-funnel",
  "ph-door": "bi-door-open",
  "ph-shield-check": "bi-shield-check",
  "ph-currency-dollar": "bi-currency-dollar",
  "ph-magnifying-glass": "bi-search",
  "ph-upload-simple": "bi-upload",
  "ph-download-simple": "bi-download",
  "ph-video-camera": "bi-camera-video",
  "ph-arrow-clockwise": "bi-arrow-clockwise",
  "ph-arrows-clockwise": "bi-arrow-repeat",
  "ph-list-checks": "bi-card-checklist",
  "ph-circle-notch": "bi-arrow-repeat",
  "ph-spinner": "bi-arrow-repeat",
  "ph-tree-structure": "bi-diagram-3",
  "ph-user-plus": "bi-person-plus",
  "ph-x": "bi-x",
  "ph-note-pencil": "bi-pencil-square",
};

function applyBootstrapIconTheme(root = document) {
  root.querySelectorAll("i").forEach((icon) => {
    const classes = Array.from(icon.classList);
    const phosphorClass = classes.find((cls) => cls.startsWith("ph-") && cls !== "ph-light" && cls !== "ph-regular" && cls !== "ph-bold" && cls !== "ph-fill");
    if (!phosphorClass) return;

    const mapped = phosphorToBootstrapIconMap[phosphorClass] || "bi-circle";
    classes
      .filter((cls) => cls.startsWith("ph-"))
      .forEach((cls) => icon.classList.remove(cls));
    icon.classList.add("bi", mapped);
  });
}

function getRoleLabel(role) {
  const map = {
    administrateur: "Administrateur",
    enseignant: "Enseignant",
    delegue: "Délégué",
    surveillant: "Surveillant",
    comptable: "Comptable",
    etudiant: "Étudiant",
  };
  return map[role] || (role ? role.charAt(0).toUpperCase() + role.slice(1) : "Utilisateur");
}

function defaultPermissionsByRole(role) {
  const map = {
    administrateur: ["dashboard", "parametres", "utilisateurs", "emploi_temps", "pointage", "cahiers", "vacations", "rapports"],
    enseignant: ["dashboard", "pointage", "cahiers", "vacations", "emploi_temps"],
    delegue: ["dashboard", "cahiers", "emploi_temps"],
    surveillant: ["dashboard", "pointage", "cahiers", "vacations", "rapports"],
    comptable: ["dashboard", "vacations", "rapports"],
    etudiant: ["emploi_temps"],
  };
  return map[role] || [];
}

function normalizePermissions(value) {
  if (Array.isArray(value)) return value;
  if (!value) return [];
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    return [];
  }
}

function hasPermission(user, permission) {
  if (!permission) return true;
  if (user.role === "administrateur") return true;
  return (user.permissions || []).includes(permission);
}

function getDefaultRoute(user = getStoredUser()) {
  const orderedPages = [
    "dashboard",
    "emploi",
    "pointage",
    "cahier",
    "vacations",
    "rapports",
    "utilisateurs",
    "parametres",
  ];

  const firstPage = orderedPages.find((key) => hasPermission(user, pagePermissionMap[key]));
  const matchedSection = sidebarSections.find((section) => section.key === firstPage);
  return matchedSection?.href || "index.html";
}

function canAccessCurrentPage(user = getStoredUser()) {
  const currentKey = document.body?.dataset?.page || "";
  return hasPermission(user, pagePermissionMap[currentKey]);
}

function renderShell() {
  const shell = document.querySelector("[data-app-shell]");
  if (!shell) return;

  const currentKey = document.body.dataset.page || "dashboard";
  const user = getStoredUser();
  const visibleSections = sidebarSections
    .map((section) => {
      const allowedChildren = (section.children || []).filter((child) => {
        const permissionKey = pagePermissionMap[child.pageKey || section.key];
        return hasPermission(user, permissionKey);
      });

      const sectionPermission = pagePermissionMap[section.key];
      const sectionVisible = hasPermission(user, sectionPermission);
      const hasOwnPage = Boolean(section.href);
      const hasChildren = allowedChildren.length > 0;

      if (!hasOwnPage && !hasChildren) {
        return null;
      }

      if (hasOwnPage && !sectionVisible && !hasChildren) {
        return null;
      }

      return {
        ...section,
        children: allowedChildren,
      };
    })
    .filter(Boolean);
  const pageConfig = currentKey === "dashboard"
    ? {
        title: `Dashboard ${getRoleLabel(user.role).toLowerCase()}`,
        subtitle: "Vue adaptée au rôle connecté."
      }
    : (appPages[currentKey] || appPages.dashboard);
  const todayLabel = new Date().toLocaleDateString("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  });
  shell.innerHTML = `
    <nav class="ent-navbar" id="entNavbar">
      <div class="ent-navbar-inner">
        <a class="ent-brand-link" href="dashboard-admin.html">
          <span class="ent-logo-box">
            <img src="assets/img/logo.png" class="ent-logo" alt="EduSchedule Pro">
          </span>
          <span class="ent-brand-name">EduSchedule Pro</span>
        </a>
        <button class="ent-mobile-toggle" type="button" id="entMobileMenuToggle" aria-label="Ouvrir le menu" aria-expanded="false">
          <i class="bi bi-list"></i>
        </button>
        <ul class="ent-nav-list">
          ${visibleSections.map((section) => {
            const isActive = section.key === currentKey || (section.children || []).some((c) => c.pageKey === currentKey);
            const hasChildren = (section.children || []).length > 0;
            if (hasChildren) {
              return `
                <li class="ent-nav-item has-dropdown ${isActive ? "active" : ""}">
                  <button class="ent-nav-root" type="button">
                    <i class="ph-light ${section.icon || "ph-circle"}"></i>
                    <span>${section.label}</span>
                    <i class="ph-light ph-caret-down ent-caret"></i>
                  </button>
                  <ul class="ent-dropdown">
                    ${section.children.map((child) => {
                      const childActive = child.pageKey === currentKey;
                      return `<li><a class="ent-dropdown-item ${childActive ? "active" : ""}" href="${child.href}">${child.label}</a></li>`;
                    }).join("")}
                  </ul>
                </li>
              `;
            }
            return `
              <li class="ent-nav-item ${isActive ? "active" : ""}">
                <a class="ent-nav-root" href="${section.href || "#"}">
                  <i class="ph-light ${section.icon || "ph-circle"}"></i>
                  <span>${section.label}</span>
                </a>
              </li>
            `;
          }).join("")}
        </ul>
        <div class="ent-nav-right">
          <span class="ent-date-chip d-none d-xl-flex">
            <i class="ph-light ph-calendar-blank"></i>
            ${todayLabel}
          </span>
          <button class="ent-hdr-btn" type="button" id="entNotifToggle" aria-label="Notifications">
            <i class="ph-light ph-bell"></i>
            <span class="ent-notif-dot d-none" id="entNotifDot">0</span>
          </button>
          <button class="ent-user-pill" type="button" id="entUserPill">
            <span class="ent-avatar">${user.initials}</span>
            <span class="d-none d-lg-inline">${user.name}</span>
          </button>
        </div>
      </div>
      <div class="ent-mobile-panel d-none" id="entMobilePanel">
        ${visibleSections.map((section) => {
          const isActive = section.key === currentKey || (section.children || []).some((c) => c.pageKey === currentKey);
          const hasChildren = (section.children || []).length > 0;
          if (hasChildren) {
            return `
              <div class="ent-mobile-group">
                <div class="ent-mobile-group-title ${isActive ? "active" : ""}">
                  <i class="ph-light ${section.icon || "ph-circle"}"></i>
                  <span>${section.label}</span>
                </div>
                ${section.children.map((child) => `
                  <a class="ent-mobile-link ${child.pageKey === currentKey ? "active" : ""}" href="${child.href}">
                    ${child.label}
                  </a>
                `).join("")}
              </div>
            `;
          }
          return `
            <a class="ent-mobile-link ${isActive ? "active" : ""}" href="${section.href || "#"}">
              <i class="ph-light ${section.icon || "ph-circle"}"></i>
              <span>${section.label}</span>
            </a>
          `;
        }).join("")}
      </div>
      <div class="notification-popover d-none" id="notificationPopover">
        <div class="notification-popover-head">
          <div>
            <strong>Notifications</strong>
            <span>Actions prioritaires</span>
          </div>
          <a href="rapports.html">Voir toutes</a>
        </div>
        <div class="notification-popover-list" id="notificationPopoverList">
          <div class="text-muted-soft">Aucune notification.</div>
        </div>
      </div>
      <div class="ent-user-dropdown d-none" id="entUserDropdown">
        <div class="ent-ud-info">
          <span class="ent-avatar">${user.initials}</span>
          <div>
            <strong>${user.name}</strong>
            <span>${user.roleLabel}</span>
          </div>
        </div>
        <hr style="margin:.3rem 0;border-color:var(--esp-border);">
        <button class="ent-ud-btn" type="button" data-logout>
          <i class="ph-light ph-sign-out"></i> Déconnexion
        </button>
      </div>
    </nav>
    <main class="app-main ent-main-full" id="appMainContent"></main>
  `;

  if (shell._contentNode) {
    const main = document.getElementById("appMainContent");
    if (main) {
      main.append(...Array.from(shell._contentNode.children));
    }
  }
  applyBootstrapIconTheme(shell);
}

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
    // Ignorer si le stockage est bloqué
  }
}

function removeLocalStorageItem(key) {
  try {
    localStorage.removeItem(key);
  } catch (error) {
    // Ignorer si le stockage est bloqué
  }
}

function getStoredUser() {
  try {
    const raw = getLocalStorageItem("eduschedule_user");
    const parsed = raw ? JSON.parse(raw) : null;
    const first = parsed?.prenom || "Admin";
    const last = parsed?.nom || "ISGE";
    const role = parsed?.role || "administrateur";
    return {
      name: `${first} ${last}`.trim(),
      initials: `${String(first).charAt(0)}${String(last).charAt(0)}`.toUpperCase(),
      role: role,
      roleLabel: getRoleLabel(role),
      permissions: normalizePermissions(parsed?.permissions).length
        ? normalizePermissions(parsed?.permissions)
        : defaultPermissionsByRole(role),
    };
  } catch (error) {
    return {
      name: "Admin ISGE",
      initials: "AI",
      role: "administrateur",
      roleLabel: "Administrateur",
      permissions: defaultPermissionsByRole("administrateur"),
    };
  }
}

function moveContentIntoShell() {
  const shell = document.querySelector("[data-app-shell]");
  const pageContent = document.querySelector("[data-page-content]");
  if (!shell || !pageContent) return;
  pageContent.classList.remove("d-none");
  shell._contentNode = pageContent;
  pageContent.remove();
}

function getSidebarCollapsed() {
  return getLocalStorageItem("eduschedule_sidebar_collapsed") === "1";
}

function getExpandedGroups(currentKey) {
  const stored = getLocalStorageItem("eduschedule_sidebar_groups");
  if (stored) {
    try {
      return JSON.parse(stored);
    } catch (error) {
      return sidebarSections.filter((section) => section.key === currentKey).map((section) => section.key);
    }
  }
  return sidebarSections.filter((section) => section.key === currentKey).map((section) => section.key);
}

function saveExpandedGroups(keys) {
  setLocalStorageItem("eduschedule_sidebar_groups", JSON.stringify(keys));
}

function bindSidebar() {
  const syncSidebarHashState = () => {
    const currentKey = document.body.dataset.page || "";
    const currentHash = window.location.hash || "";
    document.querySelectorAll(".sidebar-tree-child[data-page-key]").forEach((node) => {
      const pageKey = node.getAttribute("data-page-key") || "";
      const hash = node.getAttribute("data-hash") || "";
      const isActive = pageKey === currentKey && (!hash || hash === currentHash);
      node.classList.toggle("active", isActive);
    });
  };

  syncSidebarHashState();
  window.addEventListener("hashchange", syncSidebarHashState);
}

function bindNotificationPopover() {
  const popover = document.getElementById("notificationPopover");
  const toggleBtn = document.getElementById("entNotifToggle");
  if (!popover || !toggleBtn) return;

  toggleBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    popover.classList.toggle("d-none");
  });

  document.addEventListener("click", (event) => {
    if (popover.classList.contains("d-none")) return;
    if (popover.contains(event.target) || toggleBtn.contains(event.target)) return;
    popover.classList.add("d-none");
  });
}

function setNotifications(items = []) {
  const list = document.getElementById("notificationPopoverList");
  const badge = document.getElementById("entNotifDot");
  if (badge) {
    const total = Array.isArray(items) ? items.length : 0;
    badge.textContent = `${Math.min(total, 9)}`;
    badge.classList.toggle("d-none", total === 0);
  }
  if (!list) return;
  if (!items.length) {
    list.innerHTML = `<div class="text-muted-soft">Aucune notification.</div>`;
    return;
  }

  list.innerHTML = items.map((item, index) => `
    <div class="notification-item ${item.tone || "warning"}">
      <span class="notification-accent"></span>
      <div class="notification-content">
        <strong>${item.label || "Notification"}</strong>
        <span>${item.detail || ""}</span>
      </div>
      <div class="notification-meta">${index === 0 ? "Il y a 1 h" : index === 1 ? "+35 min" : `Il y a ${index + 1} h`}</div>
      <a class="btn btn-sm btn-outline-primary" href="${item.href || "rapports.html"}">Voir</a>
    </div>
  `).join("");
}

function bindEntHeader() {
  // Dropdowns de navigation horizontale
  document.querySelectorAll(".ent-nav-item.has-dropdown").forEach((item) => {
    const btn = item.querySelector(".ent-nav-root");
    if (!btn) return;
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const isOpen = item.classList.contains("open");
      document.querySelectorAll(".ent-nav-item.open").forEach((el) => el.classList.remove("open"));
      if (!isOpen) item.classList.add("open");
    });
  });
  document.addEventListener("click", () => {
    document.querySelectorAll(".ent-nav-item.open").forEach((el) => el.classList.remove("open"));
  });

  // Menu déroulant utilisateur
  const userPill = document.getElementById("entUserPill");
  const userDropdown = document.getElementById("entUserDropdown");
  if (userPill && userDropdown) {
    userPill.addEventListener("click", (e) => {
      e.stopPropagation();
      userDropdown.classList.toggle("d-none");
    });
    document.addEventListener("click", (e) => {
      if (userDropdown.classList.contains("d-none")) return;
      if (userDropdown.contains(e.target) || userPill.contains(e.target)) return;
      userDropdown.classList.add("d-none");
    });
  }

  const mobileToggle = document.getElementById("entMobileMenuToggle");
  const mobilePanel = document.getElementById("entMobilePanel");
  if (mobileToggle && mobilePanel) {
    const closeMobilePanel = () => {
      mobilePanel.classList.add("d-none");
      mobileToggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("ent-mobile-open");
    };
    mobileToggle.addEventListener("click", (event) => {
      event.stopPropagation();
      const isHidden = mobilePanel.classList.contains("d-none");
      mobilePanel.classList.toggle("d-none", !isHidden);
      mobileToggle.setAttribute("aria-expanded", isHidden ? "true" : "false");
      document.body.classList.toggle("ent-mobile-open", isHidden);
    });
    mobilePanel.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => closeMobilePanel());
    });
    document.addEventListener("click", (event) => {
      if (mobilePanel.classList.contains("d-none")) return;
      if (mobilePanel.contains(event.target) || mobileToggle.contains(event.target)) return;
      closeMobilePanel();
    });
    window.addEventListener("resize", () => {
      if (window.innerWidth >= 992) {
        closeMobilePanel();
      }
    });
  }
}

function bindDemoActions() {
  document.querySelectorAll("[data-demo-message]").forEach((button) => {
    button.addEventListener("click", () => {
      const targetId = button.getAttribute("data-demo-target");
      const alertText = button.getAttribute("data-demo-message");
      if (!targetId || !alertText) return;
      const alertBox = document.getElementById(targetId);
      if (!alertBox) return;
      alertBox.classList.remove("d-none");
      alertBox.querySelector(".demo-alert-text").textContent = alertText;
    });
  });
}

function updateYear() {
  document.querySelectorAll("[data-current-year]").forEach((node) => {
    node.textContent = new Date().getFullYear();
  });
}

function bindLogout() {
  document.querySelectorAll("[data-logout]").forEach((button) => {
    button.addEventListener("click", () => {
      removeLocalStorageItem("eduschedule_token");
      removeLocalStorageItem("eduschedule_user");
      window.location.href = "index.html";
    });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  const user = getStoredUser();
  if (document.body.dataset.page && !canAccessCurrentPage(user)) {
    window.location.href = getDefaultRoute(user);
    return;
  }
  moveContentIntoShell();
  renderShell();
  bindSidebar();
  bindEntHeader();
  bindNotificationPopover();
  bindDemoActions();
  bindLogout();
  updateYear();
  applyBootstrapIconTheme(document);
  const iconObserver = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === 1) {
          applyBootstrapIconTheme(node);
        }
      });
    }
  });
  iconObserver.observe(document.body, { childList: true, subtree: true });
  document.dispatchEvent(new CustomEvent("eduschedule:ready"));
});

window.EduScheduleProShell = {
  setNotifications,
};

window.EduScheduleProAccess = {
  getDefaultRoute,
};
