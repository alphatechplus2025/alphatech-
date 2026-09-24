// ==========================================================================
// ALPHATECH+ ZOHO-CRM ADMIN PANEL & PROJECT CMS ENGINE
// Real-time Firestore Sync, Pipeline Kanban, Project CMS Editor, Cloudinary Upload, CSV Export
// ==========================================================================

import { 
  db, 
  collection, 
  onSnapshot, 
  doc, 
  setDoc, 
  updateDoc, 
  deleteDoc, 
  query, 
  orderBy, 
  serverTimestamp 
} from "./firebase-config.js";

// MASTER PASSCODE (Default: alpha2026)
const MASTER_PASSCODE = 'alpha2026';

// CLOUDINARY CONFIGURATION (From user Cloudinary Console)
export const CLOUDINARY_CONFIG = {
  cloudName: 'ia0hqhoi',
  uploadPreset: 'Alpha Tech +'
};

// GLOBAL STATE
window.allLeads = [];
window.allProjects = [];
window.currentFilter = 'all';
window.currentView = 'table'; // 'table' | 'kanban' | 'projects'
window.activeLeadId = null;
window.editingProjectId = null;

// DEFAULT SEED PROJECTS
export const DEFAULT_PROJECTS = [
  {
    id: "reliefmind",
    title: "ReliefMind Triage",
    category: "ai",
    categoryLabel: "AI · HealthTech",
    status: "Live System",
    metric: "99.4% Accuracy",
    image: "assets/images/project-reliefmind.png",
    description: "AI-powered bystander emergency triage system using LLM-driven symptom extraction, START disaster protocol alignment, and real-time Google Maps hospital locator.",
    techStack: "Gemini AI, Google Maps API, START Protocol, Node.js"
  },
  {
    id: "myhealth",
    title: "MyHealth+ AI Health Analyzer",
    category: "ai",
    categoryLabel: "AI · Healthcare",
    status: "Deployed",
    metric: "Interactive AI",
    image: "assets/images/project-myhealth.png",
    description: "Full-stack health analyzer powered by Google Gemini AI for NLP-based symptom analysis, AI health assistant chatbot, searchable medicine database, and nearby facility locator.",
    techStack: "Gemini Pro, NLP Extraction, Medicine DB, Express.js"
  },
  {
    id: "eduvoice",
    title: "EduVoice AI Voice Counselor",
    category: "ai enterprise",
    categoryLabel: "AI · EdTech",
    status: "Live Prototype",
    metric: "Voice + Text",
    image: "assets/images/project-eduvoice.png",
    description: "Multilingual AI voice admission counselor supporting English, Tamil, and Tanglish — delivers real-time college recommendations, cutoff analysis, and scholarship guidance.",
    techStack: "Voice AI, Tamil & Tanglish NLP, Cutoff Engine, WebSocket"
  },
  {
    id: "flyggo",
    title: "Flyggo Bus Booking Platform",
    category: "web",
    categoryLabel: "Full-Stack · Travel",
    status: "Production Grade",
    metric: "Real-Time Sync",
    image: "assets/images/project-flyggo.png",
    description: "Complete online bus ticket booking system with interactive seat selection maps, route search engine, admin dashboard with revenue analytics, and Firebase authentication.",
    techStack: "Interactive Seat Map, Firebase Auth, Revenue CRM, REST API"
  },
  {
    id: "college-crm",
    title: "College CRM Management System",
    category: "enterprise",
    categoryLabel: "Enterprise · EdTech",
    status: "Multi-Portal ERP",
    metric: "Role-Based Auth",
    image: "assets/images/project-college-crm.png",
    description: "Multi-role institutional CRM with dedicated Admin, Faculty, Student, and Parent portals — featuring OTP authentication, library management, and exam scheduling.",
    techStack: "4 User Roles, OTP Auth, Exam Scheduler, Attendance Engine"
  },
  {
    id: "workconnect",
    title: "WorkConnect Service Platform",
    category: "web",
    categoryLabel: "Full-Stack · Marketplace",
    status: "Marketplace",
    metric: "Multi-Tenant",
    image: "assets/images/project-workconnect.png",
    description: "Local service marketplace connecting customers with verified professionals — featuring 3-role dashboards, real-time booking workflow, and glassmorphism UI.",
    techStack: "3-Role Dashboard, Service Verification, Glassmorphism UI, Booking Flow"
  }
];

