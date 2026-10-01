{{--
    Shown by the service worker when a page is asked for and the device has no
    network. Deliberately self-contained: no @vite, no fonts, no images. It is
    served from the cache, and anything it referenced would have to be cached
    too, which is how an offline page ends up rendering as unstyled text on
    the one occasion it matters.

    It is shown only for a page that has not been kept on this device; every
    page that has been is served from the device instead. It asks the service
    worker which pages are kept, and lists them, so the person can carry on.
--}}
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>No connection</title>
        <style>
            :root { color-scheme: light dark; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f8fafc;
                color: #0f172a;
            }
            @media (prefers-color-scheme: dark) {
                body { background: #0f172a; color: #e2e8f0; }
                .card { background: #1e293b !important; border-color: #334155 !important; }
                .hint { color: #94a3b8 !important; }
            }
            .card {
                width: 100%;
                max-width: 360px;
                text-align: center;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 32px 24px;
            }
            .mark {
                width: 56px;
                height: 56px;
                margin: 0 auto 18px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(79, 70, 229, 0.12);
                font-size: 26px;
            }
            h1 { margin: 0 0 8px; font-size: 19px; font-weight: 800; }
            p { margin: 0; font-size: 13.5px; line-height: 1.55; }
            .hint { color: #64748b; }
            button {
                margin-top: 22px;
                width: 100%;
                padding: 12px 16px;
                font-size: 13.5px;
                font-weight: 700;
                color: #ffffff;
                background: #4f46e5;
                border: 0;
                border-radius: 9px;
                cursor: pointer;
            }
            button:active { background: #4338ca; }
            #pending { margin-top: 10px; font-weight: 600; color: #b45309; }
            .saved { list-style: none; margin: 16px 0 0; padding: 0; text-align: left; max-height: 45vh; overflow: auto; }
            .saved li { border-top: 1px solid rgba(100, 116, 139, 0.25); }
            .saved a { display: block; padding: 10px 4px; color: #4f46e5; font-weight: 600; font-size: 13.5px; text-decoration: none; }
            .card { max-width: 420px !important; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="mark" aria-hidden="true">&#128246;</div>
            <h1>You&rsquo;re offline</h1>
            <p class="hint" id="lead">
                This page has not been saved on this device yet. Nothing has been lost &mdash;
                open one of your saved pages below, or reconnect and try again.
            </p>
            <p class="hint" id="pending" hidden></p>
            <ul class="saved" id="saved" hidden></ul>
            <button type="button" onclick="location.reload()">Try again</button>
        </div>

        <script>
            // Reload by itself the moment the device is back, so nobody is left
            // looking at this page after the connection returns.
            window.addEventListener('online', () => location.reload());

            // The pages kept on this device for the account in use, from the
            // service worker, so the person can carry on with any of them.
            (function () {
                var controller = navigator.serviceWorker && navigator.serviceWorker.controller;

                if (!controller) {
                    return;
                }

                var channel = new MessageChannel();
                channel.port1.onmessage = function (event) {
                    var list = (event.data || []).filter(function (page) {
                        return page.url && page.url.indexOf('/offline') === -1;
                    });

                    if (!list.length) {
                        return;
                    }

                    list.sort(function (a, b) { return a.title.localeCompare(b.title); });

                    var ul = document.getElementById('saved');
                    list.slice(0, 60).forEach(function (page) {
                        var li = document.createElement('li');
                        var a = document.createElement('a');
                        a.href = page.url;
                        a.textContent = page.title;
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                    ul.hidden = false;
                    document.getElementById('lead').textContent = 'This page has not been saved on this device yet. These pages are available offline:';
                };
                controller.postMessage({ type: 'saved-pages' }, [channel.port2]);

                try {
                    var open = indexedDB.open('akademicnest-offline');
                    open.onsuccess = function () {
                        var db = open.result;

                        if (!db.objectStoreNames.contains('queue')) {
                            return;
                        }

                        var count = db.transaction('queue', 'readonly').objectStore('queue').count();
                        count.onsuccess = function () {
                            if (count.result > 0) {
                                var p = document.getElementById('pending');
                                p.textContent = count.result + ' change' + (count.result === 1 ? ' is' : 's are') + ' saved on this device and will be sent when you reconnect.';
                                p.hidden = false;
                            }
                        };
                    };
                } catch (e) {}
            })();
        </script>
    </body>
</html>
