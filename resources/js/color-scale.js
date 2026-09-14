/**
 * Client-side mirror of `app/Support/BrandColorScale.php`, keep the ratio
 * tables below identical to that file so client-side live preview and the
 * server-rendered `<style>` override always agree.
 */

const LIGHTEN_RATIOS = {
    50: 0.95,
    100: 0.85,
    200: 0.65,
    300: 0.46,
    400: 0.24,
    500: 0.10,
};

const DARKEN_MULTIPLIERS = {
    700: 0.82,
    800: 0.62,
    900: 0.44,
};

const SATURATION_MULTIPLIERS = {
    50: 0.75, 100: 0.82, 200: 0.88, 300: 0.92, 400: 0.96,
    500: 0.98, 600: 1.0, 700: 1.02, 800: 1.04, 900: 1.06,
};

function hexToHsl(hex) {
    hex = hex.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;

    if (max === min) {
        return [0, 0, l * 100];
    }

    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    let h;
    if (max === r) {
        h = ((g - b) / d) % 6;
    } else if (max === g) {
        h = (b - r) / d + 2;
    } else {
        h = (r - g) / d + 4;
    }

    h *= 60;
    if (h < 0) {
        h += 360;
    }

    return [h, s * 100, l * 100];
}

function hslToHex([h, s, l]) {
    s = Math.max(0, Math.min(100, s)) / 100;
    l = Math.max(0, Math.min(100, l)) / 100;

    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = l - c / 2;

    let r, g, b;
    if (h < 60) { [r, g, b] = [c, x, 0]; }
    else if (h < 120) { [r, g, b] = [x, c, 0]; }
    else if (h < 180) { [r, g, b] = [0, c, x]; }
    else if (h < 240) { [r, g, b] = [0, x, c]; }
    else if (h < 300) { [r, g, b] = [x, 0, c]; }
    else { [r, g, b] = [c, 0, x]; }

    const toHex = (v) => Math.round((v + m) * 255).toString(16).padStart(2, '0');

    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

/**
 * @param {string} hex
 * @returns {Record<string, string>} shade (e.g. "600") => "#rrggbb"
 */
export function generateColorScale(hex) {
    const [h, s, l] = hexToHsl(hex);
    const scale = { 600: `#${hex.replace('#', '').toLowerCase()}` };

    for (const [shade, ratio] of Object.entries(LIGHTEN_RATIOS)) {
        const shadeL = l + ratio * (100 - l);
        const shadeS = Math.min(100, s * SATURATION_MULTIPLIERS[shade]);
        scale[shade] = hslToHex([h, shadeS, shadeL]);
    }

    for (const [shade, multiplier] of Object.entries(DARKEN_MULTIPLIERS)) {
        const shadeL = l * multiplier;
        const shadeS = Math.min(100, s * SATURATION_MULTIPLIERS[shade]);
        scale[shade] = hslToHex([h, shadeS, shadeL]);
    }

    return Object.fromEntries(
        Object.entries(scale).sort((a, b) => Number(a[0]) - Number(b[0]))
    );
}

/**
 * Normalizes any browser-parseable CSS color (hex, `rgb()`, `hsl()`, named
 * color) to a `#rrggbb` string, by letting the browser's own CSS parser do
 * the work rather than shipping a lookup table.
 *
 * @param {string} input
 * @returns {string|null} `#rrggbb`, or null if the browser rejected the input.
 */
export function normalizeCssColorToHex(input) {
    const probe = document.createElement('div');
    probe.style.color = '';
    probe.style.color = input;

    if (! probe.style.color) {
        return null;
    }

    document.body.appendChild(probe);
    const computed = getComputedStyle(probe).color;
    document.body.removeChild(probe);

    const match = computed.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);

    if (! match) {
        return null;
    }

    const toHex = (v) => Number(v).toString(16).padStart(2, '0');

    return `#${toHex(match[1])}${toHex(match[2])}${toHex(match[3])}`;
}
