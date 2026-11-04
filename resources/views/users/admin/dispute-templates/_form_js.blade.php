<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('template_content');
    const lengthDisplay = document.getElementById('contentLength');
    const variablesPanel = document.getElementById('variablesPanel');
    const toggleVariablesBtn = document.getElementById('toggleVariables');
    const generatePreviewBtn = document.getElementById('generatePreview');
    const previewContent = document.getElementById('previewContent');
    const submitBtn = document.getElementById('submitBtn');

    // Character counter
    function updateCount() {
        const len = textarea.value.length;
        lengthDisplay.textContent = len + ' characters';
        if (len < 50) {
            lengthDisplay.classList.add('text-red-500');
            lengthDisplay.classList.remove('text-gray-400');
        } else {
            lengthDisplay.classList.remove('text-red-500');
            lengthDisplay.classList.add('text-gray-400');
        }
    }
    textarea.addEventListener('input', updateCount);
    updateCount();

    // Toggle variables panel
    toggleVariablesBtn.addEventListener('click', function() {
        const hidden = variablesPanel.classList.contains('hidden');
        variablesPanel.classList.toggle('hidden', !hidden);
        this.textContent = hidden ? 'Hide Variables' : 'Variables';
    });

    // Insert variable at cursor
    document.querySelectorAll('.insert-variable').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const text = '{{' + this.dataset.variable + '}}';
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + text.length, start + text.length);
            updateCount();
        });
    });

    // Generate preview with sample data
    const sampleData = {
        booking_reference: 'BK-2024-001234',
        customer_name: 'John Smith',
        customer_email: 'john.smith@example.com',
        tour_name: 'Grand Canyon Adventure Tour',
        tour_date: '2024-03-15',
        tour_time: '09:00 AM',
        booking_amount: '{{ currencyFormation(false, true) }}299.99',
        payment_date: 'March 1, 2024',
        transaction_id: 'TXN-789456123',
        dispute_amount: '{{ currencyFormation(false, true) }}299.99',
        dispute_reason: 'Product Not Received',
        company_name: 'Adventure Tours Inc.',
        company_email: 'support@adventuretours.com',
        company_phone: '(555) 123-4567',
        current_date: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
    };

    generatePreviewBtn.addEventListener('click', function() {
        const content = textarea.value.trim();
        if (!content) {
            previewContent.innerHTML = '<p class="text-red-500 text-sm">Please enter template content first.</p>';
            return;
        }
        this.disabled = true;
        this.innerHTML = `<svg class="animate-spin" width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="16 10"/></svg> Generating…`;

        setTimeout(() => {
            let preview = content;
            Object.keys(sampleData).forEach(key => {
                preview = preview.replace(new RegExp('{{' + key + '}}', 'g'), sampleData[key]);
            });
            previewContent.innerHTML = `<div class="text-gray-800 text-sm font-mono leading-relaxed whitespace-pre-wrap">${preview.replace(/\n/g, '<br>')}</div>`;
            this.disabled = false;
            this.innerHTML = `<svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M6.5 2C4 2 1.8 3.6 1 5.8c.8 2.2 3 3.7 5.5 3.7s4.7-1.5 5.5-3.7C11.2 3.6 9 2 6.5 2z" stroke="currentColor" stroke-width="1.2"/><circle cx="6.5" cy="5.8" r="1.8" stroke="currentColor" stroke-width="1.2"/></svg> Generate Preview`;
        }, 600);
    });

    // Form submit loading state
    document.getElementById('{{ $formId }}').addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<svg class="animate-spin" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="20 14"/></svg> {{ $submitLabelLoading }}`;
    });
});
</script>
