import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import cbtAttempt from './cbt-attempt';
import { registerDraggableResizable } from './directives/draggable-resizable';
import pageBuilder from './page-builder';
import { generateColorScale, normalizeCssColorToHex } from './color-scale';
import { registerIdCardPreviewStore } from './id-card-preview-modal';
import { registerResultPreviewStore } from './result-preview-modal';
import uploadProgressForm from './upload-progress-form';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.generateColorScale = generateColorScale;
window.normalizeCssColorToHex = normalizeCssColorToHex;

registerDraggableResizable(Alpine);
Alpine.data('cbtAttempt', cbtAttempt);
Alpine.data('pageBuilder', pageBuilder);
Alpine.data('uploadProgressForm', uploadProgressForm);
registerIdCardPreviewStore(Alpine);
registerResultPreviewStore(Alpine);

Alpine.start();
