// formBuilder.js (class-based, main drag only, always-visible layout)
class Utils {
  static $(s, ctx=document){ return ctx.querySelector(s); }
  static $$(s, ctx=document){ return Array.from(ctx.querySelectorAll(s)); }
  static uid = 0;
  static id(prefix){ return `${prefix}_${++Utils.uid}`; }
  static esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }
  static cap(s){ return s.charAt(0).toUpperCase()+s.slice(1); }
}

const TYPE_HAS_OPTIONS = new Set(['radio','dropdown']);
const TYPE_HAS_RULES = new Set(['radio','dropdown','checkbox']);

class FieldManager {
  constructor(paletteEl, builder){
    this.paletteEl = paletteEl;
    this.builder = builder;
    this.bind();
  }
  bind(){
    Utils.$$('#fieldPalette .palette-item').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        this.builder.addField(btn.dataset.type);
      });
    });
  }
}

class FormBuilder {
  constructor(rootListEl, mainAddonForm){
    this.rootListEl = rootListEl;
    this.tree = [];
    this.optionEditor = new OptionEditor(this);
    this.logic = new ConditionalLogic(this);
    this.preview = new PreviewManager(this);
    this.mainAddonForm = mainAddonForm; // Save reference
    this.initDrag();
    this.initEvents();
    document.addEventListener('options:updated', ()=> this.refresh());
  }
  makeNode(type, isChild = false){
    // Find highest existing sort order
    const highestSort = this.tree.length
      ? Math.max(...this.tree.map(f => f.sort_order || 0))
      : 0;

    return {
      id: Utils.id('fld'),
      label: Utils.cap(type.replace(/_/g,' ')),
      type,
      required: false,
      options: TYPE_HAS_OPTIONS.has(type) ? [{label:'Option 1', value:'opt1', sort_order: 1}] : [],
      rules: [],
      additional_fee: false,
      display_once: false,       // Only meaningful for unit_type === 'price_per_pax'; unchecked by default
      visibility: isChild ? null : 'both',       // Null for child fields
      status: isChild ? null : 'Active',         // Null for child fields
      unit_type: isChild ? null : 'one_time',    // Null for child fields
      category_id: isChild ? null : this.getDefaultCategory()?.id ?? null,   // Null for child fields
      category_name: isChild ? null : this.getDefaultCategory()?.name ?? null,
      package_id: null,           // No default — most add-ons aren't package-linked. Null for child fields.
      package_name: null,
      sort_order: highestSort + 1 // Automatically set next sort_order
    };
  }

  getCategoriesCache() {
    return (this.mainAddonForm && this.mainAddonForm.categoriesCache) || [];
  }

  getDefaultCategory() {
    return this.getCategoriesCache().find(c => c.name === 'Default') || null;
  }

  getPackagesCache() {
    return (this.mainAddonForm && this.mainAddonForm.packagesCache) || [];
  }

  triggerSaveReminder() {
    if (this.mainAddonForm && typeof this.mainAddonForm.renderSaveReminder === "function") {
      this.mainAddonForm.renderSaveReminder(true);
    }
  }

  addField(type){
    this.tree.push(this.makeNode(type));
    this.refresh();

    // Show reminder after adding a field too
    this.mainAddonForm.renderSaveReminder(true);
  }

  deleteField(index, siblings, node, btn){
    // 1. Remove from data
    siblings.splice(index, 1);

    // 2. Remove from modal UI immediately if clicked inside modal
    const fieldWrapper = btn.closest('.mt-2');
    if (fieldWrapper && fieldWrapper.parentNode) {
        fieldWrapper.parentNode.removeChild(fieldWrapper);
    }

    // Then recursively remove from this.builder.tree
    this.removeFieldFromTree(this.tree, node.id);

    // 3. Refresh the main builder UI
    this.refresh();

    // Show reminder after deleting field
    this.mainAddonForm.renderSaveReminder(true);
  }

  removeFieldFromTree(arr, deleteId) {
    if (!Array.isArray(arr)) return;

    for (let i = arr.length - 1; i >= 0; i--) {
      const field = arr[i];

      // 1. Remove from rule.children if present
      if (Array.isArray(field.rules)) {
        field.rules.forEach(rule => {
          if (Array.isArray(rule.children)) {
            this.removeFieldFromTree(rule.children, deleteId);
          }
        });
      }

      // 2. Remove from field.children[] if present
      if (Array.isArray(field.children)) {
        this.removeFieldFromTree(field.children, deleteId);
      }

      // 3. Remove from current array if matches deleteId
      if (String(field.id) === String(deleteId)) {
        arr.splice(i, 1);
      }
    }
  }

  duplicateField(index, siblings){
    const copy = JSON.parse(JSON.stringify(siblings[index]));

    // Give the copy a new temp field id and strip DB ids from its options and
    // rules so the backend inserts new rows instead of reassigning the
    // original's. Options/rules ids are validated as integers, so they must be
    // removed rather than replaced with temp strings. Rules are re-linked to the
    // copy's options by value on save, so option_id is cleared as well.
    const regenerateIds = (node) => {
      node.id = Utils.id('fld');
      if(node.options) node.options.forEach(o => {
        delete o.id;
        delete o.field_id;
      });
      if(node.rules) node.rules.forEach(r => {
        delete r.id;
        delete r.field_id;
        r.option_id = null;
        r.children?.forEach(c => regenerateIds(c));
      });
      node.children?.forEach(c => regenerateIds(c));
    };
    regenerateIds(copy);

    siblings.splice(index+1, 0, copy);
    // siblings.splice(index+1, 0, JSON.parse(JSON.stringify(siblings[index])));
    this.refresh();
    this.mainAddonForm.renderSaveReminder(true);
  }

  initDrag(){
    new Sortable(this.rootListEl, {
      handle: '.handle',
      animation: 150,
      ghostClass: 'bg-light',
      onEnd: () => {
        const order = Utils.$$('#rootList > li').map(li => li.dataset.nodeId);
        // Reorder the tree based on new positions
        this.tree = order.map(id => this.tree.find(f => String(f.id) === String(id))).filter(Boolean);

        // Update sort_order starting from 1
        this.tree.forEach((field, index) => {
          field.sort_order = index + 1;
        });

        // Keep form.fields synced
        if (this.form) {
          this.form.fields = [...this.tree];
        }
        this.refresh();
      }
    });
  }

  refresh(){
    // rebuild builder UI
    this.rootListEl.innerHTML='';
    this.tree.forEach((node, i)=>{
      const li = document.createElement('li');
      li.dataset.nodeId = node.id;
      li.classList.add('active');
      this.removeActiveFromOthers(li);
      li.innerHTML = this.renderFieldCard(node, this.tree, i, true);
      this.hydrateFieldCard(li, node, this.tree, i, true);
      this.rootListEl.appendChild(li);
    });
    // rebuild preview
    this.preview.render();
    // this.tree.forEach(node => this.applyUnitTypeVisibility(node));

    // Emit tree update event whenever UI refreshes
    const event = new CustomEvent('formbuilder:updated', {
        detail: { tree: this.tree }
    });
    document.dispatchEvent(event);
  }

  /**
   * ✅ Centralized delegated event bindings
   */
  initEvents() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;

      const action = btn.dataset.act;

      // Find owning node
      const containerEl = btn.closest('.child-section, li');
      const nodeId = containerEl?.dataset.childId || containerEl?.dataset.nodeId || btn.dataset.nodeId;
      const node = nodeId ? this.findNodeById(nodeId) : null;

