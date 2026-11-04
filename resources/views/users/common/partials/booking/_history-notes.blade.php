<style>
    /* ── History log ── */
    .history-log {
        padding: 8px 0;
    }

    .log-entry {
        display: grid;
        grid-template-columns: 155px 80px 1fr;
        border-top: 1px solid #f1f5f9;
        padding: 12px 20px;
        align-items: flex-start;
        transition: background .1s;
    }

    .log-entry:hover {
        background: #fafbfc;
    }

    .log-entry[data-hidden="true"] {
        display: none;
    }

    .log-time {
        font-size: 12px;
        color: #94a3b8;
        padding-top: 2px;
    }

    .log-user {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 6px;
    }

    .log-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid #e2e8f0;
        flex-shrink: 0;
    }

    .log-avatar-initials {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        color: #fff;
        border: 1.5px solid rgba(255, 255, 255, .3);
    }

    .log-user-info {
        display: flex;
        flex-direction: column;
    }

    .log-user-name {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.2;
    }

    .log-cred {
        font-size: 11px;
        font-weight: 500;
        color: #94a3b8;
    }

    .log-badge {
        font-size: 12px;
        font-weight: 700;
        padding-top: 2px;
    }

    .log-badge.payment {
        color: #1e8c42;
    }

    .log-badge.custom-charge {
        color: #7c3aed;
    }

    .log-badge.addons {
        color: #0e7490;
    }

    .log-badge.refund {
        color: #c43030;
    }

    .log-badge.cancelled {
        color: #c05018;
    }

    .log-badge.update-pax {
        color: #7040c8;
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .log-badge.update-pax .badge-sub-payment {
        color: #1e8c42;
    }

    .log-badge.update-pax .badge-sub-refund {
        color: #c43030;
    }

    .log-badge.email {
        color: #2d5ecc;
    }

    .log-badge.note {
        color: #b87010;
    }

    .log-badge.original {
        color: #7040c8;
    }

    .log-badge.booked {
        color: #0880a8;
    }

    .log-badge.system {
        color: #64748b;
    }

    .log-badge.permit {
        color: #1a6898;
    }

    .log-badge.check-in {
        color: #0d7a5c;
    }

    .log-badge.rescheduled {
        color: #0369a1;
    }

    .log-badge.waiver {
        color: #6d28d9;
    }

    .log-badge.card-save {
        color: #334155;
    }

    .log-badge.tip {
        color: #d97706;
    }

    /* ── Check-in status colors ── */
    .ci-not-checked {
        background: #ffffff;
        color: #374151;
        border: 1.5px solid #d1d5db;
    }

    .ci-1st {
        background: #d0f5e8;
        color: #0d7a5c;
        border: 1.5px solid #7dd4b0;
    }

    .ci-2nd {
        background: #dbe8f4;
        color: #1e5a90;
        border: 1.5px solid #93c0e4;
    }

    .ci-absent {
        background: #fce4e4;
        color: #a83030;
        border: 1.5px solid #f0a8a8;
    }

    .ci-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .log-content {
        font-size: 13px;
        color: #374151;
        line-height: 1.55;
    }

    .log-content strong {
        color: #0f172a;
    }

    /* Transaction card */
    .txn-card {
        border-radius: 8px;
        padding: 12px 14px;
        margin-top: 2px;
        border: 1px solid;
    }

    .txn-card.payment {
        background: #f2fbf5;
        border-color: #a8dbb8;
    }

    .txn-card.refund {
        background: #fdf2f2;
        border-color: #e8b0b0;
    }

    .txn-card.cancelled {
        background: #fdf6ef;
        border-color: #e8c090;
    }

    .txn-card.custom-charge {
        background: #f5f3ff;
        border-color: #c4b5fd;
    }

    /* Custom Charge/Add-ons/Tip cards are always positive money (the refund side of each uses
       its own separate .txn-card.refund styling) — same green as .txn-card.payment .txn-amount
       below, so every incoming-money amount reads the same color regardless of card type. */
    .txn-card.custom-charge .txn-amount {
        color: #1e8c42;
    }

    .txn-card.addons {
        background: #ecfeff;
        border-color: #67e8f9;
    }

    .txn-card.addons .txn-amount {
        color: #1e8c42;
    }

    .txn-card.tip {
        background: #fffbeb;
        border-color: #fcd34d;
    }

    .txn-card.tip .txn-amount {
        color: #1e8c42;
    }

    .addon-pills {
        margin-top: 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-width: 420px;
    }

    .addon-pill {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 7px 12px;
        background: #f0fdff;
        border: 1px solid #a5f3fc;
        border-radius: 8px;
        gap: 10px;
    }

    .addon-pill-name {
        font-size: 12px;
        font-weight: 600;
        color: #0e4556;
        display: flex;
        align-items: center;
        gap: 6px;
        flex: 1;
        min-width: 0;
    }

    .addon-qty-badge {
        font-size: 10px;
        font-weight: 700;
        background: #0e7490;
        color: #fff;
        border-radius: 10px;
        padding: 1px 7px;
        flex-shrink: 0;
        letter-spacing: .2px;
    }

    .addon-pill-amounts {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
        flex-shrink: 0;
    }

    .addon-pill-price {
        font-size: 13px;
        font-weight: 700;
        color: #0e4556;
        line-height: 1;
    }

    .addon-pill-fee {
        font-size: 10px;
        color: #0e7490;
        background: #cffafe;
        border: 1px solid #a5f3fc;
        border-radius: 4px;
        padding: 1px 5px;
        font-weight: 600;
        line-height: 1.4;
    }

    .addon-pills-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 12px;
        background: #0e7490;
        border-radius: 6px;
        margin-top: 2px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .addon-pills-footer.refund {
        background: #dc2626;
    }

    .addon-pills-section-label {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        padding: 4px 6px 2px;
    }

    .addon-pills-section-label--added {
        color: #16a34a;
    }

    .addon-pills-section-label--removed {
        color: #dc2626;
        margin-top: 4px;
    }

    .addon-pill--removed {
        background: #fff5f5;
        border-color: #fca5a5;
    }

    .addon-pill--removed .addon-pill-name {
        color: #7f1d1d;
    }

    .addon-pill-price--credit {
        color: #dc2626;
    }

    .addon-pills-footer--net {
        background: #1e40af;
    }

    .addon-net-calc {
        font-size: 10px;
        font-weight: 400;
        opacity: .8;
        margin-left: 4px;
    }

    .refund-card-breakdown {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 7px 10px;
        border-radius: 6px;
        margin-top: 6px;
        background: #f0f7ff;
        border: 1px solid #93c0e4;
    }

    .refund-card-breakdown-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
        color: #0369a1;
        margin-bottom: 2px;
    }

    .refund-card-breakdown-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .refund-card-breakdown-amount {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
    }

    .txn-amount {
        font-size: 16px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 8px;
    }

    .txn-card.payment .txn-amount {
        color: #1e8c42;
    }

    .txn-card.refund .txn-amount {
        color: #c43030;
    }

    .txn-card.cancelled .txn-amount {
        color: #c05018;
    }

    .txn-method {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #374151;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .card-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
    }


    .txn-breakdown {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        padding: 7px 10px;
        background: rgba(255, 255, 255, .7);
        border-radius: 6px;
        font-size: 12px;
        color: #64748b;
        margin-bottom: 6px;
    }

    .txn-breakdown b {
        color: #334155;
    }

    /* ── Combo payment split breakdown ── */
    .split-breakdown { border: 1px solid #e2e8f0; border-radius: 7px; overflow: hidden; margin: 8px 0 6px; }
    .split-row { display: flex; align-items: center; gap: 10px; padding: 7px 11px; font-size: 12px; color: #374151; border-bottom: 1px solid #f1f5f9; }
    .split-row:last-child { border-bottom: none; }
    .split-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .split-name { flex: 1; font-weight: 600; min-width: 0; }
    .split-sub { color: #94a3b8; font-size: 11px; white-space: nowrap; }
    .split-amount { font-weight: 700; color: #1e8c42; white-space: nowrap; }

    .txn-note-box {
        font-size: 12px;
        color: #475569;
        line-height: 1.5;
        padding: 7px 10px;
        background: rgba(255, 255, 255, .6);
        border-left: 3px solid #f0d060;
        border-radius: 0 5px 5px 0;
    }

    .txn-action-row {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #c8e6cf;
        display: flex;
        justify-content: flex-end;
    }

    .txn-change-pay-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
        line-height: 1.5;
        white-space: nowrap;
    }

    .txn-change-pay-btn:hover:not([disabled]) {
        background: #dbeafe;
        border-color: #93c5fd;
        color: #1e40af;
    }

    .txn-change-pay-btn[disabled] {
        opacity: 0.45;
        cursor: not-allowed;
        color: #6b7280;
        background: #f3f4f6;
        border-color: #d1d5db;
    }

    /* Note box */
    .note-box {
        background: #fdfbee;
        border: 1px solid #edd870;
        border-radius: 7px;
        padding: 10px 12px;
        margin-top: 2px;
        font-size: 13px;
        color: #374151;
        line-height: 1.55;
    }

    .note-box .note-by {
        font-size: 11px;
        font-weight: 700;
        color: #b87010;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .note-attachment {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
        padding: 8px 10px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 7px;
        flex-wrap: wrap;
    }

    .note-att-thumb {
        width: 44px;
        height: 44px;
        object-fit: cover;
        border-radius: 5px;
        border: 1px solid #e5e7eb;
        flex-shrink: 0;
    }

    .note-att-icon {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff1f2;
        border: 1px solid #fecdd3;
        border-radius: 5px;
        flex-shrink: 0;
    }

    .note-att-info {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .note-att-name {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        word-break: break-all;
    }

    .note-att-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 5px;
        text-decoration: none;
        border: 1px solid;
        transition: background 0.15s;
    }

    .note-att-btn--view {
        color: #1d4ed8;
        background: #eff6ff;
        border-color: #bfdbfe;
    }

    .note-att-btn--view:hover {
        background: #dbeafe;
    }

    .note-att-btn--download {
        color: #15803d;
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .note-att-btn--download:hover {
        background: #dcfce7;
    }

    /* System event cards */
    .sys-card {
        border-radius: 8px;
        padding: 12px 14px;
        margin-top: 2px;
        border: 1px solid;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .sys-card.original {
        background: #f8f2ff;
        border-color: #cca8e8;
    }

    .sys-card.booked {
        background: #eefbff;
        border-color: #80d4ec;
    }

    .sys-card-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sys-card.original .sys-card-icon {
        background: #7040c0;
    }

    .sys-card.booked .sys-card-icon {
        background: #0880a8;
    }

    .sys-card-body {
        flex: 1;
    }

    .sys-card-title {
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .sys-card.original .sys-card-title {
        color: #6030b8;
    }

    .sys-card.booked .sys-card-title {
        color: #0878a0;
    }

    .sys-card-rows {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .sys-card-row {
        font-size: 12px;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sys-pax-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .sys-card.original .sys-pax-badge {
        background: #ede0fc;
        color: #6030b8;
        border: 1px solid #c8a0e8;
    }

    .sys-card.booked .sys-pax-badge {
        background: #c8f0fc;
        color: #0878a0;
        border: 1px solid #80d4ec;
    }

    .sys-card.check-in {
        background: #f6f7f8;
        border-color: #cbd5e1;
    }

    .sys-card.check-in .sys-card-icon {
        background: #94a3b8;
    }

    .sys-card.check-in .sys-card-title {
        color: #475569;
    }

    .sys-card.redeemed {
        background: #fdf8ee;
        border-color: #f0cc80;
    }

    .sys-card.redeemed .sys-card-icon {
        background: #d4930a;
    }

    .sys-card.redeemed .sys-card-title {
        color: #92620a;
    }

    .sys-card.rescheduled {
        background: #f0f7ff;
        border-color: #93c0e4;
    }

    .sys-card.rescheduled .sys-card-icon {
        background: #0369a1;
    }

    .sys-card.rescheduled .sys-card-title {
        color: #0369a1;
    }

    .sys-card.waiver {
        background: #f5f3ff;
        border-color: #c4b5fd;
    }

    .sys-card.waiver .sys-card-icon {
        background: #6d28d9;
    }

    .sys-card.waiver .sys-card-title {
        color: #5b21b6;
    }

    .sys-card.card-save {
        background: #f8fafc;
        border-color: #94a3b8;
    }

    .sys-card.card-save .sys-card-icon {
        background: #334155;
    }

    .sys-card.card-save .sys-card-title {
        color: #1e293b;
    }

    /* Matches .log-badge.addons's teal (#0e7490) for the Pay Later "Add-ons Updated" card */
    .sys-card.addons {
        background: #ecfeff;
        border-color: #67e8f9;
    }

    .sys-card.addons .sys-card-icon {
        background: #0e7490;
    }

    .sys-card.addons .sys-card-title {
        color: #0e7490;
    }

    .card-save-number {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: .5px;
    }

    .card-save-source {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    .badge-sub-permit {
        color: #1a6898;
    }

    .permit-refund-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 4px;
    }

    .permit-refund-label {
        font-size: 11px;
        font-weight: 700;
        color: #1a6898;
        text-transform: uppercase;
        letter-spacing: .3px;
        margin-bottom: 2px;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .permit-refund-item {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        background: rgba(255, 255, 255, .7);
        border-radius: 5px;
        border: 1px solid #d0e4f0;
    }

    .permit-refund-item-name {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
    }

    .permit-refund-item-sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 1px;
    }

    .permit-refund-item-price {
        text-align: right;
    }

    .permit-refund-original {
        font-size: 11px;
        color: #64748b;
        text-decoration: line-through;
    }

    .permit-refund-new {
        font-size: 12px;
        font-weight: 700;
        color: #c43030;
    }

    .permit-refund-diff {
        font-size: 10px;
        font-weight: 600;
        color: #c43030;
    }

    .waiver-list {
        margin-top: 6px;
        border: 1px solid #c4b5fd;
        border-radius: 6px;
        overflow: hidden;
        max-width: 260px;
    }

    .waiver-list-header {
        display: grid;
        grid-template-columns: 1fr 60px;
        gap: 8px;
        padding: 5px 10px;
        background: #6d28d9;
        font-size: 10px;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .waiver-list-header span:last-child,
    .waiver-list-row span:last-child {
        text-align: center;
    }

    .waiver-list-row {
        display: grid;
        grid-template-columns: 1fr 60px;
        gap: 8px;
        padding: 5px 10px;
        font-size: 12px;
        color: #374151;
        align-items: center;
        border-top: 1px solid #ede9fe;
    }

    .waiver-list-row:nth-child(even) {
        background: #f5f3ff;
    }

    .waiver-list-row:nth-child(odd) {
        background: #fff;
    }

    .waiver-signer-name {
        font-weight: 600;
        color: #1e1b4b;
    }

    .waiver-signer-age {
        font-size: 11px;
        color: #6d28d9;
        font-weight: 700;
        text-align: center;
    }

    .reschedule-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .reschedule-arrow {
        color: #0369a1;
        font-size: 14px;
        flex-shrink: 0;
    }

    .reschedule-date {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .reschedule-date-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: #94a3b8;
    }

    .reschedule-date-value {
        font-size: 12px;
        font-weight: 600;
        color: #0f172a;
    }

    .sys-card--spaced {
        margin-bottom: 8px;
    }

    .switch-pkg-payment {
        padding: 8px 12px;
        background: #f2fbf5;
        border: 1px solid #a8dbb8;
        border-radius: 6px;
    }

    .switch-pkg-payment-amount {
        font-size: 16px;
        font-weight: 700;
        color: #1e8c42;
        line-height: 1;
        margin-bottom: 5px;
    }

    .switch-pkg-payment-method {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #374151;
        margin-bottom: 5px;
    }

    .switch-pkg-payment-breakdown {
        display: flex;
        gap: 16px;
        font-size: 11px;
        color: #64748b;
    }

    .switch-pkg-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background: rgba(112, 64, 192, .07);
        border: 1px solid #cca8e8;
        border-radius: 6px;
    }

    .switch-pkg-payment--refund {
        background: #fdf2f2;
        border-color: #e8b0b0;
    }

    .switch-pkg-payment--refund .switch-pkg-payment-amount {
        color: #c43030;
    }

    .switch-pkg-from {
        padding: 3px 10px;
        border-radius: 20px;
        background: #e8e0f8;
        color: #6030b8;
        border: 1px solid #c8a0e8;
        font-size: 12px;
        font-weight: 700;
    }

    .switch-pkg-to {
        padding: 3px 10px;
        border-radius: 20px;
        background: #7040c0;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    /* ── Split Booking ── */
    .log-badge.split {
        color: #7040c8;
    }

    .log-avatar-initials--purple {
        background: #7040c8;
    }

    .split-booking-entry {
        border: 1px solid #e0d0f8;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 2px;
    }

    .split-header {
        background: linear-gradient(135deg, #7040c8, #8c58e0);
        padding: 12px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .split-header-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: rgba(255, 255, 255, .18);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .split-header-title {
        font-size: 14px;
        font-weight: 800;
        color: #fff;
        line-height: 1.2;
    }

    .split-info-body {
        background: #fff;
        border-bottom: 1px solid #e8d8f8;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .split-info-col {
        padding: 14px 16px;
        border-right: 1px solid #f0e8fc;
        background: #fff;
    }

    .split-info-col--right {
        padding: 16px 18px;
        border-right: none;
    }

    .split-orig-box {
        border: 1.5px solid #cca8e8;
        border-radius: 10px;
        padding: 14px;
        background: #fff;
    }

    .split-section-label {
        font-size: 10px;
        font-weight: 700;
        color: #9060d8;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 10px;
    }

    .split-orig-name {
        font-size: 15px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .split-orig-meta {
        font-size: 12px;
        color: #475569;
        margin-bottom: 10px;
    }

    .split-orig-stats {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .split-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .split-stat-label {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .split-stat-value {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
    }

    .split-stat-value--id {
        color: #2563eb;
    }

    .split-stat-divider {
        width: 1px;
        align-self: stretch;
        background: #f0e8fc;
    }

    .split-summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .split-stat-tile {
        background: #f3eeff;
        border: 1px solid #e0d0f8;
        border-radius: 8px;
        padding: 10px 12px;
    }

    .split-stat-tile--green {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .split-stat-tile-value {
        font-size: 20px;
        font-weight: 800;
        color: #7040c8;
        line-height: 1;
    }

    .split-stat-tile-value--green {
        color: #16a34a;
    }

    .split-stat-tile-value--sm {
        font-size: 16px;
    }

    .split-stat-tile-label {
        font-size: 11px;
        color: #9060d8;
        font-weight: 600;
        margin-top: 3px;
    }

    .split-stat-tile-label--green {
        color: #15803d;
    }

    .split-tree-section {
        padding: 4px 18px 18px;
        background: #faf5ff;
    }

    .split-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .split-card-col {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
    }

    .split-card-ts-wrap {
        display: flex;
        justify-content: center;
    }

    .split-card-ts {
        background: #fff;
        border: 1px solid #cca8e8;
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 10px;
        font-weight: 700;
        color: #7040c8;
        white-space: nowrap;
    }

    .split-card {
        background: #fff;
        border: 1.5px solid #ddd0f5;
        border-radius: 10px;
        padding: 12px 14px;
    }

    .split-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .split-card-num {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .split-num-badge {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #7040c8;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .split-card-id {
        font-size: 13px;
        font-weight: 700;
        color: #7040c8;
        text-decoration: none;
    }

    .split-card-id:hover {
        text-decoration: underline;
    }

    .split-card-divider {
        border: none;
        border-top: 1px solid #f0e8fc;
        margin: 8px 0;
    }

    .split-card-guests-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #475569;
    }

    .split-card-amount {
        font-size: 18px;
        font-weight: 800;
        color: #7040c8;
        margin: 6px 0 2px;
    }

    .split-card-amount-label {
        font-size: 11px;
        color: #94a3b8;
        margin-bottom: 10px;
    }

    .split-view-btn {
        display: flex;
        width: 100%;
        justify-content: center;
        padding: 6px 14px;
        border-radius: 6px;
        background: #7040c8;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }

    .split-view-btn:hover {
        background: #5e30b0;
    }

    .split-modal-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #94a3b8;
        font-size: 13px;
        gap: 8px;
    }

    .pax-changes-box {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 7px 10px;
        border-radius: 6px;
        margin-top: 6px;
    }

    .pax-changes-box--added {
        background: rgba(30, 140, 66, .08);
        border: 1px solid #90d0a8;
    }

    .pax-changes-box--removed {
        background: rgba(185, 28, 28, .07);
        border: 1px solid #fca5a5;
    }

    .pax-changes-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
        margin-bottom: 2px;
    }

    .pax-changes-label--added {
        color: #1e8040;
    }

    .pax-changes-label--removed {
        color: #b91c1c;
    }

    .pax-changes-item {
        font-size: 12px;
        color: #374151;
    }

    .pax-changes-item-sub {
        font-size: 11px;
        color: #64748b;
    }

    /* ── Pax diff (side-by-side added / removed) ── */
    .pax-diff {
        display: flex;
        gap: 8px;
        margin-top: 6px;
    }

    .pax-diff-col {
        flex: 1;
        border-radius: 6px;
        padding: 8px 10px;
        min-width: 0;
    }

    .pax-diff-col--added {
        background: rgba(30, 140, 66, .08);
        border: 1px solid #90d0a8;
    }

    .pax-diff-col--removed {
        background: rgba(185, 28, 28, .07);
        border: 1px solid #fca5a5;
    }

    .pax-diff-header {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 6px;
    }

    .pax-diff-icon {
        font-size: 14px;
        font-weight: 800;
        line-height: 1;
    }

    .pax-diff-col--added .pax-diff-icon  { color: #1e8040; }
    .pax-diff-col--removed .pax-diff-icon { color: #b91c1c; }

    .pax-diff-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .pax-diff-col--added .pax-diff-title  { color: #1e8040; }
    .pax-diff-col--removed .pax-diff-title { color: #b91c1c; }

    .pax-diff-count {
        margin-left: auto;
        font-size: 11px;
        font-weight: 800;
        padding: 1px 7px;
        border-radius: 10px;
        color: #fff;
    }

    .pax-diff-col--added .pax-diff-count  { background: #1e8040; }
    .pax-diff-col--removed .pax-diff-count { background: #b91c1c; }

    .pax-diff-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 6px;
        font-size: 12px;
        color: #374151;
        padding: 1px 0;
    }

    .pax-diff-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pax-diff-qty {
        font-weight: 700;
        flex-shrink: 0;
    }

    .pax-diff-col--added .pax-diff-qty  { color: #1e8040; }
    .pax-diff-col--removed .pax-diff-qty { color: #b91c1c; }

    .no-charge-box {
        padding: 10px 14px;
        background: #f0fdf4;
        border: 1px solid #86efac;
        border-radius: 6px;
    }

    .no-charge-row {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 4px;
    }

    .no-charge-amount {
        font-size: 16px;
        font-weight: 700;
        color: #1e8c42;
        line-height: 1;
    }

    .no-charge-label {
        font-size: 12px;
        font-weight: 700;
        color: #166534;
    }

    .no-charge-note {
        font-size: 11px;
        color: #4a7c59;
    }

    /* Email preview card */
    .email-card {
        border: 1px solid #a8c4e8;
        border-radius: 8px;
        background: #f0f6ff;
        padding: 10px 14px;
        margin-top: 4px;
        cursor: pointer;
        transition: background .15s, border-color .15s;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .email-card:hover {
        background: #e4eef8;
        border-color: #80a8d8;
    }

    .email-card-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #2d5ecc;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .email-card-body {
        flex: 1;
        min-width: 0;
    }

    .email-card-type {
        font-size: 13px;
        font-weight: 700;
        color: #1e48a8;
        margin-bottom: 2px;
    }

    .email-card-sub {
        font-size: 12px;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .email-card-meta {
        font-size: 11px;
        color: #64748b;
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 4px;
    }

    .email-status-pill {
        padding: 1px 6px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
    }

    .email-status-pill.delivered {
        background: #d0f5dc;
        color: #1a8040;
        border: 1px solid #90d8a8;
    }

    .email-status-pill.sent {
        background: #d4e8fc;
        color: #2d5ecc;
        border: 1px solid #90b8e8;
    }

    .email-status-pill.bounced {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fca5a5;
    }

    .epm-node.bounced {
        background: #dc2626;
    }

    .epm-label.bounced {
        color: #b91c1c;
    }

    .email-card.bounced-card {
        border-color: #a8c4e8 !important;
        background: #f0f6ff !important;
    }

    .email-card.bounced-card .email-pipeline-mini {
        border-top-color: #a8c4e8;
    }

    .email-card.bounced-card .email-card-icon {
        background: #dc2626;
    }

    @keyframes emailFly {
        0% {
            left: 0;
            transform: scaleX(1);
        }

        42% {
            left: calc(100% - 14px);
            transform: scaleX(1);
        }

        50% {
            left: calc(100% - 14px);
            transform: scaleX(-1);
        }

        92% {
            left: 0;
            transform: scaleX(-1);
        }

        100% {
            left: 0;
            transform: scaleX(1);
        }
    }

    .bounce-envelope {
        position: absolute;
        top: 50%;
        margin-top: -7px;
        left: 0;
        animation: emailFly 5s cubic-bezier(.45, 0, .55, 1) infinite;
        transform-origin: center;
    }

    /* Compact pipeline inside email card */
    .email-pipeline-mini {
        display: flex;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #a8c4e8;
    }

    .epm-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .epm-node {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        animation: epmPop 0.4s cubic-bezier(.34, 1.56, .64, 1) both;
    }

    .epm-node.sent {
        background: #2d5ecc;
    }

    .epm-node.delivered {
        background: #1e8c42;
    }

    .epm-node.view {
        background: #d08020;
        cursor: pointer;
        transition: transform .15s;
        animation: epmPop 0.4s cubic-bezier(.34, 1.56, .64, 1) both, epmViewGlow 1.8s ease 2.4s 3;
    }

    .epm-node.view:hover {
        transform: scale(1.12);
    }

    .epm-node.inactive {
        background: #d1d5db;
        animation: none !important;
    }

    .epm-label {
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .epm-label.sent {
        color: #2d5ecc;
    }

    .epm-label.delivered {
        color: #1e8c42;
    }

    .epm-label.view {
        color: #c07010;
    }

    .epm-label.inactive {
        color: #9ca3af;
    }

    .epm-time {
        font-size: 9px;
        color: #94a3b8;
        white-space: nowrap;
    }

    .epm-connector {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        margin-bottom: 14px;
        transform-origin: left center;
        animation: epmDotsIn 0.3s ease both;
    }

    .epm-dot {
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.4s ease;
        color: #1e8c42;
    }

    .epm-dot.pending {
        color: #d1d5db;
    }

    .combo-pill-row {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 8px;
    }

    .combo-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        background: #2563eb;
        color: #fff;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        font-family: inherit;
        max-width: 375px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .combo-pill:hover {
        background: #1d4ed8;
        color: #fff;
    }

    /* ── Combo Card ── */
    .combo-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 2px;
        font-size: 13px;
    }
    .combo-card-header {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 14px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .combo-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .combo-card-title {
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }
    .combo-card-user {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }
    .combo-exp-rows {
        padding: 10px 14px;
        display: flex;
        flex-direction: column;
    }
    .combo-exp-row {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 6px 10px;
        border-radius: 7px;
        border: 1px solid #e2e8f0;
        font-size: 12px;
    }
    .exp-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .exp-dot.green  { background: #059669; }
    .exp-dot.purple { background: #7c3aed; }
    .exp-dot.blue   { background: #2563eb; }
    .exp-dot.orange { background: #d97706; }
    .exp-dot.pink   { background: #db2777; }
    .ticket-num {
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        flex-shrink: 0;
    }
    .ticket-num.green  { color: #059669; }
    .ticket-num.purple { color: #7c3aed; }
    .ticket-num.blue   { color: #2563eb; }
    .ticket-num.orange { color: #d97706; }
    .ticket-num.pink   { color: #db2777; }
    .combo-parent-badge {
        font-size: 9px;
        font-weight: 800;
        background: #059669;
        color: #fff;
        padding: 1px 6px;
        border-radius: 8px;
        flex-shrink: 0;
    }
    .combo-child-badge {
        font-size: 9px;
        color: #94a3b8;
        border: 1px solid #e2e8f0;
        padding: 1px 6px;
        border-radius: 8px;
        flex-shrink: 0;
    }
    .combo-row-meta {
        color: #94a3b8;
        font-size: 11px;
        flex-shrink: 0;
    }
    .combo-row-name {
        font-weight: 600;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .combo-connector {
        padding: 0 0 0 14px;
    }
    .combo-connector-line {
        width: 1px;
        height: 10px;
        background: #cbd5e1;
        margin-left: 3px;
    }
    .combo-footer {
        padding: 7px 14px;
        font-size: 11px;
        color: #64748b;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        line-height: 1.5;
    }

    @keyframes epmPop {
        0% {
            transform: scale(0);
            opacity: 0;
        }

        70% {
            transform: scale(1.15);
            opacity: 1;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    @keyframes epmDotsIn {
        0% {
            opacity: 0;
            transform: scaleX(0);
        }

        100% {
            opacity: 1;
            transform: scaleX(1);
        }
    }

    @keyframes epmViewGlow {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
        }

        50% {
            box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.35);
        }
    }

    /* Step 1 — Sent */
    .email-pipeline-mini>.epm-step:nth-child(1) .epm-node {
        animation-delay: 0.15s;
    }

    /* Connector 1 */
    .email-pipeline-mini>.epm-connector:nth-child(2) {
        animation-delay: 0.55s;
    }

    /* Step 2 — Delivered */
    .email-pipeline-mini>.epm-step:nth-child(3) .epm-node {
        animation-delay: 0.9s;
    }

    /* Connector 2 */
    .email-pipeline-mini>.epm-connector:nth-child(4) {
        animation-delay: 1.3s;
    }

    /* Step 3 — View */
    .email-pipeline-mini>.epm-step:nth-child(5) .epm-node {
        animation-delay: 1.65s;
    }

    /* ── History header ── */
    .history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px 0;
    }

    .history-title {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
    }

    .filter-wrap {
        display: flex;
        gap: 3px;
    }

    .ftab {
        padding: 5px 12px;
        border-radius: 6px;
        border: none;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        background: none;
        cursor: pointer;
        transition: all .12s;
    }

    .ftab:hover {
        background: #f1f5f9;
    }

    .ftab.active {
        background: #f59e0b;
        color: #fff;
    }

    .ftab .ct {
        font-size: 11px;
        opacity: .8;
        margin-left: 3px;
    }

    /* ── Note panel ── */
    .note-panel {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
    }

    .note-col {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px;
    }

    .note-col-label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
    }

    .note-col textarea {
        width: 100%;
        min-height: 72px;
        border: 1.5px solid #e2e8f0;
        border-radius: 7px;
        padding: 9px 11px;
        font-size: 13px;
        color: #1e293b;
        font-family: inherit;
        resize: vertical;
        outline: none;
        transition: border-color .15s;
        background: #fff;
    }

    .note-col textarea:focus {
        border-color: #f59e0b;
    }

    .note-col textarea::placeholder {
        color: #c0c8d2;
    }

    .note-col-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        align-items: center;
    }

    .btn-update-note {
        height: 32px;
        padding: 0 16px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-update-note:hover {
        background: #f9fafb;
    }

    .btn-attach {
        height: 32px;
        padding: 0 14px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .btn-attach:hover {
        background: #f9fafb;
    }

    .attach-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }

    .attach-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px 3px 7px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        color: #1d4ed8;
        cursor: pointer;
    }

    .attach-chip:hover {
        background: #dbeafe;
    }

    /* Note compose card (inline) */
    .note-compose-card {
        margin-bottom: 4px;
    }

    .note-compose-body {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .note-compose-textarea {
        width: 100%;
        min-height: 80px;
        resize: vertical;
        font-family: inherit;
        line-height: 1.55;
    }

    .note-compose-attach-zone {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border: 1.5px dashed #d1d5db;
        border-radius: 7px;
        cursor: pointer;
        color: #6b7280;
        font-size: 12px;
        background: #fafafa;
        transition: border-color 0.15s, background 0.15s, color 0.15s;
        user-select: none;
    }

    .note-compose-attach-zone:hover {
        border-color: #f59e0b;
        background: #fffbeb;
        color: #92400e;
    }

    .note-compose-attach-label {
        font-weight: 600;
        font-size: 13px;
    }

    .note-compose-attach-hint {
        font-size: 11px;
        color: #9ca3af;
        margin-left: auto;
    }

    .note-compose-file-note {
        font-size: 11px;
        color: #9ca3af;
        margin: 0;
        padding: 0;
    }

    .note-compose-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 10px 16px;
        border-top: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .note-compose-submit {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        background: #16a34a;
        border: none;
        border-radius: 7px;
        cursor: pointer;
        transition: background 0.15s;
    }

    .note-compose-submit:hover {
        background: #15803d;
    }

    /* Email modal */
    .email-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 1000;
        align-items: flex-start;
        justify-content: center;
        padding: 32px 16px;
        overflow-y: auto;
    }

    .email-modal-overlay.open {
        display: flex;
    }

    .email-modal {
        background: #fff;
        border-radius: 12px;
        width: 100%;
        max-width: 640px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        overflow: hidden;
        position: relative;
    }

    .email-modal-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        background: #1e293b;
        color: #fff;
    }

    .email-modal-title {
        font-size: 14px;
        font-weight: 700;
    }

    .email-modal-close {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: none;
        background: rgba(255, 255, 255, .15);
        color: #fff;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .email-modal-close:hover {
        background: rgba(255, 255, 255, .25);
    }

    .email-modal-meta {
        padding: 10px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12px;
        color: #64748b;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }

    .email-modal-meta b {
        color: #334155;
    }

    .email-modal-body {
        padding: 24px;
        background: #f0f2f5;
    }

    /* Discount breakdown stats */
    .text-discount {
        color: #f59e0b;
    }

    .discount-breakdown {
        margin-top: 6px;
        padding: 8px 10px;
        background: rgba(196, 48, 48, .06);
        border: 1px solid #e0a8a8;
        border-radius: 6px;
        display: inline-flex;
        gap: 20px;
        align-items: flex-start;
    }

    .discount-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .discount-stat--fixed {
        flex-shrink: 0;
    }

    .discount-divider {
        width: 1px;
        align-self: stretch;
        background: #e0a8a8;
        flex-shrink: 0;
    }

    .discount-stat-label {
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 5px;
    }

    .discount-stat-value {
        font-size: 14px;
        font-weight: 700;
        color: #c43030;
    }

    .discount-stat-value--bold {
        font-weight: 800;
    }

    .discount-stat-value--muted {
        color: #94a3b8;
    }

    /* Email card expanded layout */
    .email-card--expanded {
        flex-direction: column;
        align-items: stretch;
        cursor: default;
    }

    .email-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }

</style>

<!-- History header -->
<div class="history-header">
    <div class="history-title">History &amp; Notes</div>
    <div class="filter-wrap">
        <button class="ftab active" data-filter="all" onclick="filterBy('all',this)">All<span class="ct"></span></button>
        <button class="ftab" data-filter="transaction" onclick="filterBy('transaction',this)">Transactions<span class="ct"></span></button>
        <button class="ftab" data-filter="note" onclick="filterBy('note',this)">Notes<span class="ct"></span></button>
        <button class="ftab" data-filter="email" onclick="filterBy('email',this)">Emails<span class="ct"></span></button>
        <button class="ftab" data-filter="system" onclick="filterBy('system',this)">System<span class="ct"></span></button>
        <button class="ftab" data-filter="attachment" onclick="filterBy('attachment',this)">Attachments<span class="ct"></span></button>
    </div>
</div>

@include('users.common.partials.booking._history-log-body')



<script src="{{ asset('js/user-common/booking-history.js') }}"></script>

