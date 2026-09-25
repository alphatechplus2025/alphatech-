// ==========================================================================
// ALPHATECH+ MAIN CLIENT APPLICATION LOGIC
// 3D Visualizer, Navigation, Interactive AI Chatbot, Portfolio, Modal
// ==========================================================================

/* ---------- NAVIGATION ---------- */
const nav = document.getElementById('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
  });
}

/* ---------- MOBILE MENU ---------- */
const navToggle = document.getElementById('nav-toggle');
const navLinks = document.getElementById('nav-links');

window.toggleMobileMenu = function() {
  if (!navToggle || !navLinks) return;
  navToggle.classList.toggle('active');
  navLinks.classList.toggle('active');
  
  const icon = navToggle.querySelector('i');
  if (navToggle.classList.contains('active')) {
    if (icon) icon.className = 'bi bi-x-lg';
    document.body.style.overflow = 'hidden';
  } else {
    if (icon) icon.className = 'bi bi-list';
    document.body.style.overflow = '';
  }
};

document.querySelectorAll('.nav-links a').forEach(link => {
  link.addEventListener('click', () => {
    if (navToggle && navLinks) {
      navToggle.classList.remove('active');
      navLinks.classList.remove('active');
      const icon = navToggle.querySelector('i');
      if (icon) icon.className = 'bi bi-list';
      document.body.style.overflow = '';
    }
  });
});