      switch (action) {
        case "condition": {
          if (!node) break;
          this.logic.openFor(node);
          break;
        }

        case "editRule": {
          if (!node) break;
          const ruleBlock = btn.closest(".rule-block");
          if (!ruleBlock) break;

          const ruleIndex = [...ruleBlock.parentElement.children].indexOf(ruleBlock);
          if (ruleIndex < 0 || !node.rules?.[ruleIndex]) break;

          this.logic.openFor(node, ruleIndex);
          // setTimeout(() => {
          //   // Pass modal container explicitly
          //   this.applyUnitTypeVisibility(node, Utils.$('#logicEditor'));
          // }, 0);
          break;
        }

        case "options": {
          if (!node) break;

          // Use the existing logicModal instance from this.logic
          const logicModal = this.logic.modal;
          const logicModalEl = logicModal._element;

          const openOptions = () => this.optionEditor.openFor(node);

          if (logicModalEl.classList.contains("show")) {
            logicModalEl.addEventListener("hidden.bs.modal", openOptions, { once: true });
            logicModal.hide();
          } else {
            openOptions();
          }

          break;
        }

        case "collapse": {
          if (!node || !containerEl) break;

          const body = containerEl.querySelector('[data-role="toggle-target"]');
          const btnCollapse = containerEl.querySelector('[data-act="collapse"]');
          const btnExpand = containerEl.querySelector('[data-act="expand"]');

          node._collapsed = true;

          if (body) body.style.display = "";
          if (btnCollapse) btnCollapse.style.display = "none";
          if (btnExpand) btnExpand.style.display = "";

          // Main field highlight
          const isMainField = containerEl.closest("li");
          if (isMainField) {
            containerEl.classList.add("active");
            this.removeActiveFromOthers(containerEl);
          }
          break;
        }

        case "expand": {
          if (!node || !containerEl) break;

          const body = containerEl.querySelector('[data-role="toggle-target"]');
          const btnCollapse = containerEl.querySelector('[data-act="collapse"]');
          const btnExpand = containerEl.querySelector('[data-act="expand"]');

          node._collapsed = false;

          if (body) body.style.display = "none";
          if (btnExpand) btnExpand.style.display = "none";
          if (btnCollapse) btnCollapse.style.display = "";

          // Main field highlight remove
          const isMainField = containerEl.closest("li");
          if (isMainField) {
            containerEl.classList.remove("active");
          }
          break;
        }

        case "del": {
          if (!node) break;

          // We need to know siblings & index for correct removal
          const parentInfo = this.findParentAndSiblings(nodeId);
          if (!parentInfo) return;

          this.confirmAndDeleteField(
            node,
            parentInfo.index,
            parentInfo.siblings,
            btn
          );
          break;
        }

        case "dup": {
          if (!node) break;

          // Get parent info so we know siblings & index
          const parentInfo = this.findParentAndSiblings(nodeId);
          if (!parentInfo) return;

          this.duplicateField(parentInfo.index, parentInfo.siblings);
          break;
        }

        case "addChild": {
          if (!node) break;

          // Which rule is this button inside?
          const ruleBlock = btn.closest(".rule-block");
          if (!ruleBlock) break;

          const ruleIndex = [...ruleBlock.parentElement.children].indexOf(ruleBlock);
          const rule = node.rules?.[ruleIndex];
          if (!rule) break;

          const child = this.makeNode("text", true);
          child.label = "Child field";
          child.sort_order = (rule.children?.length || 0) + 1;

          rule.children.push(child);
          this.refresh();
          this.mainAddonForm.renderSaveReminder(true);
          // this.applyUnitTypeVisibility(node);
          break;
        }

        case "delRule": {
          if (!node) break;

          const ruleBlock = btn.closest(".rule-block");
          if (!ruleBlock) break;

          const ruleIndex = [...ruleBlock.parentElement.children].indexOf(ruleBlock);
          const currentRule = node.rules?.[ruleIndex];
          if (!currentRule) break;

          const childCount = currentRule.children?.length || 0;

          if (childCount === 0) {
            node.rules.splice(ruleIndex, 1);
            this.refresh();
            break;
          }

          swal({
            title: "Delete Rule?",
            text: `This rule has ${childCount} child field${childCount > 1 ? "s" : ""}. 
              Deleting this rule will also delete all its child fields permanently. 
              Are you sure you want to continue?`,
            icon: "warning",
            buttons: ["Cancel", "Yes, Delete Rule"],
            dangerMode: true,
          }).then((willDelete) => {
            if (willDelete) {
              node.rules.splice(ruleIndex, 1);
              this.refresh();
              this.mainAddonForm.renderSaveReminder(true);
              swal("Deleted!", "The rule and all its child fields have been removed.", "success");
            }
          });
          break;
        }
      }
    });
  }

  /**
   * ✅ Helper: find node in tree by ID
   */
  findNodeById(id) {
    let found = null;
    function recurse(nodes) {
      for (const n of nodes) {
        if (String(n.id) === String(id)) {
          found = n;
          return;
        }
        recurse(n.children || []);
        (n.rules || []).forEach(r => recurse(r.children || []));
      }
    }
    recurse(this.tree || []);
    return found;
  }

  findParentAndSiblings(id, nodes = this.tree, parent = null) {
    for (let i = 0; i < nodes.length; i++) {
      if (String(nodes[i].id) === String(id)) {
        return { parent, siblings: nodes, index: i };
      }
      if (nodes[i].children) {
        const found = this.findParentAndSiblings(id, nodes[i].children, nodes[i]);
        if (found) return found;
      }
      if (nodes[i].rules) {
        for (let r of nodes[i].rules) {
          if (r.children) {
            const found = this.findParentAndSiblings(id, r.children, nodes[i]);
            if (found) return found;
          }
        }
      }
    }
    return null;
  }

  renderFieldCard(node, siblings, index, isMain, modal = false) {
    const collapsed = !!node._collapsed;
    const reqId = isMain ? `req_${node.id}` : ((modal) ? `modal_req_${node.id}` : `ch_req_${node.id}`);
    const feeId = isMain ? `fee_${node.id}` : ((modal) ? `modal_fee_${node.id}` : `ch_fee_${node.id}`);
    const priceWrapperId = isMain ? `priceWrapper_${node.id}` : ((modal) ? `modal_priceWrapper_${node.id}` : `ch_priceWrapper_${node.id}`);
    const displayOnceId = `displayOnce_${node.id}`;
    const displayOnceWrapperId = `displayOnceWrapper_${node.id}`;
    
    return `
      <!-- Field Header -->
      <div class="field-header flex justify-between items-center border border-[#E5E7EB] rounded-lg overflow-hidden mt-3 bg-[#F9FAFB] p-3">
        <div class="field-main flex items-center gap-2 form-custom-label w-full">
          ${isMain 
            ? '<span class="handle text-xs text-[#020817] mr-2 cursor-grab"><i class="fas fa-solid fa-grip-vertical fa-lg"></i></span>' 
            : '<span class="condition-drag-handle text-xs text-[#020817] mr-2 cursor-grab"><i class="fas fa-solid fa-grip-vertical fa-lg"></i></span>'
          }
          <!-- ✅ Field Label Shown Here -->
          <span class="addon-label fw-bold text-primary" style="min-width: 180px;">
            ${Utils.esc(node.label || 'Untitled Field')}
          </span>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-sm btn-outline-primary btn-primary-small ${TYPE_HAS_OPTIONS.has(node.type) ? '' : 'hidden'}" data-node-id="${node.id}" data-act="options" data-toggle="tooltip" title="Manage Options">
            <i class="fas fa-solid fa-list-ul"></i>
          </button>

          <button class="btn btn-sm btn-outline-success btn-success-small ${TYPE_HAS_RULES.has(node.type) ? '' : 'hidden'}" data-node-id="${node.id}" data-act="condition" data-toggle="tooltip" title="Manage Conditions">
            <i class="fas fa-solid fa-bolt"></i>
          </button>
          ${isMain
            ? '<button class="btn btn-sm btn-outline-secondary btn-info-small" data-act="dup" data-toggle="tooltip" title="Duplicate"><i class="fas fa-regular fa-copy"></i></button>'
            : ''
          }
          <!-- ▼ Up / Down buttons -->
          <button class="btn btn-sm btn-outline-danger btn-danger-small" data-act="del" data-toggle="tooltip" title="Delete" data-node-id="${node.id}">
            <i class="fas fa-regular fa-trash-alt"></i>
          </button>

          <button class="btn btn-sm btn-outline-secondary btn-secondary-small" data-act="expand" data-node-id="${node.id}" style="${collapsed ? 'display:none' : ''}">
            <i class="fas fa-solid fa-chevron-up"></i>
          </button>
          <button class="btn btn-sm btn-outline-secondary btn-secondary-small" data-act="collapse" data-node-id="${node.id}" style="${collapsed ? '' : 'display:none'}">
            <i class="fas fa-solid fa-chevron-down"></i>
          </button>
        </div>
      </div>

      <!-- ▼ everything below header goes here so it toggles as one -->
      <div class="addon-content border border-[#E5E7EB] -mt-[1px] rounded-lg p-3" data-role="toggle-target" style="display:${collapsed ? 'none' : ''}">
        <!-- keep your existing rows exactly as they are -->
        <div class="flex flex-wrap -mx-3 g-2">
          <!-- Field Label -->
          <div class="w-full md:w-2/3 max-w-full px-3">
            <label class="form-label mini form-custom-label">Field Label <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-custom-input" data-k="label" value="${Utils.esc(node.label || '')}">
          </div>

          <!-- Field Type -->
          <div class="w-full md:w-1/3 max-w-full px-3">
            <label class="form-label mini form-custom-label">Type <span class="text-danger">*</span></label>
            <select class="form-select form-custom-input" data-k="type">
              ${['text', 'textbox', 'number', 'checkbox', 'radio', 'dropdown']
                .map(t => `
                  <option value="${t}" ${node.type === t ? 'selected' : ''}>
                    ${Utils.cap(t.replace(/_/g, ' '))}
                  </option>`
                ).join('')}
            </select>
          </div>
        </div>

        <!-- Description Field -->
        <div class="mt-2">
          <label class="form-label mini form-custom-label">Description</label>
          <input type="text" class="form-control form-custom-input" data-k="description" value="${Utils.esc(node.description || '')}" placeholder="Enter Description....">
        </div>

        <div class="flex flex-wrap -mx-3 mt-2" id="minMaxWrapper_${node.id}" style="display: none;">
          <!-- Min/Max Values -->
          <div class="w-full md:w-1/2 max-w-full px-3">
            <label class="form-label mini form-custom-label">Min</label>
            <input type="number" class="form-control form-custom-input" id="min_${node.id}" value="${node.min ?? ''}" min="0" step="1">
          </div>

          <div class="w-full md:w-1/2 max-w-full px-3">
            <label class="form-label mini form-custom-label">Max</label>
            <input type="number" class="form-control form-custom-input" id="max_${node.id}" value="${node.max ?? ''}" min="0" step="1">
          </div>
        </div>

        <!-- MAIN FORM EXTRA FIELDS -->
        ${isMain ? `
          <div class="flex flex-wrap -mx-3 mt-2">
            <!-- Visibility Dropdown -->
            <div class="w-full md:w-1/3 max-w-full px-3">
              <label class="form-label mini form-custom-label">Visibility</label>
              <select class="form-select form-custom-input" data-k="visibility">
                ${['Both', 'Frontend', 'Backend'].map(v => {
                  const value = v.toLowerCase(); // "Both" → "both"
                  return `<option value="${value}" ${node.visibility === value ? 'selected' : ''}>${v}</option>`;
                }).join('')}
              </select>
            </div>

            <!-- Status Dropdown -->
            <div class="w-full md:w-1/3 max-w-full px-3">
              <label class="form-label mini form-custom-label">Status</label>
              <select class="form-select form-custom-input" data-k="status">
                ${['Active', 'Inactive'].map(s =>
                  `<option value="${s}" ${node.status === s ? 'selected' : ''}>${s}</option>`
                ).join('')}
              </select>
            </div>

            <!-- Unit Type -->
            <div class="w-full md:w-1/3 max-w-full px-3">
              <label class="form-label mini form-custom-label">Unit Type</label>
              <select class="form-select form-custom-input" data-k="unit_type">
                ${['One time', 'Price per unit', 'Price per pax'].map(u => {
                  const value = u.toLowerCase().replace(/\s+/g, '_'); // convert "One time" → "one_time"
                  return `<option value="${value}" ${node.unit_type === value ? 'selected' : ''}>${u}</option>`;
                }).join('')}
              </select>
            </div>
          </div>

          <!-- Category & Package (own row, directly under Visibility/Status/Unit Type) -->
          <div class="flex flex-wrap -mx-3 mt-2">
            <!-- Category -->
            <div class="w-full md:w-1/3 max-w-full px-3">
              <label class="form-label mini form-custom-label">Category</label>
              <select class="form-select form-custom-input" data-k="category_id">
                ${this.getCategoriesCache().map(c => {
                  const selected = String(node.category_id ?? '') === String(c.id) ? 'selected' : '';
                  return `<option value="${c.id}" ${selected}>${Utils.esc(c.name)}</option>`;
                }).join('')}
              </select>
            </div>

            <!-- Linked Package -->
            <div class="w-full md:w-1/3 max-w-full px-3">
              <label class="form-label mini form-custom-label">Package</label>
              <select class="form-select form-custom-input" data-k="package_id">
                <option value="" ${!node.package_id ? 'selected' : ''}>None</option>
                ${this.getPackagesCache().map(p => {
                  const selected = String(node.package_id ?? '') === String(p.id) ? 'selected' : '';
                  return `<option value="${p.id}" ${selected}>${Utils.esc(p.name)}</option>`;
                }).join('')}
              </select>
            </div>
          </div>
        ` : ''}

        <!-- Checkboxes -->
        <!-- Required Checkbox -->
        <div class="flex flex-wrap -mx-3 g-2 mt-1 form-checkbox">
          <div class="w-full md:w-1/3 max-w-full px-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="${reqId}" ${node.required ? 'checked' : ''}>
              <label class="form-check-label mini form-custom-label" for="${reqId}">Required</label>
            </div>
          </div>
        </div>

        ${isMain ? `
        <!-- Display Once Checkbox (only meaningful when Unit Type = Price per pax) -->
        <div class="flex flex-wrap -mx-3 g-2 form-checkbox" id="${displayOnceWrapperId}" style="display: ${node.unit_type === 'price_per_pax' ? 'block' : 'none'};">
          <div class="w-full md:w-1/3 max-w-full px-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="${displayOnceId}" ${node.display_once ? 'checked' : ''}>
              <label class="form-check-label mini form-custom-label" for="${displayOnceId}">Show Once</label>
              <i class="fas fa-info-circle ms-1" style="color:#6B7280;cursor:pointer;font-size:0.8rem" data-toggle="tooltip" title="Shown once with the other add-ons instead of once per traveler — the charged amount is still based on the total number of travelers."></i>
            </div>
          </div>
        </div>
        ` : ''}

        <!-- Additional Fee Checkbox -->
        <div class="flex flex-wrap -mx-3 g-2 form-checkbox">
          <div class="w-full md:w-1/3 max-w-full px-3">
            <div class="form-check">
              <input class="form-check-input additional-fee-toggle" type="checkbox" id="${feeId}" ${node.additional_fee ? 'checked' : ''}>
              <label class="form-check-label mini form-custom-label" for="${feeId}">Additional Fee</label>
            </div>
          </div>
        </div>

        <!-- Price Field (Visible Only If Additional Fee Checked) -->
        <div class="flex flex-wrap -mx-3 g-2 mt-1" id="${priceWrapperId}" style="display: ${node.additional_fee ? 'block' : 'none'};">
          <div class="w-full md:w-1/3 max-w-full px-3">
            <label class="form-label mini mb-1 form-custom-label">Price</label>
            <input type="number" class="form-control form-custom-input" data-price-id="${node.id}"
              value="${Utils.esc(node.price || '')}" placeholder="Enter price">
          </div>
        </div>

        <!-- Rules Section -->
        <div data-role="rules" class="mt-2">
          ${(node.rules && node.rules.length)
            ? '<div class="mini text-muted mb-1">Conditional rules</div>'
            : ''
          }
        </div>
      </div>
    `;
  }

  hydrateFieldCard(containerEl, node, siblings, index, isMain){
    // bindings
    if (Array.isArray(node.children)) {
      node.children.sort((a, b) => {
        const sa = Number(a.sort_order ?? Number.MAX_SAFE_INTEGER);
        const sb = Number(b.sort_order ?? Number.MAX_SAFE_INTEGER);
        return sa - sb;
      });
    }

    (node.rules || []).forEach(rule => {
      if (Array.isArray(rule.children)) {
        rule.children.sort((a, b) => {
          const sa = Number(a.sort_order ?? Number.MAX_SAFE_INTEGER);
          const sb = Number(b.sort_order ?? Number.MAX_SAFE_INTEGER);
          return sa - sb;
        });
      }
    });

    const getChildCard = (id) =>
      document.querySelector(`.child-section[data-child-id="${id}"]`);

    const labelInput = containerEl.querySelector('[data-k="label"]');
    if (labelInput) {
        labelInput.addEventListener('input', e => {
            node.label = e.target.value !== "" ? e.target.value : 'Untitled Field';

            // Update page UI from popup
            // 🔄 Update Page UI child card
            const cardEl = getChildCard(node.id); // e.g. .child-section[data-child-id="fld_4"]
            if (cardEl) {
              const labelSpan = cardEl.querySelector('.addon-label');
              if (labelSpan) {
                labelSpan.textContent = node.label;
                labelSpan.dataset.originalText = node.label;
                labelSpan.setAttribute("title", node.label);
              }

              const labelInputInCard = cardEl.querySelector('[data-k="label"]');
              if (labelInputInCard) labelInputInCard.value = node.label;
            }

            // 🔄 Update popup’s own label (so user sees truncation while typing)
            const popupLabelSpan = containerEl.querySelector('.addon-label');
            if (popupLabelSpan) {
              const fullText = node.label;
              const maxLength = 32;
              popupLabelSpan.dataset.originalText = fullText;

              if (fullText.length > maxLength) {
                popupLabelSpan.textContent = fullText.substring(0, maxLength) + "...";
                popupLabelSpan.setAttribute("title", fullText);
              } else {
                popupLabelSpan.textContent = fullText;
                popupLabelSpan.removeAttribute("title");
              }
            }

            // Update the preview section as before
            this.preview.render();
            applyLabelTruncation();
        });
    }

    // Field Type Change Handler
    const typeSelect = containerEl.querySelector('[data-k="type"]');
    if (typeSelect) {
        typeSelect.addEventListener('change', e => {
            const newType = e.target.value;
            node.type = newType;

            // Show/Hide Min & Max fields based on selected type
            const minMaxWrapper = containerEl.querySelector(`#minMaxWrapper_${node.id}`);
            if (minMaxWrapper) {
                minMaxWrapper.style.display = (newType === 'number') ? 'flex' : 'none';
            }

            // Reset options if type doesn't support them
            if (!TYPE_HAS_OPTIONS.has(newType)) node.options = [];

            // Reset rules for new type
            (node.rules || []).forEach(r => {
                const base = this.logic.makeRule(newType);
                r.operator = base.operator;
                r.value = '';
            });

            // Toggle buttons visibility instantly
            const optionsBtn = containerEl.querySelector('[data-act="options"]');
            const conditionBtn = containerEl.querySelector('[data-act="condition"]');

            if (optionsBtn) {
              optionsBtn.classList.toggle('hidden', !TYPE_HAS_OPTIONS.has(newType));
            }
            if (conditionBtn) {
              conditionBtn.classList.toggle('hidden', !TYPE_HAS_RULES.has(newType));
            }

            this.refresh();
        });
    }

    const descInput = containerEl.querySelector('[data-k="description"]');
    if (descInput) {
      descInput.addEventListener('input', e => {
        node.description = e.target.value;

        const cardEl = getChildCard(node.id);
        if (cardEl) {
          const descEl = cardEl.querySelector('[data-k="description"]');
          if (descEl) descEl.value = node.description || '';
        }

        this.preview.render();
      });
    }

    // On initial load, show min/max if type = number
    const minMaxWrapper = containerEl.querySelector(`#minMaxWrapper_${node.id}`);
    if (minMaxWrapper) {
      minMaxWrapper.style.display = (node.type === 'number') ? 'flex' : 'none';
    }

    // Bind Min Value Input
    const minInput = containerEl.querySelector(`#min_${node.id}`);
    if (minInput) {
      minInput.addEventListener('input', e => {
        node.min = e.target.value ? parseFloat(e.target.value) : null;

        const cardEl = getChildCard(node.id);
        if (cardEl) {
          const minEl = cardEl.querySelector(`#min_${node.id}`);
          if (minEl) minEl.value = node.min ?? '';
        }

        // Reset preview input when min changes
        const previewField = document.querySelector(`#preview [data-node-id="${node.id}"] input[type="number"]`);
        if (previewField) {
            previewField.value = "";
        }

        // Re-render preview to refresh price
        this.preview.render();
      });
    }

    // Bind Max Value Input
    const maxInput = containerEl.querySelector(`#max_${node.id}`);
    if (maxInput) {
        maxInput.addEventListener('input', e => {
          node.max = e.target.value ? parseFloat(e.target.value) : null;

          const cardEl = getChildCard(node.id);
          if (cardEl) {
            const maxEl = cardEl.querySelector(`#max_${node.id}`);
            if (maxEl) maxEl.value = node.max ?? '';
          }

          // Reset preview input when max changes
          const previewField = document.querySelector(`#preview [data-node-id="${node.id}"] input[type="number"]`);
          if (previewField) {
              previewField.value = "";
          }

          // Re-render preview to refresh price
          this.preview.render();
        });
    }

    // Main field without children
    const reqInputMain = containerEl.querySelector(`#req_${node.id}`);
    if (reqInputMain) {
      reqInputMain.addEventListener("change", e => {
        node.required = e.target.checked;
        this.preview.render();
      });
    }

    const additionalFeeInput = containerEl.querySelector(`#fee_${node.id}`);
    if (additionalFeeInput) {
      additionalFeeInput.addEventListener('change', e => {
        node.additional_fee = e.target.checked;

        // Find the price wrapper div for this field
        const priceWrapper = containerEl.querySelector(`#priceWrapper_${node.id}`);
        if (priceWrapper) {
          priceWrapper.style.display = e.target.checked ? 'block' : 'none';
        }

        // Hide price preview if additional fee is unchecked
        const pricePreviewEl = document.querySelector(
          `#preview [data-node-id="${node.id}"] .price-preview`
        );
        if (pricePreviewEl) {
          pricePreviewEl.style.display = e.target.checked ? 'block' : 'none';
        }

        // Force refresh preview when toggled
        this.preview.render();
      });
    }

    const bindRequired = (el) => {
      if (!el) return;
      el.addEventListener("change", e => {
        node.required = e.target.checked;

        // sync both sides if present
        const pageEl  = document.querySelector(`#ch_req_${node.id}`);
        const modalEl = document.querySelector(`#modal_req_${node.id}`);
        if (pageEl && pageEl !== el) pageEl.checked = node.required;
        if (modalEl && modalEl !== el) modalEl.checked = node.required;

        this.preview.render();
      });
    };

    // Attach when available on either side
    bindRequired(containerEl.querySelector(`#req_${node.id}`));
    bindRequired(containerEl.querySelector(`#ch_req_${node.id}`));
    bindRequired(containerEl.querySelector(`#modal_req_${node.id}`));

    // ----- robust bindings for additional fee -----
    const bindFee = (el) => {
      if (!el) return;
      el.addEventListener("change", e => {
        node.additional_fee = e.target.checked;

        // sync checkboxes
        const pageEl  = document.querySelector(`#ch_fee_${node.id}`);
        const modalEl = document.querySelector(`#modal_fee_${node.id}`);
        if (pageEl) pageEl.checked = node.additional_fee;
        if (modalEl) modalEl.checked = node.additional_fee;

        // show/hide wrappers if they exist
        const pricePage  = document.querySelector(`#ch_priceWrapper_${node.id}`);
        const priceModal = document.querySelector(`#modal_priceWrapper_${node.id}`);
        if (pricePage)  pricePage.style.display  = node.additional_fee ? "block" : "none";
        if (priceModal) priceModal.style.display = node.additional_fee ? "block" : "none";

        // update preview
        const pricePreviewEl = document.querySelector(`#preview [data-node-id="${node.id}"] .price-preview`);
        if (pricePreviewEl) pricePreviewEl.style.display = node.additional_fee ? "block" : "none";

        this.preview.render();
      });
    };

    bindFee(containerEl.querySelector(`#fee_${node.id}`));
    bindFee(containerEl.querySelector(`#ch_fee_${node.id}`));
    bindFee(containerEl.querySelector(`#modal_fee_${node.id}`));

    // PRICE INPUT BINDING (IMPORTANT)
    const priceInput = containerEl.querySelector(`[data-price-id="${node.id}"]`);
    if (priceInput) {
      priceInput.addEventListener('input', e => {
        node.price = parseFloat(e.target.value) || 0;

        const cardEl = getChildCard(node.id);
        if (cardEl) {
          const priceEl = cardEl.querySelector(`[data-price-id="${node.id}"]`);
          if (priceEl) priceEl.value = node.price;
        }

        this.preview.render(); // 🔄 Update preview instantly
      });
    }

    // Bind visibility & status ONLY for main forms
    if (isMain) {
      const visibilitySelect = containerEl.querySelector(`[data-k="visibility"]`);
      if (visibilitySelect) {
          visibilitySelect.addEventListener('change', e => {
              node.visibility = e.target.value;
              this.preview.render();
          });
      }

      const statusSelect = containerEl.querySelector(`[data-k="status"]`);
      if (statusSelect) {
          statusSelect.addEventListener('change', e => {
              node.status = e.target.value;
              this.preview.render();
          });
      }

      // BIND UNIT TYPE DROPDOWN TO NODE
      const unitTypeSelect = containerEl.querySelector(`[data-k="unit_type"]`);
      if (unitTypeSelect) {
        // Set dropdown value on initial load
        unitTypeSelect.value = node.unit_type;

        unitTypeSelect.addEventListener('change', e => {
            node.unit_type = e.target.value;
            // this.applyUnitTypeVisibility(node);

            // "Show once with other add-ons" only makes sense for price_per_pax fields —
            // hide it and clear the flag when switching away from that unit type.
            const displayOnceWrapper = containerEl.querySelector(`#displayOnceWrapper_${node.id}`);
            if (displayOnceWrapper) {
              const isPricePerPax = node.unit_type === 'price_per_pax';
              displayOnceWrapper.style.display = isPricePerPax ? 'block' : 'none';
              if (!isPricePerPax) {
                node.display_once = false;
                const displayOnceCheckbox = containerEl.querySelector(`#displayOnce_${node.id}`);
                if (displayOnceCheckbox) displayOnceCheckbox.checked = false;
              }
            }

            this.preview.render();
        });
      }

      // BIND "SHOW ONCE WITH OTHER ADD-ONS" CHECKBOX TO NODE
      const displayOnceInput = containerEl.querySelector(`#displayOnce_${node.id}`);
      if (displayOnceInput) {
        displayOnceInput.addEventListener('change', e => {
          node.display_once = e.target.checked;
          this.preview.render();
        });
      }

      // BIND CATEGORY DROPDOWN TO NODE
      const categorySelect = containerEl.querySelector(`[data-k="category_id"]`);
      if (categorySelect) {
        // Existing add-ons saved before the Category field existed have no
        // category_id — fall back to Default so the underlying node (and
        // whatever gets saved next) matches what the dropdown visibly shows.
        if (!node.category_id) {
          const defaultCategory = this.getDefaultCategory();
          if (defaultCategory) {
            node.category_id = defaultCategory.id;
            node.category_name = defaultCategory.name;
          }
        }

        // Set dropdown value on initial load
        categorySelect.value = node.category_id ?? '';

        categorySelect.addEventListener('change', e => {
            const selectedId = e.target.value ? Number(e.target.value) : null;
            node.category_id = selectedId;
            node.category_name = this.getCategoriesCache().find(c => Number(c.id) === selectedId)?.name ?? null;
            this.preview.render();
        });
      }

      // BIND LINKED PACKAGE DROPDOWN TO NODE
      const packageSelect = containerEl.querySelector(`[data-k="package_id"]`);
      if (packageSelect) {
        // Set dropdown value on initial load
        packageSelect.value = node.package_id ?? '';

        packageSelect.addEventListener('change', e => {
            const selectedId = e.target.value ? Number(e.target.value) : null;
            node.package_id = selectedId;
            node.package_name = this.getPackagesCache().find(p => Number(p.id) === selectedId)?.name ?? null;
            this.preview.render();
        });
      }
    }

    // Collapse / Expand by up/down buttons
    /*const body = containerEl.querySelector('[data-role="toggle-target"]');
    const btnCollapse = containerEl.querySelector('[data-act="collapse"]');
    const btnExpand = containerEl.querySelector('[data-act="expand"]');

    if (body && btnCollapse && btnExpand) {
      // Detect if this is a main field or child field
      const isMainField = containerEl.closest("li"); // Main fields are in <li>
      const currentContainer = isMainField || containerEl; // Use <li> for main, fallback to self for children

      // Collapse (UP button)
      btnCollapse.addEventListener("click", (e) => {
        e.preventDefault();
        node._collapsed = true;

        // Hide the body
        body.style.display = "";
        btnCollapse.style.display = "none";
        btnExpand.style.display = "";

        // Highlight active for main fields only
        if (isMainField) {
          currentContainer.classList.add("active");
          this.removeActiveFromOthers(containerEl);
        }
      });

      // Expand (DOWN button)
      btnExpand.addEventListener("click", (e) => {
        e.preventDefault();
        node._collapsed = false;

        // Show the body
        body.style.display = "none";
        btnExpand.style.display = "none";
        btnCollapse.style.display = "";

        // Remove active highlight for main fields only
        if (isMainField) {
          currentContainer.classList.remove("active");
        }
      });
    }*/

    // render rules + children (not draggable)
    const rulesHolder = containerEl.querySelector('[data-role="rules"]');
    rulesHolder.innerHTML = '';
    (node.rules||[]).forEach((rule, ri)=>{
      const block = document.createElement('div');
      block.className = 'rule-block mt-2 ml-4 pl-4 border-l border-[#E5E7EB]';
      block.innerHTML = `
        <div class="flex justify-between items-center mb-2">
          <div class="mini text-muted text-xs text-[#020817]">If <strong>${Utils.esc(node.label)}</strong> ${this.ruleSummary(node, rule)} then show:</div>
          <div class="flex gap-2">
            <button class="btn btn-sm btn-outline-success btn-success-small" data-act="addChild" data-node-id="${node.id}"><i class="fas fa-solid fa-plus"></i></button>
            <button class="btn btn-sm btn-outline-primary btn-primary-small" data-act="editRule" data-node-id="${node.id}"><i class="far fa-edit"></i></button>
            <button class="btn btn-sm btn-outline-danger btn-light-small" data-act="delRule" data-node-id="${node.id}"><i class="fas fa-regular fa-trash-alt"></i></button>
          </div>
        </div>
        <div class="indent mt-2" data-role="children"></div>
      `;
      
      const childrenWrap = block.querySelector('[data-role="children"]');
      (rule.children||[]).forEach((ch, ci)=>{
        const childCard = document.createElement('div');
        childCard.className = 'mt-2 child-section';
        childCard.dataset.childId = ch.id;
        childCard.dataset.nodeId = ch.id;
        childCard.innerHTML = this.renderFieldCard(ch, rule.children, ci, false);
        this.hydrateFieldCard(childCard, ch, rule.children, ci, false);
        childrenWrap.appendChild(childCard);
      });
      rulesHolder.appendChild(block);

      // Enable drag & drop for conditional child fields
      if (childrenWrap && rule.children.length > 0) {
        // Store sortable instances in a Map instead of overwriting a single variable
        if (!this.sortableChildren) {
          this.sortableChildren = new Map();
        }

        // If this block already has a sortable, destroy it before re-initializing
        if (this.sortableChildren.has(childrenWrap)) {
          this.sortableChildren.get(childrenWrap).destroy();
        }

        const sortableInstance = new Sortable(childrenWrap, {
            animation: 150,
            handle: '.condition-drag-handle', // Must exist in DOM
            onEnd: (evt) => {
              // 1. Reorder local array
              const moved = rule.children.splice(evt.oldIndex, 1)[0];
              rule.children.splice(evt.newIndex, 0, moved);
              // 2. Update children's sort_order
              // Update sort_order for child fields
              rule.children.forEach((child, index) => {
                child.sort_order = index + 1;
              });

              this.refresh();
            }
        });
        // Save sortable instance for this specific children container
        this.sortableChildren.set(childrenWrap, sortableInstance);
      }
    });
  }

  /**
   * ✅ Centralized: Hide or show "Additional Fee" and "Price" fields
   * depending on parent's unit_type = "Price per pax"
   */
  applyUnitTypeVisibility(node, rootEl = document) {
    if (!node) return;

    const isPricePerPax = node.unit_type === "price_per_pax";

    // Helper: find field element inside a given root
    const findFieldElement = (fieldId, root = document) => {
        const candidates = [
            `[data-node-id="${fieldId}"]`,
            `[data-child-id="${fieldId}"]`,
            `#fee_${fieldId}`,
            `#priceWrapper_${fieldId}`,
            `#ch_fee_${fieldId}`,
            `#ch_priceWrapper_${fieldId}`,
            `#modal_fee_${fieldId}`,
            `#modal_priceWrapper_${fieldId}`,
            `#rootList [data-node-id="${fieldId}"]`,
            `#rootList [data-child-id="${fieldId}"]`,
            `#logicEditor [data-node-id="${fieldId}"]`,
            `#logicEditor [data-child-id="${fieldId}"]`,
        ];

        for (const sel of candidates) {
            try {
                const el = root.querySelector(sel);
                if (el) return el;
            } catch (err) {}
        }
        return null;
    };

    // Hide/show UI and clear model values if hide === true
    const updateFeeUI = (field, hide, root = document) => {
        field.additional_fee = hide ? false : field.additional_fee;
        field.price = hide ? null : field.price;

        const cardEl = findFieldElement(field.id, root);
        if (!cardEl) return false;

        const feeCheckbox = cardEl.querySelector('.additional-fee-toggle');
        const priceWrapper = cardEl.querySelector(
          `#priceWrapper_${field.id}, #modal_priceWrapper_${field.id}, #ch_priceWrapper_${field.id}`
        );
        const priceInput = cardEl.querySelector(`[data-price-id]`);

        if (feeCheckbox) {
            const feeRow = feeCheckbox.closest('.row') || feeCheckbox.parentElement;
            if (feeRow) feeRow.style.display = hide ? 'none' : '';
            if (hide) feeCheckbox.checked = false;
        }

        if (priceWrapper) {
            priceWrapper.style.display = hide ? 'none' : (field.additional_fee ? 'block' : 'none');
        }

        if (priceInput && hide) {
            priceInput.value = '';
        }

        return true;
    };

    // Apply to parent field
    updateFeeUI(node, isPricePerPax, rootEl);

    // Apply to options if any
    if (Array.isArray(node.options)) {
        node.options.forEach(opt => {
            opt.additional_fee = isPricePerPax ? false : opt.additional_fee;
            opt.price = isPricePerPax ? null : opt.price;
        });
    }

    // Recursively apply to children via rules
    const applyToChildren = (rules, root = rootEl) => {
        (rules || []).forEach(rule => {
            (rule.children || []).forEach(child => {
                updateFeeUI(child, isPricePerPax, root);
                if (child.rules?.length) applyToChildren(child.rules, root);
            });
        });
    };

    applyToChildren(node.rules, rootEl);
  }

  confirmAndDeleteField(node, index, siblings, btn) {
    // Count related data
    const optionsCount = node.options?.length || 0;
    const rulesCount = node.rules?.length || 0;
    const childrenCount = (node.rules || [])
      .reduce((sum, r) => sum + (r.children?.length || 0), 0);

    const hasOptions = optionsCount > 0;
    const hasRules = rulesCount > 0;
    const hasChildren = childrenCount > 0;

    // Build dynamic warning message
    let warningText = "Are you sure you want to delete this addon field?";
    if (hasOptions || hasRules || hasChildren) {
      warningText = `
        ⚠️ This addon contains related data:\n
        - ${optionsCount} option${optionsCount !== 1 ? "s" : ""}
        - ${rulesCount} rule${rulesCount !== 1 ? "s" : ""}
        - ${childrenCount} child field${childrenCount !== 1 ? "s" : ""}\n
        If you delete this addon field, **ALL related options, rules, and children will be permanently removed**.
      `;
    }

    // First confirmation
    swal({
      title: "Confirm Deletion",
      text: warningText,
      icon: "warning",
      buttons: ["Cancel", "Delete"],
      dangerMode: true,
    }).then((willDelete) => {
      if (willDelete) {
        // If dependencies exist → show second confirmation
        if (hasOptions || hasRules || hasChildren) {
          swal({
            title: "Final Confirmation",
            text: "This action cannot be undone! Are you absolutely sure?",
            icon: "error",
            buttons: ["No, Cancel", "Yes, Delete Permanently"],
            dangerMode: true,
          }).then((finalConfirm) => {
            if (finalConfirm) {
              this.deleteField(index, siblings, node, btn);
              swal("Deleted!", "The addon and all its related data have been deleted.", "success");
            }
          });
        } else {
          // No dependencies → delete directly
          this.deleteField(index, siblings, node, btn);
          swal("Deleted!", "The addon has been deleted.", "success");
        }
      }
    });
  }

  removeActiveFromOthers(containerEl) {
    const currentLi = containerEl.closest("li");

    document.querySelectorAll("#rootList > li.active").forEach((li) => {
      if (li !== currentLi) {
        li.classList.remove("active");
        const otherBody = li.querySelector('[data-role="toggle-target"]');
        const otherBtnExpand = li.querySelector('[data-act="expand"]');
        const otherBtnCollapse = li.querySelector('[data-act="collapse"]');

        if (otherBody && otherBtnExpand && otherBtnCollapse) {
          otherBody.style.display = "none";          // Collapse others
          otherBtnCollapse.style.display = "";         // Show DOWN button
          otherBtnExpand.style.display = "none";   // Hide UP button
        }
      }
    });
  }

  ruleSummary(parent, rule){
    const opMap = { checked:'is checked', not_checked:'is not checked', equals:'is', not_equals:'is not', gt:'>', gte:'≥', lt:'<', lte:'≤' };
    let rhs = '';
    if(parent.type==='radio' || parent.type==='dropdown'){
      const option = (parent.options || []).find(
        o => String(o.id) === String(rule.option_id)
      );
      rhs = option ? option.label : (rule.value || '(value)');
    } else {
      rhs = String(rule.value || '');
    }
    return `${opMap[rule.operator] ?? rule.operator} ${rhs ? `<em>${Utils.esc(rhs)}</em>` : ''}`;
  }
}

