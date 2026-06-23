/**
 * msv-phone.js - Swiss phone number formatting & validation
 * Format: +41 XX XXX XX XX
 */

function formatSwissPhone(value) {
    if (!value) return '';
    var raw = value.replace(/[^\d+]/g, '');
    if (!raw) return '';

    // Normalize prefixes: 079... → +4179..., 0041... → +41...
    if (raw.startsWith('0041')) raw = '+41' + raw.slice(4);
    else if (raw.startsWith('0')) raw = '+41' + raw.slice(1);
    else if (!raw.startsWith('+')) raw = '+41' + raw;

    // Extract digits after +
    var nums = raw.replace('+', '');
    if (!nums.startsWith('41')) return value; // Not Swiss — leave as-is

    var rest = nums.slice(2); // digits after 41
    if (rest.length > 9) rest = rest.slice(0, 9); // max 9 digits

    // Format: +41 XX XXX XX XX
    var parts = ['+41'];
    if (rest.length > 0) parts.push(rest.slice(0, 2));
    if (rest.length > 2) parts.push(rest.slice(2, 5));
    if (rest.length > 5) parts.push(rest.slice(5, 7));
    if (rest.length > 7) parts.push(rest.slice(7, 9));
    return parts.join(' ');
}

function isValidSwissPhone(value) {
    if (!value || value.trim() === '') return true; // Empty = OK (optional field)
    return /^\+41 \d{2} \d{3} \d{2} \d{2}$/.test(value.trim());
}
