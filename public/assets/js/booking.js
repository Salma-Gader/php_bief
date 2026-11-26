/**
 * Sunset Brunch: booking form
 *
 * 1. Checks every field in the browser before sending (the server checks again).
 * 2. Sends the form with fetch(), without reloading the page.
 * 3. Shows a clear success or error message.
 *
 * The booking rules (sittings, guests, opening day...) are not written here:
 * PHP prints them as data-* attributes on the <form>, from config/booking.php.
 */

const form = document.getElementById('booking-form');

if (form) {
    initBookingForm(form);
}

/* ==========================================================================
   Validation rules
   ========================================================================== */

// Letters from any language (including Arabic and accents), spaces, apostrophes, dots and hyphens.
const NAME_PATTERN = /^[\p{L}\p{M}' ’.-]+$/u;
// Moroccan numbers: 06 12 34 56 78 or +212 6 12 34 56 78 (5, 6 or 7 after the prefix).
const MOROCCAN_PHONE = /^(?:\+212|0)[5-7]\d{8}$/;
// Any other country in international format: + then 8 to 15 digits.
const INTERNATIONAL_PHONE = /^\+[1-9]\d{7,14}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

const MESSAGE_MAX_LENGTH = 500;
const REQUEST_TIMEOUT_MS = 15000;

/**
 * One function per field. Each returns an error message, or '' when the value is valid.
 * `v` holds the current form values, `rules` the booking rules read from the form.
 */
const validators = {
    full_name(v) {
        if (v.full_name === '') return 'Enter your full name.';
        if (v.full_name.length < 2 || v.full_name.length > 100) return 'Your name must be between 2 and 100 characters.';
        if (!NAME_PATTERN.test(v.full_name)) return 'Use letters, spaces, apostrophes or hyphens only.';
        return '';
    },

    phone(v) {
        if (v.phone === '') return 'Enter a phone number so we can reach you.';
        const phone = normalisePhone(v.phone);
        if (!MOROCCAN_PHONE.test(phone) && !INTERNATIONAL_PHONE.test(phone)) {
            return 'Enter a valid phone number, for example 06 12 34 56 78 or +212 6 12 34 56 78.';
        }
        return '';
    },

    email(v) {
        if (v.email === '') return 'Enter your email address.';
        if (v.email.length > 190 || !EMAIL_PATTERN.test(v.email)) return 'Enter a valid email address, for example name@example.com.';
        return '';
    },

    booking_date(v, rules) {
        if (v.booking_date === '') return 'Choose a date.';

        const date = parseIsoDate(v.booking_date);
        if (date === null) return 'Enter a valid date.';
        // getUTCDay(): 0 = Sunday ... 6 = Saturday. The config uses ISO numbers (7 = Sunday).
        if (date.getUTCDay() !== rules.openWeekday % 7) return 'Brunch is served on Sundays only. Choose a Sunday.';

        const today = restaurantNow(rules.timeZone).date;
        // YYYY-MM-DD strings sort like dates, so they can be compared directly.
        if (v.booking_date < today) return 'This date has passed. Choose an upcoming Sunday.';
        if (v.booking_date > addDays(today, rules.windowDays)) {
            return `You can book up to ${rules.windowDays} days ahead. Choose an earlier Sunday.`;
        }
        return '';
    },

    time_slot(v, rules) {
        if (v.time_slot === '') {
            if (v.booking_date !== '' && rules.slots.every((slot) => slotHasStarted(v.booking_date, slot, rules))) {
                return 'All sittings on this date have started. Choose another Sunday.';
            }
            return 'Choose a sitting.';
        }
        if (!rules.slots.includes(v.time_slot)) return 'Choose one of the sittings shown.';
        if (slotHasStarted(v.booking_date, v.time_slot, rules)) {
            return 'This sitting has already started. Choose a later sitting or another Sunday.';
        }
        return '';
    },

    guests(v, rules) {
        const guests = Number(v.guests);
        if (!Number.isInteger(guests) || guests < rules.minGuests || guests > rules.maxGuests) {
            return `Choose between ${rules.minGuests} and ${rules.maxGuests} guests.`;
        }
        return '';
    },

    message(v) {
        if (v.message.length > MESSAGE_MAX_LENGTH) return `Keep your message under ${MESSAGE_MAX_LENGTH} characters.`;
        return '';
    },
};

/* ==========================================================================
   Form behaviour
   ========================================================================== */

function initBookingForm(form) {
    const rules = readRules(form);
    const statusBox = document.getElementById('form-status');
    const submitButton = form.querySelector('button[type="submit"]');
    const submitLabel = submitButton.textContent;
    const checkedFields = new Set(); // fields the guest has already left once
    let isSending = false;

    // Showing or hiding an error moves everything below it. If that happened between
    // mouse-down and mouse-up on the submit button, the button would slide away and the
    // click would be lost. So while the guest is mid-click, we wait for the click to finish.
    let pointerIsDown = false;
    document.addEventListener('pointerdown', () => { pointerIsDown = true; });
    document.addEventListener('pointerup', () => { pointerIsDown = false; });

    // Check a field when the guest leaves it, not while they are still typing it.
    form.addEventListener('focusout', (event) => {
        const name = event.target.name;
        if (!(name in validators)) return;
        checkedFields.add(name);

        const check = () => showFieldError(form, name, validateField(form, name, rules));
        if (pointerIsDown) {
            document.addEventListener('pointerup', () => setTimeout(check), { once: true });
        } else {
            check();
        }
    });

    // Once a field has shown an error, update it live so the guest sees it is fixed.
    form.addEventListener('input', (event) => {
        const name = event.target.name;

        if (name === 'booking_date') {
            updateSlotChoices(form, rules);
            if (checkedFields.has('time_slot')) {
                showFieldError(form, 'time_slot', validateField(form, 'time_slot', rules));
            }
        }
        if (checkedFields.has(name)) {
            showFieldError(form, name, validateField(form, name, rules));
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault(); // stop the normal page reload
        if (isSending) return;  // ignore double clicks

        clearStatus(statusBox);

        // 1. Validate everything in the browser first.
        const errors = validateAll(form, rules);
        // From now on every field updates live, including ones never tabbed out of.
        Object.keys(validators).forEach((name) => checkedFields.add(name));

        if (Object.keys(errors).length > 0) {
            showAllErrors(form, errors);
            const count = Object.keys(errors).length;
            showStatus(statusBox, 'error', count === 1 ? 'Check the highlighted field.' : `Check the ${count} highlighted fields.`);
            focusField(form, Object.keys(errors)[0]);
            return;
        }

        // 2. Send it.
        isSending = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Sending…';
        form.setAttribute('aria-busy', 'true');

        try {
            const result = await sendForm(form);
            handleServerResponse(result);
        } catch (error) {
            const message = error.name === 'AbortError'
                ? 'The server took too long to answer. Check your connection and try again.'
                : 'We could not reach the server. Check your connection and try again.';
            showStatus(statusBox, 'error', message);
        } finally {
            isSending = false;
            submitButton.disabled = false;
            submitButton.textContent = submitLabel;
            form.removeAttribute('aria-busy');
        }
    });

    // 3. Show what the server answered.
    function handleServerResponse({ ok, status, data }) {
        if (ok && data?.ok) {
            form.reset();
            checkedFields.clear();
            clearAllErrors(form);
            updateSlotChoices(form, rules);
            showStatus(statusBox, 'success', data.message || 'Your booking request has been sent.');
            statusBox.focus(); // move keyboard and screen-reader users to the confirmation
            return;
        }

        // The server may point at specific fields (422 invalid data, 409 sitting full).
        const fieldErrors = data?.errors ?? {};
        showAllErrors(form, fieldErrors);
        showStatus(statusBox, 'error', data?.message || messageForStatus(status));

        const firstField = Object.keys(fieldErrors).find((name) => name in validators);
        if (firstField) {
            focusField(form, firstField);
        } else {
            statusBox.focus();
        }
    }

    updateSlotChoices(form, rules);
}

/** Send the form as multipart/form-data and read the JSON answer. */
async function sendForm(form) {
    // Abort the request if the server does not answer in time.
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        let data = null;
        try {
            data = await response.json();
        } catch {
            // Not JSON (for example an HTML error page): handled as a generic error.
        }
        return { ok: response.ok, status: response.status, data };
    } finally {
        clearTimeout(timer);
    }
}

function messageForStatus(status) {
    if (status === 409) return 'That sitting is full. Choose another sitting or another Sunday.';
    if (status === 422) return 'Check the highlighted fields.';
    if (status === 429) return 'Too many booking attempts. Wait a few minutes, then try again.';
    return 'The booking could not be saved because of a problem on our side. Try again in a moment.';
}

/* ==========================================================================
   Reading values and rules
   ========================================================================== */

function readRules(form) {
    const d = form.dataset;
    return {
        slots: JSON.parse(d.slots),
        openWeekday: Number(d.openWeekday),
        minGuests: Number(d.minGuests),
        maxGuests: Number(d.maxGuests),
        windowDays: Number(d.windowDays),
        timeZone: d.timezone,
    };
}

function getValues(form) {
    const data = new FormData(form);
    const text = (name) => String(data.get(name) ?? '').trim();
    return {
        full_name: text('full_name'),
        phone: text('phone'),
        email: text('email'),
        booking_date: text('booking_date'),
        time_slot: text('time_slot'),
        guests: text('guests'),
        message: text('message'),
    };
}

function validateField(form, name, rules) {
    return validators[name](getValues(form), rules);
}

function validateAll(form, rules) {
    const values = getValues(form);
    const errors = {};
    for (const [name, validate] of Object.entries(validators)) {
        const message = validate(values, rules);
        if (message) errors[name] = message;
    }
    return errors;
}

/* ==========================================================================
   Showing errors and messages
   ========================================================================== */

/** The element that gets aria-invalid: the input, or the fieldset for the sitting radios. */
function fieldElement(form, name) {
    return name === 'time_slot' ? form.querySelector('.slots') : form.elements[name];
}

function showFieldError(form, name, message) {
    const errorBox = document.getElementById(`${name}-error`);
    const field = fieldElement(form, name);
    if (!errorBox || !field) return;

    errorBox.textContent = message;
    if (message) {
        field.setAttribute('aria-invalid', 'true');
    } else {
        field.removeAttribute('aria-invalid');
    }
}

function showAllErrors(form, errors) {
    for (const name of Object.keys(validators)) {
        showFieldError(form, name, errors[name] ?? '');
    }
}

function clearAllErrors(form) {
    showAllErrors(form, {});
}

function focusField(form, name) {
    // For the sittings: the chosen one if any, otherwise the first one that can be chosen.
    const target = name === 'time_slot'
        ? form.querySelector('input[name="time_slot"]:checked') ?? form.querySelector('input[name="time_slot"]:not(:disabled)')
        : form.elements[name];
    target?.focus();
}

function showStatus(box, type, message) {
    box.className = `form-status form-status--${type}`;
    box.textContent = message;
}

function clearStatus(box) {
    box.className = 'form-status';
    box.textContent = '';
}

/**
 * On the open day itself, sittings that have already started cannot be chosen.
 * Their radio buttons are disabled (and unticked if they were selected).
 */
function updateSlotChoices(form, rules) {
    const date = form.elements.booking_date.value;
    for (const radio of form.querySelectorAll('input[name="time_slot"]')) {
        const started = date !== '' && slotHasStarted(date, radio.value, rules);
        radio.disabled = started;
        if (started) radio.checked = false;
    }
}

/* ==========================================================================
   Dates and times, always in the restaurant's time zone
   ========================================================================== */

/**
 * Current date and time in Tanger, whatever the visitor's own time zone is.
 * Returns strings that compare correctly: { date: '2026-11-29', time: '12:05' }.
 */
function restaurantNow(timeZone) {
    const parts = {};
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });
    for (const part of formatter.formatToParts(new Date())) {
        parts[part.type] = part.value;
    }
    return {
        date: `${parts.year}-${parts.month}-${parts.day}`,
        time: `${parts.hour}:${parts.minute}`,
    };
}

/** True when the date is today (in Tanger) and the sitting time has been reached. */
function slotHasStarted(isoDate, slot, rules) {
    const now = restaurantNow(rules.timeZone);
    return isoDate === now.date && slot <= now.time;
}

/**
 * Strictly parse 'YYYY-MM-DD'. Returns a Date at midnight UTC, or null.
 * UTC is used so the visitor's time zone can never shift the day.
 */
function parseIsoDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return null;

    const [year, month, day] = match.slice(1).map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    // Reject impossible dates such as 2026-02-31, which Date would roll over to March.
    if (date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
    return date;
}

function addDays(isoDate, days) {
    const date = parseIsoDate(isoDate);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
}

/** Remove spaces, dots, dashes and brackets; turn a leading 00 into +. */
function normalisePhone(raw) {
    const phone = raw.replace(/[\s.\-()]/g, '');
    return phone.startsWith('00') ? `+${phone.slice(2)}` : phone;
}