class ConditionalLogic {
  constructor(builder){
    this.builder = builder;
    this.modal = new bootstrap.Modal(Utils.$('#logicModal'));
    this.editor = Utils.$('#logicEditor');
    this.addChildBtn = Utils.$('#addChildUnderRuleBtn');
    this.currentParent = null;
    this.currentRule = null;
    this.closeRole = Utils.$('#closeRole');
    this.addChildBtn.addEventListener('click', ()=>{
      if(!this.currentRule) return;
      // Default child type text
      const child = this.builder.makeNode('text', true);
      child.label = 'Child field';
      child.sort_order = (this.currentRule.children?.length || 0) + 1;

      // const isPricePerPax = this.currentParent.unit_type === "price_per_pax";
      // if (isPricePerPax) {
      //   child.additional_fee = false;
      //   child.price = null;
      // }
      this.currentRule.children.push(child);
      this.renderEditor();
      // this.builder.applyUnitTypeVisibility(this.currentParent, this.editor);
      this.builder.refresh();
      this.builder.triggerSaveReminder();
    });
    this.closeRole.addEventListener('click', ()=>{
      this.modal.hide();
    });
  }
  openFor(node, ruleIndex=null){
    this.currentParent = node;
    if(ruleIndex==null){
      // create rule if none passed
      const rule = this.makeRule(node.type);
      node.rules.push(rule);
      this.currentRule = rule;
    } else {
      this.currentRule = node.rules[ruleIndex];
    }
    this.renderEditor();
    this.modal.show();
    // this.builder.applyUnitTypeVisibility(node);
    applyLabelTruncation();
  }
  makeRule(type){
    let operator = 'checked', value='';
    if(type==='radio' || type==='dropdown') operator = 'equals';
    // if(type==='radio' || type==='dropdown' || type==='text' || type==='textbox') operator = 'equals';
    // if(type==='number' || type.startsWith('price')) operator = 'equals';
    return { operator, value, children:[] };
  }
  renderEditor(){
    const n = this.currentParent, r = this.currentRule;

    let control = '';
    if(n.type==='checkbox'){
      control = `
        <select class="form-select form-custom-input" data-k="op" style="max-width:160px;">
          <option value="checked" ${r.operator ==='checked'?'selected':''}>is checked</option>
          <!--<option value="not_checked" ${r.operator ==='not_checked'?'selected':''}>is not checked</option>-->
        </select>`;
    } else if(n.type==='radio' || n.type==='dropdown' || n.type==='text'){
      control = `
        <div class="flex gap-2 items-center">
          <select class="form-select form-custom-input" data-k="op" style="max-width:150px;">
            <option value="equals" ${r.operator ==='equals'?'selected':''}>is</option>
            <!--<option value="not_equals" ${r.operator ==='not_equals'?'selected':''}>is not</option>-->
          </select>
          ${ n.type==='text'
            ? `<input class="form-control form-custom-input" data-k="value" placeholder="Value" value="${Utils.esc(r.value??'')}" style="max-width:220px;">`
            : `<select class="form-select form-custom-input" data-k="value" style="max-width:220px;">
                <option value="">Select value</option>
                ${(n.options||[]).map(o=>`
                  <option value="${Utils.esc(o.value)}" data-option-id="${o.id || ''}"
                    ${
                      (
                        (o.id && String(r.option_id) === String(o.id)) || 
                        (!r.option_id && String(r.value) === String(o.value))
                      ) ? 'selected' : ''
                    }>
                    ${Utils.esc(o.label)}
                  </option>
                `).join('')}
               </select>` }
        </div>`;
    } else { // number/date/price
      control = `
        <div class="flex gap-2 items-center">
          <select class="form-select form-custom-input" data-k="op" style="max-width:150px;">
            <option value="equals" ${r.operator ==='equals'?'selected':''}>equals</option>
            <option value="gt" ${r.operator ==='gt'?'selected':''}>greater than</option>
            <option value="gte" ${r.operator ==='gte'?'selected':''}>≥</option>
            <option value="lt" ${r.operator ==='lt'?'selected':''}>less than</option>
            <option value="lte" ${r.operator ==='lte'?'selected':''}>≤</option>
          </select>
          <input type="number" class="form-control form-custom-input" data-k="value" value="${Utils.esc(r.value??'')}" style="max-width:160px;">
        </div>`;
    }

    this.editor.innerHTML = `
      <div class="rule-block">
        <div class="mb-2 mini text-muted text-xs">If <strong>${Utils.esc(n.label||'(field)')}</strong> ${control} then show:</div>
        <div class="indent" id="childrenList"></div>
      </div>
    `;
    const bindOp = Utils.$('[data-k="op"]', this.editor);
    const bindVal = Utils.$('[data-k="value"]', this.editor);
    if(bindOp) bindOp.addEventListener('change', e=>{ r.operator = e.target.value; this.builder.refresh(); });
    // if(bindVal) bindVal.addEventListener('input', e=>{ r.value = e.target.value; this.builder.refresh(); });
    if(bindVal) {
      bindVal.addEventListener('input', e => {
        const selectedOption = bindVal.selectedOptions ? bindVal.selectedOptions[0] : null;
        r.value = e.target.value;
        r.option_id = selectedOption ? String(selectedOption.dataset.optionId) : null;
        this.builder.refresh();
      });
    }

    const childrenList = Utils.$('#childrenList', this.editor);
    childrenList.innerHTML = '';
    if (Array.isArray(r.children)) {
      r.children.sort((a, b) => {
        const sa = Number(a.sort_order ?? Number.MAX_SAFE_INTEGER);
        const sb = Number(b.sort_order ?? Number.MAX_SAFE_INTEGER);
        return sa - sb;
      });
    }
    r.children.forEach((child, i)=>{
      const card = document.createElement('div');
      card.className = 'mt-2 child-section';
      card.dataset.nodeId = child.id;
      card.innerHTML = this.builder.renderFieldCard(child, r.children, i, false /*main?*/, "modal_");
      // hook up child events inside
      this.builder.hydrateFieldCard(card, child, r.children, i, false);
      childrenList.appendChild(card);

      // this.builder.applyUnitTypeVisibility(child);
    });

    // Enable drag & drop for conditional child fields
    if (childrenList && r.children.length > 0) {
      if (this.sortableChildren) this.sortableChildren.destroy();

      this.sortableChildren = new Sortable(childrenList, {
          animation: 150,
          handle: '.condition-drag-handle', // Must exist in DOM
          onEnd: (evt) => {
            // 1. Reorder r.children array
            const moved = r.children.splice(evt.oldIndex, 1)[0];
            r.children.splice(evt.newIndex, 0, moved);
            // 2. Update sort_order for children
            // Update sort_order for child fields
            r.children.forEach((child, index) => {
              child.sort_order = index + 1;
            });

            this.renderEditor();
            this.builder.refresh();
          }
      });
    }
  }
}

