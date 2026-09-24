// ==========================================================================
// ALPHATECH+ CONVERSION TRACKING ENGINE
// Meta Pixel + Google Analytics 4 + WhatsApp Link Interceptor
// ==========================================================================

export function trackAlphaConversion(eventName, eventData = {}) {
  try {
    // 1. Google Analytics 4 (gtag)
    if (typeof window.gtag === 'function') {
      window.gtag('event', eventName, eventData);
    }

    // 2. Meta Pixel (fbq)
    if (typeof window.fbq === 'function') {
      if (eventName === 'lead_submitted') {
        window.fbq('track', 'Lead', {
          content_name: eventData.service || 'Web Inquiry',
          value: eventData.budget || 0,
          currency: 'INR',
          status: 'submitted'
        });
      } else if (eventName === 'whatsapp_click') {
        window.fbq('trackCustom', 'WhatsAppClick', eventData);
      }
    }

    // 3. Google Tag Manager / Generic DataLayer
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: eventName, ...eventData });

    console.log(`[AlphaTrack] Dispatched: ${eventName}`, eventData);
  } catch (err) {
    console.warn('[AlphaTrack] Warning:', err);
  }
}

// Make accessible on window for inline handlers
window.trackAlphaConversion = trackAlphaConversion;

// Auto-tag and monitor all WhatsApp links
export function initWhatsAppTracker() {
  document.querySelectorAll('a[href*="wa.me"]').forEach(link => {
    link.addEventListener('click', function() {
      const placement = this.getAttribute('data-wa-placement') || 
                        (this.closest('.floating-wa') ? 'floating_widget' : 
                         this.closest('.hero') ? 'hero_cta' : 
                         this.closest('.offer-card') ? 'offer_card' : 
                         this.closest('footer') ? 'footer_contact' : 'content_link');
      trackAlphaConversion('whatsapp_click', {
        placement: placement,
        destination: this.href,
        timestamp: new Date().toISOString()
      });
    });
  });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initWhatsAppTracker);
} else {
  initWhatsAppTracker();
}
