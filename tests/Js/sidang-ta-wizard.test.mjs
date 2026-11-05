import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import sidangTaWizard, { sidangTaUpload } from '../../resources/js/sidang-ta-wizard.js';

const originalDataTransfer = globalThis.DataTransfer;
before(() => {
    globalThis.DataTransfer = class {
        constructor() {
            this.files = [];
            this.items = { add: (file) => this.files.push(file) };
        }
    };
});
after(() => {
    if (originalDataTransfer === undefined) delete globalThis.DataTransfer;
    else globalThis.DataTransfer = originalDataTransfer;
});

function uploadState() {
    const events = [];
    const input = {
        files: [], disabled: false,
        set value(value) { if (value === '') this.files = []; },
    };
    const state = sidangTaUpload();
    state.$refs = { file: input };
    state.$dispatch = (name) => events.push(name);
    return { state, input, events };
}

test('one dropped file reaches multipart input and marks the draft dirty', () => {
    const { state, input, events } = uploadState();
    const file = { name: 'capture.pdf', size: 1024 * 1024 };
    state.drop({ dataTransfer: { files: [file] } });
    assert.equal(input.files[0], file);
    assert.equal(state.fileName, 'capture.pdf');
    assert.equal(state.fileSize, '1.00 MB');
    assert.equal(state.localError, '');
    assert.deepEqual(events, ['sidang-ta-file-changed']);
});

test('multiple dropped files preserve the previous valid selection', () => {
    const { state, input, events } = uploadState();
    const first = { name: 'previous.pdf', size: 50 };
    input.files = [first];
    state.picked();
    state.drop({ dataTransfer: { files: [{ name: 'a.pdf' }, { name: 'b.pdf' }] } });
    assert.equal(input.files[0], first);
    assert.equal(state.fileName, 'previous.pdf');
    assert.match(state.localError, /tepat satu berkas/);
    assert.equal(events.length, 1);
});

test('non-file drop does not erase the previous selection', () => {
    const { state, input } = uploadState();
    const first = { name: 'previous.pdf', size: 50 };
    input.files = [first];
    state.picked();
    state.drop({ dataTransfer: { files: [] } });
    assert.equal(input.files[0], first);
    assert.equal(state.fileName, 'previous.pdf');
    assert.equal(state.hasError(), true);
});

test('cancel clears only the browser selection and dispatches dirty state', () => {
    const { state, input, events } = uploadState();
    input.files = [{ name: 'replacement.pdf', size: 100 }];
    state.picked();
    state.clear();
    assert.equal(input.files.length, 0);
    assert.equal(state.fileName, '');
    assert.equal(state.fileSize, '');
    assert.deepEqual(events, ['sidang-ta-file-changed', 'sidang-ta-file-changed']);
});

test('inactive branch ignores a drop and does not mark dirty', () => {
    const { state, input, events } = uploadState();
    input.disabled = true;
    state.drop({ dataTransfer: { files: [{ name: 'hidden.pdf', size: 100 }] } });
    state.clear();
    assert.equal(input.files.length, 0);
    assert.equal(state.fileName, '');
    assert.deepEqual(events, []);
});

test('unsupported DataTransfer retains the previous selection and offers file picker', () => {
    const { state, input, events } = uploadState();
    const first = { name: 'previous.pdf', size: 100 };
    input.files = [first];
    state.picked();
    const previous = globalThis.DataTransfer;
    try {
        globalThis.DataTransfer = class { constructor() { throw new Error('Unsupported'); } };
        state.drop({ dataTransfer: { files: [{ name: 'replacement.pdf', size: 100 }] } });
        assert.equal(input.files[0], first);
        assert.equal(state.fileName, 'previous.pdf');
        assert.match(state.localError, /Klik kotak/);
        assert.equal(events.length, 1);
    } finally {
        globalThis.DataTransfer = previous;
    }
});

test('file picker updates filename without claiming that the file is saved', () => {
    const { state, input, events } = uploadState();
    input.files = [{ name: 'picked.pdf', size: 1024 }];
    state.serverError = true;
    state.picked();
    assert.equal(state.fileName, 'picked.pdf');
    assert.equal(state.selectionChanged, true);
    assert.equal(state.hasError(), false);
    assert.deepEqual(events, ['sidang-ta-file-changed']);
});

test('leaving unsaved changes requires one confirmation before navigation', () => {
    const originalWindow = globalThis.window;
    try {
        let confirmations = 0;
        let prevented = 0;
        const state = sidangTaWizard({ dirty: true });
        globalThis.window = { confirm: () => { confirmations++; return false; } };
        state.leave({ preventDefault: () => prevented++ });
        assert.equal(state.dirty, true);
        assert.equal(prevented, 1);
        globalThis.window.confirm = () => { confirmations++; return true; };
        state.leave({ preventDefault: () => prevented++ });
        state.leave({ preventDefault: () => prevented++ });
        assert.equal(state.dirty, false);
        assert.equal(confirmations, 2);
        assert.equal(prevented, 1);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});