class OptionEditor {
  constructor(builder){
    this.builder = builder; // Save builder reference
    this.modal = new bootstrap.Modal(Utils.$('#optionModal'));
    this.list = Utils.$('#optionList');
    this.addBtn = Utils.$('#addOptionBtn');
    this.saveBtn = Utils.$('#saveOptionsBtn');
    this.closeOption = Utils.$('#closeOption');
    this.currentNode = null;
    this.bind();
  }
  bind(){
    this.addBtn.addEventListener('click', ()=>{
      if(!this.currentNode) return;
      this.currentNode.options.push(this.makeOption());
      this.renderOptions();
    });
    this.closeOption.addEventListener('click', ()=>{
      this.modal.hide();
    });
    this.saveBtn.addEventListener('click', ()=>{
      this.modal.hide();
      document.dispatchEvent(new CustomEvent('options:updated'));
      if (this.builder && typeof this.builder.triggerSaveReminder === "function") {
        this.builder.triggerSaveReminder();
      }
    });
  }
  openFor(node){
    this.currentNode = node;
    if(!Array.isArray(node.options)) node.options = [];
    this.renderOptions();
    this.modal.show();
  }
  renderOptions(){
    this.list.innerHTML = '';
    (this.currentNode.options || []).forEach((opt, i)=>{
      const row = document.createElement('div');
      row.className = 'option-row border border-[#E5E7EB] rounded-lg mt-3 bg-[#F9FAFB] p-3';
      row.innerHTML = `
        <div class="flex justify-between items-center">
          <span class="option-drag-handle mr-2"><i class="fas fa-solid fa-grip-vertical fa-lg text-base"></i></span>
          <input class="form-control form-custom-input mr-2" data-k="label" value="${Utils.esc(opt.label ?? '')}" placeholder="Label">
          <input class="form-control form-custom-input mr-2" data-k="value" value="${Utils.esc(opt.value ?? '')}" placeholder="Value">
          <button class="btn btn-outline-danger btn-sm text-[#A32624]">&times;</button>
        </div>
        <div class="option-fee-row mt-2">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" data-k="additional_fee" ${opt.additional_fee ? 'checked' : ''}>
            <label class="form-check-label mini">Additional Fee</label>
          </div>
          <input type="number" class="form-control form-custom-input mt-1" data-k="price" value="${opt.price ?? ''}" placeholder="Price" style="${opt.additional_fee ? '' : 'display:none'}">
        </div>
      `;
      row.querySelector('[data-k="label"]').addEventListener('input', e=> opt.label = e.target.value);
      // row.querySelector('[data-k="value"]').addEventListener('input', e=> opt.value = e.target.value);
      const valueInput = row.querySelector('[data-k="value"]');
      valueInput.addEventListener("change", e => {
        this.updateOptionWithWarning(this.currentNode, i, opt.label, e.target.value, valueInput);
      });
      const additionalFeeCheckbox = row.querySelector('[data-k="additional_fee"]');
      const priceValueInput = row.querySelector('[data-k="price"]');

      additionalFeeCheckbox.checked = !!opt.additional_fee;
      priceValueInput.style.display = opt.additional_fee ? 'block' : 'none';
      priceValueInput.value = opt.price ?? '';

      // Show/hide price input based on checkbox
      additionalFeeCheckbox.addEventListener('change', () => {
        priceValueInput.style.display = additionalFeeCheckbox.checked ? 'block' : 'none';

        // Update the currentNode option object
        opt.additional_fee = additionalFeeCheckbox.checked;
        if (!opt.additional_fee) {
          opt.price = null; // reset price if unchecked
          priceValueInput.value = '';
        }
      });

      // Update option price when user types
      priceValueInput.addEventListener('input', (e) => {
        opt.price = parseFloat(e.target.value) || 0;
      });
      row.querySelector('button').addEventListener('click', () => {
        const option = this.currentNode.options[i];

        // Check if any rules exist for this option
        const hasRules = (this.currentNode.rules || []).some(rule => {
          const byValue = rule.value === option.value;
          const byOptionId = option.id && String(rule.option_id) === String(option.id);
          return (byValue || byOptionId) && Array.isArray(rule.children) && rule.children.length > 0;
        });

        const performDelete = () => {
          this.currentNode.options.splice(i, 1);

          // Remove related rules
          this.currentNode.rules = (this.currentNode.rules || []).filter(rule => {
            const byValue = rule.value === option.value;
            const byOptionId = option.id && String(rule.option_id) === String(option.id);
            return !(byValue || byOptionId);
          });

          if (this.builder?.preview) {
            this.builder.preview.render();
          }
          document.dispatchEvent(new CustomEvent("options:updated"));
          if (this.builder?.triggerSaveReminder) {
            this.builder.triggerSaveReminder();
          }
          this.modal.hide();
        };

        if (hasRules) {
          swal({
            title: "Delete Option?",
            text: "This option has rules and child fields attached. Deleting it will also remove its rules and child fields. Are you sure?",
            icon: "warning",
            buttons: ["Cancel", "Delete"],
            dangerMode: true,
          }).then((willDelete) => {
            if (willDelete) performDelete();
          });
        } else {
          performDelete();
        }
      });
      
      this.list.appendChild(row);
    });

    // Enable drag & drop on the option list
    if (this.sortable) this.sortable.destroy();
    this.sortable = new Sortable(this.list, {
      animation: 150,
      handle: '.option-drag-handle',
      onEnd: (evt) => {
        const moved = this.currentNode.options.splice(evt.oldIndex, 1)[0];
        this.currentNode.options.splice(evt.newIndex, 0, moved);
        // Update sort_order for each option
        this.currentNode.options.forEach((option, index) => {
          option.sort_order = index + 1;
        });
        this.renderOptions();
        document.dispatchEvent(new CustomEvent('options:updated', {
          detail: {
            fieldId: this.currentNode.id,
            options: this.currentNode.options
          }
        }));
        // document.dispatchEvent(new CustomEvent('options:updated'));
      }
    });
  }
  updateOptionWithWarning(node, optionIndex, newLabel, newValue, inputEl) {
    const option = node.options[optionIndex];
    const oldValue = option.value;

    if (oldValue === newValue) {
      option.label = newLabel;
      return;
    }

    // const affectedRules = (node.rules || []).filter(r => r.value === oldValue);
    const affectedRules = (node.rules || []).filter(r =>
      r.value === oldValue || (option.id && String(r.option_id) === String(option.id))
    );

    // CASE 1: If rules exist → show SweetAlert as before
    if (affectedRules.length > 0) {
      this.showUpdateRulesConfirmation(node, optionIndex, newLabel, newValue, inputEl, affectedRules);
      return;
    }

    // CASE 2: If no rules and no children → just update locally, but DON'T hide modal
    option.label = newLabel;
    option.value = newValue;

    if (this.builder?.preview) {
      this.builder.preview.render();
    }

    // Notify UI but DON'T hide the modal here anymore ❌
    document.dispatchEvent(new CustomEvent("options:updated"));
    if (this.builder?.triggerSaveReminder) {
      this.builder.triggerSaveReminder();
    }
  }
  showUpdateRulesConfirmation(node, optionIndex, newLabel, newValue, inputEl, affectedRules) {
    swal({
      title: "Update Option?",
      text: `This option is used in ${affectedRules.length} rule${affectedRules.length > 1 ? "s" : ""}.
              Do you want to update those rules to use "${newValue}"?`,
      icon: "warning",
      buttons: ["Cancel", "Yes, Update"],
      dangerMode: true,
    }).then((willUpdate) => {
      if (willUpdate) {
        const option = node.options[optionIndex];
        option.label = newLabel;
        option.value = newValue;

        // Update affected rules
        affectedRules.forEach(rule => {
          const oldVal = rule.value;
          rule.value = newValue;

          if (option.id) {
            rule.option_id = String(option.id);
          } else {
            rule.option_id = null; // keep a consistent shape
          }

          const cardEl = document.querySelector(`[data-node-id="${node.id}"]`);
          if (cardEl) {
            const ruleBlocks = cardEl.querySelectorAll('[data-role="rules"] .rule-block');
            ruleBlocks.forEach(block => {
              const summary = block.querySelector(".mini");
              if (summary && summary.textContent.includes(oldVal)) {
                summary.innerHTML = `If <strong>${Utils.esc(node.label)}</strong> ${this.builder.ruleSummary(node, rule)} then show:`;
              }
            });
          }
        });

        if (this.builder?.preview) {
          this.builder.preview.render();
        }
        document.dispatchEvent(new CustomEvent("options:updated"));
        if (this.builder?.triggerSaveReminder) {
          this.builder.triggerSaveReminder();
        }
      } else {
          if (inputEl) inputEl.value = oldValue; // Revert back
      }
    });
  }
  // makeOption(){ return { label:'Option', value:`opt_${Math.random().toString(36).slice(2,7)}` }; }
  makeOption(label = 'Option') {
    // Find highest sort_order among existing options
    const highestSort = this.currentNode.options && this.currentNode.options.length
      ? Math.max(...this.currentNode.options.map(o => o.sort_order || 0))
      : 0;

    const formattedValue = label
      .toLowerCase()            // Convert to lowercase
      .trim()                   // Remove extra spaces at start/end
      .replace(/\s+/g, '_');    // Replace spaces with underscores

    return {
      // id: Utils.id('opt'),         // ADD unique ID here
      field_id: this.currentNode.id,   // Always send the actual DB field ID
      label: label,
      value: formattedValue,
      additional_fee: false, // default false
      price: null, // default null
      sort_order: highestSort + 1 // Automatically assign next available sort_order
    };
  }
}

