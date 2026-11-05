import './bootstrap';
import Alpine from 'alpinejs';
import sidangTaWizard, { sidangTaUpload, sidangTaDocumentPreview } from './sidang-ta-wizard';

window.Alpine = Alpine;
Alpine.data('sidangTaWizard', sidangTaWizard);
Alpine.data('sidangTaUpload', sidangTaUpload);
Alpine.data('sidangTaDocumentPreview', sidangTaDocumentPreview);
Alpine.start();