/* ---------- AUTH / PASSCODE GATE ---------- */
window.unlockCRM = function() {
  const pin = document.getElementById('passcodeInput')?.value.trim();
  if (pin === MASTER_PASSCODE) {
    sessionStorage.setItem('alpha_crm_auth', 'true');
    const lockScreen = document.getElementById('lockScreen');
    if (lockScreen) lockScreen.style.display = 'none';
  } else {
    const lockError = document.getElementById('lockError');
    if (lockError) lockError.style.display = 'block';
  }
};

window.lockCRM = function() {
  sessionStorage.removeItem('alpha_crm_auth');
  const lockScreen = document.getElementById('lockScreen');
  if (lockScreen) lockScreen.style.display = 'flex';
  const passcodeInput = document.getElementById('passcodeInput');
  if (passcodeInput) passcodeInput.value = '';
};

if (sessionStorage.getItem('alpha_crm_auth') === 'true') {
  const lockScreen = document.getElementById('lockScreen');
  if (lockScreen) lockScreen.style.display = 'none';
}

/* ---------- 1. FIRESTORE REAL-TIME LEADS LISTENER ---------- */
function initLeadsListener() {
  try {
    const q = query(collection(db, 'leads'), orderBy('createdAt', 'desc'));
    onSnapshot(q, (snapshot) => {
      const leads = [];
      snapshot.forEach(docSnap => {
        leads.push({ id: docSnap.id, ...docSnap.data() });
      });
      window.allLeads = leads;
      updateLeadsUI();
    }, (error) => {
      console.error("Firestore error reading leads:", error);
    });
  } catch (err) {
    console.error("Listener error:", err);
  }
}

/* ---------- 2. FIRESTORE REAL-TIME PROJECTS LISTENER ---------- */
function initProjectsListener() {
  try {
    const q = collection(db, 'projects');
    onSnapshot(q, (snapshot) => {
      const projs = [];
      snapshot.forEach(docSnap => {
        projs.push({ id: docSnap.id, ...docSnap.data() });
      });

      if (projs.length > 0) {
        window.allProjects = projs;
      } else {
        window.allProjects = DEFAULT_PROJECTS;
      }
      renderProjectsCMS();
    }, (error) => {
      console.warn("Using default local projects seed:", error);
      window.allProjects = DEFAULT_PROJECTS;
      renderProjectsCMS();
    });
  } catch (err) {
    console.error("Projects listener error:", err);
    window.allProjects = DEFAULT_PROJECTS;
    renderProjectsCMS();
  }
}

initLeadsListener();
initProjectsListener();

/* ---------- LEADS UI RENDERING ---------- */
function updateLeadsUI() {
  const leads = window.allLeads;

  const kpiTotal = document.getElementById('kpiTotal');
  const sidebarCount = document.getElementById('sidebarCount');
  const kpiNew = document.getElementById('kpiNew');
  const kpiDiscussion = document.getElementById('kpiDiscussion');
  const kpiWon = document.getElementById('kpiWon');

  if (kpiTotal) kpiTotal.innerText = leads.length;
  if (sidebarCount) sidebarCount.innerText = leads.length;
  if (kpiNew) kpiNew.innerText = leads.filter(l => (l.status || 'new') === 'new').length;
  if (kpiDiscussion) kpiDiscussion.innerText = leads.filter(l => l.status === 'discussion').length;
  if (kpiWon) kpiWon.innerText = leads.filter(l => l.status === 'won').length;

  renderActiveLeadsView();
}

function getFilteredLeads() {
  const term = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
  let list = window.allLeads;

  if (window.currentFilter === 'new') list = list.filter(l => (l.status || 'new') === 'new');
  else if (window.currentFilter === 'contacted') list = list.filter(l => l.status === 'contacted');
  else if (window.currentFilter === 'discussion') list = list.filter(l => l.status === 'discussion');
  else if (window.currentFilter === 'won') list = list.filter(l => l.status === 'won');
  else if (window.currentFilter === '5999') list = list.filter(l => (l.service || '').includes('5,999') || (l.brief || '').includes('5999'));

  if (term) {
    list = list.filter(l => 
      (l.name || l.fullName || '').toLowerCase().includes(term) ||
      (l.phone || '').toLowerCase().includes(term) ||
      (l.email || '').toLowerCase().includes(term) ||
      (l.service || '').toLowerCase().includes(term) ||
      (l.budget || '').toLowerCase().includes(term) ||
      (l.brief || l.message || '').toLowerCase().includes(term)
    );
  }

  return list;
}

