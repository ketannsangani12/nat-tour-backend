class PreviewManager {
  constructor(builder){
    this.builder = builder;
    this.wrap = Utils.$('#preview');
  }
  render(){
    this.wrap.innerHTML='';
    this.builder.tree.forEach(n=> this.wrap.appendChild(this.renderField(n)) );
    this.attachLogic();
    this.attachLiveUpdates(); // ✅ Attach dynamic updates for visibility & status
    applyLabelTruncation();
    this.initDescriptionToggle();
  }
  initDescriptionToggle() {
    document.querySelectorAll('[data-toggle-desc]').forEach(icon => {
      icon.addEventListener('click', () => {
        const targetId = icon.getAttribute('data-toggle-desc');
        const box = document.getElementById(targetId);
        if (!box) return;
        box.classList.toggle('open');
      });
    });
  }
  renderField(node, isChild = false){
    let labelHtml = '';
    const id = Utils.id('pv');
    const holder = document.createElement('div');
    holder.className = 'addon-card fade-in border border-[#E5E7EB] rounded-lg overflow-hidden mt-3 bg-[#F9FAFB] p-3';
    holder.dataset.nodeId = node.id;

    if(node.type !== 'checkbox') 
      labelHtml = `<span class="form-label preview-label form-custom-label">${Utils.esc(node.label || '')}</span>`;

    if (node.description?.trim()) {
      const descId = `desc_${node.id}`;
      labelHtml += `
        <span class="desc-icon ms-2" style="cursor:pointer; color:#0d6efd;" data-toggle-desc="${descId}">
          <i class="fas fa-info-circle"></i>
        </span>
        <div id="${descId}" class="desc-box mt-1 hidden text-xs">${Utils.esc(node.description)}</div>
      `;
    }

    // label + input
    // Render field type
    switch(node.type) {
      case 'checkbox':
        holder.innerHTML = `
          <div class="form-check mb-2">
            <input class="form-check-input w-4 h-4 mr-2" type="checkbox" id="${id}">
            <label class="form-check-label form-custom-label !mb-0" for="${id}">${Utils.esc(node.label)}</label>
            ${labelHtml}
          </div>`;
        break;
      case 'radio':
        holder.innerHTML = `${labelHtml}`;
        (node.options || []).forEach((o, i) => {
          const rid = `${id}_${i}`;
          const priceText = (o.additional_fee && o.price) ? ` <span class="text-success fw-bold">(${this.toCurrency(o.price)})</span>` : '';
          holder.insertAdjacentHTML('beforeend', `
            <div class="form-check flex items-center mb-2">
              <input class="form-check-input w-4 h-4 mr-2" type="radio" name="${id}" id="${rid}" value="${Utils.esc(o.value)}" data-option-id="${o.id || ''}">
              <label class="form-check-label form-custom-label" for="${rid}">${Utils.esc(o.label)}${priceText}</label>
            </div>`);
        });
        break;
      case 'dropdown':
        holder.innerHTML = `
          ${labelHtml}
          <select class="form-select form-custom-input" id="${id}">
            <option value="">Select</option>
            ${(node.options || []).map(o => {
              const priceText = (o.additional_fee && o.price) ? ` <span class="text-success fw-bold">(${this.toCurrency(o.price)})</span>` : '';
              return `<option value="${Utils.esc(o.value)}" data-option-id="${o.id || ''}">${Utils.esc(o.label)}${priceText}</option>`
            }).join('')}
          </select>`;
        break;
      case 'number':
        holder.innerHTML = `
          ${labelHtml}
          <input type="number" class="form-control node-input form-custom-input" id="${id}" min="${node.min ?? 0}" max="${node.max ?? ''}" value="" step="1">`;
        break;
      case 'textbox':
        holder.innerHTML = `${labelHtml}<textarea class="form-control form-custom-input min-h-16" id="${id}" rows="3"></textarea>`;
        break;
      default:
        holder.innerHTML = `${labelHtml}<input type="text" class="form-control form-custom-input" id="${id}">`;
    }

    // ✅ Always add a placeholder for price
    const priceEl = document.createElement('div');
    priceEl.className = 'price-preview mt-2 fw-bold text-success';
    priceEl.style.display = 'none';
    holder.appendChild(priceEl);
    if (priceEl) node.priceEl = priceEl;
    applyLabelTruncation();

    // Attach input listener for price updates
    const inputEl = holder.querySelector('input, textarea, select');
    if (inputEl) {
      inputEl.addEventListener('input', () => this.updatePricePreview(node, priceEl, node.unit_type));
      holder.addEventListener('change', () => this.updatePricePreview(node, priceEl, node.unit_type));
    }

    // ====== VISIBILITY & STATUS BADGES (MAIN FORMS ONLY) ======
    if (!isChild) {
      const metaEl = document.createElement('div');
      metaEl.className = 'addon-meta mt-2';
      metaEl.dataset.metaId = node.id; // ✅ store reference for live updates
      this.updateMetaPreview(metaEl, node); // ✅ initial render
      holder.appendChild(metaEl);
    }
    
    // child containers per rule
    (node.rules||[]).forEach((r, idx)=>{
      const childWrap = document.createElement('div');
      childWrap.className = 'indent mt-2';
      childWrap.dataset.ruleIndex = idx;
      childWrap.style.display = 'none';
      (r.children || []).forEach(ch => childWrap.appendChild(this.renderField(ch, true)));
      holder.appendChild(childWrap);
    });

    // Initial price calculation on page load
    this.updatePricePreview(node, priceEl, node.unit_type);

    return holder;
  }
  updateMetaPreview(metaEl, node) {
    const visibility = node.visibility || 'Both';
    const status = node.status || 'Active';

    const visibilityBadge = `<span style="
        background:#e8f3ff;
        color:#007bff;
        padding:3px 8px;
        border-radius:6px;
        font-weight:500;
        margin-right:6px;
        font-size:0.8rem;
    ">Visibility: ${Utils.esc(visibility)}</span>`;

    const statusBadge = `<span style="
        background:${status === 'Active' ? '#e6ffed' : '#ffeaea'};
        color:${status === 'Active' ? '#0a8f3a' : '#c82333'};
        padding:3px 8px;
        border-radius:6px;
        font-weight:500;
        font-size:0.8rem;
    ">Status: ${Utils.esc(status)}</span>`;

    metaEl.innerHTML = visibilityBadge + statusBadge;
  }
  toCurrency(num) {
    return `${currencyFormation.symbol}${new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(num)}`;
  }
  updatePricePreview(node, priceEl, parentUnitType = null) {
    let total = 0;
    const effectiveUnitType = parentUnitType || node.unit_type;

    const inputEl = this.wrap.querySelector(`[data-node-id="${node.id}"] input[type="number"]`);
    let qty = inputEl ? parseFloat(inputEl.value) || 0 : 0;

    if (node.additional_fee && !isNaN(node.price)) {
      if (node.type === 'number' && effectiveUnitType === "price_per_unit") total = node.price * qty;
      else total = node.price;
    }

    // Update price display
    if(total > 0){
      let displayLabel = '';
      let unitLabel = Utils.esc(effectiveUnitType);
      if (unitLabel === 'price_per_unit') {
        unitLabel = 'Price per unit'; // override label for display
      }

      if(effectiveUnitType === 'price_per_pax') {
        // priceEl.style.display = 'none';
        priceEl.style.display = 'block';
        displayLabel = `Price: ${this.toCurrency(total)} <small class="text-muted">${unitLabel}</small>`;
      } else {
        // let label = total;
        if(node.type === 'number' && effectiveUnitType === "price_per_unit"){
          const unitTypeBadge = `<span style="
            background: #f2eaf5;
            color: #9400d3;
            padding:3px 8px;
            border-radius:6px;
            font-weight:500;
            font-size:0.8rem;
          ">${unitLabel} : ${this.toCurrency(parseFloat(node.price))}</span>`;

          // label = `Total: ${this.toCurrency(total)} <small class="text-muted">${unitTypeBadge}</small>`;
          displayLabel = `Total: ${this.toCurrency(total)} <small class="text-muted">${unitTypeBadge}</small>`;
        } else {
          // label = `Price: ${this.toCurrency(total)}`;
          displayLabel = `Price: ${this.toCurrency(total)}`;
        }
        priceEl.style.display = 'block';
        // priceEl.innerHTML = `<span class="price-value">${label}</span>`;
      }
      priceEl.innerHTML = `<span class="price-value">${displayLabel}</span>`;
    } else {
      priceEl.style.display = 'none';
    }

    // Update children recursively, passing parent unit_type
    (node.rules || []).forEach(rule => {
      (rule.children || []).forEach(child => {
        if (child.priceEl) {
          // Reuse stored element
          this.updatePricePreview(child, child.priceEl, effectiveUnitType);
        } else {
          // Fallback if not cached
          const childPriceEl = this.wrap.querySelector(`[data-node-id="${child.id}"] .price-preview`);
          if (childPriceEl) {
            child.priceEl = childPriceEl; // cache for next time
            this.updatePricePreview(child, childPriceEl, effectiveUnitType);
          }
        }
      });
    });
  }
  attachLiveUpdates() {
    // ✅ Watch for changes in the Form Builder dropdowns
    document.addEventListener('change', e => {
      const target = e.target;

      // If the changed field is visibility or status dropdown
      if (target.matches('[data-visibility-id], [data-status-id]')) {
        const nodeId = target.dataset.visibilityId || target.dataset.statusId;
        // const node = this.builder.findNode(nodeId);
        const node = this.builder.findNodeById(nodeId);
        if (!node) return;

        // Update node values
        if (target.dataset.visibilityId) node.visibility = target.value;
        if (target.dataset.statusId) node.status = target.value;

        // Update preview dynamically
        const metaEl = this.wrap.querySelector(`.addon-meta[data-meta-id="${node.id}"]`);
        if (metaEl) this.updateMetaPreview(metaEl, node);
      }
    });
  }
  attachLogic(){
    const wrap = this.wrap;
    const builder = this.builder;

    function getValueOf(elWrap, type){
      if(type==='checkbox') return Utils.$('.form-check-input', elWrap).checked ? 'checked' : 'not_checked';
      if(type==='radio'){ const sel = Utils.$('input[type="radio"]:checked', elWrap); return sel ? sel.value : ''; }
      if(type==='dropdown') return Utils.$('select', elWrap).value;
      /*if(type==='number') return Number(Utils.$('input[type="number"]', elWrap).value || 0);
      if(type==='text') return Utils.$('input[type="text"]', elWrap)?.value || '';
      if(type==='textbox') return Utils.$('textarea', elWrap)?.value || '';*/
      return '';
    }
    function evalRule(val, type, rule, elWrap){
      if(type==='checkbox') return rule.operator ==='checked' ? val==='checked' : val!=='checked';
      if(type==='radio' || type==='dropdown'){
        if (rule.option_id) {
          const selectedEl =
            type === 'radio'
              ? elWrap.querySelector('input[type="radio"]:checked')
              : elWrap.querySelector('select');

          if (!selectedEl) return false;

          const selectedOptionId =
            type === 'radio'
              ? String(selectedEl.dataset.optionId)
              : String(selectedEl.selectedOptions[0]?.dataset.optionId);

          return rule.operator === 'equals' ? selectedOptionId === String(rule.option_id) : selectedOptionId !== String(rule.option_id);
        } else {
          // Fallback to value-based comparison
          if (rule.operator === 'equals') return String(val) === String(rule.value);
          if (rule.operator === 'not_equals') return String(val) !== String(rule.value);
        }
      }
      /*if(type==='text' || type==='textbox'){
        if(rule.operator ==='equals') return String(val)===String(rule.value);
        if(rule.operator ==='not_equals') return String(val)!==String(rule.value);
      }
      if(type==='number'){
        const n=Number(val), r=Number(rule.value);
        if(rule.operator ==='equals') return n===r; if(rule.operator ==='gt') return n>r; if(rule.operator ==='gte') return n>=r; if(rule.operator ==='lt') return n<r; if(rule.operator ==='lte') return n<=r;
      }*/
      return false;
    }
    function walk(node){
      const elWrap = wrap.querySelector(`[data-node-id="${node.id}"]`);
      if(!elWrap) return;
      const val = getValueOf(elWrap, node.type);
      (node.rules || []).forEach((r, idx)=>{
        const childWrap = elWrap.querySelector(`.indent[data-rule-index="${idx}"]`);
        if(!childWrap) return;
        const show = evalRule(val, node.type, r, elWrap);
        childWrap.style.display = show ? '' : 'none';
        (r.children || []).forEach(ch=> walk(ch));
      });
    }
    wrap.addEventListener('input', ()=> builder.tree.forEach(n=> walk(n)) );
    wrap.addEventListener('change', ()=> builder.tree.forEach(n=> walk(n)) );
    builder.tree.forEach(n=> walk(n));
  }
}