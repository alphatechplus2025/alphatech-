/* ================= COUNTER ANIMATION ================= */
const counters = document.querySelectorAll('.counter');
const speed = 200;

if (counters.length > 0) {
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const counter = entry.target;
            const target = +counter.dataset.target;
            let count = 0;
            const increment = target / speed;

            const updateCounter = () => {
                if (count < target) {
                    count += increment;
                    counter.innerText = Math.ceil(count);
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.innerText = target + '+';
                }
            };
            updateCounter();
            counterObserver.unobserve(counter);
        });
    }, { threshold: 0.5 });

    counters.forEach(counter => {
        counterObserver.observe(counter);
    });
}

/* ================= INITIALIZE DEFAULT LEADS DATA ================= */
function getStoredLeads() {
    const data = localStorage.getItem('alpha_tech_leads');
    if (data) return JSON.parse(data);
    
    // Default initial mock leads for final year college & startup demo
    const defaultLeads = [
        {
            id: 'LEAD-101',
            name: 'Rahul Sharma',
            college: 'SRM University',
            email: 'rahul.srm2025@gmail.com',
            phone: '+91 98765 12345',
            package: '₹5,999 Startup/College Offer',
            project: 'E-Commerce Platform with Admin Panel',
            status: 'In Progress',
            date: '2026-07-24 14:30'
        },
        {
            id: 'LEAD-102',
            name: 'Priya Venkatesh',
            college: 'Anna University',
            email: 'priya.v@gmail.com',
            phone: '+91 91234 56789',
            package: '₹5,999 Startup/College Offer',
            project: 'AI Powered Student Attendance System',
            status: 'Pending',
            date: '2026-07-24 16:45'
        },
        {
            id: 'LEAD-103',
            name: 'Karthik Raja',
            college: 'VIT Chennai',
            email: 'karthik.vit@gmail.com',
            phone: '+91 99887 76655',
            package: 'Custom Enterprise Portal',
            project: 'Hospital Management Web App',
            status: 'Delivered',
            date: '2026-07-23 11:15'
        }
    ];
    localStorage.setItem('alpha_tech_leads', JSON.stringify(defaultLeads));
    return defaultLeads;
}

function saveLead(newLead) {
    const leads = getStoredLeads();
    leads.unshift(newLead);
    localStorage.setItem('alpha_tech_leads', JSON.stringify(leads));
    if (window.location.pathname.includes('admin.html')) {
        renderAdminDashboard();
    }
}

