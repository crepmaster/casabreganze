/**
 * EasyRest - Checkout Guest Pre-fill
 *
 * Minimal flow:
 * 1) Persist context from query params (dates + guests).
 * 2) Capture guests on MPHB form submit/book clicks.
 * 3) Pre-fill adults/children on checkout.
 *
 * @package EasyRest_Child
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'easyrest_booking_guests';
    var CONTEXT_KEY = 'easyrest_booking_context';

    function getQueryParam(candidates) {
        if (!window.location.search) {
            return '';
        }

        var params = new URLSearchParams(window.location.search);
        for (var i = 0; i < candidates.length; i++) {
            var value = params.get(candidates[i]);
            if (value) {
                return value;
            }
        }
        return '';
    }

    function persistContextFromQuery() {
        var checkin = getQueryParam(['mphb_check_in_date', 'check_in_date', 'check-in-date', 'checkin']);
        var checkout = getQueryParam(['mphb_check_out_date', 'check_out_date', 'check-out-date', 'checkout']);
        var adults = getQueryParam(['mphb_adults', 'adults']);
        var children = getQueryParam(['mphb_children', 'children']);

        if (!checkin || !checkout) {
            return;
        }

        try {
            sessionStorage.setItem(CONTEXT_KEY, JSON.stringify({
                checkin: checkin,
                checkout: checkout,
                adults: adults || '1',
                children: children || '0'
            }));
        } catch (e) {
            // Ignore private mode restrictions.
        }

        if (adults || children) {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                    adults: adults || '1',
                    children: children || '0'
                }));
            } catch (e) {
                // Ignore private mode restrictions.
            }
        }
    }

    function captureGuests() {
        document.addEventListener('click', function (e) {
            var action = e.target.closest('.mphb-reserve-btn, .mphb-confirm-reservation, button[type="submit"], a[href*="hotel-checkout"], a[href*="mphb-checkout"]');
            if (!action) {
                return;
            }

            var adultsSelect = document.querySelector('.mphb-reserve-room-section select[name*="adults"], .mphb_sc_booking_form-wrapper select[name*="adults"], select[name="mphb_adults"]');
            var childrenSelect = document.querySelector('.mphb-reserve-room-section select[name*="children"], .mphb_sc_booking_form-wrapper select[name*="children"], select[name="mphb_children"]');

            var payload = {};
            if (adultsSelect && adultsSelect.value) {
                payload.adults = adultsSelect.value;
            }
            if (childrenSelect && childrenSelect.value) {
                payload.children = childrenSelect.value;
            }

            if (!payload.adults && !payload.children) {
                return;
            }

            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            } catch (err) {
                // Ignore private mode restrictions.
            }
        }, true);
    }

    function prefillGuests() {
        var stored;
        try {
            stored = JSON.parse(sessionStorage.getItem(STORAGE_KEY));
        } catch (e) {
            return;
        }

        if (!stored) {
            return;
        }

        var adultsSelects = document.querySelectorAll('.mphb-checkout-guests-chooser select[name*="adults"], .mphb-guest-chooser select[name*="adults"], select[id*="mphb_room_details"][id*="adults"]');
        var childrenSelects = document.querySelectorAll('.mphb-checkout-guests-chooser select[name*="children"], .mphb-guest-chooser select[name*="children"], select[id*="mphb_room_details"][id*="children"]');

        if (adultsSelects.length === 0) {
            adultsSelects = document.querySelectorAll('select[name*="adults"]');
        }
        if (childrenSelects.length === 0) {
            childrenSelects = document.querySelectorAll('select[name*="children"]');
        }

        function setSelectValue(selects, value) {
            selects.forEach(function (select) {
                if (select.value !== '') {
                    return;
                }

                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === value) {
                        select.selectedIndex = i;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }
                }
            });
        }

        if (stored.adults) {
            setSelectValue(adultsSelects, stored.adults);
        }
        if (stored.children) {
            setSelectValue(childrenSelects, stored.children);
        }
    }

    persistContextFromQuery();
    captureGuests();
    prefillGuests();
})();
