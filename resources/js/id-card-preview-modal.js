export function registerIdCardPreviewStore(Alpine) {
    Alpine.store('idCardPreview', {
        open: false,
        loading: false,
        downloading: false,
        error: null,
        side: 'front',
        zoom: 1,
        // Multiplier that fits the card's actual rendered size (physical mm
        // dimensions, landscape or portrait) inside the modal's visible
        // viewport, recalculated from real measurements every time a card
        // loads, since a fixed multiplier overflows the viewport for some
        // card/screen combinations and clips the card behind scrollbars.
        baseScale: 1,
        hasTemplate: true,
        cardNumber: '',
        frontHtml: '',
        backHtml: '',
        holderType: '',
        holderUuid: '',
        templateUuid: '',
        printUrl: '',
        downloadUrl: '',

        async openPreview(previewUrl, printUrl, downloadUrl) {
            this.open = true;
            this.loading = true;
            this.error = null;
            this.side = 'front';
            this.zoom = 1;
            this.baseScale = 1;
            this.printUrl = printUrl;
            this.downloadUrl = downloadUrl;

            try {
                const response = await fetch(previewUrl, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error('Failed to load card preview.');
                }

                const data = await response.json();

                this.cardNumber = data.card_number;
                this.hasTemplate = data.has_template;
                this.frontHtml = data.front;
                this.backHtml = data.back;
                this.holderType = data.holder_type;
                this.holderUuid = data.holder_uuid;
                this.templateUuid = data.template_uuid;
            } catch (e) {
                this.error = 'Could not load this card. Please try again.';
            } finally {
                this.loading = false;
            }

            Alpine.nextTick(() => this.fitToViewport());
        },

        close() {
            this.open = false;
        },

        effectiveScale() {
            return this.baseScale * this.zoom;
        },

        /**
         * Measures the currently visible card's true (pre-transform) size
         * against its scrollable viewport and picks a scale that fits both
         * dimensions, CSS transforms don't affect offsetWidth/offsetHeight,
         * so this reads the card's real physical-mm-based layout size
         * regardless of any scale already applied to it.
         */
        fitToViewport() {
            const canvas = document.getElementById('id-card-preview-canvas');
            const card = document.getElementById(this.side === 'front' ? 'id-card-preview-front' : 'id-card-preview-back')?.firstElementChild;

            if (!canvas || !card) {
                return;
            }

            const availableWidth = canvas.clientWidth - 16;
            const availableHeight = canvas.clientHeight - 48;

            if (availableWidth <= 0 || availableHeight <= 0 || !card.offsetWidth || !card.offsetHeight) {
                return;
            }

            const fit = Math.min(availableWidth / card.offsetWidth, availableHeight / card.offsetHeight);
            this.baseScale = Math.max(0.5, Math.min(fit, 3));
        },

        switchSide(side) {
            this.side = side;
            Alpine.nextTick(() => this.fitToViewport());
        },

        zoomIn() {
            this.zoom = Math.min(3, Math.round((this.zoom + 0.25) * 100) / 100);
        },

        zoomOut() {
            this.zoom = Math.max(0.25, Math.round((this.zoom - 0.25) * 100) / 100);
        },

        resetZoom() {
            this.zoom = 1;
        },

        print() {
            const form = document.getElementById('id-card-print-form');

            if (!form) {
                return;
            }

            document.getElementById('id-card-print-type').value = this.holderType;
            document.getElementById('id-card-print-record').value = this.holderUuid;
            document.getElementById('id-card-print-template').value = this.templateUuid ?? '';
            form.submit();
        },

        async download() {
            this.downloading = true;

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const body = new URLSearchParams();
                body.append('type', this.holderType);
                body.append('records[]', this.holderUuid);

                if (this.templateUuid) {
                    body.append('template', this.templateUuid);
                }

                const response = await fetch(this.downloadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    body,
                });

                if (!response.ok) {
                    throw new Error('Download failed.');
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `id-card-${this.cardNumber}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (e) {
                this.error = 'Could not download the PDF. Please try again.';
            } finally {
                this.downloading = false;
            }
        },
    });
}