/* ---------- SCROLL REVEAL ---------- */
const reveals = document.querySelectorAll('.reveal');
if (reveals.length) {
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('is-visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });
  reveals.forEach(el => io.observe(el));
}

/* ---------- MODAL CONTROLLER ---------- */
const modal = document.getElementById('modal');

window.openModal = function(serviceName = 'General Project') {
  if (!modal) return;
  const serviceInput = document.getElementById('leadService');
  const heading = document.getElementById('modalHeading');

  if (serviceInput) serviceInput.value = serviceName;
  if (heading) {
    heading.innerText = (serviceName && serviceName !== 'General Project') 
      ? `Inquire ${serviceName}` 
      : 'Launch Your Project';
  }
  modal.classList.add('active');
};

window.closeModal = function() {
  if (!modal) return;
  modal.classList.remove('active');
  const feedback = document.getElementById('leadFeedback');
  if (feedback) feedback.style.display = 'none';
};

if (modal) {
  modal.addEventListener('click', (e) => {
    if (e.target === modal) window.closeModal();
  });
}

/* ---------- PORTFOLIO CATEGORY FILTER ---------- */
window.filterPortfolio = function(cat, btn) {
  document.querySelectorAll('.p-tab-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  
  const cards = document.querySelectorAll('#portfolioGrid .portfolio-card');
  cards.forEach(card => {
    const cardCats = (card.getAttribute('data-cat') || '').split(' ');
    if (cat === 'all' || cardCats.includes(cat)) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
};

/* ---------- FAQ ACCORDION ---------- */
window.toggleFaq = function(item) {
  if (item) item.classList.toggle('active');
};

/* ---------- ENHANCED AI CHATBOT ---------- */
const chatDrawer = document.getElementById('chatDrawer');
const chatToggle = document.getElementById('chatToggle');
const chatBody = document.getElementById('chatBody');
const chatInput = document.getElementById('chatInput');

if (chatToggle) {
  chatToggle.addEventListener('click', toggleChat);
}

function toggleChat() {
  if (chatDrawer) chatDrawer.classList.toggle('active');
}
window.toggleChat = toggleChat;

window.sendQuickChat = function(queryText) {
  if (chatInput) {
    chatInput.value = queryText;
    sendChat();
  }
};

window.sendChat = function() {
  if (!chatInput) return;
  const text = chatInput.value.trim();
  if (!text) return;
  
  const userMsg = document.createElement('div');
  userMsg.className = 'chat-bubble user';
  userMsg.innerText = text;
  chatBody.appendChild(userMsg);
  chatInput.value = '';
  chatBody.scrollTop = chatBody.scrollHeight;

  const botMsg = document.createElement('div');
  botMsg.className = 'chat-bubble bot';
  botMsg.innerHTML = '<i class="bi bi-three-dots"></i> Analyzing your request...';
  chatBody.appendChild(botMsg);
  chatBody.scrollTop = chatBody.scrollHeight;

  setTimeout(() => {
    const q = text.toLowerCase();
    let reply = "";

    if (q.includes('5999') || q.includes('package') || q.includes('offer') || q.includes('pricing')) {
      reply = "Our <b>₹5,999 Launch Package</b> delivers a complete full-stack web solution — custom domain, managed hosting, admin dashboard, SEO optimization, WhatsApp integration, and full source code ownership — all within 30 hours.<br><a href='https://wa.me/919042115140?text=I%20want%20to%20book%20the%205999%20package' target='_blank' class='chat-cta-btn'><i class='bi bi-whatsapp'></i> Reserve Package</a>";
    } else if (q.includes('ai') || q.includes('bot') || q.includes('agent') || q.includes('llm')) {
      reply = "<b>Alpha AI</b> delivers enterprise-grade conversational AI, autonomous workflow agents, and custom LLM integrations (Gemini / OpenAI) engineered for your specific business requirements.<br><a href='https://wa.me/919042115140?text=Interested%20in%20Alpha%20AI%20solutions' target='_blank' class='chat-cta-btn'><i class='bi bi-cpu'></i> Explore AI Solutions</a>";
    } else if (q.includes('college') || q.includes('student') || q.includes('project') || q.includes('bca') || q.includes('mca') || q.includes('btech')) {
      reply = "We deliver production-quality academic projects (BCA/MCA/B.Tech) with complete source code, database architecture, presentation materials, technical documentation, and live deployment assistance.<br><a href='https://wa.me/919042115140?text=Need%20College%20Project%20help' target='_blank' class='chat-cta-btn'><i class='bi bi-mortarboard'></i> Discuss Your Project</a>";
    } else if (q.includes('phone') || q.includes('number') || q.includes('contact') || q.includes('call') || q.includes('whatsapp') || q.includes('email')) {
      reply = "Connect with our team directly:<br>📞 <b>Phone / WhatsApp:</b> +91 90421 15140<br>📧 <b>Email:</b> alphatechplus2025@gmail.com<br>📍 <b>Headquarters:</b> Chennai, Tamil Nadu<br><a href='https://wa.me/919042115140' target='_blank' class='chat-cta-btn'><i class='bi bi-whatsapp'></i> Connect Now</a>";
    } else if (q.includes('website') || q.includes('app') || q.includes('service') || q.includes('erp') || q.includes('crm') || q.includes('business')) {
      reply = "We architect high-performance web platforms, cross-platform mobile applications (Flutter/React Native), enterprise ERP/CRM systems, and scalable e-commerce ecosystems — all with complete source code ownership.<br><a href='javascript:void(0)' onclick='openModal()' class='chat-cta-btn'><i class='bi bi-rocket-takeoff'></i> Launch Your Project</a>";
    } else {
      reply = "Thank you for reaching out to AlphaTech+. We engineer solutions that accelerate growth. Our team is available for immediate consultation on WhatsApp at <b>+91 90421 15140</b>.<br><a href='https://wa.me/919042115140' target='_blank' class='chat-cta-btn'><i class='bi bi-whatsapp'></i> Start a Conversation</a>";
    }

    botMsg.innerHTML = reply;
    chatBody.appendChild(botMsg);
    chatBody.scrollTop = chatBody.scrollHeight;
  }, 450);
};

/* ---------- 3D HERO: BUILDING BLOCKS (THREE.JS) ---------- */
function initThreeHero() {
  const canvas = document.getElementById('hero-canvas');
  if (!canvas || typeof THREE === 'undefined') return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 100);
  camera.position.set(0, 3.2, 11);

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);

  const group = new THREE.Group();
  scene.add(group);

  const accent = new THREE.Color(0x0FBE85);
  const soft = new THREE.Color(0x14151B);

  const blocks = [];
  const levels = 7;
  for (let level = 0; level < levels; level++) {
    const spread = 3.4 - level * 0.32;
    const blocksThisLevel = Math.max(3, 9 - level);
    for (let i = 0; i < blocksThisLevel; i++) {
      const angle = (i / blocksThisLevel) * Math.PI * 2 + level * 0.4;
      const radius = spread * (0.5 + Math.random() * 0.5);
      const size = 0.62 - level * 0.02;

      const geo = new THREE.BoxGeometry(size, size * 0.7, size * 1.1);
      const isAccent = (level === 2 && i === 1) || (level === 4 && i === 0) || Math.random() < 0.12;
      const mat = new THREE.MeshBasicMaterial({
        color: isAccent ? accent : soft,
        wireframe: true,
        transparent: true,
        opacity: isAccent ? 0.95 : 0.45
      });
      const mesh = new THREE.Mesh(geo, mat);

      const targetX = Math.cos(angle) * radius;
      const targetY = (level - levels / 2) * 0.75;
      const targetZ = Math.sin(angle) * radius * 0.7;

      mesh.position.set(
        targetX + (Math.random() - 0.5) * 4,
        targetY + (Math.random() - 0.5) * 5,
        targetZ + (Math.random() - 0.5) * 4
      );

      group.add(mesh);
      blocks.push({
        mesh,
        target: new THREE.Vector3(targetX, targetY, targetZ),
        delay: Math.random() * 0.8,
        speed: 0.045 + Math.random() * 0.02,
        floatOffset: Math.random() * Math.PI * 2,
        rotSpeed: (Math.random() - 0.5) * 0.002
      });
    }
  }

  group.position.y = -0.6;

  let clock = 0;
  let mouseX = 0, mouseY = 0;
  window.addEventListener('mousemove', (e) => {
    mouseX = (e.clientX / window.innerWidth - 0.5);
    mouseY = (e.clientY / window.innerHeight - 0.5);
  });

  function animate() {
    requestAnimationFrame(animate);
    clock += 0.016;

    blocks.forEach(b => {
      if (clock > b.delay) {
        b.mesh.position.lerp(b.target, b.speed);
      }
      if (!reduceMotion) {
        b.mesh.position.y += Math.sin(clock * 0.8 + b.floatOffset) * 0.0015;
        b.mesh.rotation.y += b.rotSpeed;
      }
    });

    if (!reduceMotion) {
      group.rotation.y += (mouseX * 0.6 - group.rotation.y * 0.1) * 0.02;
      group.rotation.x += (mouseY * 0.15 - group.rotation.x * 0.1) * 0.02;
      group.rotation.y += 0.0009;
    }

    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
}

// Initialize Three.js on load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initThreeHero);
} else {
  initThreeHero();
}