function renderActiveLeadsView() {
  const leads = getFilteredLeads();
  const label = document.getElementById('filteredCountLabel');
  if (label) label.innerText = `Showing ${leads.length} of ${window.allLeads.length} leads`;

  if (window.currentView === 'table') {
    renderTable(leads);
  } else if (window.currentView === 'kanban') {
    renderKanban(leads);
  }
}

// Render Table
function renderTable(leads) {
  const tbody = document.getElementById('leadsTableBody');
  if (!tbody) return;

  if (!leads.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center; padding:40px; color:var(--text-dim);">
          <i class="bi bi-inbox" style="font-size:26px;"></i>
          <div style="margin-top:6px;">No leads matching current search/filter.</div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = leads.map(l => {
    const name = l.name || l.fullName || 'Anonymous';
    const phone = l.phone || 'N/A';
    const email = l.email && l.email !== 'Not provided' ? `<div style="font-size:11.5px; color:var(--text-dim);">${escapeHtml(l.email)}</div>` : '';
    const service = l.service || 'General Project';
    const budget = l.budget ? `<div style="font-size:11.5px; color:var(--accent); font-weight:600;">${escapeHtml(l.budget)}</div>` : '';
    const status = l.status || 'new';
    const source = l.source || l.utm_source || 'website';
    const dateStr = l.submittedAt ? new Date(l.submittedAt).toLocaleDateString('en-IN', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : 'Recently';

    const cleanPhone = phone.replace(/[^0-9]/g, '');
    const waMsg = encodeURIComponent(`Hi ${name}! This is AlphaTech+ regarding your inquiry for ${service}. How can we assist you today?`);

    return `
      <tr>
        <td>
          <div class="lead-name-box">
            <span class="lead-name">${escapeHtml(name)}</span>
            <span class="lead-date">${dateStr}</span>
          </div>
        </td>
        <td>
          <span style="font-family:var(--font-mono); font-size:13px;">${escapeHtml(phone)}</span>
          ${email}
        </td>
        <td>
          <span style="font-weight:600; color:var(--text);">${escapeHtml(service)}</span>
          ${budget}
        </td>
        <td>
          <span class="status-badge ${status}">${getStatusLabel(status)}</span>
        </td>
        <td>
          <span style="font-size:12px; color:var(--text-muted); background:rgba(255,255,255,0.05); padding:3px 8px; border-radius:6px;">${escapeHtml(source)}</span>
        </td>
        <td style="font-size:12.5px; color:var(--text-dim);">${dateStr}</td>
        <td style="text-align:right;">
          <div class="action-btn-group" style="justify-content:flex-end;">
            <a href="https://wa.me/${cleanPhone}?text=${waMsg}" target="_blank" class="btn-icon wa" title="Chat on WhatsApp"><i class="bi bi-whatsapp"></i></a>
            <a href="tel:${cleanPhone}" class="btn-icon call" title="Call Client"><i class="bi bi-telephone"></i></a>
            <button class="btn-icon view" onclick="openLeadDrawer('${l.id}')" title="View Full Details"><i class="bi bi-eye"></i></button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// Render Kanban
function renderKanban(leads) {
  const cols = {
    new: document.getElementById('kanbanColNew'),
    contacted: document.getElementById('kanbanColContacted'),
    discussion: document.getElementById('kanbanColDiscussion'),
    won: document.getElementById('kanbanColWon')
  };

  Object.values(cols).forEach(c => { if(c) c.innerHTML = ''; });
  const counts = { new: 0, contacted: 0, discussion: 0, won: 0 };

  leads.forEach(l => {
    let status = l.status || 'new';
    if (!cols[status]) status = 'new';
    counts[status]++;

    const name = l.name || l.fullName || 'Anonymous';
    const service = l.service || 'General Project';
    const brief = l.brief || l.message || 'No description entered.';
    const dateStr = l.submittedAt ? new Date(l.submittedAt).toLocaleDateString('en-IN', { month:'short', day:'numeric' }) : 'Recent';

    const card = document.createElement('div');
    card.className = 'lead-card';
    card.onclick = () => window.openLeadDrawer(l.id);
    card.innerHTML = `
      <div class="lead-card-header">
        <span class="lead-card-name">${escapeHtml(name)}</span>
        <span class="status-badge ${status}">${getStatusLabel(status)}</span>
      </div>
      <div class="lead-card-service">${escapeHtml(service)}</div>
      <div class="lead-card-brief">${escapeHtml(brief)}</div>
      <div class="lead-card-footer">
        <span><i class="bi bi-phone"></i> ${escapeHtml(l.phone || '')}</span>
        <span>${dateStr}</span>
      </div>
    `;
    if (cols[status]) cols[status].appendChild(card);
  });

  if (document.getElementById('countColNew')) document.getElementById('countColNew').innerText = counts.new;
  if (document.getElementById('countColContacted')) document.getElementById('countColContacted').innerText = counts.contacted;
  if (document.getElementById('countColDiscussion')) document.getElementById('countColDiscussion').innerText = counts.discussion;
  if (document.getElementById('countColWon')) document.getElementById('countColWon').innerText = counts.won;
}

function getStatusLabel(s) {
  switch(s) {
    case 'new': return 'New';
    case 'contacted': return 'Contacted';
    case 'discussion': return 'Discussion';
    case 'won': return 'Won';
    case 'lost': return 'Lost';
    default: return 'New';
  }
}

function escapeHtml(str) {
  return String(str || '').replace(/[&<>"']/g, function(m) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
  });
}

/* ---------- LEAD DRAWER ---------- */
window.openLeadDrawer = function(leadId) {
  const lead = window.allLeads.find(l => l.id === leadId);
  if (!lead) return;

  window.activeLeadId = leadId;
  const nameEl = document.getElementById('drawerName');
  const phoneEl = document.getElementById('drawerPhone');
  const serviceEl = document.getElementById('drawerService');
  const statusEl = document.getElementById('drawerStatusSelect');
  const briefEl = document.getElementById('drawerBrief');
  const metaEl = document.getElementById('drawerMeta');
  const notesEl = document.getElementById('drawerNotesInput');
  const logEl = document.getElementById('drawerNotesLog');

  if (nameEl) nameEl.innerText = lead.name || lead.fullName || 'Lead Details';
  if (phoneEl) phoneEl.innerText = `${lead.phone || 'N/A'} • ${lead.email || 'No email'}`;
  if (serviceEl) serviceEl.innerText = `${lead.service || 'General Project'} (Budget: ${lead.budget || 'Not specified'})`;
  if (statusEl) statusEl.value = lead.status || 'new';
  if (briefEl) briefEl.innerText = lead.brief || lead.message || 'No requirement brief provided.';

  if (metaEl) {
    metaEl.innerHTML = `
      Source: ${lead.source || 'website'}<br>
      UTM Source: ${lead.utm_source || 'direct'}<br>
      UTM Campaign: ${lead.utm_campaign || 'none'}<br>
      Submitted: ${lead.submittedAt || 'N/A'}
    `;
  }

  if (notesEl) notesEl.value = lead.adminNotes || '';
  if (logEl) logEl.innerText = lead.notesUpdatedAt ? `Last saved: ${new Date(lead.notesUpdatedAt).toLocaleString()}` : '';

  document.getElementById('drawerOverlay')?.classList.add('active');
  document.getElementById('leadDrawer')?.classList.add('active');
};

window.closeDrawer = function() {
  document.getElementById('drawerOverlay')?.classList.remove('active');
  document.getElementById('leadDrawer')?.classList.remove('active');
  window.activeLeadId = null;
};

window.updateLeadStatusFromDrawer = async function() {
  if (!window.activeLeadId) return;
  const newStatus = document.getElementById('drawerStatusSelect')?.value;
  try {
    const leadRef = doc(db, 'leads', window.activeLeadId);
    await updateDoc(leadRef, {
      status: newStatus,
      statusUpdatedAt: new Date().toISOString()
    });
    console.log(`[CRM] Lead status updated: ${newStatus}`);
  } catch (err) {
    console.error("Error updating status:", err);
    alert("Failed to update status. Check Firestore rules.");
  }
};

window.saveDrawerNotes = async function() {
  if (!window.activeLeadId) return;
  const notes = document.getElementById('drawerNotesInput')?.value.trim();
  try {
    const leadRef = doc(db, 'leads', window.activeLeadId);
    await updateDoc(leadRef, {
      adminNotes: notes,
      notesUpdatedAt: new Date().toISOString()
    });
    const logEl = document.getElementById('drawerNotesLog');
    if (logEl) logEl.innerText = `Last saved: Just now`;
    alert("Follow-up notes saved successfully!");
  } catch (err) {
    console.error("Error saving notes:", err);
    alert("Failed to save notes.");
  }
};

window.sendWhatsAppTemplate = function(type) {
  if (!window.activeLeadId) return;
  const lead = window.allLeads.find(l => l.id === window.activeLeadId);
  if (!lead || !lead.phone) return;

  const cleanPhone = lead.phone.replace(/[^0-9]/g, '');
  const name = lead.name || lead.fullName || 'there';
  const service = lead.service || 'your project';

  let msg = '';
  if (type === 'intro') {
    msg = `Hello ${name}! 👋\n\nThank you for reaching out to AlphaTech+. We received your inquiry regarding *${service}*.\n\nOur team has reviewed your brief. When would be a convenient time for a quick 5-minute call today?`;
  } else if (type === '5999') {
    msg = `Hi ${name}! 🎉\n\nRegarding the *₹5,999 Launch Package* you reserved:\n✓ Custom Domain & Managed Hosting\n✓ Admin Dashboard & Lead Capture\n✓ Full Source Code Ownership\n✓ 30-Hour Express Turnaround\n\nShall we share our onboarding checklist so we can get started right away?`;
  } else if (type === 'meet') {
    msg = `Hi ${name}!\n\nWe would love to schedule a brief Google Meet demo to present our live project architecture for *${service}*.\n\nPlease let us know if 11:00 AM or 4:00 PM works better for you tomorrow.`;
  }

  window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`, '_blank');
};

/* ---------- VIEW SWITCHING ---------- */
window.switchView = function(view) {
  window.currentView = view;
  
  document.getElementById('viewTableBtn')?.classList.toggle('active', view === 'table');
  document.getElementById('viewKanbanBtn')?.classList.toggle('active', view === 'kanban');
  document.getElementById('viewProjectsBtn')?.classList.toggle('active', view === 'projects');

  const tableView = document.getElementById('tableView');
  const kanbanView = document.getElementById('kanbanView');
  const projectsView = document.getElementById('projectsView');
  const filterBar = document.getElementById('filterBar');
  const kpiGrid = document.querySelector('.kpi-grid');

  if (view === 'table') {
    if (tableView) tableView.style.display = 'block';
    if (kanbanView) kanbanView.style.display = 'none';
    if (projectsView) projectsView.style.display = 'none';
    if (filterBar) filterBar.style.display = 'flex';
    if (kpiGrid) kpiGrid.style.display = 'grid';
    renderActiveLeadsView();
  } else if (view === 'kanban') {
    if (tableView) tableView.style.display = 'none';
    if (kanbanView) kanbanView.style.display = 'grid';
    if (projectsView) projectsView.style.display = 'none';
    if (filterBar) filterBar.style.display = 'flex';
    if (kpiGrid) kpiGrid.style.display = 'grid';
    renderActiveLeadsView();
  } else if (view === 'projects') {
    if (tableView) tableView.style.display = 'none';
    if (kanbanView) kanbanView.style.display = 'none';
    if (projectsView) projectsView.style.display = 'block';
    if (filterBar) filterBar.style.display = 'none';
    if (kpiGrid) kpiGrid.style.display = 'none';
    renderProjectsCMS();
  }
};

window.setFilter = function(filter, btn) {
  window.currentFilter = filter;
  document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');
  renderActiveLeadsView();
};

window.handleSearch = function() {
  renderActiveLeadsView();
};

window.refreshLeads = function() {
  updateLeadsUI();
};

/* ---------- CSV EXPORT ---------- */
window.exportLeadsCSV = function() {
  const leads = window.allLeads;
  if (!leads.length) {
    alert("No leads available to export.");
    return;
  }

  let csv = "ID,Name,Phone,Email,Service,Budget,Status,Source,UTM_Source,UTM_Campaign,SubmittedAt,Brief,AdminNotes\n";
  leads.forEach(l => {
    const row = [
      `"${l.id}"`,
      `"${(l.name || l.fullName || '').replace(/"/g, '""')}"`,
      `"${(l.phone || '').replace(/"/g, '""')}"`,
      `"${(l.email || '').replace(/"/g, '""')}"`,
      `"${(l.service || '').replace(/"/g, '""')}"`,
      `"${(l.budget || '').replace(/"/g, '""')}"`,
      `"${(l.status || 'new')}"`,
      `"${(l.source || 'website')}"`,
      `"${(l.utm_source || 'direct')}"`,
      `"${(l.utm_campaign || 'none')}"`,
      `"${(l.submittedAt || '')}"`,
      `"${(l.brief || l.message || '').replace(/"/g, '""')}"`,
      `"${(l.adminNotes || '').replace(/"/g, '""')}"`
    ];
    csv += row.join(",") + "\n";
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `alphatech_crm_leads_${new Date().toISOString().slice(0,10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
};

/* ==========================================================================
   CLOUDINARY IMAGE UPLOAD ENGINE
   ========================================================================== */

window.handleProjectImageUpload = async function(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const statusEl = document.getElementById('uploadStatusText');
  const inputEl = document.getElementById('projImageInput');
  const previewBox = document.getElementById('projImagePreviewBox');
  const previewImg = document.getElementById('projImagePreview');

  if (statusEl) {
    statusEl.innerHTML = `<i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite; display:inline-block; margin-right:4px;"></i> Uploading to Cloudinary...`;
    statusEl.style.color = '#F59E0B';
  }

  try {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_preset', CLOUDINARY_CONFIG.uploadPreset);

    const res = await fetch(`https://api.cloudinary.com/v1_1/${CLOUDINARY_CONFIG.cloudName}/image/upload`, {
      method: 'POST',
      body: formData
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error?.message || 'Upload failed');
    }

    const data = await res.json();
    const secureUrl = data.secure_url;

    if (inputEl) inputEl.value = secureUrl;
    if (previewImg) previewImg.src = secureUrl;
    if (previewBox) previewBox.style.display = 'block';

    if (statusEl) {
      statusEl.innerHTML = `<i class="bi bi-check-circle-fill"></i> Uploaded!`;
      statusEl.style.color = '#10B981';
    }
  } catch (err) {
    console.error('Cloudinary upload error:', err);
    if (statusEl) {
      statusEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Upload error: ${err.message}`;
      statusEl.style.color = '#EF4444';
    }
  }
};

/* ==========================================================================
   PROJECTS CMS MANAGER (FRONTEND PROJECT EDIT)
   ========================================================================== */

function renderProjectsCMS() {
  const grid = document.getElementById('projectsCmsGrid');
  if (!grid) return;

  const projects = window.allProjects;
  grid.innerHTML = projects.map(p => {
    return `
      <div class="cms-project-card">
        <div class="cms-thumb">
          <img src="${p.image || 'assets/images/project-reliefmind.png'}" alt="${escapeHtml(p.title)}">
        </div>
        <div class="cms-body">
          <span class="cms-tag">${escapeHtml(p.categoryLabel || p.category)}</span>
          <h4 class="cms-title">${escapeHtml(p.title)}</h4>
          <p class="cms-desc">${escapeHtml(p.description)}</p>
          <div style="font-size:11.5px; color:var(--text-dim); margin-bottom:12px;">
            <strong>Tech:</strong> ${escapeHtml(p.techStack || 'Custom')}
          </div>
          <div class="cms-actions">
            <button class="cms-btn edit" onclick="openProjectModal('${p.id}')"><i class="bi bi-pencil-square"></i> Edit Project</button>
            <button class="cms-btn delete" onclick="deleteProjectCMS('${p.id}')"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

window.openProjectModal = function(projectId = null) {
  window.editingProjectId = projectId;
  const modal = document.getElementById('cmsModal');
  const title = document.getElementById('cmsModalTitle');
  const statusEl = document.getElementById('uploadStatusText');
  const previewBox = document.getElementById('projImagePreviewBox');
  const previewImg = document.getElementById('projImagePreview');

  if (statusEl) {
    statusEl.innerText = "No file chosen";
    statusEl.style.color = "var(--text-dim)";
  }

  if (projectId) {
    const proj = window.allProjects.find(p => p.id === projectId);
    if (!proj) return;
    if (title) title.innerText = `Edit Solution: ${proj.title}`;
    document.getElementById('projTitleInput').value = proj.title || '';
    document.getElementById('projCategorySelect').value = proj.category || 'ai';
    document.getElementById('projTaglineInput').value = proj.categoryLabel || '';
    document.getElementById('projStatusInput').value = proj.status || 'Live System';
    document.getElementById('projMetricInput').value = proj.metric || '';
    document.getElementById('projImageInput').value = proj.image || '';
    document.getElementById('projTechInput').value = proj.techStack || '';
    document.getElementById('projDescInput').value = proj.description || '';

    if (proj.image) {
      if (previewImg) previewImg.src = proj.image;
      if (previewBox) previewBox.style.display = 'block';
    } else {
      if (previewBox) previewBox.style.display = 'none';
    }
  } else {
    if (title) title.innerText = "Create New Project Solution";
    document.getElementById('projTitleInput').value = '';
    document.getElementById('projCategorySelect').value = 'ai';
    document.getElementById('projTaglineInput').value = 'AI · HealthTech';
    document.getElementById('projStatusInput').value = 'Live Production';
    document.getElementById('projMetricInput').value = 'High Performance';
    document.getElementById('projImageInput').value = 'assets/images/project-reliefmind.png';
    document.getElementById('projTechInput').value = 'Gemini AI, Node.js';
    document.getElementById('projDescInput').value = '';
    if (previewBox) previewBox.style.display = 'none';
  }

  modal?.classList.add('active');
};

window.closeProjectModal = function() {
  document.getElementById('cmsModal')?.classList.remove('active');
  window.editingProjectId = null;
};

window.saveProjectCMS = async function() {
  const title = document.getElementById('projTitleInput')?.value.trim();
  const category = document.getElementById('projCategorySelect')?.value;
  const categoryLabel = document.getElementById('projTaglineInput')?.value.trim() || 'Software Solution';
  const status = document.getElementById('projStatusInput')?.value.trim() || 'Live System';
  const metric = document.getElementById('projMetricInput')?.value.trim() || 'Verified';
  const image = document.getElementById('projImageInput')?.value.trim() || 'assets/images/project-reliefmind.png';
  const techStack = document.getElementById('projTechInput')?.value.trim();
  const description = document.getElementById('projDescInput')?.value.trim();

  if (!title || !description) {
    alert("Please provide at least a Project Title and Description.");
    return;
  }

  const projId = window.editingProjectId || `proj_${Date.now()}`;
  const projectData = {
    id: projId,
    title,
    category,
    categoryLabel,
    status,
    metric,
    image,
    techStack,
    description,
    updatedAt: new Date().toISOString()
  };

  try {
    await setDoc(doc(db, 'projects', projId), projectData);
    alert("Project saved successfully in Firestore!");
    window.closeProjectModal();
  } catch (err) {
    console.error("Error saving project to Firestore:", err);
    const idx = window.allProjects.findIndex(p => p.id === projId);
    if (idx !== -1) {
      window.allProjects[idx] = projectData;
    } else {
      window.allProjects.push(projectData);
    }
    renderProjectsCMS();
    window.closeProjectModal();
    alert("Saved locally! (Ensure Firestore security rules permit project writes).");
  }
};

window.deleteProjectCMS = async function(projectId) {
  if (!confirm("Are you sure you want to remove this project?")) return;
  try {
    await deleteDoc(doc(db, 'projects', projectId));
    alert("Project removed.");
  } catch (err) {
    console.warn("Delete error:", err);
    window.allProjects = window.allProjects.filter(p => p.id !== projectId);
    renderProjectsCMS();
  }
};
