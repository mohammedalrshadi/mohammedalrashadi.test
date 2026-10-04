document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('support-form');
  if (form) {
    const nameInput = document.getElementById('support-name');
    const emailInput = document.getElementById('support-email');
    const catInput = document.getElementById('support-category');
    const subjInput = document.getElementById('support-subject');
    const msgInput = document.getElementById('support-message');
    const honeypot = document.getElementById('website_url');
    const csrfInput = document.getElementById('csrf_token');
    const submitBtn = document.getElementById('support-btn');
    const alertBox = document.getElementById('support-alert');

    function showAlert(msg, isError = true) {
      alertBox.textContent = msg;
      alertBox.className = isError 
        ? 'alert-error block mb-4'
        : 'alert-success block mb-4';
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('hidden');

      const name = nameInput.value.trim();
      const email = emailInput.value.trim();
      const category = catInput.value;
      const subject = subjInput.value.trim();
      const message = msgInput.value.trim();
      const website_url = honeypot ? honeypot.value : '';
      const csrf_token = csrfInput.value;

      if (!name || !email || !subject || !message) {
        showAlert('Please fill in all required fields.');
        return;
      }

      if (message.length < 10) {
        showAlert('Please provide a little more detail in your message (at least 10 characters).');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Sending inquiry...</span>';

      try {
        const res = await fetch('/api/support/create.php', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf_token
          },
          credentials: 'same-origin',
          body: JSON.stringify({ name, email, category, subject, message, website_url, csrf_token })
        });

        const data = await res.json();
        showAlert(data.message || 'Message sent successfully!', !data.success);

        if (data.success) {
          form.reset();
          submitBtn.innerHTML = '<span>Message Sent</span><span class="material-symbols-outlined text-[18px]">check</span>';
          setTimeout(() => {
            submitBtn.innerHTML = '<span>Send Message</span><span class="material-symbols-outlined text-[18px]">send</span>';
            submitBtn.disabled = false;
          }, 3000);
        } else {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Send Message</span><span class="material-symbols-outlined text-[18px]">send</span>';
        }
      } catch (err) {
        showAlert('Network error. Please try again later.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Send Message</span><span class="material-symbols-outlined text-[18px]">send</span>';
      }
    });
  }
});