/* ================= AI CHATBOT LOGIC ================= */
document.addEventListener('DOMContentLoaded', () => {
    const botBtn = document.getElementById('chatbotLauncher');
    const botBox = document.getElementById('chatbotBox');
    const botClose = document.getElementById('chatbotClose');
    const botInput = document.getElementById('chatbotInput');
    const botSend = document.getElementById('chatbotSend');
    const botBody = document.getElementById('chatbotBody');

    if (botBtn && botBox) {
        botBtn.addEventListener('click', () => {
            botBox.classList.toggle('active');
        });

        if (botClose) {
            botClose.addEventListener('click', () => {
                botBox.classList.remove('active');
            });
        }

        const addMessage = (text, sender = 'bot') => {
            const msgDiv = document.createElement('div');
            msgDiv.className = `chat-msg ${sender}`;
            msgDiv.innerHTML = text;
            botBody.appendChild(msgDiv);
            botBody.scrollTop = botBody.scrollHeight;
        };

        const botReply = (query) => {
            const q = query.toLowerCase();
            if (q.includes('5999') || q.includes('offer') || q.includes('price')) {
                return `🔥 <b>₹5,999 Special Startup & College Offer</b> includes:<br>
                • Domain & Web Hosting<br>
                • WhatsApp Chat/Call Integration<br>
                • Full Backend + Admin Portal<br>
                • AI Chatbot & 30-Hour Delivery!<br><br>
                <a href="#offer-section" class="btn btn-sm btn-primary mt-1" onclick="document.getElementById('chatbotBox').classList.remove('active')">View & Book Offer</a>`;
            } else if (q.includes('college') || q.includes('final year') || q.includes('project')) {
                return `🎓 Yes! We specialize in <b>Final Year College Projects</b> and <b>Startup MVPs</b>. Complete source code, documentation, and 15 days free support are included!`;
            } else if (q.includes('delivery') || q.includes('time') || q.includes('hours')) {
                return `⚡ Guarantee delivery within <b>30 Hours</b> with 2 days unlimited free revisions!`;
            } else if (q.includes('contact') || q.includes('email')) {
                return `📧 Reach out directly at <b>alphatechplus2025@gmail.com</b> or call/WhatsApp us at <b>+91 98765 43210</b>.`;
            } else {
                return `Thanks for reaching out! AlphaTech Plus offers full-stack web solutions starting at just ₹5,999 for students and startups. Would you like to book a project or call us?`;
            }
        };

        const handleSend = () => {
            const val = botInput.value.trim();
            if (!val) return;
            addMessage(val, 'user');
            botInput.value = '';

            setTimeout(() => {
                const reply = botReply(val);
                addMessage(reply, 'bot');
            }, 600);
        };

        if (botSend && botInput) {
            botSend.addEventListener('click', handleSend);
            botInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') handleSend();
            });
        }

        // Delegate quick chip clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-chip')) {
                const query = e.target.getAttribute('data-query');
                addMessage(query, 'user');
                setTimeout(() => {
                    addMessage(botReply(query), 'bot');
                }, 500);
            }
        });
    }

    /* ================= BOOKING FORM HANDLER ================= */
    const bookingForm = document.getElementById('offerBookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('bookName').value;
            const email = document.getElementById('bookEmail').value;
            const phone = document.getElementById('bookPhone').value;
            const project = document.getElementById('bookProject').value;
            const college = document.getElementById('bookCollege').value || 'N/A';

            const leadObj = {
                id: 'LEAD-' + Math.floor(100 + Math.random() * 900),
                name,
                college,
                email,
                phone,
                package: '₹5,999 Startup/College Offer',
                project,
                status: 'Pending',
                date: new Date().toLocaleString('sv-SE').slice(0, 16)
            };

            saveLead(leadObj);

            // Hide modal
            const modalEl = document.getElementById('offerBookingModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }

            alert(`🎉 Success! Your inquiry for the ₹5,999 Offer has been sent.\nOur team will contact you at ${email} shortly.`);
            bookingForm.reset();
        });
    }

    /* ================= ADMIN DASHBOARD RENDERER ================= */
    if (window.location.pathname.includes('admin.html')) {
        renderAdminDashboard();
    }
});

function renderAdminDashboard() {
    const leads = getStoredLeads();
    const tableBody = document.getElementById('adminLeadTableBody');
    const totalLeadsEl = document.getElementById('statTotalLeads');
    const offerLeadsEl = document.getElementById('statOfferLeads');
    const pendingLeadsEl = document.getElementById('statPendingLeads');
    const deliveredLeadsEl = document.getElementById('statDeliveredLeads');

    if (totalLeadsEl) totalLeadsEl.innerText = leads.length;
    if (offerLeadsEl) offerLeadsEl.innerText = leads.filter(l => l.package.includes('5,999')).length;
    if (pendingLeadsEl) pendingLeadsEl.innerText = leads.filter(l => l.status === 'Pending').length;
    if (deliveredLeadsEl) deliveredLeadsEl.innerText = leads.filter(l => l.status === 'Delivered').length;

    if (!tableBody) return;
    tableBody.innerHTML = '';

    leads.forEach((l, index) => {
        let badgeClass = 'badge-pending';
        if (l.status === 'In Progress') badgeClass = 'badge-progress';
        if (l.status === 'Delivered') badgeClass = 'badge-delivered';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${l.id}</strong></td>
            <td>
                <strong>${l.name}</strong><br>
                <small class="text-muted">${l.college !== 'N/A' ? l.college : ''}</small>
            </td>
            <td>
                <a href="mailto:${l.email}">${l.email}</a><br>
                <small>${l.phone}</small>
            </td>
            <td><span class="badge bg-primary">${l.package}</span></td>
            <td>${l.project}</td>
            <td><span class="badge-status ${badgeClass}">${l.status}</span></td>
            <td>
                <select class="form-select form-select-sm" onchange="updateLeadStatus(${index}, this.value)">
                    <option value="Pending" ${l.status === 'Pending' ? 'selected' : ''}>Pending</option>
                    <option value="In Progress" ${l.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                    <option value="Delivered" ${l.status === 'Delivered' ? 'selected' : ''}>Delivered</option>
                </select>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function updateLeadStatus(index, newStatus) {
    const leads = getStoredLeads();
    leads[index].status = newStatus;
    localStorage.setItem('alpha_tech_leads', JSON.stringify(leads));
    renderAdminDashboard();
}
