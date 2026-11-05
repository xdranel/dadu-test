import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const moduleUrl = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;

test('app registers upload before start so picked file can immediately update its component', async () => {
    // Jalankan dari root project, seperti command di dokumen ini.
    const entry = await readFile(resolve('resources/js/app.js'), 'utf8');
    const wizard = await readFile(resolve('resources/js/sidang-ta-wizard.js'), 'utf8');
    const providers = new Map();
    let providersAtStart;
    const alpine = {
        data(name, provider) { providers.set(name, provider); },
        start() { providersAtStart = new Map(providers); },
    };
    const previousWindow = globalThis.window;
    const previousAdapter = globalThis.__sidangTaEntryAlpine;
    const adapterUrl = moduleUrl('export default globalThis.__sidangTaEntryAlpine;');
    // Bootstrap Axios tidak terkait registrasi komponen dan membutuhkan window.
    // Entry lainnya tetap dieksekusi; test tidak menambahkan registrasi yang hilang.
    const executable = entry
        .replace(/^\s*import\s+['"]\.\/bootstrap(?:\.js)?['"];?\s*$/m, '')
        .replace(/from\s+(['"])alpinejs\1/g, `from '${adapterUrl}'`)
        .replace(/from\s+(['"])\.\/sidang-ta-wizard(?:\.js)?\1/g, `from '${moduleUrl(wizard)}'`);
    try {
        globalThis.window = {};
        globalThis.__sidangTaEntryAlpine = alpine;
        await import(moduleUrl(executable));
        assert.equal(typeof providersAtStart?.get('sidangTaWizard'), 'function');
        assert.equal(typeof providersAtStart?.get('sidangTaUpload'), 'function',
            'sidangTaUpload harus terdaftar sebelum Alpine.start().');
        assert.equal(typeof providersAtStart?.get('sidangTaDocumentPreview'), 'function',
            'sidangTaDocumentPreview harus terdaftar sebelum Alpine.start().');

        const upload = providersAtStart.get('sidangTaUpload')();
        const events = [];
        const file = { name: 'langsung-terlihat.pdf', size: 1024 * 1024 };
        upload.$refs = { file: { files: [file], disabled: false } };
        upload.$dispatch = (name) => events.push(name);
        upload.picked();
        assert.equal(upload.fileName, 'langsung-terlihat.pdf');
        assert.equal(upload.fileSize, '1.00 MB');
        assert.deepEqual(events, ['sidang-ta-file-changed']);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
        if (previousAdapter === undefined) delete globalThis.__sidangTaEntryAlpine;
        else globalThis.__sidangTaEntryAlpine = previousAdapter;
    }
});
