const SHADOW_MAP = {
    none: 'none',
    sm: '0 1px 2px rgba(0,0,0,0.15)',
    md: '0 4px 8px rgba(0,0,0,0.20)',
    lg: '0 10px 25px rgba(0,0,0,0.25)',
    xl: '0 20px 45px rgba(0,0,0,0.30)',
};

const BLUR_MAP = { none: '0px', sm: '4px', md: '8px', lg: '16px', xl: '24px' };

const DEFAULT_STYLE = {
    text: {
        font_family: 'Inter', font_weight: '400', font_size: 16,
        line_height: 1.5, letter_spacing: 0, text_transform: 'none', align: 'left',
        color: '#111827', visible: true,
    },
    button: {
        font_family: 'Inter', font_weight: '700', font_size: 14,
        line_height: 1, letter_spacing: 0, text_transform: 'none', align: 'center',
        bg_color: '#166fe5', text_color: '#ffffff',
        hover_bg_color: '#1259bd', hover_text_color: '#ffffff',
        border_width: 0, border_color: '#000000',
        radius: 8, shadow: 'lg', padding_x: 24, padding_y: 12, visible: true,
    },
    card: {
        bg_color: '#ffffff', bg_opacity: 100, blur: 'none',
        border_color: '#e5e7eb', border_opacity: 100, border_width: 1,
        radius: 10, shadow: 'sm',
        font_family: 'Inter', font_weight: '700', font_size: 16,
        line_height: 1.3, letter_spacing: 0, text_transform: 'none', align: 'left',
        color: '#111827', visible: true,
    },
};

function hexToRgba(hex, opacityPercent) {
    hex = (hex || '').replace('#', '');

    if (hex.length !== 6) {
        return hex === 'transparent' || hex === '' ? 'transparent' : `#${hex}`;
    }

    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    const a = Math.round(Math.max(0, Math.min(100, opacityPercent ?? 100)) * 100) / 10000;

    return `rgba(${r}, ${g}, ${b}, ${a})`;
}

function boxStyle(block) {
    return `left:${block.x}%;top:${block.y}%;width:${block.w}%;height:${block.h}%;`;
}

