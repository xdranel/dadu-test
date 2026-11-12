import * as wizardExports79 from '../../resources/js/sidang-ta-wizard.js';
const sidangTaConfirmation = wizardExports79.sidangTaConfirmation;
import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import sidangTaWizard, { sidangTaUpload, validWhatsapp, periodCountdown, sidangTaPeriodCountdown } from '../../resources/js/sidang-ta-wizard.js';

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


test('PDF upload rejects image and oversize selections and drops', () => {
    const {state,input}=uploadState();
    input.files=[{name:'capture.png',type:'image/png',size:100}]; state.picked();
    assert.match(state.localError,/PDF/); assert.equal(input.files.length,0);
    state.drop({dataTransfer:{files:[{name:'large.pdf',type:'application/pdf',size:11*1024*1024}]}});
    assert.match(state.localError,/5 MB/); assert.equal(input.files.length,0);
});

test('WA validation preserves digit strings and rejects punctuation letters and invalid lengths', () => {
    assert.equal(validWhatsapp('081234567890'),true);
    for (const value of ['+628123456789','08abc12345','1234567','1234567890123456','08 12345678','1e12345678']) assert.equal(validWhatsapp(value),false);
});

test('period countdown follows actual schedule and has no invented empty deadline', () => {
    assert.equal(periodCountdown(null,null,0),null);
    assert.deepEqual(periodCountdown(1000,61000,0),{status:'Belum dibuka',seconds:1});
    assert.deepEqual(periodCountdown(1000,61000,1000),{status:'Aktif',seconds:60});
    assert.deepEqual(periodCountdown(1000,61000,61001),{status:'Ditutup',seconds:0});
});


test('WA submission helper blocks invalid string even when form uses novalidate', () => {
    let reported=0;
    const field={value:'08abc12345',setCustomValidity(value){this.error=value;},reportValidity(){reported++;}};
    const state=sidangTaWizard();
    assert.equal(state.validateContacts({querySelectorAll:()=>[field]}),false);
    assert.match(field.error,/8–15/); assert.equal(reported,1);
    field.value='081234567890';
    assert.equal(state.validateContacts({querySelectorAll:()=>[field]}),true);
    assert.equal(field.error,'');
});


test('live period badge and timer use the same state across open and close transitions', () => {
    const originalNow=Date.now;
    try {
        const state=sidangTaPeriodCountdown(1000,61000);
        Date.now=()=>0; state.tick();
        assert.equal(state.status,'Belum dibuka'); assert.match(state.label,/Belum dibuka/);
        Date.now=()=>1000; state.tick();
        assert.equal(state.status,'Aktif'); assert.match(state.label,/Aktif/);
        Date.now=()=>61001; state.tick();
        assert.equal(state.status,'Ditutup'); assert.equal(state.label,'Ditutup');
    } finally { Date.now=originalNow; }
});

test('Indonesian WA validates prefixes and rejects whitespace or arbitrary numeric strings', () => {
    for (const value of ['08123456','62812345','081234567890123','628123456789012']) assert.equal(validWhatsapp(value),true,value);
    for (const value of ['55555555','620812345678','0712345678','0812345','6281234','0812345678901234','08123456\n',' 08123456','+62812345678']) assert.equal(validWhatsapp(value),false,value);
});

test('confirmation needs explicit consent and disables again on uncheck or sending', () => {
    assert.equal(typeof sidangTaConfirmation, 'function');
    const state=sidangTaConfirmation(false);let prevented=0;
    assert.equal(state.canConfirm,false);state.submit({preventDefault(){prevented++;}});assert.equal(prevented,1);
    state.accepted=true;assert.equal(state.canConfirm,true);
    state.accepted=false;assert.equal(state.canConfirm,false);
    state.accepted=true;state.submit({preventDefault(){prevented++;}});assert.equal(state.sending,true);assert.equal(state.canConfirm,false);
    const blocked=sidangTaConfirmation(true);blocked.accepted=true;assert.equal(blocked.canConfirm,false);
});


test('countdown exposes four numeric units and clears them for unconfigured schedules', () => {
    const originalNow = Date.now;
    try {
        Date.now = () => 0;
        const state = sidangTaPeriodCountdown(1000, 90062000);
        state.tick();
        assert.deepEqual([state.days, state.hours, state.minutes, state.seconds], [0, 0, 0, 1]);
        Date.now = () => 1000; state.tick();
        assert.deepEqual([state.days, state.hours, state.minutes, state.seconds], [1, 1, 1, 1]);
        const empty = sidangTaPeriodCountdown(null, null); empty.tick();
        assert.equal(empty.status, 'Belum diatur');
        assert.deepEqual([empty.days, empty.hours, empty.minutes, empty.seconds], [0, 0, 0, 0]);
    } finally { Date.now = originalNow; }
});

test('changing selected period updates registration and verification schedules together', () => {
    const state = sidangTaWizard();
    state.updatePeriodHint({selectedOptions: [{dataset: {window: 'Registration B', periodId: '2'}}]});
    assert.equal(state.periodHint, 'Registration B');
    assert.equal(state.selectedPeriodId, '2');
    state.updatePeriodHint({selectedOptions: []});
    assert.equal(state.selectedPeriodId, '');
});


test('upload limit accepts five MB and rejects one byte above it', () => {
    const { state, input }=uploadState();
    input.files=[{name:'batas.pdf',type:'application/pdf',size:5*1024*1024}];state.picked();
    assert.equal(state.localError,'');assert.equal(state.fileName,'batas.pdf');
    input.files=[{name:'kelebihan.pdf',type:'application/pdf',size:5*1024*1024+1}];state.picked();
    assert.match(state.localError,/5 MB/);assert.equal(input.files.length,0);
});

test('upload picker uses the size supplied by the server', () => {
    const state=sidangTaUpload({maxSizeMb:3});
    const input={files:[{name:'besar.pdf',type:'application/pdf',size:3*1024*1024+1}],disabled:false,
        set value(value) {if(value==='') this.files=[];}};
    state.$refs={file:input};state.$dispatch=()=>{};state.picked();
    assert.match(state.localError,/3 MB/);assert.equal(input.files.length,0);
});
