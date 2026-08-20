export function registerResultPreviewStore(Alpine) {
    Alpine.store('resultPreview', {
        open: false,
        loading: false,
        downloading: false,
        sending: false,
        error: null,
        sendMessage: null,
        tab: 'details',
        cardNumber: '',
        detailsHtml: '',
        reportCardHtml: '',
        hasScores: true,
        guardianCount: 0,
        lastSentAt: null,
        remarksUrl: '',
        sendUrl: '',
        printUrl: '',
        downloadUrl: '',
        recipients: { student: true, guardians: false },
        zoom: 1,

        async openPreview(previewUrl, remarksUrl, sendUrl, printUrl, downloadUrl, initialTab = 'details') {
            this.open = true;
            this.loading = true;
            this.error = null;
            this.sendMessage = null;
            this.tab = initialTab;
            this.zoom = 1;
            this.remarksUrl = remarksUrl;
            this.sendUrl = sendUrl;
            this.printUrl = printUrl;
            this.downloadUrl = downloadUrl;
            this.recipients = { student: true, guardians: false };

            try {
                const response = await fetch(previewUrl, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error('Failed to load result.');
                }

                const data = await response.json();

                this.cardNumber = data.card_number;
                this.detailsHtml = data.details_html;
                this.reportCardHtml = data.report_card_html;
                this.hasScores = data.has_scores;
                this.guardianCount = data.guardian_count;
                this.lastSentAt = data.last_sent_at;
            } catch (e) {
                this.error = 'Could not load this result. Please try again.';
            } finally {
                this.loading = false;

                // Scheduled after loading flips to false (not before), since
                // the report-card container is hidden via x-show while
                // loading - measuring it before that flag flips reads a
                // zero-size element and the fit calculation silently no-ops.
                if (!this.error && this.tab === 'report-card') {
                    this.scheduleFitToScreen();
                }
            }
        },

        showReportCard() {
            this.tab = 'report-card';
            this.scheduleFitToScreen();
        },

        // Alpine.nextTick() only guarantees the DOM has been updated (e.g.
        // x-html's innerHTML write), not that the browser has laid it out -
        // reading scrollHeight/clientHeight right after can still return 0.
        // Waiting two animation frames after nextTick ensures a real layout
        // pass has happened before fitToScreen() measures anything.
        scheduleFitToScreen() {
            Alpine.nextTick(() => {
                requestAnimationFrame(() => requestAnimationFrame(() => this.fitToScreen()));
            });
        },

        close() {
            this.open = false;
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async saveRemarksFromDom() {
            const teacherRemark = document.getElementById('result-teacher-remark')?.value ?? '';
            const principalRemark = document.getElementById('result-principal-remark')?.value ?? '';

            try {
                const response = await fetch(this.remarksUrl, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ teacher_remark: teacherRemark, principal_remark: principalRemark }),
                });

                if (!response.ok) {
                    throw new Error('Failed to save remarks.');
                }
            } catch (e) {
                this.error = 'Could not save remarks. Please try again.';
            }
        },

        zoomIn() {
            this.zoom = Math.min(2, Math.round((this.zoom + 0.1) * 10) / 10);
        },

        zoomOut() {
            this.zoom = Math.max(0.5, Math.round((this.zoom - 0.1) * 10) / 10);
        },

        zoomReset() {
            this.zoom = 1;
        },

        // Scales the report card down (never up past 100%) so the whole A4
        // page fits inside the visible preview area without scrolling -
        // transform: scale() doesn't affect layout, so scrollHeight/Width on
        // the content wrapper still reflect its natural, unscaled size.
        fitToScreen() {
            const container = document.getElementById('result-report-card-container');
            const content = document.getElementById('result-report-card-content');
            const page = content?.firstElementChild;

            if (!container || !page) {
                return;
            }

            const naturalHeight = page.scrollHeight;
            const naturalWidth = page.scrollWidth;

            if (!naturalHeight || !naturalWidth) {
                return;
            }

            const availableHeight = container.clientHeight - 16;
            const availableWidth = container.clientWidth - 16;
            const fit = Math.min(availableHeight / naturalHeight, availableWidth / naturalWidth, 1);

            this.zoom = Math.max(0.3, Math.round(fit * 100) / 100);
        },

        print() {
            this.printDirect(this.printUrl);
        },

        printDirect(url) {
            const iframe = document.getElementById('result-print-frame');

            if (iframe) {
                iframe.src = url;
            }
        },

        async download() {
            this.downloading = true;
            this.error = null;

            const succeeded = await this.downloadDirect(this.downloadUrl, this.cardNumber);

            if (!succeeded) {
                this.error = 'Could not download the PDF. Please try again.';
            }

            this.downloading = false;
        },

        async downloadDirect(url, admissionNumber) {
            try {
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error('Download failed.');
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = objectUrl;
                a.download = `report-card-${admissionNumber}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(objectUrl);

                return true;
            } catch (e) {
                alert('Could not download the PDF. Please try again.');

                return false;
            }
        },

        async confirmSend() {
            const recipients = [];
            if (this.recipients.student) recipients.push('student');
            if (this.recipients.guardians) recipients.push('guardians');

            if (recipients.length === 0) {
                return;
            }

            this.sending = true;
            this.sendMessage = null;

            try {
                const response = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ recipients }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message ?? 'Failed to send.');
                }

                this.sendMessage = data.status;
            } catch (e) {
                this.error = 'Could not send the result. Please try again.';
            } finally {
                this.sending = false;
            }
        },
    });

    window.resultPreviewSaveRemarks = () => Alpine.store('resultPreview').saveRemarksFromDom();
}
