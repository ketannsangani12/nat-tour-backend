@foreach ($tourGuides as $guide)
    <div class="col-6 col-sm-4 col-md-2"> {{-- NOW 6 CARDS PER ROW --}}
        <div class="card h-100"
             style="
                border-radius:12px;
                transition:0.25s ease;
                border:0;
                padding:6px;
                height:140px; /* compact */
                background: linear-gradient(135deg, #dbeafe, #bfdbfe);
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
             "
             onmouseover="this.style.transform='translateY(-4px)'; 
                           this.style.boxShadow='0 6px 18px rgba(0,0,0,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; 
                          this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'"
        >
            <div class="card-body" style="padding:12px;">

                <h5 class="card-title mb-1" style="font-size:16px; font-weight:700;">
                    {{ $guide->name }}
                </h5>

                <!-- <p class="mb-2" style="margin:0; color:#374151; font-size:14px;">
                    <strong>Capacity:</strong> <span style="font-size:14px; font-weight:600;">{{ $guide->capacity }}</span>
                </p> -->

                <div class="d-flex flex-column align-items-start mt-2">
                    <select 
                        name="guide_status_{{ $guide->id }}"
                        class="form-select form-select-sm text-white fw-semibold status-dropdown"
                        data-guide-id="{{ $guide->id }}"
                        style="
                            min-width:90px;       /* reduced from 120 */
                            max-width:90px;       /* fixed width */
                            height:30px;          /* reduced height */
                            border:none;
                            border-radius:6px;
                            font-size:14px;       /* smaller text */
                            font-weight:600;
                            padding:2px 4px;      /* reduced spacing */
                        "
                    >
                        <option value="Active"   {{ $guide->guide_status == 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Inactive" {{ $guide->guide_status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

            </div>
        </div>
    </div>
@endforeach