class MainAddonForm {
    /**
     * This is called from the template blade view to init the builder
     * @param {*} props 
     * @returns 
     */
    static initMainAddonForm(props = {}) {
      return new MainAddonForm(props).init();
    }

    /**
     * Set base vars and call init
     * @param {*} props 
     */
    constructor(props) {
      this.tour_package_id = parseInt(props.tour_package_id);
      this.save_url = props.save_url;
      this.delete_url = props.delete_url;
      this.get_url = props.get_url;
      this.categories_url = props.categories_url;
      this.categoriesCache = [];
      this.packages_url = props.packages_url;
      this.packagesCache = [];
      this.originalFieldCount = 0; // Track number of fields loaded from DB
      this.builderEl = props.builderEl || null;
      this.form = {
        id: null,
        name: "",
        description: "",
        fields: [],
        tour_package_id: this.tour_package_id,
        status: "Active"
      };

      // Attach events
      this.eventListeners();
    }

    eventListeners() {
      $(document).off('click', '#saveForm').on('click', '#saveForm', this.saveForm.bind(this));
      $(document).off('click', '#deleteForm').on('click', '#deleteForm', this.deleteForm.bind(this));
      $(document).off("input", "#formName").on("input", "#formName", this.setFormName.bind(this));
      $(document).off("input", "#formDescription").on("input", "#formDescription", this.setFormDescription.bind(this));
      $(document).off("change", "#formStatus").on("change", "#formStatus", this.setFormStatus.bind(this));
    }

