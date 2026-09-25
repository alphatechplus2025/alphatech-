// ==========================================================================
// ALPHATECH+ LEAD CAPTURE & FIRESTORE INTEGRATION
// Detailed Qualifying Form Handler with UTM Attribution
// ==========================================================================

const firebaseConfig = {
  apiKey: "AIzaSyCbd_16UIKRGUQsUc47Zl8ccsRrq0DWBw0",
  authDomain: "alpha-tech-plus.firebaseapp.com",
  projectId: "alpha-tech-plus",
  storageBucket: "alpha-tech-plus.firebasestorage.app",
  messagingSenderId: "1040509888533",
  appId: "1:1040509888533:web:5192a429834ed9ce95861c",
  measurementId: "G-MQQ6ZZ539D"
};

function getFirestoreDb() {
  try {
    if (typeof firebase !== 'undefined') {
      if (!firebase.apps || !firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
      }
      return firebase.firestore();
    }
  } catch (err) {
    console.warn('[Firebase Init Warning]', err);
  }
  return null;
}

function initLeadForm() {
  const projectForm = document.getElementById('projectForm');
  const submitBtn = document.getElementById('leadSubmitBtn');
  const feedbackEl = document.getElementById('leadFeedback');

  if (!projectForm) return;

  projectForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    // 1. Gather all detailed form fields
    const name = (document.getElementById('leadName')?.value || '').trim();
    const phone = (document.getElementById('leadPhone')?.value || '').trim();
    const email = (document.getElementById('leadEmail')?.value || '').trim();
    const service = (document.getElementById('leadService')?.value || 'General Project').trim();
    const budget = (document.getElementById('leadBudget')?.value || 'Not Specified').trim();
    const brief = (document.getElementById('leadBrief')?.value || '').trim();

    // Basic Validation
    if (!name || !phone || !brief) {
      alert('Please fill in your Name, WhatsApp Phone, and Project Brief.');
      return;
    }

    // 2. Read UTM parameters (from Instagram / Meta Ads / Google Ads)
    const urlParams = new URLSearchParams(window.location.search);
    const utmSource = urlParams.get('utm_source') || 'direct';
    const utmMedium = urlParams.get('utm_medium') || 'none';
    const utmCampaign = urlParams.get('utm_campaign') || 'none';

    // 3. UI Loading State
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite; display:inline-block; margin-right:6px;"></i> Submitting to AlphaTech+...';
    }
    if (feedbackEl) feedbackEl.style.display = 'none';

    try {
      // 4. Construct complete, dual-compatible lead record
      const leadRecord = {
        name: name,
        fullName: name,
        phone: phone,
        email: email || 'Not provided',
        service: service,
        budget: budget,
        brief: brief,
        message: brief,
        source: utmSource !== 'direct' ? `ad_${utmSource}` : 'website_modal',
        utm_source: utmSource,
        utm_medium: utmMedium,
        utm_campaign: utmCampaign,
        status: 'new',
        submittedAt: new Date().toISOString()
      };

      // 5. Store in Cloud Firestore
      const db = getFirestoreDb();
      let leadId = 'lead_' + Date.now();
      if (db) {
        leadRecord.createdAt = firebase.firestore.FieldValue.serverTimestamp();
        const docRef = await db.collection('leads').add(leadRecord);
        leadId = docRef.id;
        console.log('[Firestore] Lead recorded successfully with ID:', leadId);
      }

      // 6. Dispatch Conversions to Meta Pixel & GA4
      if (typeof window.trackAlphaConversion === 'function') {
        window.trackAlphaConversion('lead_submitted', {
          leadId: leadId,
          service: service,
          budget: budget,
          source: utmSource
        });
      }

      // 7. Reset Form & Render Success Box with WhatsApp Direct Connect
      projectForm.reset();
      const waText = encodeURIComponent(
        `Hi AlphaTech! 👋 I just submitted a project inquiry on your website.\n\n` +
        `👤 *Name:* ${name}\n` +
        `💼 *Service:* ${service}\n` +
        `💰 *Budget/Timeline:* ${budget}\n` +
        `📝 *Brief:* ${brief}`
      );

      if (feedbackEl) {
        feedbackEl.innerHTML = `
          <div style="background:rgba(37,211,102,0.12); border:1px solid rgba(37,211,102,0.35); color:#25D366; padding:16px; border-radius:14px; margin-top:16px; text-align:center;">
            <div style="font-weight:700; font-size:15px; margin-bottom:4px;">
              <i class="bi bi-check-circle-fill"></i> Project Brief Received!
            </div>
            <p style="margin:4px 0 12px; font-size:13px; color:#D1D5DB; line-height:1.5;">
              Lead recorded in AlphaTech+ CRM. Our engineers will reach out within 24 hours. Need faster response?
            </p>
            <a href="https://wa.me/919042115140?text=${waText}" target="_blank" data-wa-placement="modal_success_redirect" class="btn btn-ghost" style="padding:10px 18px; font-size:13px; display:inline-flex; align-items:center; gap:8px; justify-content:center; width:100%; border-color:#25D366; color:#25D366; font-weight:700;">
              <i class="bi bi-whatsapp"></i> Fast-Track on WhatsApp Now →
            </a>
          </div>
        `;
        feedbackEl.style.display = 'block';
      }

      setTimeout(() => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Submit Project Brief';
        }
      }, 3500);

    } catch (err) {
      console.error('[Firestore Error]', err);
      const waFallback = encodeURIComponent(`Hi AlphaTech! I wanted to inquire about a project:\nName: ${name}\nPhone: ${phone}\nService: ${service}\nBrief: ${brief}`);
      if (feedbackEl) {
        feedbackEl.innerHTML = `
          <div style="background:rgba(255,80,80,0.12); border:1px solid rgba(255,80,80,0.35); color:#FF6B6B; padding:14px; border-radius:12px; margin-top:16px; text-align:center; font-size:13px;">
            <div><i class="bi bi-exclamation-triangle-fill"></i> Database temporarily offline.</div>
            <div style="margin-top:8px;">
              <a href="https://wa.me/919042115140?text=${waFallback}" target="_blank" style="color:#25D366; text-decoration:underline; font-weight:700;">
                Click here to send directly via WhatsApp →
              </a>
            </div>
          </div>
        `;
        feedbackEl.style.display = 'block';
      }
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Project Brief';
      }
    }
  });
}

// Auto initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initLeadForm);
} else {
  initLeadForm();
}
