import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // Design Tokens - Font Scale
            fontSize: {
                // H1 - Page Title
                'page-title': ['24px', { lineHeight: '32px', fontWeight: '700' }],
                // H2 - Section Title
                'section-title': ['18px', { lineHeight: '28px', fontWeight: '600' }],
                // Body - Table & General Text
                'body': ['14px', { lineHeight: '20px', fontWeight: '400' }],
                // Label - Table Headers, Form Labels
                'label': ['12px', { lineHeight: '16px', fontWeight: '500', letterSpacing: '0.025em' }],
                // Caption - Secondary Text
                'caption': ['13px', { lineHeight: '18px', fontWeight: '400' }],
            },
            // Status Color Palette
            colors: {
                status: {
                    // Draft (abu netral) - akan dihapus
                    draft: {
                        bg: '#F1F5F9',
                        text: '#64748B',
                        border: '#CBD5E1',
                        accent: '#94A3B8',
                    },
                    // Diajukan/Menunggu (biru)
                    submitted: {
                        bg: '#DBEAFE',
                        text: '#1E40AF',
                        border: '#BFDBFE',
                        accent: '#3B82F6',
                    },
                    // Perlu Revisi (kuning/oranye)
                    needsReview: {
                        bg: '#FEF3C7',
                        text: '#B45309',
                        border: '#FDE68A',
                        accent: '#F59E0B',
                    },
                    // Diterima/Disetujui (hijau)
                    approved: {
                        bg: '#D1FAE5',
                        text: '#065F46',
                        border: '#A7F3D0',
                        accent: '#10B981',
                    },
                    // Ditolak (merah)
                    rejected: {
                        bg: '#FEE2E2',
                        text: '#991B1B',
                        border: '#FECACA',
                        accent: '#EF4444',
                    },
                    // Dibatalkan (merah muda/merah pudar)
                    cancelled: {
                        bg: '#FCE7F3',
                        text: '#9F1239',
                        border: '#FBCFE8',
                        accent: '#EC4899',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