    setFormName(e) {
      const newVal = e.target.value.trim();
      if (this.form.name !== newVal) {
        this.form.name = newVal;
        this.renderSaveReminder(true);
      }
    }

    setFormDescription(e) {
      const newVal = e.target.value.trim();
      if (this.form.description !== newVal) {
        this.form.description = newVal;
        this.renderSaveReminder(true);
      }
    }

    setFormStatus(e) {
      const newVal = e.target.value;
      if (this.form.status !== newVal) {
        this.form.status = newVal;
        this.renderSaveReminder(true);
      }
    }


    init() {
      const view = $('#tour-package-custom-form');

      // Show loader
      view.html(`
          <div class="text-center p-5">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="mt-2">Loading form, please wait...</p>
          </div>
      `);

      // Fetch categories and packages first so the builder has them ready for
      // the initial render, then fetch form data and render UI.
      this.fetchCategories(() => this.fetchPackages(() => this.loadExistingForm(view)));

      return this; // Return instance
    }

    fetchCategories(callback) {
      $.ajax({
        url: this.categories_url,
        method: "GET",
        dataType: "json",
        success: (response) => {
          this.categoriesCache = response.categories || [];
          callback();
        },
        error: (xhr) => {
          console.error("❌ Failed to load addon categories:", xhr.responseText);
          this.categoriesCache = [];
          callback();
        }
      });
    }

