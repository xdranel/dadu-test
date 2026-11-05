import test from 'node:test';
import assert from 'node:assert/strict';
import { sidangTaDocumentPreview } from '../../resources/js/sidang-ta-wizard.js';

const setup = () => {
    const state = sidangTaDocumentPreview();
    const calls = [];
    state.$refs = { dialog: {
            open: false,
            showModal() { this.open = true; calls.push('show'); },
            close() { this.open = false; calls.push('close'); },
        } };
    return { state, calls };
};

test('preview opens a PDF using server supplied routes and a literal filename', () => {
    const { state, calls } = setup();
    const data = { previewUrl: '/documents/1/preview', downloadUrl: '/documents/1/download',
        fileName: 'nama " <img>.pdf', fileType: 'PDF' };
    state.open(data);
    assert.equal(state.previewUrl, data.previewUrl);
    assert.equal(state.downloadUrl, data.downloadUrl);
    assert.equal(state.fileName, data.fileName);
    assert.equal(state.isPdf, true);
    assert.deepEqual(calls, ['show']);
});

test('preview switches from PDF to image without reopening an open dialog', () => {
    const { state, calls } = setup();
    state.open({ previewUrl: '/pdf/preview', downloadUrl: '/pdf/download', fileName: 'a.pdf', fileType: 'pdf' });
    state.open({ previewUrl: '/image/preview', downloadUrl: '/image/download', fileName: 'a.png', fileType: 'png' });
    assert.equal(state.previewUrl, '/image/preview');
    assert.equal(state.downloadUrl, '/image/download');
    assert.equal(state.isPdf, false);
    assert.deepEqual(calls, ['show']);
});

test('preview close requests native close and close event reset removes viewer URLs', () => {
    const { state, calls } = setup();
    state.open({ previewUrl: '/preview', downloadUrl: '/download', fileName: 'a.pdf', fileType: 'pdf' });
    state.close();
    assert.deepEqual(calls, ['show', 'close']);
    // Native event close memanggil reset melalui x-on:close pada partial.
    state.reset();
    assert.equal(state.previewUrl, '');
    assert.equal(state.downloadUrl, '');
    assert.equal(state.fileName, '');
    assert.equal(state.isPdf, false);
});

test('guide image reuses the document dialog without a download URL or extra opening', () => {
    const { state, calls } = setup();
    state.open({ previewUrl: '/documents/1/preview', downloadUrl: '/documents/1/download', fileName: 'a.pdf', fileType: 'pdf' });
    state.open({ previewUrl: '/assets/images/forms/sidang-ta/ta-data.png', downloadUrl: '',
        fileName: 'Contoh Data TA', fileType: 'png' });
    assert.equal(state.previewUrl, '/assets/images/forms/sidang-ta/ta-data.png');
    assert.equal(state.downloadUrl, '');
    assert.equal(state.fileName, 'Contoh Data TA');
    assert.equal(state.isPdf, false);
    assert.deepEqual(calls, ['show']);
    state.close();
    state.reset();
    assert.equal(state.previewUrl, '');
    assert.equal(state.downloadUrl, '');
});
