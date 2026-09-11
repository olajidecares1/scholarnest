import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import cbtAttempt from './cbt-attempt';
import { registerDraggableResizable } from './directives/draggable-resizable';
import pageBuilder from './page-builder';
import photoField from './photo-field';
import initPortalInstall from './pwa';
import { generateColorScale, normalizeCssColorToHex } from './color-scale';
import { registerIdCardPreviewStore } from './id-card-preview-modal';
import galleryViewer from './gallery-viewer';
import marqueeList from './marquee-list';
import reportEvidence from './report-evidence';
import { registerResultPreviewStore } from './result-preview-modal';
import initScrollAnimations from './scroll-reveal';
import signaturePad from './signature-pad';
import uploadProgressForm from './upload-progress-form';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.generateColorScale = generateColorScale;
window.normalizeCssColorToHex = normalizeCssColorToHex;

registerDraggableResizable(Alpine);
Alpine.data('cbtAttempt', cbtAttempt);
Alpine.data('pageBuilder', pageBuilder);
Alpine.data('photoField', photoField);
Alpine.data('marqueeList', marqueeList);
Alpine.data('galleryViewer', galleryViewer);
Alpine.data('reportEvidence', reportEvidence);
Alpine.data('uploadProgressForm', uploadProgressForm);
Alpine.data('signaturePad', signaturePad);
registerIdCardPreviewStore(Alpine);
registerResultPreviewStore(Alpine);

Alpine.start();

/*
 * Scroll reveals, count-ups, the navbar's scrolled state and the parallax
 * drift on the public school website.
 *
 * After Alpine, and guarded on readyState, because the observer measures what
 * is on screen - and measuring before the document has finished parsing means
 * observing a fraction of the elements that will exist a moment later.
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollAnimations);
} else {
    initScrollAnimations();
}

/*
 * Installing a school portal.
 *
 * Only ever does anything on a page that declared itself installable - see
 * the x-pwa component, which writes the per-school settings this reads. On
 * every other page it returns immediately.
 */
initPortalInstall();