    fetchPackages(callback) {
      $.ajax({
        url: `${this.packages_url}/${this.tour_package_id}`,
        method: "GET",
        dataType: "json",
        success: (response) => {
          this.packagesCache = response.packages || [];
          callback();
        },
        error: (xhr) => {
          console.error("❌ Failed to load addon-eligible packages:", xhr.responseText);
          this.packagesCache = [];
          callback();
        }
      });
    }

    loadExistingForm(view) {
      $.ajax({
        url: `${this.get_url}/${this.tour_package_id}/1`,
        method: "GET",
        dataType: "json",
        success: (response) => {
          // Always set this.form first
          if(response.form) {
            this.form = response.form;
          } else {
            this.form.name = `${$("#name").val()} Addons`;
          }

          // Always render the form HTML
          view.html(this.mainAddonFormHtml());

          // Initialize Form Builder + Palette
          this.builder = new FormBuilder(Utils.$('#rootList'), this);
          new FieldManager(Utils.$('#fieldPalette'), this.builder);

          // Initialize OptionEditor & ConditionalLogic properly
          this.optionEditor = new OptionEditor(this);
          this.logicEditor = new ConditionalLogic(this.builder);

          // Set builder tree from the form
          // this.builder.tree = Array.isArray(this.form.fields) ? [...this.form.fields] : [];
          this.builder.tree = Array.isArray(this.form.fields) ? this.form.fields : [];
          this.originalFieldCount = this.builder.tree.length; // Track initial field count
          this.builder.refresh();
          // this.builder.tree.forEach(node => this.builder.applyUnitTypeVisibility(node));

          // Populate header fields safely
          $("#formName").val(this.form.name || "");
          $("#formDescription").val(this.form.description || "");
          $("#formStatus").val(this.form.status || "Active").trigger("change");
          // $("#formStatus").val(this.form.status || "Active");

          // Show delete button only if form exists
          $("#deleteForm").css("display", this.form.id ? "block" : "none");

          setTimeout(() => {
            this.collapseAllActiveDivs();
          }, 200);
        },
        error: (xhr) => {
          console.error("❌ Failed to load form:", xhr.responseText);
          view.html(`
            <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded flex items-center justify-between">
              <span>Failed to load form. Please try again.</span>
              <button id="retryLoadForm"
                class="flex items-center gap-1 font-medium hover:text-red-900">
                <i class="fas fa-sync-alt"></i>
                Refresh
              </button>
            </div>
          `);

          // Retry button click
          $('#retryLoadForm').on('click', (e) => {
            const btn = $(e.currentTarget);

            btn.prop('disabled', true)
              .html('<i class="fas fa-spinner fa-spin"></i> Refreshing...');
            this.init(); // calls init() again → loader → loadExistingForm()
          });
        }
      });
    }

    saveForm() {
      if (!this.addonFormValidation()) {
        return false; // ❌ Stop saving if labels are missing
      }

      this.form.fields = this.builder.tree;
      console.log(this.form)
      this.renderSaveReminder(false);

      // validate tour pkg
      if ($("#tour_packages").val() === "") {
        return this.onError("Please Select Tour Package");
      }
      // validate field name
      if (this.form.name === "" && $("#formName").val() === "") {
        $("#formName").focus();
        return this.onError("Addon Form Name Can Not Be Blank");
      }
      // vallidate a field is set
      if (this.originalFieldCount === 0 && this.builder.tree.length === 0) {
        return this.onError("Addon Form Contains No Fields");
      }

      const csrf = $('meta[name="csrf-token"]').attr('content');
      // const toastr = `
      //   <div id="addon-form-messages" style="opacity:.8;  position: fixed;
      //     margin-left: auto;
      //     margin-right: auto;
      //     z-index: 1000;
      //     width: 50%;
      //     left: 0;
      //     top: 40%;
      //     right: 0; ">
      //   </div>`;

      showLoading();

      $.ajax({
          url: `${this.save_url}`,
          method: "POST",
          headers: { 'X-CSRF-TOKEN': csrf },
          data: JSON.stringify(this.form),
          contentType: "application/json",
          beforeSend: () => {
            $("#saveForm").prop("disabled", true);
            $(".save-form").text("Saving...");
          },
          success: (response) => {
            hideLoading();
            $("#saveForm").prop("disabled", false);
            $(".save-form").text("Save Form");
            swal('Success', 'Add-on form saved successfully', 'success');
            // $("body").prepend(toastr);
            
            if (response.action === 'insert') {
              this.form.id = parseInt(response.form.id);
            }

            // Step 2: Sync real backend IDs with frontend temporary IDs (fld_123 → 16)
            const idMap = {};
            response.form.fields.forEach((field, idx) => {
              const oldField = this.builder.tree[idx];
              if (oldField && oldField.id && oldField.id.toString().startsWith("fld_")) {
                idMap[oldField.id] = field.id;
              }
            });

            this.builder.tree = response.form.fields;
            this.originalFieldCount = this.builder.tree.length; // Reset original count

            const updateFieldRecursively = (node) => {
              // Map temporary ID → real numeric ID
              if (idMap[node.id]) {
                node.id = idMap[node.id];
              }

              // Fix options: Assign correct field_id to radio/dropdown options
              if (node.options && node.options.length) {
                node.options.forEach(opt => {
                  opt.field_id = node.id;
                });
              }

              // Fix rules recursively
              if (node.rules && node.rules.length) {
                node.rules.forEach(rule => {
                  if (rule.children && rule.children.length) {
                    rule.children.forEach(updateFieldRecursively);
                  }
                });
              }

              // Fix child fields recursively
              if (node.children && node.children.length) {
                node.children.forEach(updateFieldRecursively);
              }
            };

            this.builder.tree.forEach(updateFieldRecursively);

            // // Re-render UI with latest IDs (optional if you want auto-refresh fields)
            this.builder.refresh();

            // Re-render preview to attach input listeners
            if (!this.preview) {
              this.preview = new PreviewManager(this.builder);
            }
            this.preview.render();

            // Collapse all active divs after saving
            setTimeout(() => {
              this.collapseAllActiveDivs();
            }, 100);
            
            // if we have a new form allow the user to delete it 
            if (this.form.id) {
              $("#deleteForm").css('display', 'block');
            }

            this.renderMsg("Form Saved Successfully!", "success", 3000);
          },
          error: (xhr) => {
            $("#saveForm").prop("disabled", false);
            $(".save-form").text("Save Form");
            this.renderMsg(xhr.responseJSON?.error || "Something went wrong!", "success", 3000);
          }
      });
    }

