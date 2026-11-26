import './bootstrap';
import Alpine from 'alpinejs';
import roomBookingDashboard from './room-booking-dashboard';
import sidangTaPlottingRooms from './sidang-ta-plotting-rooms';
import sidangTaWizard, { sidangTaUpload, sidangTaDocumentPreview, sidangTaPeriodCountdown, sidangTaConfirmation } from './sidang-ta-wizard';

window.Alpine = Alpine;
Alpine.data('roomBookingDashboard', roomBookingDashboard);
Alpine.data('sidangTaPlottingRooms', sidangTaPlottingRooms);
Alpine.data('sidangTaWizard', sidangTaWizard);
Alpine.data('sidangTaConfirmation', sidangTaConfirmation);
Alpine.data('sidangTaPeriodCountdown', sidangTaPeriodCountdown);
Alpine.data('sidangTaUpload', sidangTaUpload);
Alpine.data('sidangTaDocumentPreview', sidangTaDocumentPreview);
Alpine.start();
