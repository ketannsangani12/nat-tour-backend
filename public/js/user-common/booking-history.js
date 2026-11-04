function openEmailModal(emailId) {
    var overlay = document.getElementById('emailModal');
    var ref     = document.getElementById('eml-' + emailId);
    if (!overlay || !ref) return;

    var svgIcon = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>';
    document.getElementById('emailModalTitle').innerHTML = svgIcon + (ref.dataset.type || 'Email');

    document.getElementById('emailModalTo').textContent     = ref.dataset.to || '';
    document.getElementById('emailModalSentAt').textContent = ref.dataset.sentAt || '';
    document.getElementById('emailModalBy').textContent     = ref.dataset.by || '';

    var pill   = document.getElementById('emailModalStatusPill');
    var status = ref.dataset.status;
    pill.textContent = status === 'Delivered' ? '✓ Delivered' : '✉ Sent';
    pill.className   = 'email-status-pill ' + (status === 'Delivered' ? 'delivered' : 'sent');

    var frame   = document.getElementById('emailModalFrame');
    var content = (window.__emailContent || {})[emailId];
    frame.srcdoc = content || '<html><body style="font-family:sans-serif;color:#94a3b8;text-align:center;padding:60px 24px;font-size:13px;">No email content available.</body></html>';

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeEmailModal() {
    var overlay = document.getElementById('emailModal');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
    var frame = document.getElementById('emailModalFrame');
    if (frame) frame.srcdoc = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEmailModal();
});

function filterBy(type, btn) {
    document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    let firstVisible = null;
    document.querySelectorAll('.log-entry').forEach(el => {
        const t = el.dataset.type;
        const hasAttachment = el.dataset.hasAttachment === 'true';
        const match = type === 'all'
            || (type === 'transaction' && (t === 'payment' || t === 'refund'))
            || (type === 'note' && t === 'note' && !hasAttachment)
            || (type === 'attachment' && hasAttachment)
            || (type === 'email' && t === 'email')
            || (type === 'system' && t === 'system');
        el.dataset.hidden = match ? 'false' : 'true';
        if (match && !firstVisible) firstVisible = el;
    });
    if (firstVisible) {
        setTimeout(() => firstVisible.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }
}

const attachments = { internal: [], tourist: [] };

function handleAttach(source, input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const isImage = file.type.startsWith('image/');
        attachments[source].push({ name: file.name, url: e.target.result, isImage });
        renderAttachPreviews(source);
    };
    reader.readAsDataURL(file);
    input.value = '';
}

function renderAttachPreviews(source) {
    const wrap = document.getElementById(source + 'AttachPreview');
    if (!wrap) return;
    wrap.innerHTML = attachments[source].map((a, i) =>
        `<div class="attach-chip" onclick="removeAttach('${source}',${i})">
      ${a.isImage ? `<img src="${a.url}" style="width:16px;height:16px;object-fit:cover;border-radius:2px;">` : '📄'}
      ${a.name}
      <span class="attach-chip-remove">×</span>
    </div>`
    ).join('');
}

function removeAttach(source, idx) {
    attachments[source].splice(idx, 1);
    renderAttachPreviews(source);
}

function submitNote(source) {
    const ta = document.getElementById(source === 'internal' ? 'internalNote' : 'touristNote');
    if (ta) ta.value = '';
    attachments[source] = [];
    renderAttachPreviews(source);
}

function updateTabCounts() {
    const log = document.getElementById('historyLog');
    if (!log) return;
    const counts = { all: 0, transaction: 0, note: 0, email: 0, system: 0, attachment: 0 };
    log.querySelectorAll('.log-entry').forEach(el => {
        const t = el.dataset.type;
        const hasAttachment = el.dataset.hasAttachment === 'true';
        counts.all++;
        if (t === 'payment' || t === 'refund') counts.transaction++;
        else if (t === 'note' && !hasAttachment) counts.note++;
        else if (t === 'email') counts.email++;
        else if (t !== 'note') counts.system++;
        if (hasAttachment) counts.attachment++;
    });
    document.querySelectorAll('.ftab[data-filter]').forEach(btn => {
        const ct = btn.querySelector('.ct');
        const key = btn.dataset.filter;
        if (ct && key in counts) ct.textContent = counts[key];
    });
}

updateTabCounts();