    addonFormValidation() {
      const invalidFields = [];

      // Loop through all main addon fields
      this.builder.tree.forEach((field, fieldIndex) => {
        if (!field.label || !field.label.trim()) {
          invalidFields.push({
            type: "addon",
            index: fieldIndex,
            message: `Addon field #${fieldIndex + 1} is missing a label`
          });
        }

        // Check rules and children inside this field
        if (field.rules && field.rules.length > 0) {
          field.rules.forEach((rule, ruleIndex) => {
            if (rule.children && rule.children.length > 0) {
              rule.children.forEach((child, childIndex) => {
                if (!child.label || !child.label.trim()) {
                  invalidFields.push({
                    type: "child",
                    index: childIndex,
                    message: `Child field #${childIndex + 1} under addon "${field.label || '(no label)'}" is missing a label`
                  });
                }
              });
            }
          });
        }
      });

      if (invalidFields.length > 0) {
        // Build an error message
        let errorMsg = "Please fill in addon labels for the following fields:\n\n";
        invalidFields.forEach(err => {
          errorMsg += `• ${err.message}\n`;
        });

        // Show a warning alert
        swal({
          title: "Missing Field Labels",
          text: errorMsg,
          icon: "warning"
        });

        // Stop form submission
        return false;
      }

      return true;
    }

    deleteForm() {
      const deleteOnConfirm = (deleteIt = false) => {
        if (!deleteIt) { return false; }
        // this.renderSaveReminder(false);
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const data = { _token: csrf, _method: "delete" };
        showLoading();
        $.ajax({
          type: "POST",
          url: `${this.delete_url}/${this.form.id}`,
          data: data,
          headers: { 'X-CSRF-TOKEN': csrf },
          success: this.deleteFormOk.bind(this),
          dataType: 'json'
        }).fail((err) => this.onError(err.responseJSON.message));
      }; //end deleteOnConfirm

      swal({
        title: "Are you sure you want to delete?",
        text: "You will not be able to recover this form",
        icon: "warning",
        buttons: ['Cancel', 'Delete'],
        dangerMode: true,
      }).then((del) => deleteOnConfirm(del))
    }

    /** Display any Errors */
    onError(err) {
      if (typeof (err) === 'object') {
        if (err.hasOwnProperty('errorInfo')) {
          err = err.errorInfo.join(" ");
        }
      }

      $("#saveForm").prop('disabled', false);

      swal({
        title: 'Error',
        text: `${err}`,
        icon: 'error'
      });
    }

    /**
     * Delete the form 
     * @param {*} response 
     */
    deleteFormOk(response) {
      if (response.success) {
        hideLoading();
        this.form = { id: null, description: "", name: "Checkout Options", fields: [], tour_package_id: this.tour_package_id };
        this.resetForm();
        $("#deleteForm").css("display", "none");
        swal('Success', 'Add-on form deleted successfully', 'success');
      }
    }

    /**
     * this method resets the add field form values 
     */
    resetForm() {
      // reset the form 
      $("#formName").val("");
      $("#formDescription").val("");
      $("#formStatus").val("Active");
      this.form.status = "Active";
      $("#rootList").empty();
      $("#preview").empty();

      this.field = null;
      this.builder.tree = [];
    }

    collapseAllActiveDivs() {
      // Find all expanded divs and collapse them
      document.querySelectorAll('li[data-node-id]').forEach(li => {
        const content = li.querySelector('.addon-content');
        const expandBtn = li.querySelector('button[data-act="expand"]');
        const collapseBtn = li.querySelector('button[data-act="collapse"]');

        // Remove active class
        li.classList.remove('active');

        // Hide the addon content
        if (content) {
          content.style.display = 'none';
        }

        // Reset buttons visibility
        if (expandBtn) expandBtn.style.display = 'none';
        if (collapseBtn) collapseBtn.style.display = '';
      });

      // Collapse all child addon fields inside rules
      document.querySelectorAll('[data-role="children"] .mt-2').forEach(child => {
        const childContent = child.querySelector('.addon-content');
        const childExpandBtn = child.querySelector('button[data-act="expand"]');
        const childCollapseBtn = child.querySelector('button[data-act="collapse"]');

        // Hide the child content
        if (childContent) {
          childContent.style.display = 'none';
        }

        // Reset buttons for child fields
        if (childExpandBtn) childExpandBtn.style.display = 'none';
        if (childCollapseBtn) childCollapseBtn.style.display = '';
      });
    }

    /**
     * @param {*} msg // str / html message to display 
     * @param {*} typ // success, info, danger 
     * @param {*} exp // number to sections to remove messge -1 for never
     */
    renderMsg(msg = "", typ = "success", exp = 5000) {
      let alert = `
        <div class="alert alert-${typ} fade show" role="alert">
          <div class="btn btn-sm close" style="float:right; clear: none; margin-top: -10px;" onclick="this.parentElement.style.display = 'none';" >&times;</div>
          <div> ${msg} </div>
        </div>`;

      $("#addon-form-messages").html(alert);

      if (exp > -1) {
        setTimeout(() => {
            $("#addon-form-messages").html("");
        }, exp);
      }
    }

    /**
     * remind the user when they update fields they still have to save the form
     * @param {*} show 
     */
    renderSaveReminder(show = true) {
      let alert = `
        <div class="alert alert-warning" role="alert">
          You have unsaved changes, remember to save your form.
        </div>
      `;

      if (!show) { alert = ""; }

      $("#addon-form-save-reminder").html(alert);
    }

    mainAddonFormHtml() {
      return `
        <div class="container-xxl">
          <div id="addon-form-save-reminder"> </div>
            <div class="flex flex-wrap">
              <!-- ✅ Form Header -->
              <div class="card border border-[#ccc] rounded-md p-1.5 mt-3 w-full">
                <div class="flex flex-wrap items-center">
                <!-- Form Name -->
                <div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3">
                    <label class="form-custom-label">Form Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-custom-input" id="formName" placeholder="Enter form name">
                </div>

                <!-- Form Description -->
                <div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3">
                    <label class="form-custom-label">Form Description</label>
                    <input type="text" class="form-control form-custom-input" id="formDescription" placeholder="Enter description">
                </div>

                <!-- Form Status -->
                <div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3">
                    <label class="form-custom-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select form-custom-input" id="formStatus">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    </select>
                </div>
              </div>
            </div>

            <!-- ✅ Combined Single Card for Three Sections -->
            <div class="card border border-[#ccc] rounded-md p-1.5 mt-3 w-full">
              <div class="flex flex-wrap">
                <!-- Section 1: Field Types -->
                <div class="w-full md:w-1/3 lg:w-1/6 max-w-full px-3">
                  <div class="panel-title mb-3 font-normal text-gray-600 text-lg">1. Field Types</div>
                  <div id="fieldPalette" class="palette vstack">
                  <button class="palette-item btn-outline-small mb-2 !block !w-full" data-type="text"><i class="fas fa-solid fa-font me-2"></i>Text</button>
                  <button class="palette-item btn-outline-small mb-2 !block !w-full" data-type="textbox"><i class="fas fa-solid fa-file-alt me-2"></i>Textbox</button>
                  <button class="palette-item btn-outline-small mb-2 !block !w-full" data-type="number"><i class="fas fa-solid fa-hashtag me-2"></i>Number</button>
                  <button class="palette-item btn-outline-small mb-2 !block !w-full" data-type="checkbox"><i class="fas fa-regular fa-check-square me-2"></i>Checkbox</button>
                  <button class="palette-item btn-outline-small mb-2 !block !w-full" data-type="radio"><i class="fas fa-regular fa-dot-circle me-2"></i>Radio</button>
                  <button class="palette-item btn-outline-small mb-2 !block !w-full" data-type="dropdown"><i class="fas fa-solid fa-caret-down me-2"></i>Dropdown</button>
                  </div>
                  <!--<div class="text-muted mini mt-3">Click a type to add to the builder. Drag-and-drop is enabled for <strong>main fields only</strong>.</div>-->
                </div>

                <!-- Section 2: Form Builder -->
                <div class="w-full md:w-2/3 lg:w-1/2 max-w-full px-3 border-l border-r border-[#ccc]">
                  <div class="panel-title flex justify-between items-center mb-3 font-normal text-gray-600 text-lg">
                  <span>2. Form Builder</span>
                  <span class="text-muted mini">
                      <i class="fas fa-solid fa-arrows-alt me-1"></i>Drag handle to reorder fields
                  </span>
                  </div>
                  <ul id="rootList" class="sortable-list"></ul>
                </div>

                <!-- Section 3: Live Preview -->
                <div class="w-full lg:w-1/3 max-w-full px-3">
                  <div class="panel-title mb-3 font-normal text-gray-600 text-lg">3. Live Preview</div>
                  <form id="preview"></form>
                </div>
              </div>
            </div>

            <!-- ✅ Form Footer -->
            <div class="flex justify-end mt-3 w-full">
              <button class="btn btn-success btn-sm btn-primary-small mr-2" id="saveForm">
                <i class="fas fa-solid fa-save me-1"></i> <span class="save-form">Save Form</span>
              </button>
              <button class="btn btn-danger btn-sm btn-light-small" id="deleteForm" style="display: none;">
                <i class="fas fa-solid fa-trash me-1"></i> Delete Form
              </button>
            </div>
          </div>
        </div>
        <!-- Option Editor Modal -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="optionModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
              <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                <h5 class="modal-title text-lg font-semibold text-[#111827]">Edit Options</h5>
                <button type="button" id="closeOption" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
              </div>
              <div class="modal-body p-4">
                <div id="optionList" class="vstack gap-2"></div>
                <button id="addOptionBtn" class="btn btn-info-small btn-sm mt-2" type="button">
                    <i class="fas fa-solid fa-plus me-1"></i>Add Option
                </button>
              </div>
              <div class="modal-footer pb-4 px-4 text-right">
                <button id="saveOptionsBtn" type="button" class="btn btn-primary btn-primary-small">Save</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Conditional Logic Modal -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="logicModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
              <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                <h5 class="modal-title text-lg font-semibold text-[#111827]">Conditional Logic</h5>
                <button type="button" id="closeRole" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
              </div>
              <div class="modal-body p-4">
                <div class="text-muted small mb-2 text-xs">Define when this field should reveal child fields.</div>
                <div id="logicEditor"></div>
              </div>
              <div class="modal-footer pb-4 px-4 flex items-center justify-center">
                <button id="addChildUnderRuleBtn" type="button" class="btn btn-success btn-primary-small">
                    <i class="fas fa-solid fa-plus me-1"></i>Add Child Field
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    }
}

// Global Function
function applyLabelTruncation() {
  document.querySelectorAll(".addon-label, .preview-label").forEach(el => {
    const fullText = el.dataset.originalText || el.innerText.trim();
    el.dataset.originalText = fullText;

    const maxLength = 32;
    if (fullText.length > maxLength) {
      el.textContent = fullText.substring(0, maxLength) + "...";
      el.setAttribute("title", fullText);
    } else {
      el.textContent = fullText;
      el.removeAttribute("title");
    }
  });
}