function uid() {
    return (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`);
}

/**
 * Alpine.data() component powering the unified Website visual builder, one
 * instance per page tab (Home/About/Admissions/Contact/Footer), operating on
 * an arbitrary set of blocks grouped by section rather than fixed named
 * elements, so the exact same engine covers every page.
 */
export default function pageBuilder(initialBlocks, fontOptions) {
    const sections = {};

    for (const block of initialBlocks) {
        sections[block.section] = sections[block.section] || [];
        sections[block.section].push({ ...block });
    }

    return {
        sections,
        selected: null,
        editingId: null,
        fontOptions,
        handles: ['nw', 'n', 'ne', 'w', 'e', 'sw', 's', 'se'],

        select(uuid) {
            this.selected = uuid;
        },

        deselect() {
            this.selected = null;
            this.editingId = null;
        },

        get selectedBlock() {
            for (const key in this.sections) {
                const found = this.sections[key].find((b) => b.uuid === this.selected);

                if (found) {
                    return found;
                }
            }

            return null;
        },

        startEditing(block, event, secondary = false) {
            this.selected = block.uuid;
            this.editingId = secondary ? `${block.uuid}-secondary` : block.uuid;
            this.$nextTick(() => event.target.focus());
        },

        stopEditing(block, event, secondary = false) {
            const text = event.target.textContent;

            if (secondary) {
                block.secondary_content = text;
            } else {
                block.content = text;
            }

            this.editingId = null;
        },

        addBlock(section, type) {
            const block = {
                uuid: uid(),
                section,
                type,
                content: type === 'button' ? 'New Button' : 'New text',
                secondary_content: type === 'card' ? 'Description' : null,
                url: type === 'button' ? '#' : null,
                x: 10, y: 10, w: type === 'button' ? 20 : 30, h: type === 'button' ? 8 : 15,
                style: { ...DEFAULT_STYLE[type] },
            };

            this.sections[section] = this.sections[section] || [];
            this.sections[section].push(block);
            this.select(block.uuid);
        },

        removeBlock(section, uuid) {
            this.sections[section] = (this.sections[section] || []).filter((b) => b.uuid !== uuid);

            if (this.selected === uuid) {
                this.deselect();
            }
        },

        weightsFor(family) {
            return this.fontOptions[family] || [400];
        },

        /**
         * @returns {Record<string, number[]>} font family => distinct weights used, across every section
         */
        usedFonts() {
            const used = {};

            for (const key in this.sections) {
                for (const block of this.sections[key]) {
                    const family = block.style.font_family;
                    const weight = block.style.font_weight;

                    if (! family || ! weight) {
                        continue;
                    }

                    used[family] = used[family] || [];

                    if (! used[family].includes(Number(weight))) {
                        used[family].push(Number(weight));
                    }
                }
            }

            return used;
        },

        ensureFontLoaded(family) {
            if (! family || family === 'Inter') {
                return;
            }

            const id = `edn-google-font-${family.replace(/\s+/g, '-')}`;

            if (document.getElementById(id)) {
                return;
            }

            const weights = this.weightsFor(family).join(';');
            const link = document.createElement('link');
            link.id = id;
            link.rel = 'stylesheet';
            link.href = `https://fonts.googleapis.com/css2?family=${family.replace(/\s+/g, '+')}:wght@${weights}&display=swap`;
            document.head.appendChild(link);
        },

        allBlocksJson() {
            const all = [];

            for (const key in this.sections) {
                for (const block of this.sections[key]) {
                    all.push({ ...block, section: key });
                }
            }

            return JSON.stringify(all);
        },

        textStyleFor(block) {
            const s = block.style;

            return boxStyle(block)
                + `font-family:'${s.font_family}',var(--font-sans);font-weight:${s.font_weight};`
                + `font-size:${s.font_size}px;line-height:${s.line_height};letter-spacing:${s.letter_spacing}px;`
                + `text-align:${s.align};text-transform:${s.text_transform};color:${s.color};`;
        },

        buttonStyleFor(block) {
            const s = block.style;
            const justify = s.align === 'left' ? 'flex-start' : s.align === 'right' ? 'flex-end' : 'center';
            const shadow = SHADOW_MAP[s.shadow] || 'none';

            return boxStyle(block)
                + `display:flex;align-items:center;justify-content:${justify};`
                + `font-family:'${s.font_family}',var(--font-sans);font-weight:${s.font_weight};`
                + `font-size:${s.font_size}px;line-height:${s.line_height};letter-spacing:${s.letter_spacing}px;`
                + `text-align:${s.align};text-transform:${s.text_transform};`
                + `background-color:${s.bg_color};color:${s.text_color};`
                + `border-width:${s.border_width}px;border-style:solid;border-color:${s.border_color};`
                + `border-radius:${s.radius}px;box-shadow:${shadow};`
                + `padding:0 ${s.padding_x}px;text-decoration:none;`;
        },

        cardStyleFor(block) {
            const s = block.style;
            const shadow = SHADOW_MAP[s.shadow] || 'none';
            const blur = BLUR_MAP[s.blur] || '0px';

            return boxStyle(block)
                + `background-color:${hexToRgba(s.bg_color, s.bg_opacity)};`
                + `backdrop-filter:blur(${blur});-webkit-backdrop-filter:blur(${blur});`
                + `border-width:${s.border_width}px;border-style:solid;border-color:${hexToRgba(s.border_color, s.border_opacity)};`
                + `border-radius:${s.radius}px;box-shadow:${shadow};padding:16px;box-sizing:border-box;overflow:auto;`;
        },
    };
}
