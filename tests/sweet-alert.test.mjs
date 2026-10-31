import assert from 'node:assert/strict';
import { after, mock, test } from 'node:test';

const fire = mock.fn();
mock.module('sweetalert2', {
    defaultExport: { fire, stopTimer() {}, resumeTimer() {} },
});
const { confirmAction, showNotification } =
    await import('../resources/js/lib/sweet-alert.ts');
const previousDocument = globalThis.document;
const body = {};
globalThis.document = { body, querySelector: () => null };
after(() => {
    globalThis.document = previousDocument;
});

void test('cancel leaves the operation unconfirmed and a later confirmation can proceed', async () => {
    fire.mock.mockImplementationOnce(async () => ({ isConfirmed: false }));
    assert.equal(await confirmAction('Cancel?', 'Order #42'), false);
    fire.mock.mockImplementationOnce(async () => ({ isConfirmed: true }));

    assert.equal(await confirmAction('Proceed?', 'Order #42'), true);
});

void test('confirmation stays inside an open form dialog and treats record names as text', async () => {
    const dialog = {};
    globalThis.document.querySelector = () => dialog;
    fire.mock.mockImplementationOnce(async () => ({ isConfirmed: true }));

    await confirmAction('<img src=x>', 'Payout #42', true);

    const options = fire.mock.calls.at(-1).arguments[0];
    assert.equal(options.target, dialog);
    assert.equal(options.titleText, '<img src=x>');
    assert.equal(options.html, undefined);
    assert.equal(options.icon, 'warning');
    assert.equal(options.focusCancel, true);
    globalThis.document.querySelector = () => null;
});

void test('a second click cannot replace a pending confirmation', async () => {
    const popup = Promise.withResolvers();
    fire.mock.mockImplementationOnce(() => popup.promise);
    const first = confirmAction('Proceed?', 'Order #42');

    assert.equal(await confirmAction('Duplicate?', 'Order #42'), false);
    popup.resolve({ isConfirmed: true });
    assert.equal(await first, true);
});

void test('a popup failure does not permanently block subsequent actions', async () => {
    fire.mock.mockImplementationOnce(async () => {
        throw new Error('Popup unavailable');
    });
    await assert.rejects(
        confirmAction('Proceed?', 'Order #42'),
        /Popup unavailable/,
    );
    fire.mock.mockImplementationOnce(async () => ({ isConfirmed: true }));

    assert.equal(await confirmAction('Retry?', 'Order #42'), true);
});

void test('error notifications preserve server text and remain visible longer', async () => {
    fire.mock.mockImplementationOnce(async () => ({}));

    await showNotification({
        type: 'error',
        message: '<script>record</script>',
    });

    const options = fire.mock.calls.at(-1).arguments[0];
    assert.equal(options.toast, true);
    assert.equal(options.icon, 'error');
    assert.equal(options.titleText, '<script>record</script>');
    assert.equal(options.timer, 7000);
    assert.equal(options.showConfirmButton, false);
});
