@extends('layouts.default-2')

@php
        $uTypeAdmin = session('userType') == 'Admin';
        $routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
        $cartPackages = (isset($cart['tour_packages']) && !empty($cart['tour_packages'])) ? $cart['tour_packages'] : [];
        $cartCustomerDetails = (isset($cart['customer_details']) && !empty($cart['customer_details'])) ? $cart['customer_details'] : [];
        $isAddonsEnabled = hasSetting('CUSTOM_FIELDS');
        $slotVisibility = hasSetting('SLOT_VISIBILITY');
        $isPaymentLink = hasSetting('PAYMENT_LINK');
        $paymentLinkDays = hasSetting('PAYMENT_LINK_DAYS');
        $paymentLinkDaysNumber = getSettingsByKey('PAYMENT_LINK_DAYS');
        $disableCardSave = hasSetting('DISABLE_CARD_SAVE');
        $disableAffiliateCardSave = disableAffiliateCardSave();
        $cashPriceEnabled = cashPriceEnabled();
@endphp

@if(session('userType') == 'Admin')
        @php
                $prefixForCustomerSearch = 'admin.';
        @endphp
@elseif(session('userType') == 'Tour Operator')
        @php
                $prefixForCustomerSearch = 'tour-operator.';
        @endphp
@elseif(session('userType') == 'Affiliate')
        @php
                $prefixForCustomerSearch = 'affiliate.';
        @endphp
@endif

