/**
 * Priority Note Manager - Enhanced Version
 * Handles priority note modal functionality for tour bookings
 * Improved error handling, validation, and user experience
 */

(function() {
    'use strict';

    // Configuration constants
    const CONFIG = {
        COLORS: {
            PRIORITY_RED: '#DC2626',
            PRIORITY_BG: '#FEF2F2',
            TEXT_GRAY: '#6B7280',
            POPUP_CHECKED_BG: '#FFF7ED',
            POPUP_CHECKED_BORDER: '#F59E0B',
            POPUP_UNCHECKED_BG: '#F9FAFB',
            POPUP_UNCHECKED_BORDER: '#E5E7EB'
        },
        MESSAGES: {
            SAVING: '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...',
            SAVE_BUTTON: '<i class="fas fa-flag mr-1"></i> Save Priority Note',
            SUCCESS: 'Priority note saved successfully.',
            ERROR_DEFAULT: 'Failed to save priority note. Please try again.',
            ERROR_PERMISSION: 'You do not have permission to perform this action.',
            ERROR_SERVER: 'Server error occurred. Please contact support.'
        },
        MAX_LENGTH: 1000,
        DEBOUNCE_DELAY: 300
    };

    // Cache DOM elements
    let elements = {};
    let saveTimeout = null;

    // Initialize when DOM is ready
    $(document).ready(function () {
        try {
            cacheElements();
            bindEvents();
            if (window.priorityNoteAutoPopup) {
                openPriorityNoteAlertModal();
            }
        } catch (error) {
            console.error('Priority Note initialization failed:', error);
        }
    });

    function cacheElements() {
        elements = {
            modal: document.getElementById('priorityNoteModal'),
            text: document.getElementById('priorityNoteText'),
            popup: document.getElementById('priorityNotePopup'),
            toggle: document.getElementById('priorityNotePopupToggle'),
            saveBtn: document.getElementById('savePriorityNoteBtn'),
            badgeArea: document.getElementById('priorityNoteBadgeArea')
        };
        
        // Set global reference for backward compatibility
        window.priorityNoteModalEl = elements.modal;
    }

    function bindEvents() {
        if (elements.modal) {
            // Close modal when clicking outside
            elements.modal.addEventListener('click', function(e) {
                if (e.target === elements.modal) {
                    closePriorityNoteModal();
                }
            });
        }

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isModalOpen()) {
                closePriorityNoteModal();
            }
        });

        // Input validation
        if (elements.text) {
            elements.text.addEventListener('input', function () {
                if (elements.text.value.trim()) {
                    elements.text.style.borderColor = '';
                }
                validateInput();
            });
        }
    }

    function validateInput() {
        if (!elements.text) return true;
        
        const value = elements.text.value;
        if (value.length > CONFIG.MAX_LENGTH) {
            elements.text.value = value.substring(0, CONFIG.MAX_LENGTH);
            swal('Warning', `Note cannot exceed ${CONFIG.MAX_LENGTH} characters`, 'warning');
            return false;
        }
        return true;
    }

    function isModalOpen() {
        return elements.modal && elements.modal.style.display === 'block';
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Global functions (maintaining original API)
    window.openPriorityNoteModal = function() {
        if (!elements.text || !elements.popup || !elements.modal) {
            console.error('Required DOM elements not found');
            return;
        }

        elements.text.value = window.priorityNoteData ? (window.priorityNoteData.details || '') : '';
        const popup = window.priorityNoteData && window.priorityNoteData.charge_type == '1';
        elements.popup.checked = popup;
        updatePriorityNotePopupStyle(popup);
        elements.modal.style.display = 'block';
        elements.text.focus();
    };

    window.closePriorityNoteModal = function() {
        if (elements.modal) {
            elements.modal.style.display = 'none';
        }
    };

    window.togglePriorityNotePopup = function() {
        if (!elements.popup) return;
        elements.popup.checked = !elements.popup.checked;
        updatePriorityNotePopupStyle(elements.popup.checked);
    };

    window.updatePriorityNotePopupStyle = function(checked) {
        if (!elements.toggle) return;
        elements.toggle.style.background = CONFIG.COLORS.POPUP_CHECKED_BG;
        elements.toggle.style.border = '1.5px solid ' + CONFIG.COLORS.POPUP_CHECKED_BORDER;
    };

    window.savePriorityNote = function() {
        // Clear existing timeout for debouncing
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }

        saveTimeout = setTimeout(performSave, CONFIG.DEBOUNCE_DELAY);
    };

    function performSave() {
        if (!validateInput()) return;

        const note = elements.text.value.trim();

        if (!note) {
            elements.text.style.borderColor = '#DC2626';
            elements.text.focus();
            return;
        }
        elements.text.style.borderColor = '';
        const popup = elements.popup.checked ? 1 : 0;
        const btn = elements.saveBtn;

        if (btn.disabled) return; // Prevent double submission

        btn.disabled = true;
        btn.innerHTML = CONFIG.MESSAGES.SAVING;

        $.ajax({
            url: window.priorityNoteSaveUrl,
            type: 'POST',
            data: {
                _token: csrfToken,
                note: note,
                popup: popup
            },
            timeout: 30000
        })
        .done(function (resp) {
            if (resp && resp.success) {
                window.priorityNoteData = { details: note, charge_type: popup ? '1' : null };
                updatePriorityNoteBadge(note);
                closePriorityNoteModal();
                showToast('Priority note saved', 'success');
            } else {
                handleError(resp.message || CONFIG.MESSAGES.ERROR_DEFAULT);
            }
        })
        .fail(function (xhr) {
            let errorMessage = CONFIG.MESSAGES.ERROR_DEFAULT;
            
            switch (xhr.status) {
                case 422:
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = Object.values(xhr.responseJSON.errors).flat();
                        errorMessage = 'Validation error: ' + errors.join(', ');
                    }
                    break;
                case 403:
                    errorMessage = CONFIG.MESSAGES.ERROR_PERMISSION;
                    break;
                case 500:
                    errorMessage = CONFIG.MESSAGES.ERROR_SERVER;
                    break;
                case 0:
                    errorMessage = 'Network error. Please check your connection.';
                    break;
            }
            
            handleError(errorMessage);
        })
        .always(function () {
            btn.disabled = false;
            btn.innerHTML = CONFIG.MESSAGES.SAVE_BUTTON;
        });
    }

    function showToast(message, type) {
        var existing = document.getElementById('priorityNoteToast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.id = 'priorityNoteToast';
        var bg = (type === 'error') ? '#DC2626' : '#16A34A';
        toast.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);'
            + 'background:' + bg + ';color:#fff;padding:12px 24px;border-radius:8px;'
            + 'font-size:14px;font-weight:600;z-index:99999;display:flex;align-items:center;gap:10px;'
            + 'box-shadow:0 4px 16px rgba(0,0,0,0.18);white-space:nowrap;';
        var icon = (type === 'error') ? 'fa-times-circle' : 'fa-check-circle';
        toast.innerHTML = '<i class="fas ' + icon + '"></i> ' + message;
        document.body.appendChild(toast);

        setTimeout(function() {
            toast.style.transition = 'opacity 0.4s';
            toast.style.opacity = '0';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3000);
    }

    function handleError(message) {
        showToast(message, 'error');
    }

    window.updatePriorityNoteBadge = function(note) {
        if (!elements.badgeArea) return;
        
        if (note) {
            const escaped = escapeHtml(note);
            elements.badgeArea.innerHTML = '<div class="flex items-center rounded-lg px-3 py-2 cursor-pointer"'
                + ' style="background:' + CONFIG.COLORS.PRIORITY_BG + ';border:1.5px solid ' + CONFIG.COLORS.PRIORITY_RED + ';gap:8px;" onclick="openPriorityNoteModal()">'
                + '<i class="fas fa-flag text-xs" style="color:' + CONFIG.COLORS.PRIORITY_RED + ';flex-shrink:0;"></i>'
                + '<div style="flex:1;min-width:0;">'
                + '<div class="text-xs font-bold uppercase tracking-wide" style="color:' + CONFIG.COLORS.PRIORITY_RED + ';line-height:1;">High Priority</div>'
                + '<div class="text-xs truncate" style="color:' + CONFIG.COLORS.TEXT_GRAY + ';margin-top:2px;">' + escaped + '</div>'
                + '</div>'
                + '<i class="fas fa-pencil-alt text-xs" style="color:' + CONFIG.COLORS.PRIORITY_RED + ';flex-shrink:0;"></i></div>';
        } else {
            elements.badgeArea.innerHTML = '<button type="button" class="text-xs font-medium"'
                + ' style="color:' + CONFIG.COLORS.PRIORITY_RED + ';background:none;border:none;cursor:pointer;display:block;width:100%;text-align:center;"'
                + ' onclick="openPriorityNoteModal()">'
                + '<i class="fas fa-flag mr-1 text-xs"></i> Add Priority Note</button>';
        }
    };

    // Expose main functions for backward compatibility
    window.openPriorityNoteModal = window.openPriorityNoteModal;
    window.closePriorityNoteModal = window.closePriorityNoteModal;
    window.togglePriorityNotePopup = window.togglePriorityNotePopup;
    window.savePriorityNote = window.savePriorityNote;

    // New booking form — tab switcher and popup toggle
    window.switchNoteTab = function(tab) {
        var isNote     = tab === 'note';
        var noteText   = document.getElementById('tour_operator_note');
        var pnText     = document.getElementById('new_booking_priority_note');
        var noteTypeEl = document.getElementById('note_type_input');

        if (isNote) {
            // Copy priority note text → note, then clear priority note
            if (noteText && pnText) noteText.value = pnText.value;
            if (pnText) pnText.value = '';
            var pnPopup = document.getElementById('new_booking_priority_popup');
            if (pnPopup) pnPopup.checked = false;
        } else {
            // Copy note text → priority note, then clear note
            if (pnText && noteText) pnText.value = noteText.value;
            if (noteText) noteText.value = '';
            if (pnText) pnText.focus();
        }

        if (noteTypeEl) noteTypeEl.value = tab;

        document.getElementById('noteSection').style.display = isNote ? '' : 'none';
        document.getElementById('priorityNoteSection').style.display = isNote ? 'none' : '';
    };

    window.toggleNewBookingPopup = function() {
        var checkbox = document.getElementById('new_booking_priority_popup');
        var toggle = document.getElementById('newBookingPopupToggle');
        if (!checkbox || !toggle) return;
        checkbox.checked = !checkbox.checked;
        toggle.style.background = CONFIG.COLORS.POPUP_CHECKED_BG;
        toggle.style.border = '1.5px solid ' + CONFIG.COLORS.POPUP_CHECKED_BORDER;
    };

    // Priority Note Alert Modal (auto-popup view)
    window.openPriorityNoteAlertModal = function() {
        var alertModal = document.getElementById('priorityNoteAlertModal');
        if (!alertModal || !window.priorityNoteData) return;

        // Populate note text
        var noteEl = document.getElementById('priorityNoteAlertText');
        if (noteEl) noteEl.textContent = window.priorityNoteData.details || '';

        // Populate booking ref
        var refEl = document.getElementById('priorityNoteAlertRef');
        if (refEl) refEl.textContent = window.priorityNoteBookingRef || '';

        // Populate set by
        var setByEl = document.getElementById('priorityNoteAlertSetBy');
        if (setByEl) {
            var photo = window.priorityNoteData.set_by_photo;
            var name  = window.priorityNoteData.set_by_name || '';
            var at    = window.priorityNoteData.operation_at || '';
            var imgHtml = photo
                ? '<img src="' + photo + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid #E5E7EB;">'
                : '<span style="width:32px;height:32px;border-radius:50%;background:#E5E7EB;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-user" style="color:#9CA3AF;font-size:12px;"></i></span>';
            var dateStr = at ? new Date(at).toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit'}) : '';
            setByEl.innerHTML = imgHtml + '<span>Set by <strong style="color:#374151;">' + name + '</strong> &bull; ' + dateStr + '</span>';
        }

        // Reset "don't show again"
        var dontShow = document.getElementById('priorityNoteDontShow');
        if (dontShow) dontShow.value = '0';
        resetDontShowToggle();

        alertModal.style.display = 'block';
    };

    function resetDontShowToggle() {
        var box  = document.getElementById('dontShowToggleBox');
        var icon = document.getElementById('dontShowIcon');
        var text = document.getElementById('dontShowText');
        if (!box || !icon || !text) return;
        box.style.background  = '#fff';
        box.style.border      = '1px solid #E5E7EB';
        icon.style.background = '';
        icon.style.border     = '2px solid #D1D5DB';
        icon.innerHTML        = '';
        text.style.color      = '#6B7280';
        text.style.fontWeight = '';
        text.textContent      = 'Don\'t show this note again when opening this booking';
    }

    window.toggleDontShowNote = function() {
        var hidden = document.getElementById('priorityNoteDontShow');
        var box    = document.getElementById('dontShowToggleBox');
        var icon   = document.getElementById('dontShowIcon');
        var text   = document.getElementById('dontShowText');
        if (!hidden || !box || !icon || !text) return;

        var isSelected = hidden.value === '1';
        if (isSelected) {
            // Deselect
            hidden.value          = '0';
            box.style.background  = '#fff';
            box.style.border      = '1px solid #E5E7EB';
            icon.style.background = '';
            icon.style.border     = '2px solid #D1D5DB';
            icon.innerHTML        = '';
            text.style.color      = '#6B7280';
            text.style.fontWeight = '';
            text.textContent      = 'Don\'t show this note again when opening this booking';
        } else {
            // Select
            hidden.value          = '1';
            box.style.background  = '#fff';
            box.style.border      = '1px solid #86EFAC';
            icon.style.background = '#16A34A';
            icon.style.border     = '2px solid #16A34A';
            icon.innerHTML        = '<i class="fas fa-check" style="color:#fff;font-size:10px;"></i>';
            text.style.color      = '#15803D';
            text.style.fontWeight = '700';
            text.textContent      = 'Note marked — will not pop up on next open';
        }
    };

    window.continuePriorityNoteAlert = function() {
        var alertModal = document.getElementById('priorityNoteAlertModal');
        var dontShow   = document.getElementById('priorityNoteDontShow');
        var isDontShow = dontShow && dontShow.value === '1';

        if (alertModal) alertModal.style.display = 'none';

        if (isDontShow && window.priorityNoteData && window.priorityNoteSaveUrl) {
            $.ajax({
                url: window.priorityNoteSaveUrl,
                type: 'POST',
                data: {
                    _token: csrfToken,
                    note: window.priorityNoteData.details || '',
                    popup: 0
                }
            }).done(function(resp) {
                if (resp && resp.success) {
                    window.priorityNoteData.charge_type = null;
                    window.priorityNoteAutoPopup = false;
                }
            });

            showDarkToast('• Note acknowledged — will not pop up again');
        }
    };

    function showDarkToast(message) {
        var existing = document.getElementById('priorityNoteDarkToast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.id = 'priorityNoteDarkToast';
        toast.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);'
            + 'background:#111827;color:#fff;padding:12px 28px;border-radius:8px;'
            + 'font-size:14px;font-weight:600;z-index:99999;display:flex;align-items:center;gap:8px;'
            + 'box-shadow:0 4px 16px rgba(0,0,0,0.28);white-space:nowrap;';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function() {
            toast.style.transition = 'opacity 0.4s';
            toast.style.opacity = '0';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3000);
    }

})();