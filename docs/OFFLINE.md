# Using AkademicNest offline

Teachers, students, parents and school administrators can keep using their
portal when mobile data or Wi-Fi is off. This covers Android phones, iPhones,
tablets, iPads, laptops and desktops, in the browser or as the installed app.

## How it works

| Part | Where | What it does |
| --- | --- | --- |
| Service worker | `resources/views/pwa/service-worker.blade.php` (served at `/sw.js`) | Stores CSS, JS, fonts and icons when it installs. Keeps every signed-in page the person opens, network first, so a page opens from the device when there is no connection. After sign-in it also fetches the pages linked from the portal in the background (two levels deep, up to 150 pages), so pages not opened yet are there too. |
| Offline queue | `resources/js/offline/queue-core.js` | IndexedDB store, shared by the page and the service worker. Keeps every form (files included) and every script write made offline. Survives closing the app, reloading and restarting the phone. |
| Page script | `resources/js/offline/index.js` | Catches forms submitted offline and keeps them instead of failing, does the same for `fetch()` writes (CBT answers, signatures, remarks), syncs when the connection returns, and shows the status pill. |
| Server | `App\Http\Middleware\OfflineSupport`, `App\Support\OfflineScope`, `App\Support\OfflineRoutes` | Labels which pages a device may keep and for which account, makes every queued write idempotent (`offline_sync_receipts`), and answers replayed forms in JSON. |

### Sync

- Kept changes are sent oldest first as soon as the connection is back: on
  reconnect, on opening the app, every 20 seconds while anything is waiting,
  and through Background Sync on Android even with the app closed.
- Each change carries a random key. The server records the key the first time
  and answers any repeat from that record, so a change is never saved twice,
  even if two tabs or the service worker send it at the same time, or the
  phone drops out half way through a request. The same key also stops a
  double-tapped Save button online.
- A change is only ever sent for the account that made it. If that account is
  signed out, it waits and is sent after the next sign-in.
- If the server refuses a change (for example a field fails validation), it is
  kept and shown in red. "Fix and resend" reopens the form with what was typed
  filled back in.

### Status the person sees

A small pill in the bottom-left corner shows:

- **Offline · you can keep working** / **Offline · N changes saved on this device**
- **Syncing N changes…**
- **All offline changes synced** (and a toast saying how many were saved)
- **N changes could not be saved · tap to review** (Fix and resend, Try again, Discard)
- **Sign in to send N saved changes**
- **saved copy from 10:42**, when a page is the copy kept on the device

### The idle timeout

Portals sign out after three idle minutes. While offline the browser half of
that timeout does not sign anybody out (there is no server to sign out of). On
reconnecting, the device tells the server how long ago the person last touched
the app (`X-Offline-Resume`); if that is under three minutes the session
resumes so the changes can be sent without signing in again. A device nobody
touched is still signed out as before.

### Privacy on shared devices

Pages are filed under the signed-in account (an HMAC of guard and id, never a
name). Signing out with the Sign out button removes that account's pages and
photos from the device. A page kept for one account is never shown while
another account is in use.

## What needs a live connection

These cannot be done without the server, so offline the person is told so in
place, instead of seeing an error:

| Function | Why | Offline behaviour |
| --- | --- | --- |
| Signing in, password changes and resets | Checked by the server | Message: needs a connection. An account already used on the device keeps its saved pages. |
| Subscription payments, top-ups, checkout | Payment gateway | Nothing is charged; a reminder appears as soon as the device is back online. |
| Unlocking a result with a PIN or token | The result is the server's answer | Message: needs a connection. |
| Downloads and exports generated on demand (PDFs, CSVs, ID cards) | Built by the server | The button fails as before; previously opened pages remain available. |
| CBT document upload | The file is read by the server | The upload is kept and sent automatically when back online, then read. |
| Timed CBT tests | The server keeps the clock | Answers are kept and sent on reconnect; a test that ended while offline is closed by the server as usual. |
| Search boxes | Server search | Unavailable offline. |
| Live data from other people (new results, messages) | Not on the device yet | The last saved copy is shown, marked with when it was saved. |

## Testing

- `tests/Feature/OfflineSupportTest.php` covers labelling per role, idempotent
  writes, replay answers, the session endpoint and the idle resume.
- The end-to-end check used for this feature signs in as each role in
  Chromium with phone and tablet profiles, keeps the portal, cuts the network
  (browser offline and server stopped), opens pages including ones never opened
  online, submits a real form for each role offline, reloads or closes the app,
  reconnects, and checks the database holds each record exactly once.
