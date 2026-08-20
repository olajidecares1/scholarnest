import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { registerDraggableResizable } from './directives/draggable-resizable';
import pageBuilder from './page-builder';
import { generateColorScale, normalizeCssColorToHex } from './color-scale';
import { registerIdCardPreviewStore } from './id-card-preview-modal';
import { registerResultPreviewStore } from './result-preview-modal';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.generateColorScale = generateColorScale;
window.normalizeCssColorToHex = normalizeCssColorToHex;

registerDraggableResizable(Alpine);
Alpine.data('pageBuilder', pageBuilder);
registerIdCardPreviewStore(Alpine);
registerResultPreviewStore(Alpine);

Alpine.start();