@section('main-content')
        @php
                $createBookingUrl = '';
                if ($cartView == 1) {
                        $createBookingUrl = route((prefixedRouteName($routePrefix . 'booking.create-combo-booking')));
                } else {
                        $createBookingUrl = route((prefixedRouteName($routePrefix . 'booking.store')));
                }
        @endphp
        <form id="payment-form" class="form" method="post" action="{{ $createBookingUrl }}"
                onsubmit="return validateAndSubmitForm(this)">
                {{-- following submit button is to prevent submitting form on enter key press https://stackoverflow.com/a/51507806
                --}}
                <button style="display:none" disabled></button>
                {{-- Custom Form addons --}}
                <textarea id="custom_fields" name="custom_fields" style="visibility: hidden; display:none;"></textarea>
                <textarea id="custom_charge_data" name="custom_charge_data" style="visibility: hidden; display:none;"></textarea>
                <textarea id="tip_data" name="tip_data" style="visibility: hidden; display:none;"></textarea>
                <textarea id="contact_data" name="contact_data" style="visibility: hidden; display:none;"></textarea>
                <textarea id="pax_addons_data" name="pax_addons_data" hidden></textarea>
                <textarea id="addons_data" name="addons_data" hidden></textarea>
                <textarea id="payment_link_data" name="payment_link_data" style="visibility: hidden; display:none;"></textarea>
                <input id="is_payment_link" name="is_payment_link" value="0" style="visibility: hidden; display:none;">
                <!-- The payment link will be created and sent only when the value is 1 -->
                @if($paymentacknowledgement != '' && $paymentacknowledgement == 'failed')
                        <div
                                class="alert alert-danger alert-dismissible mb-6 flex items-center justify-between py-2 px-4 text-sm font-normal text-[#721c24] rounded-md border border-[#f5c6cb] bg-[#f8d7da]">
                                <ul>
                                        <li>Your payment is failed. Please try again.</li>
                                </ul>

                                <button type="button" class="btn-close ml-2" data-bs-dismiss="alert" aria-label="Close"><svg width="16"
                                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                        stroke-linejoin="round"></path>
                                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                        stroke-linejoin="round"></path>
                                        </svg></button>
                        </div>
                @endif
                @if($paymentacknowledgement != '' && $paymentacknowledgement == 'success')
                        <div
                                class="alert alert-success alert-dismissible mb-6 flex items-center justify-between py-2 px-4 text-sm font-normal text-[#721c24] rounded-md border border-[#f5c6cb] bg-[#f8d7da]">
                                Tour has been booked and payment has been verified.
                                <button type="button" class="btn-close ml-2" data-bs-dismiss="alert" aria-label="Close"><svg width="16"
                                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                        stroke-linejoin="round"></path>
                                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                        stroke-linejoin="round"></path>
                                        </svg></button>
                        </div>
                @endif
                <section class="py-6 bg-white border-b border-[#E5E7EB] select-tour-sec{{ request('modal') ? ' pt-0' : '' }}">
                        <div class="w-full container">
                                <div class="flex flex-wrap -mx-3">
                                        <div class="mb-2 lg:mb-0 w-full md:w-1/2 lg:w-[24.33%] max-w-full text-center px-3">
                                                <div class="select-tour-wrp" id="tourPackageSelectorWrp" style="position:relative;">
                                                        {{-- Hidden select keeps existing JS working --}}
                                                        <select id="tour_package" style="display:none;" onchange="getSlotTimes(1)">
                                                                <option value="">Select Tour Package</option>
                                                                @foreach($tourPackages as $tp)
                                                                        <option value="{{ $tp->id }}"
                                                                                data-multiple-customers="{{ $tp->has_contacts }}"
                                                                                data-overbook-seats="{{ (session('userType') != 'Affiliate') ? $tp->overbook_seats : 0 }}"
                                                                                {{ $tp->id == $tourPackageId ? 'selected' : '' }}>{{ $tp->name }}</option>
                                                                @endforeach
                                                                @foreach($bundlePackages as $bp)
                                                                        <option value="{{ $bp->id }}"
                                                                                data-multiple-customers="{{ $bp->has_contacts }}"
                                                                                data-overbook-seats="{{ (session('userType') != 'Affiliate') ? $bp->overbook_seats : 0 }}"
                                                                                {{ $bp->id == $tourPackageId ? 'selected' : '' }}>{{ $bp->name }}</option>
                                                                @endforeach
                                                        </select>

                                                        {{-- Custom trigger button --}}
                                                        <button type="button" id="tourPackageTrigger"
                                                                onclick="tpToggleDropdown(event)"
                                                                class="flex items-center justify-between w-full border border-black rounded-lg pl-[7px] pr-4 h-[48px] max-[1350px]:h-[40px] bg-[#FAFAFA] text-[14px] font-medium text-black cursor-pointer outline-none appearance-none"
                                                                style="line-height:1.25rem;">
                                                                <span id="tourPackageLabel" style="flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                                                                        @if($tourPackageId){{ $tourPackages->firstWhere('id', $tourPackageId)?->name ?? 'Select Tour Package' }}@else Select Tour Package @endif
                                                                </span>
                                                                <svg width="12" height="8" viewBox="0 0 12 8" fill="none" style="flex-shrink:0;margin-left:6px;"><path d="M1 1L6 6L11 1" stroke="#111827" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                        </button>

                                                        {{-- Dropdown panel --}}
                                                        <div id="tourPackageDropdown" style="display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:9999;background:#fff;border:1px solid #E5E7EB;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);width:420px;max-height:420px;overflow:hidden;display:none;flex-direction:column;">
                                                                {{-- Tabs --}}
                                                                <div style="display:flex;align-items:center;border-bottom:1px solid #f1f5f9;padding:0 8px;background:#fafbfc;flex-shrink:0;" id="tpTabBar">
                                                                        <button type="button" onclick="tpSwitchTab('single')" id="tp-tab-single"
                                                                                style="display:flex;align-items:center;gap:5px;padding:11px 13px;font-size:12.5px;font-weight:700;color:#1e3a5c;border:none;background:none;cursor:pointer;border-bottom:2.5px solid #f5891f;margin-bottom:-1px;white-space:nowrap;">
                                                                                Single
                                                                                <span style="font-size:9.5px;font-weight:800;background:#fff7ed;color:#c2410c;border-radius:10px;padding:1px 6px;">{{ count($singlePackages) }}</span>
                                                                        </button>
                                                                        @if(count($comboPackages) > 0)
                                                                        <button type="button" onclick="tpSwitchTab('combo')" id="tp-tab-combo"
                                                                                style="display:flex;align-items:center;gap:5px;padding:11px 13px;font-size:12.5px;font-weight:700;color:#94a3b8;border:none;background:none;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-1px;white-space:nowrap;">
                                                                                Combo
                                                                                <span style="font-size:9.5px;font-weight:800;background:#f1f5f9;color:#94a3b8;border-radius:10px;padding:1px 6px;">{{ count($comboPackages) }}</span>
                                                                        </button>
                                                                        @endif
                                                                        @if(count($bundlePackages) > 0)
                                                                        <button type="button" onclick="tpSwitchTab('bundles')" id="tp-tab-bundles"
                                                                                style="display:flex;align-items:center;gap:5px;padding:11px 13px;font-size:12.5px;font-weight:700;color:#94a3b8;border:none;background:none;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-1px;white-space:nowrap;">
                                                                                Bundles
                                                                                <span style="font-size:9.5px;font-weight:800;background:#f1f5f9;color:#94a3b8;border-radius:10px;padding:1px 6px;">{{ count($bundlePackages) }}</span>
                                                                        </button>
                                                                        @endif
                                                                </div>

                                                                {{-- Single panel --}}
                                                                <div id="tp-panel-single" style="overflow-y:auto;max-height:360px;padding:4px 0;">
                                                                        @forelse($singlePackages as $pkg)
                                                                        @php
                                                                            $isYearRound = isset($pkg->season_label) && $pkg->season_label === 'Year-round';
                                                                            $badgeBg     = $isYearRound ? '#f0fdf4' : '#fff7ed';
                                                                            $badgeColor  = $isYearRound ? '#16a34a' : '#d97706';
                                                                            $badgeBorder = $isYearRound ? '#86efac' : '#fed7aa';
                                                                            $isSelected  = $pkg->id == $tourPackageId;
                                                                        @endphp
                                                                        <div style="display:flex;align-items:flex-start;gap:12px;padding:13px 16px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:background .1s;text-align:left;{{ $isSelected ? 'background:#fff7ed;' : '' }}"
                                                                             class="tp-single-item" data-pkg-id="{{ $pkg->id }}"
                                                                             onmouseover="if(!this.classList.contains('tp-si-selected'))this.style.background='#f8fafc'"
                                                                             onmouseout="if(!this.classList.contains('tp-si-selected'))this.style.background='transparent'"
                                                                             onclick="tpSelectPackage({{ $pkg->id }}, {{ json_encode($pkg->name) }})">
                                                                            <input type="radio" name="tp_single_sel" value="{{ $pkg->id }}" style="display:none;" {{ $isSelected ? 'checked' : '' }}>
                                                                            <div style="flex:1;min-width:0;text-align:left;">
                                                                                <div style="font-size:13px;font-weight:700;color:#1e293b;text-align:left;{{ !empty($pkg->season_label) ? 'margin-bottom:5px;' : '' }}">{{ $pkg->name }}</div>
                                                                                @if(!empty($pkg->season_label))
                                                                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-start;">
                                                                                    <span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:20px;white-space:nowrap;background:{{ $badgeBg }};color:{{ $badgeColor }};border:1px solid {{ $badgeBorder }};">{{ $pkg->season_label }}</span>
                                                                                </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        @empty
                                                                        <p style="text-align:center;color:#9CA3AF;font-size:13px;padding:20px;">No single packages</p>
                                                                        @endforelse
                                                                </div>

                                                                {{-- Combo panel --}}
                                                                <div id="tp-panel-combo" style="display:none;overflow-y:auto;max-height:360px;">
                                                                        {{-- PRIMARY PACKAGE: fixed required package only --}}
                                                                        <div style="padding:8px 16px 6px;font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.08em;text-transform:uppercase;text-align:left;border-bottom:1px solid #E5E7EB;">Primary Package</div>
                                                                        @php $requiredPkg = $comboPackages->first(); @endphp
                                                                        @if($requiredPkg)
                                                                        @php
                                                                            $rIsYR = isset($requiredPkg->season_label) && $requiredPkg->season_label === 'Year-round';
                                                                            $rBg   = $rIsYR ? '#dcfce7' : '#fef9c3';
                                                                            $rClr  = $rIsYR ? '#15803d' : '#854d0e';
                                                                            $rBdr  = $rIsYR ? '#86efac' : '#fde047';
                                                                        @endphp
                                                                        <div style="display:flex;align-items:flex-start;gap:12px;padding:13px 16px;background:#fff7ed;cursor:pointer;border-bottom:1px solid #f8fafc;text-align:left;" class="tp-combo-primary" data-pkg-id="{{ $requiredPkg->id }}" onclick="tpConfirmCombo()">
                                                                                <input type="radio" name="tp_combo_sel" value="{{ $requiredPkg->id }}" checked style="display:none;">
                                                                                <div style="width:17px;height:17px;border-radius:50%;border:2px solid #F97316;flex-shrink:0;margin-top:2px;display:flex;align-items:center;justify-content:center;" class="si-radio-wrap">
                                                                                    <div style="width:7px;height:7px;border-radius:50%;background:#F97316;" class="si-radio-dot"></div>
                                                                                </div>
                                                                                <span style="flex:1;text-align:left;">
                                                                                        <span style="display:block;font-size:13px;font-weight:600;color:#111827;text-align:left;">{{ $requiredPkg->name }}</span>
                                                                                        <span style="display:flex;align-items:center;gap:6px;margin-top:4px;flex-wrap:wrap;">
                                                                                                @if(!empty($requiredPkg->season_label))
                                                                                                <span style="display:inline-flex;font-size:10px;font-weight:600;background:{{ $rBg }};color:{{ $rClr }};border:1px solid {{ $rBdr }};border-radius:999px;padding:1px 8px;white-space:nowrap;">{{ $requiredPkg->season_label }}</span>
                                                                                                @endif
                                                                                                <span style="font-size:10px;font-weight:700;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;border-radius:999px;padding:1px 8px;white-space:nowrap;">REQUIRED</span>
                                                                                        </span>
                                                                                </span>
                                                                        </div>
                                                                        @endif

                                                                        {{-- ADD TO THIS TICKET: other combo packages, locked until primary is confirmed --}}
                                                                        @php $nonRequiredCombos = $comboPackages->where('is_required_combo', false); @endphp
                                                                        @if($nonRequiredCombos->count() > 0)
                                                                        <div style="padding:10px 16px 6px;font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.08em;text-transform:uppercase;border-top:1px solid #E5E7EB;border-bottom:1px solid #E5E7EB;margin-top:4px;text-align:left;">Add To This Ticket</div>
                                                                        <div id="tp-addon-section" style="pointer-events:none;opacity:0.4;transition:opacity .2s;">
                                                                        @foreach($nonRequiredCombos as $pkg)
                                                                        @php
                                                                            $aIsYR = isset($pkg->season_label) && $pkg->season_label === 'Year-round';
                                                                            $aBg   = $aIsYR ? '#f0fdf4' : '#fff7ed';
                                                                            $aClr  = $aIsYR ? '#16a34a' : '#d97706';
                                                                            $aBdr  = $aIsYR ? '#86efac' : '#fed7aa';
                                                                        @endphp
                                                                        <div style="display:flex;align-items:flex-start;gap:12px;padding:13px 16px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:background .1s;text-align:left;"
                                                                             class="tp-combo-item" data-pkg-id="{{ $pkg->id }}"
                                                                             onmouseover="if(!this.classList.contains('tp-ci-selected'))this.style.background='#f8fafc'"
                                                                             onmouseout="if(!this.classList.contains('tp-ci-selected'))this.style.background='transparent'"
                                                                             onclick="tpSelectAddon({{ $pkg->id }}, {{ json_encode($pkg->name) }})">
                                                                            <input type="radio" name="tp_combo_sel" value="{{ $pkg->id }}" style="display:none;">
                                                                            <div style="width:17px;height:17px;border-radius:50%;border:2px solid #d1d5db;flex-shrink:0;margin-top:2px;display:flex;align-items:center;justify-content:center;transition:border-color .12s;" class="si-radio-wrap">
                                                                                <div style="width:7px;height:7px;border-radius:50%;background:#F97316;display:none;" class="si-radio-dot"></div>
                                                                            </div>
                                                                            <div style="flex:1;min-width:0;text-align:left;">
                                                                                <div style="font-size:13px;font-weight:700;color:#1e293b;text-align:left;{{ !empty($pkg->season_label) ? 'margin-bottom:5px;' : '' }}">{{ $pkg->name }}</div>
                                                                                @if(!empty($pkg->season_label))
                                                                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-start;">
                                                                                    <span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:20px;white-space:nowrap;background:{{ $aBg }};color:{{ $aClr }};border:1px solid {{ $aBdr }};">{{ $pkg->season_label }}</span>
                                                                                </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        @endforeach
                                                                        </div>
                                                                        @endif

                                                                        <div style="padding:10px 12px;border-top:1px solid #F3F4F6;flex-shrink:0;">
                                                                                <button type="button" onclick="tpConfirmCombo()"
                                                                                        style="width:100%;background:#F97316;color:#fff;border:none;border-radius:8px;padding:9px;font-size:13px;font-weight:600;cursor:pointer;">
                                                                                        Select Combo Package
                                                                                </button>
                                                                        </div>
                                                                </div>

                                                                {{-- Bundles panel --}}
                                                                <div id="tp-panel-bundles" style="display:none;overflow-y:auto;max-height:360px;padding:8px;">
                                                                        @foreach($bundlePackages as $bundle)
                                                                        @php
                                                                            $bIsYR = isset($bundle->season_label) && $bundle->season_label === 'Year-round';
                                                                            $bBg   = $bIsYR ? '#dcfce7' : '#fef9c3';
                                                                            $bClr  = $bIsYR ? '#15803d' : '#854d0e';
                                                                            $bBdr  = $bIsYR ? '#86efac' : '#fde047';
                                                                        @endphp
                                                                        <label style="display:block;border:1.5px solid #E5E7EB;border-radius:10px;margin-bottom:8px;cursor:pointer;overflow:hidden;background:#fff;transition:border-color .12s,box-shadow .12s;" class="tp-bundle-card"
                                                                               onmouseover="if(!this.querySelector('input').checked){this.style.borderColor='#F97316';this.style.boxShadow='0 4px 16px rgba(245,137,31,.12)';}"
                                                                               onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#E5E7EB';this.style.boxShadow='none';}">
                                                                                {{-- Header: name + badge left, custom radio right --}}
                                                                                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:13px 14px 10px;gap:10px;">
                                                                                        <div style="flex:1;text-align:left;">
                                                                                                <div style="font-size:14px;font-weight:800;color:#0F172A;margin-bottom:5px;text-align:left;">{{ $bundle->name }}</div>
                                                                                                @if(!empty($bundle->season_label))
                                                                                                <div style="display:flex;align-items:center;gap:6px;">
                                                                                                        <span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:20px;white-space:nowrap;background:{{ $bBg }};color:{{ $bClr }};border:1px solid {{ $bBdr }};">{{ $bundle->season_label }}</span>
                                                                                                </div>
                                                                                                @endif
                                                                                        </div>
                                                                                </div>
                                                                                {{-- Divider --}}
                                                                                <div style="height:1px;background:#F1F5F9;margin:0 14px;"></div>
                                                                                {{-- Package rows --}}
                                                                                <div style="padding:10px 14px 12px;display:flex;flex-direction:column;gap:6px;">
                                                                                        @foreach($bundle->bundleChildren as $i => $child)
                                                                                        <div class="bnd-pkg-row" style="display:flex;align-items:flex-start;gap:9px;background:#F8FAFC;border-radius:7px;padding:8px 10px;">
                                                                                                <div class="bnd-pkg-num" style="width:18px;height:18px;border-radius:50%;background:#1E3A5C;color:#fff;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">{{ $i+1 }}</div>
                                                                                                <div style="font-size:12px;font-weight:700;color:#1E293B;text-align:left;">{{ $child->package_name }}</div>
                                                                                        </div>
                                                                                        @endforeach
                                                                                </div>
                                                                                {{-- Footer --}}
                                                                                @if($bundle->bundleChildren->count() > 0)
                                                                                <div class="bnd-footer" style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-top:1px solid #F1F5F9;background:#F8FAFC;font-size:10.5px;font-weight:600;color:#64748B;">
                                                                                        <svg width="12" height="12" fill="none" stroke="#F97316" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                                                                        {{ $bundle->bundleChildren->count() }} packages · One ticket
                                                                                </div>
                                                                                @endif
                                                                                <input type="radio" name="tp_bundle_sel" value="{{ $bundle->id }}" style="display:none;"
                                                                                       onchange="tpSelectPackage({{ $bundle->id }}, {{ json_encode($bundle->name) }}, this.closest('label'))">
                                                                        </label>
                                                                        @endforeach
                                                                </div>
                                                        </div>
                                                </div>
                                        </div>
                                        <div class="mb-2 lg:mb-0 w-full md:w-1/2 lg:w-[27%] max-w-full text-center px-3 date-select-wrp">
                                                <div class="date-select relative">
                                                        <input
                                                                class="relative w-full resize-none outline-none shadow-none border border-[#000] rounded-lg pr-14 pl-10 h-[48px] max-[1350px]:h-[40px] text-sm font-medium leading-[14px] text-[#000] bg-[#FAFAFA] cursor-pointer"
                                                                id="tour_slot_date" value="{{ date('l, F j Y', strtotime($date)) }}">
                                                        <input type="hidden" id="hid-tour_slot_date" value="{{ $date }}">
                                                        <button type="button"
                                                                class="prev-day datepicker-btn absolute top-[calc(50%-10px)] w-5 h-5 text-center right-[35px]"
                                                                onclick="dayIncrDecr(-1)"><svg class="mx-auto" width="8" height="12" viewBox="0 0 8 12"
                                                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M6.5 11L1.5 6L6.5 1" stroke="#9CA3AF" stroke-width="1.66667"
                                                                                stroke-linecap="round" stroke-linejoin="round" />
                                                                </svg></button>
                                                        <button type="button"
                                                                class="next-day datepicker-btn absolute top-[calc(50%-10px)] w-5 h-5 text-center right-2.5"
                                                                onclick="dayIncrDecr(1)"><svg class="mx-auto" width="8" height="12" viewBox="0 0 8 12"
                                                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M1.5 11L6.5 6L1.5 1" stroke="#9CA3AF" stroke-width="1.66667"
                                                                                stroke-linecap="round" stroke-linejoin="round" />
                                                                </svg></button>
                                                </div>
                                        </div>
                                        <div class="mb-2 lg:mb-0 w-full md:w-1/2 lg:w-[24.33%] max-w-full text-center px-3 date-select-box package-has-slots">
                                                <select name="slot_time" id="slot_time"
                                                        class="form-select select-tour-time w-full border border-black rounded-[7px] pr-[15px] pl-[7px] h-[48px] max-[1350px]:h-[40px] bg-[#FAFAFA] text-[14px] font-semibold text-black"
                                                        onchange="showSlotInfo(this)">
                                                        <option value="">Select Time Slot</option>
                                                        @foreach($slotTimes as $st)
                                                                <option value="{{ $st->id }}" {{ $st->id == $timeSlotId ? ' selected' : '' }}
                                                                        data-seats="{{ $st->seats }}" data-tourists="{{ $st->tourists }}" {{ ($st->affiliateFeature == 0) ? "disabled" : "" }}
                                                                        data-booked="{{ (session('userType') == 'Affiliate') ? $st->available_tourists : 0 }}">
                                                                        {{ formatTime($st->time_from) . ($st->time_to == null ? ' onwards' : ' - ' . formatTime($st->time_to)) }}
                                                                </option>
                                                        @endforeach
                                                </select>
                                                <input type="hidden" name="slotTime" id="selected_slotTime" value="">
                                        </div>
                                        <div class="mb-2 lg:mb-0 w-full md:w-1/2 lg:w-[24.33%] max-w-full text-center px-3">
                                                <input type="hidden" value="{{auth()->id()}}" id="userId">
                                                <select
                                                        class="form-select w-full border border-black bg-[#FAFAFA] rounded-lg px-4 h-[48px] max-[1350px]:h-[40px] leading-[18px] outline-0 appearance-none custom-select text-sm font-medium"
                                                        id="affiliatecust" name="affiliatecust">
                                                        @php
                                                                $affiliateCheck = "";
                                                                if (isset($is_affiliate) && !empty($is_affiliate) && $is_affiliate['is_affiliate'] == 1) {
                                                                        $affiliateCheck = 1;
                                                                } else {
                                                                        $affiliateCheck = 0;
                                                                }
                                                                
                                                        @endphp

                                                        @if(session('userType') != 'Affiliate')
                                                                <option value="0">No Affiliate</option>
                                                        @endif

                                                        @foreach($affiliate as $customer1)
                                                                @if($affiliateCheck == 1)
                                                                        <option value="{{ $customer1->id }}" {{ ((isset($is_affiliate) && !empty($is_affiliate) && $customer1->id == $is_affiliate['affiliate_id']) || (!empty($cartCustomerDetails) && $cartCustomerDetails['affiliatecust'] > 0 && $cartCustomerDetails['affiliatecust'] == $customer1->id)) ? 'selected' : 'disabled' }}>
                                                                                {{ $customer1->name }}
                                                                        </option>
                                                                @else
                                                                        <option value="{{ $customer1->id }}" {{ (($uType == 'Affiliate' && $customer1->id == auth()->id()) || ($cartView == 1 && !empty($cartCustomerDetails) && $cartCustomerDetails['affiliatecust'] > 0 && $cartCustomerDetails['affiliatecust'] == $customer1->id)) ? 'selected' : '' }}>
                                                                                {{ $customer1->name }}
                                                                        </option>
                                                                @endif
                                                        @endforeach
                                                </select>
                                        </div>
                                        <div class="mb-2 lg:mb-0 w-full md:w-1/2 lg:w-[24.33%] max-w-full text-center px-3"
                                                style="display: none;">
                                                <div class="search-box-wrap ms-auto flex items-center">
                                                        <input type="search" id="search"
                                                                class="search-box custom-search-box w-full m-0 border border-[#E5E7EB] bg-[#FAFAFA] rounded-lg pr-3 pl-10 py-[14px] text-sm text-[#000] shadow-none outline-none"
                                                                placeholder="Search With ID or Name ...">
                                                        <input type="button" id="btn-search"
                                                                class="search-icon text-white text-sm bg-[#ED9C45] border border-[#ED9C45] hover:border-[#4B5563] hover:bg-white font-medium px-4 py-[9px] hover:text-[#4B5563] shadow-none transition-all duration-300 ease-in-out rounded-[7px] ml-1"
                                                                onclick="$('#search').trigger('input')" value="Search">
                                                </div>
                                                <div class="cust-srch-result"></div>
                                        </div>
                                </div>
                        </div>
                </section>

                <section class="py-6 bg-[#F9FAFB] border-t border-b border-[#E5E7EB]">
                        <div class="w-full container">
                                <div class="tour-detail-wrp max-[1350px]:p-4 px-[20px] py-[30px] border rounded-xl bg-white mb-6">
                                        <div class="tour-detail-items-wrap">
                                                <div class="flex flex-wrap -mx-3">
                                                        <div class="w-full lg:w-2/3 max-w-full px-3">
                                                                <div class="tourist-detail-wrp">
                                                                        <div class="tourist-card-design-wrp">
                                                                                <div class="card tourist-card-design">
                                                                                        <div class="flex flex-wrap -mx-3">
                                                                                                <div class="w-full md:w-2/3 max-w-full px-3">
                                                                                                        <div
                                                                                                                class="card-header font-semibold text-lg max-[1350px]:text-base text-[#111827] mb-4 max-[1350px]:mb-3">
                                                                                                                Contact Detail:</div>
                                                                                                </div>
                                                                                        </div>

                                                                                        <div class="form-field-wrp contact-form-field flex flex-wrap -mx-1">
                                                                                                {{-- Booking Source --}}
                                                                                                @if(session('userType') != 'Affiliate' || hasSetting('BOOKING_SOURCE_AFFILIATE'))
                                                                                                @php
    $dbs = $defaultBookingSource ?? '';                         // drives "★ Default" badge only
    $preSelect = old('booking_source', $dbs ?: 'phone_call');  // drives which option is pre-selected
@endphp
                                                                                                <style>
                                                                                                .bsdd-wrap{position:relative;}
                                                                                                .bsdd-btn{width:100%;display:flex;align-items:center;gap:8px;padding:0 12px;height:48px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:border-color .15s;text-align:left;font-family:inherit;box-sizing:border-box;}
                                                                                                @media(max-width:1350px){.bsdd-btn{height:40px;}}
                                                                                                .bsdd-btn:hover{border-color:#1e3a5c;}
                                                                                                .bsdd-btn.open{border-color:#1e3a5c;box-shadow:0 0 0 3px rgba(30,58,92,.08);}
                                                                                                .bsdd-icon-wrap{width:20px;height:20px;border-radius:5px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#f1f5f9;}
                                                                                                .bsdd-label{flex:1;font-size:13px;color:#cbd5e1;font-weight:500;}
                                                                                                .bsdd-label.chosen{color:#1e293b;font-weight:700;}
                                                                                                .bsdd-chevron{color:#94a3b8;transition:transform .2s;flex-shrink:0;}
                                                                                                .bsdd-chevron.rotated{transform:rotate(180deg);}
                                                                                                .bsdd-menu{position:absolute;top:calc(100% + 5px);left:0;right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.10),0 2px 6px rgba(0,0,0,.06);z-index:150;display:none;overflow:hidden;}
                                                                                                .bsdd-menu.open{display:block;}
                                                                                                .bsdd-menu-inner{padding:5px;}
                                                                                                .bsdd-option{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:7px;cursor:pointer;transition:background .12s;border:none;background:none;width:100%;text-align:left;font-family:inherit;}
                                                                                                .bsdd-option:hover{background:#f1f5f9;}
                                                                                                .bsdd-option.selected{background:#eff6ff;}
                                                                                                .bsdd-opt-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
                                                                                                .bsdd-opt-text{flex:1;}
                                                                                                .bsdd-opt-title{font-size:12px;font-weight:700;color:#1e293b;}
                                                                                                .bsdd-opt-desc{font-size:10.5px;color:#94a3b8;margin-top:1px;}
                                                                                                .bsdd-opt-check{color:#1e3a5c;display:none;flex-shrink:0;}
                                                                                                .bsdd-option.selected .bsdd-opt-check{display:block;}
                                                                                                .bsdd-opt-star{display:none;align-items:center;gap:3px;font-size:10px;font-weight:700;color:#f59e0b;background:#fef9c3;border:1px solid #fde68a;border-radius:4px;padding:1px 6px;white-space:nowrap;flex-shrink:0;}
                                                                                                .bsdd-opt-star.visible{display:inline-flex;}
                                                                                                .bsdd-set-default-row{display:none;align-items:center;gap:5px;margin-top:6px;}
                                                                                                .bsdd-set-default-row.visible{display:flex;}
                                                                                                .bsdd-set-default-btn{display:flex;align-items:center;gap:4px;background:none;border:none;padding:0;font-family:inherit;font-size:11px;font-weight:600;color:#94a3b8;cursor:pointer;transition:color .12s;}
                                                                                                .bsdd-set-default-btn:hover{color:#f59e0b;}
                                                                                                .bsdd-set-default-btn.is-default{color:#f59e0b;cursor:default;}
                                                                                                </style>
                                                                                                <div class="form-group px-1 mb-5" style="flex:1;min-width:0;">
                                                                                                        <label class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827]">Booking Source</label>
                                                                                                        {{-- Hidden select drives form submission --}}
                                                                                                        <select name="booking_source" id="booking_source" style="display:none;">
                                                                                                                <option value=""></option>
                                                                                                                <option value="phone_call">Phone Call</option>
                                                                                                                <option value="walk_in">Walk-In</option>
                                                                                                                <option value="email">Email</option>
                                                                                                                <option value="other">Other</option>
                                                                                                        </select>
                                                                                                        {{-- Custom dropdown --}}
                                                                                                        <div class="bsdd-wrap" id="bsddWrap">
                                                                                                                <button type="button" class="bsdd-btn" id="bsddBtn" onclick="bsddToggle(event)">
                                                                                                                        <div class="bsdd-icon-wrap" id="bsddBtnIcon">
                                                                                                                                <svg width="11" height="11" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                                                                        </div>
                                                                                                                        <span class="bsdd-label" id="bsddBtnLabel">Select source…</span>
                                                                                                                        <svg class="bsdd-chevron" id="bsddChevron" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                                                                                </button>
                                                                                                                <div class="bsdd-menu" id="bsddMenu">
                                                                                                                        <div class="bsdd-menu-inner">
                                                                                                                                <button type="button" class="bsdd-option{{ $preSelect === 'phone_call' ? ' selected' : '' }}" data-value="phone_call" data-label="Phone Call" data-bg="#dbeafe" onclick="bsddSelect(this)">
                                                                                                                                        <div class="bsdd-opt-icon" style="background:#dbeafe;"><svg width="13" height="13" fill="none" stroke="#1e40af" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div>
                                                                                                                                        <div class="bsdd-opt-text"><div class="bsdd-opt-title">Phone Call</div><div class="bsdd-opt-desc">Called in to book</div></div>
                                                                                                                                        <span class="bsdd-opt-star{{ $dbs === 'phone_call' ? ' visible' : '' }}" id="bsStar-phone_call"><svg width="9" height="9" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Default</span>
                                                                                                                                        <svg class="bsdd-opt-check" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                                                                                </button>
                                                                                                                                <button type="button" class="bsdd-option{{ $preSelect === 'walk_in' ? ' selected' : '' }}" data-value="walk_in" data-label="Walk-In" data-bg="#dcfce7" onclick="bsddSelect(this)">
                                                                                                                                        <div class="bsdd-opt-icon" style="background:#dcfce7;"><svg width="13" height="13" fill="none" stroke="#15803d" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                                                                                                                                        <div class="bsdd-opt-text"><div class="bsdd-opt-title">Walk-In</div><div class="bsdd-opt-desc">Arrived in person</div></div>
                                                                                                                                        <span class="bsdd-opt-star{{ $dbs === 'walk_in' ? ' visible' : '' }}" id="bsStar-walk_in"><svg width="9" height="9" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Default</span>
                                                                                                                                        <svg class="bsdd-opt-check" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                                                                                </button>
                                                                                                                                <button type="button" class="bsdd-option{{ $preSelect === 'email' ? ' selected' : '' }}" data-value="email" data-label="Email" data-bg="#fef9c3" onclick="bsddSelect(this)">
                                                                                                                                        <div class="bsdd-opt-icon" style="background:#fef9c3;"><svg width="13" height="13" fill="none" stroke="#854d0e" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                                                                                                                                        <div class="bsdd-opt-text"><div class="bsdd-opt-title">Email</div><div class="bsdd-opt-desc">Contacted via email</div></div>
                                                                                                                                        <span class="bsdd-opt-star{{ $dbs === 'email' ? ' visible' : '' }}" id="bsStar-email"><svg width="9" height="9" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Default</span>
                                                                                                                                        <svg class="bsdd-opt-check" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                                                                                </button>
                                                                                                                                <button type="button" class="bsdd-option{{ $preSelect === 'other' ? ' selected' : '' }}" data-value="other" data-label="Other" data-bg="#f1f5f9" onclick="bsddSelect(this)">
                                                                                                                                        <div class="bsdd-opt-icon" style="background:#f1f5f9;"><svg width="13" height="13" fill="none" stroke="#475569" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg></div>
                                                                                                                                        <div class="bsdd-opt-text"><div class="bsdd-opt-title">Other</div><div class="bsdd-opt-desc">Referral or unspecified</div></div>
                                                                                                                                        <span class="bsdd-opt-star{{ $dbs === 'other' ? ' visible' : '' }}" id="bsStar-other"><svg width="9" height="9" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Default</span>
                                                                                                                                        <svg class="bsdd-opt-check" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                                                                                </button>
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                        <div class="bsdd-set-default-row" id="bsddSetDefaultRow">
                                                                                                                <button type="button" class="bsdd-set-default-btn" id="bsddSetDefaultBtn" onclick="bsddSetDefault()">
                                                                                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                                                                                                        <span id="bsddSetDefaultText">Set as default</span>
                                                                                                                </button>
                                                                                                        </div>
                                                                                                </div>
                                                                                                @else
                                                                                                <input type="hidden" name="booking_source" value="">
                                                                                                @endif
                                                                                                <div class="form-group px-1 mb-5" style="flex:1;min-width:0;">
                                                                                                        <label for="customer_name"
                                                                                                                class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827]">Name</label>
                                                                                                        <div class="field-icon-wrp">
                                                                                                                @php
                                                                                                                        $customerName = '';
                                                                                                                        if (isset($customer)) {
                                                                                                                                $customerName = $customer->name;
                                                                                                                        } else if ($cartView == 1 && isset($cartCustomerDetails['customer_name']) && $cartCustomerDetails['customer_name'] != '') {
                                                                                                                                $customerName = $cartCustomerDetails['customer_name'];
                                                                                                                        }
                                                                                                                @endphp
                                                                                                                <input type="text" id="customer_name" name="customer_name"
                                                                                                                        class="form-control w-full h-12 max-[1350px]:h-10 rounded-lg border border-gray-300 px-4 font-light outline-none"
                                                                                                                        placeholder="Contact name" maxlength="80"
                                                                                                                        value="{{ $customerName }}"
                                                                                                                        onblur="validateCharacters('#customer_name')" required>
                                                                                                                <span class="field-icon user-icon"></span>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div class="form-group px-1 mb-5" style="flex:1;min-width:0;">
                                                                                                        <label for="customer_phone_number"
                                                                                                                class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827]">Phone</label>
                                                                                                        <div class="field-icon-wrp">
                                                                                                                @php
                                                                                                                        $customerPhoneNumber = '';
                                                                                                                        if (isset($customer)) {
                                                                                                                                $customerPhoneNumber = $customer->phone_number;
                                                                                                                        } else if ($cartView == 1 && isset($cartCustomerDetails['customer_phone_number']) && $cartCustomerDetails['customer_phone_number'] != '') {
                                                                                                                                $customerPhoneNumber = $cartCustomerDetails['customer_phone_number'];
                                                                                                                        }
                                                                                                                @endphp
                                                                                                                <input type="tel" id="customer_phone_number"
                                                                                                                        name="customer_phone_number"
                                                                                                                        class="form-control w-full h-12 max-[1350px]:h-10 rounded-lg border border-gray-300 px-4 font-light outline-none"
                                                                                                                        placeholder="Contact number" maxlength="30"
                                                                                                                        value="{{ $customerPhoneNumber }}"
                                                                                                                        data-allowed-characters="0-9 space - ( ) +"
                                                                                                                        onblur="validateCharacters('#customer_phone_number')"
                                                                                                                        data-user-display-label="Phone Number">
                                                                                                                <span class="field-icon phone-icon"></span>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div class="form-group px-1 mb-5" style="flex:1;min-width:0;">
                                                                                                        <label for="customer_email"
                                                                                                                class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827]">Email</label>
                                                                                                        <div class="field-icon-wrp">
                                                                                                                @php
                                                                                                                        $customerEmail = '';
                                                                                                                        if (isset($customer)) {
                                                                                                                                $customerEmail = $customer->email;
                                                                                                                        } else if ($cartView == 1 && isset($cartCustomerDetails['customer_email']) && $cartCustomerDetails['customer_email'] != '') {
                                                                                                                                $customerEmail = $cartCustomerDetails['customer_email'];
                                                                                                                        }
                                                                                                                @endphp
                                                                                                                <input type="email" id="customer_email" name="customer_email"
                                                                                                                        class="form-control w-full h-12 max-[1350px]:h-10 rounded-lg border border-gray-300 px-4 font-light outline-none"
                                                                                                                        placeholder="Contact email address" maxlength="50"
                                                                                                                        value="{{ $customerEmail }}" {{ session('userType') == 'Affiliate' ? 'readonly' : '' }} required>
                                                                                                                <span class="field-icon email-icon"></span>
                                                                                                        </div>
                                                                                                </div>
                                                                                        </div>
                                                                                </div>

                                                                                <div class="agent-detail-wrp mb-7">
                                                                                        @include('users.common.partials.booking.booking-note-section')
                                                                                        <div class="flex flex-wrap -mx-3">
                                                                                                @if ($hotelPickup)
                                                                                                        <div class="form-group w-full md:w-1/2 max-w-full px-3 mb-5"
                                                                                                                id="hotel-id-div" style="display: none;">
                                                                                                                <label for="hotel_id"
                                                                                                                        class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827] pt-0"><span
                                                                                                                                style="color: red;">*</span> Hotel pickup:</label>
                                                                                                                <select
                                                                                                                        class="form-select select-hotel-name w-full h-12 max-[1350px]:h-10 rounded-lg border border-gray-300 px-4 font-light outline-none"
                                                                                                                        name="hotel_id" id="hotel_id" data-width="100%">
                                                                                                                        <option value="">Please select pickup hotel</option>
                                                                                                                </select>
                                                                                                        </div>
                                                                                                @endif

                                                                                                <div class="form-group w-full md:w-1/2 max-w-full px-3 mb-5"
                                                                                                        id="reserve-seats-div" style="display: none;">
                                                                                                        <label for="is_reserved_for_affiliate"
                                                                                                                class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827]">Soft
                                                                                                                block:</label>
                                                                                                        <select name="is_reserved_for_affiliate" id="is_reserved_for_affiliate"
                                                                                                                class="form-select w-full h-12 max-[1350px]:h-10 rounded-lg border border-gray-300 px-4 font-light outline-none">
                                                                                                                <option value="0">No</option>
                                                                                                                <option value="1">Yes</option>
                                                                                                        </select>
                                                                                                </div>
                                                                                        </div>
                                                                                </div>
                                                                        </div>

                                                                        <div class="flex items-center gap-2.5 mb-6 justify-end">
                                                                                <span class="add-contacts-section hidden">
                                                                                        <button type="button"
                                                                                                class="btn-add-contacts text-white font-semibold shadow-sm rounded-lg px-4 py-2 flex items-center gap-2 bg-[#A32624] border border-[#A32624] hover:border-[#4B5563] hover:bg-white hover:!text-[#4B5563] transition-all duration-300 ease-in-out"
                                                                                                id="add_contacts"><i class="fas fa-plus-circle mr-2"></i> Add
                                                                                                Contacts</button>
                                                                                </span>
                                                                                <span class="addons-section d-none"></span>
                                                                                <span class="add-gratuity-section d-none">
										<button type="button" class="btn-gratuity hidden text-white font-semibold shadow-sm rounded-lg px-4 py-2 flex items-center gap-2 bg-[#A32624] border border-[#A32624] hover:border-[#4B5563] hover:bg-white hover:!text-[#4B5563] transition-all duration-300 ease-in-out"id="addGratuityBtn"><i class="fas fa-coins"></i> Add
																Tip</button>
										</span>
                                                                                <!-- <span id="booking-addons-form-action-btn"></span> -->
                                                                                @php
                                                                                        $isEnableCustomAddon = IsAddOnCustomChargeVisibile();
                                                                                @endphp
                                                                                @if ($customCharge == 1 && $isEnableCustomAddon['customChargeEnabled'] == 1)
                                                                                        <button
                                                                                                class="btn-add-custom-charge btn-info-regular {{ ($tourPackageId == 0) ? "hidden" : "" }}"
                                                                                                id="addCustomCharge"><i class="fas fa-credit-card mr-2"></i> Custom
                                                                                                Charge</button>
                                                                                @endif
                                                                                @if($cartView == 1)
                                                                                        <button class="btn-add-package btn-warning-regular" id="btn-add-to-cart"
                                                                                                type="button" style="display:none"><i class="fas fa-plus mr-2"></i> Add Package</button>
                                                                                @endif
                                                                        </div>

                                                                        <div class="flex flex-wrap -mx-3 default-table-design">
                                                                                <div class="w-full max-w-full px-3">
                                                                                        <h2
                                                                                                class="font-semibold text-lg max-[1350px]:text-base text-[#111827] mb-4 max-[1350px]:mb-3">
                                                                                                Please select group of people:</h2>
                                                                                        <div class="scroll-table">
                                                                                                <div class="table-responsive w-full max-w-full overflow-y-auto">
                                                                                                        <table class="table mb-1 tbl-rate-info w-full border-collapse">
                                                                                                                <thead>
                                                                                                                        <tr id="headings-rate-group">
                                                                                                                                <th
                                                                                                                                        class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap text-left">
                                                                                                                                        Age Group</th>
                                                                                                                                <th
                                                                                                                                        class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                                                                                                        Number of People</th>
                                                                                                                                <th class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap rategrpth"
                                                                                                                                        >Rate</th>
                                                                                                                                <th class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap rategrpth"
                                                                                                                                        >Permit Fee</th>
                                                                                                                                <th
                                                                                                                                        class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap partnerfeeth hidden">
                                                                                                                                        Partner Fee $</th>
                                                                                                                                <th
                                                                                                                                        class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                                                                                                        Tax</th>
                                                                                                                                <th
                                                                                                                                        class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                                                                                                        Final Cost</th>
                                                                                                                        </tr>
                                                                                                                </thead>

                                                                                                                <tbody class="hidden" id="tmpl-rate-group">
                                                                                                                        <tr>
                                                                                                                                <td
                                                                                                                                        class="bg-white border px-3 py-2 border-solid font-medium text-black text-base age">
                                                                                                                                </td>
                                                                                                                                <td class="bg-white border px-3 py-2 border-solid group">
                                                                                                                                        <input type="hidden" class="hid-rate-group"
                                                                                                                                                data-name="rate_group[]">
                                                                                                                                        <select
                                                                                                                                                style="background-image: url('{{ asset('images/arrowDown.png') }}')"
                                                                                                                                                class="h-10 w-22 w-full text-center border border-solid border-[#D5D5D5] rounded-lg outline-0 custom-select safari-select appearance-none pr-6 bg-no-repeat bg-[position:90%_center] form-select tourists"
                                                                                                                                                data-testrate="" data-testid="rate-tourist-count"
                                                                                                                                                onchange="getSelectedTouristsGroup(this)"
                                                                                                                                                data-name="tourists[]">
                                                                                                                                                <option>0</option>
                                                                                                                                        </select>
                                                                                                                                </td>
                                                                                                                                <td
                                                                                                                                        class="bg-white border px-3 py-2 border-solid text-center price rategrptd">
                                                                                                                                        <span class="tag"
                                                                                                                                                data-testid="rate-total-price">{{ moneyFormat(0.00) }}</span>
                                                                                                                                </td>
                                                                                                                                <td
                                                                                                                                        class="bg-white border px-3 py-2 border-solid group rategrptd">
                                                                                                                                        <select
                                                                                                                                                style="background-image: url('{{ asset('images/arrowDown.png') }}')"
                                                                                                                                                class="h-10 w-22 w-full text-center border border-solid border-[#D5D5D5] rounded-lg outline-0 safari-select appearance-none pr-6 bg-no-repeat bg-[position:90%_center] form-select permit-fee"
                                                                                                                                                onchange="permitFeeTable()" name="permit_fee[]">
                                                                                                                                                <option>0</option>
                                                                                                                                        </select>
                                                                                                                                        <!-- Hidden input that WILL be submitted -->
                                                                                                                                        <input type="hidden" value="0" class="permit-fee-hidden">


                                                                                                                                </td>
                                                                                                                                <td
                                                                                                                                        class="normal partner-fee hidden bg-white border px-3 py-2 border-solid text-center font-medium text-black text-base">
                                                                                                                                        $0.00</td>
                                                                                                                                <td
                                                                                                                                        class="normal adnl-charge bg-white border px-3 py-2 border-solid text-center font-medium text-black text-base">
                                                                                                                                        {{ moneyFormat(0.00) }}
                                                                                                                                </td>
                                                                                                                                <td
                                                                                                                                        class="final bg-white border px-3 py-2 border-solid text-center font-semibold text-green-600 text-base">
                                                                                                                                        {{ moneyFormat(0.00) }}</td>
                                                                                                                        </tr>
                                                                                                                </tbody>

                                                                                                                <tbody id="table-rate-groups"></tbody>

                                                                                                                <tfoot>
                                                                                                                        <tr class="w-full mt-0 bg-green-50 border border-solid">
                                                                                                                                <td
                                                                                                                                        class="cost font-medium text-[#264F73] text-xl px-7 py-6 whitespace-nowrap">
                                                                                                                                        Total Cost:</td>
                                                                                                                                <td class="total font-bold text-[#264F73] text-xl px-7 py-6 text-right"
                                                                                                                                        colspan="7">
                                                                                                                                        <span
                                                                                                                                                class="total-before-service-commission-and-discount-package">{{ currencyFormation(false, true) }}0.00</span><br>
                                                                                                                                </td>
                                                                                                                        </tr>
                                                                                                                </tfoot>
                                                                                                        </table>
                                                                                                </div>
                                                                                        </div>
                                                                                </div>
                                                                        </div>

                                                                        <div class="flex flex-wrap -mx-3 comments-from-tourist-wrp">
                                                                                <div class="w-full max-w-full px-3">
                                                                                        <div class="agent-detail-wrp">
                                                                                                <div class="form-group w-full max-w-4xl mb-6 mt-6">
                                                                                                        <label for="customer_comment"
                                                                                                                class="col-form-label block mb-2 max-[1350px]:mb-1 font-normal text-lg max-[1350px]:text-base text-[#111827]">Comments
                                                                                                                from tourist:</label>
                                                                                                        <textarea name="customer_comment" id="customer_comment"
                                                                                                                class="agent-notes w-full border border-solid border-[#D5D5D5] rounded-lg resize-none p-4 min-h-16 font-light text-base shadow-none outline-none"
                                                                                                                placeholder="Any additional comment or request from tourist"></textarea>
                                                                                                </div>
                                                                                                @if ($cartView == 1 && ($managerRequestEnable['Discount'] == 'Yes' || $managerRequestEnable['Fix Discount'] == 'Yes' || $managerRequestEnable['Percent Discount'] == 'Yes' || $managerRequestEnable['Promo Code'] == 'Yes'))
                                                                                                        <div class="form-group w-full" id="permission-granted">
                                                                                                                <div
                                                                                                                        class="form-field-wrp discount-code-wrp payment-total-count flex flex-wrap -mx-3">
                                                                                                                        <div
                                                                                                                                class="form-group flex items-center justify-between w-full md:w-1/3 max-w-full px-3 mb-5 {{ (session('userType') == 'Affiliate' && $requestPermissions['Promo Code'] == 'No') ? 'hidden' : '' }}">
                                                                                                                                <div class="flex flex-col w-full">
                                                                                                                                        <div class="flex items-center gap-2 mb-2">
                                                                                                                                                <input type="radio" class="h-5 w-5" name="discount_type"
                                                                                                                                                        @if($requestPermissions['Promo Code'] == "Yes")
                                                                                                                                                                id="discount_type_promo_code" value="Promo Code"
                                                                                                                                                        onclick="enableDiscount(this)" @else
                                                                                                                                                        onclick="managerRequest('Promo Code')" @endif>
                                                                                                                                                <label
                                                                                                                                                        class="checkbox-wrap get-email-updates font-normal text-[#111827] text-base" @if($requestPermissions['Discount'] == "Yes") for="discount_type_promo_code" @endif>Promo
                                                                                                                                                        Code:</label>
                                                                                                                                        </div>
                                                                                                                                        <div class="discount-code-input diff flex gap-2">
                                                                                                                                                <input type="text" name="discount_promo_code" disabled
                                                                                                                                                        id="discount_promo_code"
                                                                                                                                                        class="form-control w-full outline-none shadow-none border border-[#E2E8F0] rounded-md px-3 py-1 h-9 text-sm text-[#020817]"
                                                                                                                                                        placeholder="Enter Promo Code"
                                                                                                                                                        onkeyup="removeDiscount()">
                                                                                                                                                <input type="hidden" name="is_applied" id="is_applied">
                                                                                                                                                <button type="button"
                                                                                                                                                        class="btn-primary btn-color apply-promo-btn btn-primary-small"
                                                                                                                                                        onclick="applyPromoDiscount()">Apply</button>
                                                                                                                                                <button type="button"
                                                                                                                                                        class="btn-primary btn-color remove-promo-btn btn-danger-small"
                                                                                                                                                        onclick="removePromoDiscount()"
                                                                                                                                                        style="display:none;">Remove</button>
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                        </div>

                                                                                                                        <div
                                                                                                                                class="form-group flex items-center justify-between w-full md:w-1/3 max-w-full px-3 mb-5 {{ (session('userType') == 'Affiliate' && $requestPermissions['Percent Discount'] == 'No') ? 'hidden' : '' }}">
                                                                                                                                <div class="flex flex-col w-full">
                                                                                                                                        <div class="flex items-center gap-2 mb-2">
                                                                                                                                                <input type="radio" class="h-5 w-5" name="discount_type"
                                                                                                                                                        @if($requestPermissions['Percent Discount'] == "Yes")
                                                                                                                                                                id="discount_type_percentage" value="Percentage"
                                                                                                                                                        onclick="enableDiscount(this)" @else
                                                                                                                                                        onclick="managerRequest('Percent Discount')" @endif>
                                                                                                                                                <label
                                                                                                                                                        class="checkbox-wrap get-email-updates font-normal text-[#111827] text-base" @if($requestPermissions['Percent Discount'] == "Yes") for="discount_type_percentage" @endif>Percentage:</label>
                                                                                                                                        </div>
                                                                                                                                        <div class="discount-code-input">
                                                                                                                                                <input type="number" name="discount_percentage"
                                                                                                                                                        id="discount_percentage"
                                                                                                                                                        class="form-control w-full outline-none shadow-none border border-[#E2E8F0] rounded-md px-3 py-1 h-9 text-sm text-[#020817]"
                                                                                                                                                        placeholder="Enter Discount Percentage" min="0"
                                                                                                                                                        step="0.01" onkeyup="applyDiscount(this)">
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                        </div>

                                                                                                                        <div
                                                                                                                                class="form-group flex items-center justify-between w-full md:w-1/3 max-w-full px-3 mb-5 {{ (session('userType') == 'Affiliate' && $requestPermissions['Fix Discount'] == 'No') ? 'hidden' : '' }}">
                                                                                                                                <div class="flex flex-col w-full">
                                                                                                                                        <div class="flex items-center gap-2 mb-2">
                                                                                                                                                <input type="radio" class="h-5 w-5" name="discount_type"
                                                                                                                                                        @if($requestPermissions['Fix Discount'] == "Yes")
                                                                                                                                                                id="discount_type_fixed_money" value="Fixed Money"
                                                                                                                                                        onclick="enableDiscount(this)" @else
                                                                                                                                                        onclick="managerRequest('Fix Discount')" @endif>
                                                                                                                                                <label @if($requestPermissions['Fix Discount'] == "Yes")
                                                                                                                                                                for="discount_type_fixed_money" @endif
                                                                                                                                                        class="checkbox-wrap get-email-updates font-normal text-[#111827] text-base">
                                                                                                                                                        Amount:</label>
                                                                                                                                        </div>
                                                                                                                                        <div class="discount-code-input">
                                                                                                                                                <input type="number" name="discount_fixed_money"
                                                                                                                                                        id="discount_fixed_money"
                                                                                                                                                        class="form-control w-full outline-none shadow-none border border-[#E2E8F0] rounded-md px-3 py-1 h-9 text-sm text-[#020817]"
                                                                                                                                                        placeholder="Enter Discount Amount" min="0"
                                                                                                                                                        step="0.01" onkeyup="applyDiscount()">
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                @endif
                                                                                        </div>
                                                                                </div>
                                                                        </div>
                                                                </div>
                                                        </div>

                                                        <div class="w-full lg:w-1/3 max-w-full px-3">
                                                                <div class="w-full max-w-full bg-gray-50 rounded-xl border">
                                                                        @if($cartView == 1)
                                                                                <div class="tourist-package-list-wrap {{ (empty($cartPackages) ? 'hidden' : '') }}">
                                                                                        <div class="tourist-package-list"
                                                                                                id="tourist-package-list">
                                                                                                @if(!empty($cartPackages))
                                                                                                        @foreach($cartPackages as $cartPackage)
                                                                                                                <div class="tourist-package bg-white rounded-lg border border-[#E5E7EB] m-2 mb-4"
                                                                                                                        id="cart-package-{{ $cartPackage['tour_package_id'] }}">
                                                                                                                        <div class="tourist-package-title-wrap relative py-4 pl-4 pr-28">
                                                                                                                                <div class="tourist-package-title font-semibold text-gray-900 text-sm">
                                                                                                                                        {{ $cartPackage['tour_package_name'] }}
                                                                                                                                </div>

                                                                                                                                @if ($cartPackage['package_has_slots'])
                                                                                                                                        <div
                                                                                                                                                class="tourist-package-date text-xs font-normal text-[#4B5563] mt-2">
                                                                                                                                                {{ date('l, F j, Y @ g:i a', strtotime($cartPackage['date'] . ' ' . $cartPackage['time_from'])) }}
                                                                                                                                        </div>
                                                                                                                                @else
                                                                                                                                        <div
                                                                                                                                                class="tourist-package-date text-xs font-normal text-[#4B5563] mt-2">
                                                                                                                                                {{ date('l, F j, Y', strtotime($cartPackage['date'])) }}
                                                                                                                                        </div>
                                                                                                                                @endif

                                                                                                                                <div
                                                                                                                                        class="tourist-package-btn-wrap absolute top-5 right-4 flex space-x-1">
                                                                                                                                        <button
                                                                                                                                                class="btn-tourist-package btn-edit btn-edit-cart w-7 h-7 rounded-full border border-solid border-gray-400 flex items-center justify-center"
                                                                                                                                                type="button" data-id="{{ $cartPackage['tour_package_id'] }}"><i
                                                                                                                                                        class="fa fa-pencil-alt" aria-hidden="true"></i></button>
                                                                                                                                        <button
                                                                                                                                                class="btn-tourist-package btn-delete btn-delete-cart w-7 h-7 rounded-full border border-solid border-gray-400 flex items-center justify-center"
                                                                                                                                                type="button" data-id="{{ $cartPackage['tour_package_id'] }}"><i
                                                                                                                                                        class="fa fa-trash" aria-hidden="true"></i></button>
                                                                                                                                        <button
                                                                                                                                                class="w-7 h-7 rounded-full border border-solid border-gray-400 flex items-center justify-center btn-tourist-package btn-slide-down active"
                                                                                                                                                type="button"><i class="fa fa-angle-down"
                                                                                                                                                        aria-hidden="true"></i></button>
                                                                                                                                </div>
                                                                                                                        </div>
                                                                                                                        <div class="tourist-package-age-group-list mb-4 p-5 pb-0 border-t border-[#E5E7EB]">
                                                                                                                                @foreach($cartPackage['tourist_groups'] as $k => $touristGroup)
                                                                                                                                        <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                                <div
                                                                                                                                                        class="tourist-group-age-title font-medium text-base text-black">
                                                                                                                                                        {{ $touristGroup }}</div>
                                                                                                                                                <div
                                                                                                                                                        class="tourist-group-age-count font-medium text-base text-right text-black">
                                                                                                                                                        {{ ($cartPackage['tourists'][$k]) ?? '' }}</div>
                                                                                                                                        </div>
                                                                                                                                @endforeach
                                                                                                                        </div>
                                                                                                                        <div
                                                                                                                                class="tourist-package-total-cost space-y-4 border-t border-[#E5E7EB] mt-4 py-4 px-5">
                                                                                                                                <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                        <div
                                                                                                                                                class="tourist-group-age-title tourist-package-subtotal font-medium text-sm text-[#4B5563]">
                                                                                                                                                Ticket Cost</div>
                                                                                                                                        <div class="font-medium text-sm text-right text-black"
                                                                                                                                                id="package-ticketcost-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                {{ moneyFormat(sprintf('%.2f', $cartPackage['subtt'])) }}
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                                <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                        <div
                                                                                                                                                class="tourist-group-age-title tourist-package-subtotal font-medium text-sm text-[#4B5563]">
                                                                                                                                                Discount</div>
                                                                                                                                        <div class="font-medium text-sm text-right text-black"
                                                                                                                                                id="package-discount-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                -({{ intval($cartPackage['discount_percentage']) }}%)
                                                                                                                                                {{moneyFormat(sprintf('%.2f', $cartPackage['discount_amount'])) }}
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                                <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                        <div
                                                                                                                                                class="tourist-group-age-title tourist-package-subtotal font-medium text-sm text-[#4B5563]">
                                                                                                                                                Subtotal</div>
                                                                                                                                        <div class="font-medium text-sm text-right text-black"
                                                                                                                                                id="package-subtotal-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                {{ moneyFormat(sprintf('%.2f', $cartPackage['subtt'] - $cartPackage['discount_amount'])) }}
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                                @if ($isAddonsEnabled)
                                                                                                                                        <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                                <div
                                                                                                                                                        class="tourist-group-age-title tourist-package-fees font-medium text-sm text-[#4B5563]">
                                                                                                                                                        Add-ons</div>
                                                                                                                                                <div class="font-medium text-sm text-right text-black"
                                                                                                                                                        id="package-addons-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                        {{ moneyFormat(sprintf('%.2f', ($cartPackage['addon_price']))) }}
                                                                                                                                                </div>
                                                                                                                                        </div>
                                                                                                                                @endif
                                                                                                                                @if (hasSetting('CUSTOM_CHARGE'))
                                                                                                                                        <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                                <div
                                                                                                                                                        class="tourist-group-age-title tourist-package-custom-charge font-medium text-sm text-[#4B5563]">
                                                                                                                                                        Custom Charge</div>
                                                                                                                                                <div class="font-medium text-sm text-right text-black"
                                                                                                                                                        id="package-custom-charge-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                        {{ moneyFormat(sprintf('%.2f', $cartPackage['custom_charge'])) }}
                                                                                                                                                </div>
                                                                                                                                        </div>
                                                                                                                                @endif
                                                                                                                                <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                        <div
                                                                                                                                                class="tourist-group-age-title tourist-package-tip font-medium text-sm text-[#4B5563]">
                                                                                                                                                Tip</div>
                                                                                                                                        <div class="font-medium text-sm text-right text-black"
                                                                                                                                                id="package-tip-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                        {{ moneyFormat(sprintf('%.2f', $cartPackage['tip_amount'])) }}                                                                                                                                        
                                                                                                                                                </div>
                                                                                                                                </div>
                                                                                                                                <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                        <div
                                                                                                                                                class="tourist-group-age-title tourist-package-fees font-medium text-sm text-[#4B5563]">
                                                                                                                                                Booking Fees</div>
                                                                                                                                        <div class="font-medium text-sm text-right text-black"
                                                                                                                                                id="package-fees-{{$cartPackage['tour_package_id']}}">
                                                                                                                                                {{ moneyFormat(sprintf('%.2f', ($cartPackage['fee'] + $cartPackage['custom_charge_fees'] + $cartPackage['addon_fee'] + $cartPackage['tip_fees']))) }}
                                                                                                                                        </div>
                                                                                                                                        <input type="hidden" class="package-custom-fees"
                                                                                                                                                value="{{ $cartPackage['custom_charge_fees'] }}">
                                                                                                                                        <input type="hidden" class="package-addon-fees"
                                                                                                                                                value="{{ $cartPackage['addon_fee'] }}">
                                                                                                                                </div>
                                                                                                                                <div class="tourist-package-age-group mb-4 flex justify-between">
                                                                                                                                        <div
                                                                                                                                                class="tourist-group-age-title tourist-package-total font-medium text-sm text-[#4B5563]">
                                                                                                                                                <b>Total Cost</b></div>
                                                                                                                                        <div class="font-medium text-sm text-right text-black"
                                                                                                                                                id="package-totalcost-{{$cartPackage['tour_package_id']}}"
                                                                                                                                                style="font-weight: 700;">
                                                                                                                                                {{ moneyFormat(sprintf('%.2f', ($cartPackage['total'] + $cartPackage['addon_total'] + $cartPackage['custom_charge_total'] + $cartPackage['tip_total']))) }}
                                                                                                                                        </div>
                                                                                                                                </div>

                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        @endforeach
                                                                                                @endif
                                                                                        </div>
                                                                                </div>
                                                                                <div class="hidden tourist-package bg-white rounded-lg border border-[#E5E7EB] m-2 mb-4" id="tourist-package">
                                                                                        <div class="tourist-package-title-wrap relative py-4 pl-4 pr-28">
                                                                                                <div class="tourist-package-title font-semibold text-gray-900 text-sm"></div>
                                                                                                <div class="tourist-package-date text-xs font-normal text-[#4B5563] mt-2"></div>
                                                                                                <div class="tourist-package-btn-wrap absolute top-5 right-4 flex space-x-1">
                                                                                                        <button
                                                                                                                class="btn-tourist-package btn-edit btn-edit-cart w-7 h-7 rounded-full border border-solid border-gray-400 flex items-center justify-center"
                                                                                                                type="button"><i class="fa fa-pencil-alt"
                                                                                                                        aria-hidden="true"></i></button>
                                                                                                        <button
                                                                                                                class="btn-tourist-package btn-delete btn-delete-cart w-7 h-7 rounded-full border border-solid border-gray-400 flex items-center justify-center"
                                                                                                                type="button"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                                                                                        <button
                                                                                                                class="btn-tourist-package w-7 h-7 rounded-full border border-solid border-gray-400 flex items-center justify-center btn-tourist-package btn-slide-down active"
                                                                                                                type="button"><i class="fa fa-angle-down"
                                                                                                                        aria-hidden="true"></i></button>
                                                                                                </div>
                                                                                        </div>
                                                                                        <div class="tourist-package-age-group-list mb-4 p-5 pb-0 border-t border-[#E5E7EB]">

                                                                                        </div>
                                                                                        <div class="tourist-package-total-cost space-y-4 border-t border-[#E5E7EB] mt-4 py-4 px-5"></div>
                                                                                </div>
                                                                        @endif

                                                                        <div
                                                                                class="tourist-booking-sidebar bg-white rounded-lg border border-[#E5E7EB] m-2 max-[450px]:m-0 mb-4 overflow-hidden">
                                                                                @if($cartView != 1 && ($managerRequestEnable['Discount'] == 'Yes' || $managerRequestEnable['Fix Discount'] == 'Yes' || $managerRequestEnable['Percent Discount'] == 'Yes' || $managerRequestEnable['Promo Code'] == 'Yes'))
                                                                                        <div class="form-field-wrp discount-code-wrp payment-total-count p-5 border-b border-[#E5E7EB]"
                                                                                                id="permission-granted">
                                                                                                <div class="form-group mb-2 flex items-center justify-between {{ (session('userType') == 'Affiliate' && $requestPermissions['Promo Code'] == 'No') ? 'hidden' : '' }}">
                                                                                                        <label
                                                                                                                class="checkbox-wrap get-email-updates text-base font-normal text-[#020817] mr-1 flex items-center whitespace-nowrap min-w-[120px]">
                                                                                                                <input class="w-4 h-4 mr-1" type="radio" name="discount_type"
                                                                                                                        @if($requestPermissions['Promo Code'] == "Yes")
                                                                                                                                id="discount_type_promo_code" value="Promo Code"
                                                                                                                        onclick="enableDiscount(this)" @else
                                                                                                                        onclick="managerRequest('Promo Code')" @endif>
                                                                                                                <span class="checkmark"></span>
                                                                                                                Promo Code:
                                                                                                        </label>
                                                                                                        <div class="flex items-center w-full">
                                                                                                                <div class="discount-code-input diff w-full">
                                                                                                                        <input type="text" name="discount_promo_code"
                                                                                                                                onkeyup="removeDiscount()" disabled id="discount_promo_code"
                                                                                                                                class="form-control w-full outline-none shadow-none border border-[#E2E8F0] rounded-md px-3 py-1 h-9 text-xs text-[#020817]"
                                                                                                                                placeholder="Enter Promo Code">
                                                                                                                        <input type="hidden" name="is_applied" id="is_applied">
                                                                                                                </div>
                                                                                                                <div class="ml-1">
                                                                                                                        <button type="button"
                                                                                                                                class="btn-primary btn-color h-9 px-1 bg-[#264F73] rounded text-white font-semibold text-xs apply-promo-btn"
                                                                                                                                onclick="applyPromoDiscount()">Apply</button>
                                                                                                                        <button type="button"
                                                                                                                                class="btn-primary btn-color h-9 px-1 bg-[#A32624] rounded text-white font-semibold text-xs remove-promo-btn"
                                                                                                                                onclick="removePromoDiscount()"
                                                                                                                                style="display:none;">Remove</button>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div class="form-group mb-2 flex items-center justify-between {{ (session('userType') == 'Affiliate' && $requestPermissions['Percent Discount'] == 'No') ? 'hidden' : '' }}">
                                                                                                        <label
                                                                                                                class="checkbox-wrap get-email-updates text-base font-normal text-[#020817] mr-1 flex items-center whitespace-nowrap min-w-[120px]">
                                                                                                                <input class="w-4 h-4 mr-1" type="radio" name="discount_type"
                                                                                                                        @if($requestPermissions['Percent Discount'] == "Yes")
                                                                                                                                id="discount_type_percentage" value="Percentage"
                                                                                                                        onclick="enableDiscount(this)" @else
                                                                                                                        onclick="managerRequest('Percent Discount')" @endif>
                                                                                                                <span class="checkmark"></span>
                                                                                                                Percentage:                                                                                                             
                                                                                                        </label>

                                                                                                        <div class="discount-code-input w-full">
                                                                                                                <input type="number" name="discount_percentage" id="discount_percentage"
                                                                                                                        class="form-control w-full outline-none shadow-none border border-[#E2E8F0] rounded-md px-3 py-1 h-9 text-xs text-[#020817]"
                                                                                                                        placeholder="Enter Discount Percentage" min="0" step="0.01"
                                                                                                                        onkeyup="applyDiscount(this)">
                                                                                                                <span class="field-icon per-icon"></span>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div class="form-group flex items-center justify-between {{ (session('userType') == 'Affiliate' && $requestPermissions['Fix Discount'] == 'No') ? 'hidden' : '' }}">
                                                                                                        <label
                                                                                                                class="checkbox-wrap get-email-updates text-base font-normal text-[#020817] mr-1 flex items-center whitespace-nowrap min-w-[120px]">
                                                                                                                <input class="w-4 h-4 mr-1" type="radio" name="discount_type"
                                                                                                                        @if($requestPermissions['Fix Discount'] == "Yes")
                                                                                                                                id="discount_type_fixed_money" value="Fixed Money"
                                                                                                                        onclick="enableDiscount(this)" @else
                                                                                                                        onclick="managerRequest('Fix Discount')" @endif>
                                                                                                                <span class="checkmark"></span>
                                                                                                                Amount:                                                                                                         
                                                                                                        </label>
                                                                                                        <div class="discount-code-input w-full">
                                                                                                                <input type="number" name="discount_fixed_money"
                                                                                                                        id="discount_fixed_money"
                                                                                                                        class="form-control w-full outline-none shadow-none border border-[#E2E8F0] rounded-md px-3 py-1 h-9 text-xs text-[#020817]"
                                                                                                                        placeholder="Enter Discount Amount" min="0" step="0.01"
                                                                                                                        onkeyup="applyDiscount()">
                                                                                                                <span class="field-icon"></span>
                                                                                                        </div>
                                                                                                </div>
                                                                                        </div>
                                                                                @endif

                                                                                <div class="payment-total-count">
                                                                                        <div class="p-5 pb-1 space-y-4">
                                                                                                <div class="form-field-wrp final-cost-items">
                                                                                                        <div
                                                                                                                class="form-group mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                <div
                                                                                                                        class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                        Ticket Cost:</div>
                                                                                                                <div id="sub-total"
                                                                                                                        class="final-cost-item-price font-medium text-base text-right text-black total-before-service-commission-and-discount">
                                                                                                                        {{ moneyFormat($cartTicketCost) }}
                                                                                                                </div>
                                                                                                        </div>

                                                                                                        <div
                                                                                                                class="form-group mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                <div
                                                                                                                        class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                        Discount:</div>
                                                                                                                <div
                                                                                                                        class="final-cost-item-price font-medium text-base text-right text-black">
                                                                                                                        -
                                                                                                                        <span
                                                                                                                                class="discount-info">({{ $cartDiscountPercentage }}%)</span>
                                                                                                                        <span
                                                                                                                                class="discount-value">{{ moneyFormat($cartDiscount) }}</span>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                        <div
                                                                                                                class="form-group mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                <div
                                                                                                                        class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                        Subtotal:</div>
                                                                                                                <input type="hidden" name="subtt"
                                                                                                                        class="total- subtotal-addons-price-input"
                                                                                                                        value="{{ sprintf('%.2f', ($cartTicketCost - $cartDiscount)) }}">
                                                                                                                <div id="tt"
                                                                                                                        class="final-cost-item-price font-medium text-base text-right text-black total-after-discount-before-service-commission">
                                                                                                                        {{ moneyFormat($cartTicketCost - $cartDiscount) }}
                                                                                                                </div>
                                                                                                        </div>
                                                                                                        @if ($isAddonsEnabled)
                                                                                                                <div
                                                                                                                        class="form-group mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                        <div
                                                                                                                                class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                                Add-ons:</div>
                                                                                                                        <input type="hidden" name="addons" class="addons-price-input"
                                                                                                                                value="{{ sprintf('%.2f', ($cartAddonsPrice)) }}">
                                                                                                                        <div id="addons-price-display"
                                                                                                                                class="final-cost-item-price font-medium text-base text-right text-black addons-price-display">
                                                                                                                                {{ moneyFormat($cartAddonsPrice) }}
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        @endif
                                                                                                        @if (hasSetting('CUSTOM_CHARGE'))
                                                                                                                <div id="customchargecard" class="form-group col-12 ">
                                                                                                                        <div
                                                                                                                                class="mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                                <div
                                                                                                                                        class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                                        Custom Charge:
                                                                                                                                </div>
                                                                                                                                <div
                                                                                                                                        class="final-cost-item-price font-medium text-base text-right text-black">
                                                                                                                                        <span
                                                                                                                                                class="custom-charge">{{ moneyFormat($cartCustomChargeAmount) }}</span>
                                                                                                                                        <input type="hidden" name="custom_charge"
                                                                                                                                                class="custom-charge-"
                                                                                                                                                value="{{ sprintf('%.2f', ($cartCustomChargeAmount)) }}">
                                                                                                                                        <input type="hidden" name="custom_charge_fees"
                                                                                                                                                class="custom-charge-fees-"
                                                                                                                                                value="{{ sprintf('%.2f', ($cartCustomChargeFees)) }}">
                                                                                                                                </div>
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        @endif
                                                                                                        <div class="form-group col-12 ">
                                                                                                                        <div
                                                                                                                                class="mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                                <div
                                                                                                                                        class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                                        Tip:
                                                                                                                                </div>
                                                                                                                                <div
                                                                                                                                        class="final-cost-item-price font-medium text-base text-right text-black">
                                                                                                                                        <span
                                                                                                                                                id="tip-amount-display" class="final-cost-item-price tip-amount-display">{{ moneyFormat($cartTipAmount) }}</span>
                                                                                                                                       <input type="hidden" name="tip_amount"
																		class="tip-amount-input"
																		value="{{ sprintf('%.2f', ($cartTipAmount)) }}">
                                                                                                                                </div>
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        <div id="feescard" class="form-group col-12">
                                                                                                                <div
                                                                                                                        class="mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                        <div
                                                                                                                                class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                                Booking Fees:
                                                                                                                        </div>
                                                                                                                        <div
                                                                                                                                class="final-cost-item-price font-medium text-base text-right text-black">
                                                                                                                                <span
                                                                                                                                        class="service-commission">{{ moneyFormat($cartServiceCommission) }}</span>
                                                                                                                                <input type="hidden" name="fee" class="fee-"
                                                                                                                                        value="{{ sprintf('%.2f', ($cartServiceCommission)) }}">
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                        <div style="display: none;" id="feescash" class="form-group col-12">
                                                                                                                <div
                                                                                                                        class="mb-4 max-[1350px]:mb-2 flex items-center justify-between">
                                                                                                                        <div
                                                                                                                                class="final-cost-item-label font-medium text-base text-[#4B5563]">
                                                                                                                                Booking Fees:</div>
                                                                                                                        <div
                                                                                                                                class="final-cost-item-price font-medium text-base text-right text-black">
                                                                                                                                <span class="fee-set">{{ moneyFormat(0) }}</span>
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>
                                                                                        </div>
                                                                                        <div class="bg-[#264F73] px-5 py-4">
                                                                                                <div id="div1" class="form-group bg-blue col-12">
                                                                                                        <div class="flex items-center justify-between">
                                                                                                                <div class="final-cost-item-label font-bold text-white text-lg">
                                                                                                                        Total Cost:</div>
                                                                                                                <div id="total_cost" data-testid="final-cost-items-grand-total"
                                                                                                                        class="font-bold text-white text-lg ml-auto final-cost-item-price total-after-discount-and-service-commission">
                                                                                                                        {{ moneyFormat($cartTotal) }}
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>
                                                                                                <div style="display: none;" id="div2" class="form-group bg-blue col-12">
                                                                                                        <div class="flex items-center justify-between">
                                                                                                                <div class="final-cost-item-label font-bold text-white text-lg">
                                                                                                                        Total Cost:</div>
                                                                                                                <div class="font-bold text-white text-lg ml-auto cash-set">
                                                                                                                        {{ moneyFormat($cartTotal) }}</div>
                                                                                                        </div>
                                                                                                </div>
                                                                                        </div>
                                                                                </div>

                                                                                <div class="bg-white rounded-lg border border-[#E5E7EB] m-2 mb-4 overflow-hidden">
                                                                                        <div class="payment-method p-5">
                                                                                                <div
                                                                                                        class="payment-method-title font-semibold text-lg text-[#111827] mb-5  max-[1350px]:mb-3">
                                                                                                        Payment Method:</div>
                                                                                                <span id="validate" style="color: red"></span>
                                                                                                <div id="checkcard" class="form-field-wrp">
                                                                                                        @if($isPaymentLink == true)
                                                                                                                <!-- if payment options are not set in customer table display default checkout -->
                                                                                                                @can('payment-method-payment-link')
                                                                                                                        @include("/users/common/partials/booking/forms/_field_paylater_payment_link", ["cartTotal" => $cartTotal])
                                                                                                                @endcan
                                                                                                        <!-- @elseif($paymentOptions != "" && strstr($paymentOptions, 'LINK')) -->
                                                                                                                <!-- if payment options is set and inlcudes card  -->
                                                                                                                <!-- @include("/users/common/partials/booking/forms/_field_paylater_payment_link", ["cartTotal" => $cartTotal]) -->
                                                                                                        @endif
                                                                                                        @can('payment-method-pay-later')
                                                                                                                @include("/users/common/partials/booking/forms/_field_paylater", ["cartTicketCost" => $cartTicketCost, "cartDiscount" => $cartDiscount, "cartCustomChargeAmount" => $cartCustomChargeAmount, "cartAddonsPrice" => $cartAddonsPrice, "cartTipAmount" => $cartTipAmount])
                                                                                                        @endcan
                                                                                                        @can('payment-method-cash')
                                                                                                                @include("/users/common/partials/booking/forms/_field_cash", ["cartTotal" => $cartTotal, "cartCashTotal" => $cartCashTotal, "cashPriceEnabled" => $cashPriceEnabled,"cashFees" => $cashFees, "cartView" => $cartView, "cartTicketCost" => $cartTicketCost, 'cartCashTicketCost'=> $cartCashTicketCost, "cartDiscount" => $cartDiscount, 'cartDiscountCash' => $cartDiscountCash, "cartCustomChargeAmount" => $cartCustomChargeAmount, "cartAddonsPrice" => $cartAddonsPrice , "cartTipAmount" => $cartTipAmount])
                                                                                                        @endcan
                                                                                                        @can('payment-method-card')
                                                                                                                @include("/users/common/partials/booking/forms/_field_card", ["cartTotal" => $cartTotal])
                                                                                                        @endcan
                                                                                                </div>

                                                                                                @if ($cartAddonsTotal == 0 && $cartCustomChargeAmount == 0)
                                                                                                        <div class="payment-method mt-7 {{ ($cartView == 1 && !empty($cartPackages) && ((count($cartPackages) > 1) || (isset($cartPackages[0]['is_partner_package']) && $cartPackages[0]['is_partner_package'] == 1))) ? 'hidden' : '' }}"
                                                                                                                id="split-payment-div">
                                                                                                                <div class="mt-0">
                                                                                                                        <label
                                                                                                                                class="font-semibold text-[#111827] text-lg mb-4 max-[1350px]:mb-3 inline-block">Divided
                                                                                                                                by</label>
                                                                                                                        <select
                                                                                                                                class="w-full h-12 bg-white border border-[#D5D5D5] rounded px-5 custom-select outline-0 text-base font-medium"
                                                                                                                                id="dividedBy" name="dividedBy">
                                                                                                                                <option selected>Please select option</option>
                                                                                                                                <option value="2">2</option>
                                                                                                                                <option value="3">3</option>
                                                                                                                                <option value="4">4</option>
                                                                                                                                <option value="5">5</option>
                                                                                                                                <option value="6">6</option>
                                                                                                                        </select>
                                                                                                                </div>
                                                                                                                <div id="checkcard" class="mt-6">
                                                                                                                        <!-- <label class="font-semibold text-[#111827] text-lg mb-4 max-[1350px]:mb-3 inline-block">&nbsp</label> -->
                                                                                                                        <div class="form-group payment-condition-item col-12">
                                                                                                                                <label for="split-payment"
                                                                                                                                        class="checkbox-wrap checked-item mb-0 font-semibold text-base text-[#4B5563] flex items-center">
                                                                                                                                        <input type="checkbox" name="split_checkbox"
                                                                                                                                                id="split-payment" value="1"
                                                                                                                                                class="checkset w-4 h-4 mr-2">
                                                                                                                                        <span
                                                                                                                                                class="amount-text split-check bg-[#E5E7EB] py-0.5 px-1 rounded font-semibold text-base text-[#4B5563] mr-2">{{ moneyFormat(0) }}</span>
                                                                                                                                        Split <span
                                                                                                                                                class="payment-method-text ml-1 font-semibold text-base text-[#4B5563]">Payment</span>
                                                                                                                                        <span class="checkmark"></span>
                                                                                                                                </label>
                                                                                                                        </div>
                                                                                                                        <div class="col-12 mobile-full">
                                                                                                                                <div id="split_value" class="form-group col-12 mt-2" hidden>
                                                                                                                                        <div class="discount-code-input">
                                                                                                                                                <input type="number" name="split_value"
                                                                                                                                                        id="split_value_field"
                                                                                                                                                        class="form-control w-full h-12 bg-white border border-[#D5D5D5] rounded px-5 custom-select outline-0 text-base font-medium"
                                                                                                                                                        placeholder="Split amount" min="1" step="0.01">
                                                                                                                                                <span class="field-icon per-icon"></span>
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                @endif
                                                                                        </div>
                                                                                </div>

                                                                                <div class="bg-white rounded-lg border border-[#E5E7EB] m-2 mb-4 overflow-hidden">
                                                                                        <div class="p-5">
                                                                                                <p id="card-error" role="alert" class="hidden"></p>
                                                                                                <div id="pcard" class="total-payment-wrp">
                                                                                                        <div class="mb-7 max-[1350px]:mb-4 flex items-center justify-between">
                                                                                                                <div class="total-payment font-bold text-black text-xl">Total
                                                                                                                        payment</div>
                                                                                                                <div
                                                                                                                        class="total-price total-after-discount-and-service-commission font-bold text-black text-xl text-right">
                                                                                                                        {{ moneyFormat($cartTotal) }}
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div style="display: none;" id="pcash"
                                                                                                        class="total-payment-wrp">
                                                                                                        <div class="mb-7 max-[1350px]:mb-4 flex items-center justify-between">
                                                                                                                <div class="total-payment font-bold text-black text-xl">Total
                                                                                                                        payment</div>
                                                                                                                <div class="cash-set font-bold text-black text-xl text-right">
                                                                                                                        {{ moneyFormat(0) }}</div>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div style="display: none;" id="psplit"
                                                                                                        class="total-payment-wrp">
                                                                                                        <div class="mb-7 max-[1350px]:mb-4 flex items-center justify-between">
                                                                                                                <div class="total-payment font-bold text-black text-xl">Total
                                                                                                                        payment</div>
                                                                                                                <div class="split-set font-bold text-black text-xl text-right">
                                                                                                                        {{ moneyFormat(0) }}</div>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <div class="makeaayment-wrap">
                                                                                                        <div class="form-group col-12 mb-0 make-payment" id="make-payment">
                                                                                                                <input type="submit" id="btn-make-payment"
                                                                                                                        class="btn-make-payment btn-success-regular !w-full"
                                                                                                                        value="Make a Payment">
                                                                                                                <!-- <div class="payment-btn-icon"></div> -->
                                                                                                        </div>
                                                                                                        <div class="form-group col-12 mb-0" id="payment-link-section"
                                                                                                                style="display: none;">
                                                                                                                <input type="button" id="initPaymentLinkModal"
                                                                                                                        class="btn-payment-link btn-success-regular !w-full"
                                                                                                                        value="Payment Link">
                                                                                                                <!-- <div class="payment-btn-icon"></div> -->
                                                                                                        </div>
                                                                                                </div>
                                                                                        </div>
                                                                                </div>
                                                                        </div>
                                                                </div>
                                                        </div>
                                                </div>
                                        </div>
                                </div>
                        </div>
                </section>
        </form>
        <div id="booking-addons-form-view"></div>
        <div id="add-contacts-form-view"></div>

        <!-- START: Stripe Elements -->
        <div class="modal fade custom-payment-modal fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
                id="make-a-payment" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                        <div class="modal-content rounded-xl bg-white w-full overflow-hidden shadow-lg">

                                <!-- Header -->
                                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between border-b border-gray-200">
                                        <h4 class="modal-title text-lg font-semibold text-[#111827]">Secure Payment</h4>
                                        <button type="button" class="btn-close text-gray-600 hover:text-gray-900" data-bs-dismiss="modal" aria-label="Close">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 4L4 12" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        <path d="M4 4L12 12" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                        </button>
                                </div>

                                <div class="modal-body py-7 px-5">

                                        <!-- Tabs -->
                                        <div class="flex border-b border-gray-200 mb-6">
                                                <button type="button" 
                                                                class="tab-btn flex-1 py-3 text-sm font-medium transition-colors border-b-2 border-blue-600 text-blue-600"
                                                                data-tab="online-payment">
                                                        Card Online
                                                </button>
                                                <button type="button" 
                                                                class="tab-btn flex-1 py-3 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-700"
                                                                data-tab="card-reader">
                                                        Card Reader
                                                </button>
                                        </div>

                                        <!-- Online Card Tab -->
                                        <div id="online-payment" class="tab-pane">
                                                <div class="payment-box text-start">
                                                        <div class="card-detail-main">
                                                                <div class="card-detail-wrp card-form-field">
                                                                        <div id="saved-cards-container" style="display: none; margin-bottom: 20px;"></div>
                                                                        <div id="payment-element"></div>

                                                                        <div class="form-check mt-3 save-card-checkmark flex items-center">
                                                                                <input class="form-check-input w-4 h-4 mr-2" type="checkbox" id="save-card-checkbox">
                                                                                <label class="form-check-label form-custom-label !mb-0" for="save-card-checkbox">
                                                                                        Save this card for future payments
                                                                                </label>
                                                                        </div>

                                                                        <div class="form-group text-start mt-4">
                                                                                <button onclick="return handleMakePayment()" 
                                                                                                class="btn btn-primary payment-btn btn-success-regular !w-full">
                                                                                        Pay Securely
                                                                                </button>
                                                                        </div>

                                                                        <p class="secure-info mt-3 text-xs text-[#777777] text-center">
                                                                                Your payment is encrypted & secure.
                                                                        </p>
                                                                </div>
                                                        </div>
                                                </div>
                                        </div>

                                        <!-- Card Reader Tab -->
                                        @include('partials.pos.cart._cardreader_tab')

                                </div>
                        </div>
                </div>
        </div>
        <!-- END: Stripe Elements -->

        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
                id="customCharge" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                                        <h4>Custom Charge</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                                        height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                        <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                </svg></button>
                                </div>

                                <div class="modal-body py-7 px-5">
                                        <form method="post" id="customChargeform" onsubmit="return validateAndSubmitCustomChargeForm()">
                                                @csrf
                                                <div class="custom-charge-body"></div>
                                        </form>
                                </div>
                        </div>
                </div>
        </div>

        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
                id="requestBooking" tabindex="-1" role="dialog" aria-hidden="true">
                <div
                        class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                                        <h4 class="modal-title text-lg font-semibold text-[#111827]">Manager Request</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                                        height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                        <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                </svg></button>
                                </div>

                                <div class="modal-body py-7 px-5">
                                        <input type="hidden" name="cancel_booking" value="1">
                                        <div class="flex mb-4">
                                                <select class="form-control form-custom-input" name="manager_id" id="manager_id" required>
                                                        <option value="select" disabled selected>Select a manager for request</option>
                                                        @foreach($managers as $manager)
                                                                <option value="{{$manager->id}}">{{$manager->name}}</option>
                                                        @endforeach
                                                </select>
                                        </div>
                                        <span class="text-sm font-medium mb-4" id="manager-id-required"></span>
                                        <div class="mb-4">
                                                <button id="status-button"
                                                        class="btn btn-sm text-white text-[12px] font-semibold p-2 w-full rounded-[6px] hover:opacity-95 cursor-pointer transition-all duration-300 ease-in-out"
                                                        hidden></button>
                                        </div>
                                        <div class="flex flex-wrap -mx-3 mb-4">
                                                <div class="w-full px-3">
                                                        <button type="button" class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                                                id="cancelBookingRequestManual">Manual Override</button>
                                                </div>
                                        </div>
                                </div>
                        </div>
                </div>
        </div>
        <!-- Manual Override Start -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
                id="requestManualId" tabindex="-1" role="dialog" aria-hidden="true">
                <div
                        class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                                        <h4 class="modal-title text-lg font-semibold text-[#111827]">Manual Override</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                                        height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                        <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                </svg></button>
                                </div>

                                <div class="modal-body text-center py-7 px-5">
                                        <h4 class="mb-2">Manager Id</h4>
                                        <div>
                                                <input class="form-control form-custom-input" type="password" name="manager_id" id="manualManagerId"
                                                        placeholder="Enter manager id" required>
                                        </div>
                                        <span style="color: red" id="manual-manager-id"></span>
                                        <div class="mt-3 text-center"><button type="button"
                                                        class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                                        id="request-manual-id">Next</button></div>
                                </div>
                        </div>
                </div>
        </div>

        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
                id="requestManualPin" tabindex="-1" role="dialog" aria-hidden="true">
                <div
                        class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                                        <h4 class="modal-title text-lg font-semibold text-[#111827]">Manual Override</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                                        height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                        <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                </svg></button>
                                </div>

                                <div class="modal-body text-center py-7 px-5">
                                        <h4 class="mb-2">Manager Pin</h4>
                                        <div>
                                                <input class="form-control form-custom-input" type="password" name="manager_pin"
                                                        id="manualManagerPin" placeholder="Enter manager id" required>
                                        </div>
                                        <span style="color: red" id="manual-manager-pin"></span>
                                        <div class="mt-3 text-center">
                                                <button type="button" class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                                        id="request-manual-pin">Submit</button>
                                        </div>
                                </div>
                        </div>
                </div>
        </div>
        <!-- Manual Override End -->
        <!-- Payment Link Modal Start -->
        @include('users.common.partials.create-booking.payment-link', ['page' => 'create-booking'])
        <!-- Payment Link Modal End -->
        <!-- Nested Addons Modal Start -->
    @include('users.common.partials.addons.addons-modal')
    <!-- Nested Addons Modal End -->
     <!-- Tip Modal Start -->
	@include('users.common.partials.create-booking.gratuity-modal', ['page' => 'create-booking'])
	<!-- Tip Modal End -->
@endsection

@section('css-custom')
        <link href="{{ asset('css/booking.css') }}" rel="stylesheet">
@endsection

@section('css-libraries')
        @if(App::environment('local'))
                <link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
                <link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
        @else
                <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        @endif
        <link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
@endsection

@section('js-libraries')
        @if(App::environment('local'))
                <script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
                <script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
                <script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
                <script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
        @else
                <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
                <script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
        @endif

        <script src="https://js.stripe.com/v3/"></script>
        <script src="https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
        <script src="{{ asset('plugins/card-reader.js') }}"></script>
        <script src="{{ asset('js/utils/helpers.js') }}"></script>
@endsection

@section('js-custom')

        @if($uTypeAdmin)
                <x-admin-js />
        @endif

        {{-- ───── Tour Package Selector config (Blade values only) ───── --}}
        <script>
        window.tpSelectorConfig = {
            comboPrimaryId:     {!! $comboPackages->first() ? $comboPackages->first()->id : 'null' !!},
            comboPrimaryName:   {!! $comboPackages->first() ? json_encode($comboPackages->first()->name) : "''" !!},
            preSelectPackageId: {{ $tourPackageId ?? 'null' }},
            reBookIsComboAddon: {{ (isset($tourPackageId) && $comboPackages->isNotEmpty() && $comboPackages->first()->id != $tourPackageId && $comboPackages->contains('id', $tourPackageId)) ? 'true' : 'false' }}
        };
        window.bsddConfig = {
            savedDefault:  "{{ $defaultBookingSource ?? '' }}",
            setDefaultUrl: "{{ route(prefixedRouteName($routePrefix . 'booking.set-default-booking-source')) }}"
        };
        </script>
        <script src="{{ asset('js/user-common/tour-booking-package-selector.js') }}"></script>

        <script>
                var broadcastingChannel = listener.channel('bookings.{{ $uTypeAdmin ? 'admin' : auth()->user()->tour_operator_id }}');
                broadcastingChannel.listen('.client-booking-new', (notif) => {
                        var uId = "{{ auth()->id() }}";
                        var environment = "{{ config('app.env') }}";
                        var userType = "{{ session('userType')  }}";
                        var domain = "{{ url('/') }}";
                        if (notif.booking.env == environment
                                && domain == notif.booking.domain
                                && notif.booking.broadcaster_user_id != uId
                                && notif.booking.pkg_id == $("#tour_package").val()
                                && notif.booking.date == moment($("#tour_slot_date").val()).format('YYYY-MM-DD')
                                && notif.booking.seats <= notif.booking.booked_seats && userType != 'Affiliate') {
                                var overbooked = notif.booking.booked_seats - notif.booking.seats;
                                var addText = '';
                                if (overbooked > 0) {
                                        addText += ' There are ' + overbooked + ' slots overbooked in this time slot.';
                                }
                                addText += ' It is still possible for you to overbook this slot.';
                                var fromTime = moment(notif.booking.date + ' ' + notif.booking.time_from).format('h:mm a')
                                var toTime = moment(notif.booking.date + ' ' + notif.booking.time_to).format('h:mm a')
                                swal('An overbooking event', 'The slot ' + fromTime + ' - ' + toTime + ' is full.' + addText, 'warning')
                                        .then(function () {
                                                // You may perform any action you wish
                                        });
                        }
                });
                document.cookie = "customTaxOnPermission=; max-age=0";

                var countryCode = "{{ countryCode() }}";
                var tourOperatorId = "{{ Cookie::get('tourOpId') }}";
                var action = 'Add';
                var cartView = "{{ $cartView }}";
                var cashFees = "{{ $cashFees }}";
                var customCharge = "{{ $customCharge }}";
                var createPaymentIntentUrl = "{{ route(prefixedRouteName($routePrefix . 'stripe.create-payment-intent')) }}";
                var deletePaymentMethodUrl = "{{ route(prefixedRouteName($routePrefix . 'stripe.delete-payment-method')) }}";
                var getTourSlotTimesByDateUrl = "{{ route(prefixedRouteName($routePrefix . 'get-tour-slot-times-by-date')) }}";
                var getPackageRatesUrl = "{{ route(prefixedRouteName($routePrefix . 'get-tour-rates-by-slot')) }}";
                var customerSearchUrl = "{{ route($prefixForCustomerSearch . 'search-customer') }}";
                var path = '{{ url('/') }}';
                var pathname = window.location.pathname;
                if (window.location.href.indexOf('affiliate') > 0) {
                        var affiliateSearchUrl = "{{ route(('affiliate') . '.search-affiliate', ['id' => '_ID_']) }}";
                } else {
                        var affiliateSearchUrl = "{{ route($prefixForCustomerSearch . 'search-affiliate', ['id' => '_ID_']) }}";
                }

                var customerSearchUrl1 = "{{ route('affiliate.search-customer') }}";
                var checkOverbooking = "{{ route(prefixedRouteName($routePrefix . 'booking.check-overbooking')) }}";
                var addToCartUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.add-to-cart')) }}";
                var removeCartUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.remove-from-cart')) }}";
                var editCartUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.edit-cart')) }}";
                var bookingUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => '_ID_']) }}";

                var paxWiseGroupRateIds = [];
                var paxWiseGroupRates = [];
                var paxWiseGroupAdditionalCharges = [];
                @if(session('userType') == 'Affiliate')
                        paxWiseGroupRateIds = @json($paxWiseGroupRateIds);
                        paxWiseGroupRates = @json($paxWiseGroupRates);
                        paxWiseGroupAdditionalCharges = @json($paxWiseGroupAdditionalCharges);
                @endif
                                                                var taxes = [];
                var touristsGroups = @json($touristsGroups);
                var serverDT = moment('{{ date('Y-m-d H:i:s') }}');
                var serverTZ = '{{ date('P') }}';
                var serverDatenow = moment('{{$currenttime}}');
                var totalBeforeDiscountAndServiceCommission = 0, serviceCommission = 0, affiliateProcessing = 0, stripeCommissionPercentage = 0;
                var serviceCommissionPercentage = {{ trimDeci($serviceCommissionPercentage) }};
                var affiliateProcessingPercentage = {{ trimDeci($affiliateProcessingPercentage) }};
                var stripe = Stripe("{{ config('constants.stripe_keys.' . config('app.env') . '.0.public_key') }}");
                var userType = "{{ session('userType')  }}";
                var tenantId = '{{ tenant()->id }}';
                var disableAffiliateCardSave = "{{ disableAffiliateCardSave() }}";
                var disableCardSave = "{{ hasSetting('DISABLE_CARD_SAVE') }}";
                var cartPackages = @json($cartPackages);
                var cartCustomerDetails = @json($cartCustomerDetails);
                var slotTimes = @json($slotTimes ?? []);
                var rateGroups = @json($rateGroups ?? []);
                var applyOnSlots = '{{ $applyOnSlots ?? 0 }}';
                var taxOnCustomChargePermission = "{{ $requestPermissions['Tax On Custom Charge'] }}";

                setInterval(function () {
                        serverDT.add(15, 'seconds');
                }, 15000);

                var getTourOperatorAffiliates = "{{ route('tour-operator.get-tour-operator-affiliates') }}";

                @if($uTypeAdmin)
                        var getTourOperatorDataUrl = "{{ route('admin.get-tour-operator-data', ['tourOperatorId' => 0]) }}";
                        var getTourOperatorAffiliateUrl = "{{ route('admin.get-tour-operator-affiliate-data', ['tourOperatorId' => 0]) }}";
                        var getTourOperatorAffiliates = "{{ route('admin.get-tour-operator-affiliates') }}";
                @endif

                                                                var affiliate = '{{ request("customer_id") }}';
                var checkAffiliate = '{{ $affiliateCheck }}';
                var totalCartTicketCost =  {{ $cartTicketCost }};
                var totalCartDiscount =  {{ $cartDiscount }};
                var totalCartDiscountCash =  {{ $cartDiscountCash }};
                var totalCartServiceCommission =  {{ $cartServiceCommission }};
                var totalCartCashTicketCost =  {{ $cartCashTicketCost }};
                var totalCartCashServiceCommission =  {{ $cartCashServiceCommission }};
                var totalCustomChargeAmount =  {{ $cartCustomChargeAmount }};
                var totalCustomChargeFees =  {{ $cartCustomChargeFees }};
                var cartTotal =  {{ $cartTotal }};
                var cartCashTotal =  {{ $cartCashTotal }};
                var totalCartAddonsPrice =  {{ $cartAddonsPrice }};
                var totalCartAddonsFees = {{ $cartAddonsFees }};
                var totalCartAddonsTotal = {{ $cartAddonsTotal }};
                var totalCartTipAmount =  {{ $cartTipAmount }};
		var totalCartTipFees = {{ $cartTipFees }};
		var totalCartTipTotal = {{ $cartTipTotal }};
                var subDomainFromHost = '{{ getSubdomainFromHost() }}';
                var isGroupRateEnabled =  {{ $isGroupRateEnabled ?? 0 }};
                var isPartnerPackage =  {{ $isPartnerPackage ?? 0 }};
                var isAddonsEnabled = '{{ $isAddonsEnabled }}';
                var slotVisibility = '{{ $slotVisibility }}';
                var selectedPackageId = '{{ $tourPackageId }}';
                var isPaymentLink = '{{ $isPaymentLink }}';
                var paymentLinkDays = '{{ $paymentLinkDays }}';
                var paymentLinkDaysNumber = @json($paymentLinkDaysNumber);
                var manualIdCheck = "{{route(routePrefix() . '.booking.manual-id-check')}}";
                var manualPinCheckBooking = "{{route(routePrefix() . '.booking.manual-pin-check-booking')}}";
                var getPaymentLinkReasonUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.payment-link-reason')) }}";
                var expiryDateOverride = "{{ route(prefixedRouteName($routePrefix . 'booking.expiry-date-override')) }}";
                var getColorDateUrl = "{{ route(prefixedRouteName($routePrefix . 'get-color-date')) }}";
                var isCashFees = "{{ $cashFees }}";
                var cashPriceEnabled = "{{ $cashPriceEnabled }}";
                var getAddonFormUrl = "{{ route(prefixedRouteName($routePrefix.'package.get-addon-form'), ['tourPackageId' => 'PACKAGE_ID']) }}";
        </script>

        <script src="{{ asset('js/user-common/tour-package-custom-form-reader.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-package-custom-form-checkout.js') }}"></script>
        <script src="{{ asset('js/user-common/addons/components.js') }}"></script>
        <script src="{{ asset('js/user-common/addons/form-reader.js') }}"></script>

        @if($uTypeAdmin)
                <script src="{{ asset('js/admin/tour-booking-add-edit.js') }}"></script>
        @endif

        <script src="{{ asset('js/user-common/tour-booking-add-edit.js?v=56') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-group-rates.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-cart.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-custom-charge.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-gratuity.js') }}"></script>
        <script src="{{ asset('js/user-common/booking-request-new-booking.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-contacts.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-payment-link.js') }}"></script>
        <script src="https://js.stripe.com/terminal/v1/"></script>
        <script src="{{ asset('js/utils/stripe-reader-identity.js')}}"></script>
        <script src="{{ asset('js/pos/stripe-card-reader.js')}}"></script>
        <script src="{{ asset('js/user-common/backend-card-reader.js')}}"></script>
        <script src="{{ asset('js/user-common/tour-booking-add-payment-methods.js') }}"></script>
        <script src="{{ asset('js/user-common/tour-booking-priority-note.js') }}"></script>

        <script type="text/javascript">
                var routePrefixUrl = "{{ routePrefix() }}";
        </script>
@endsection