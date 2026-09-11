{{--
    Shown by the service worker when a page is asked for and the device has no
    network. Deliberately self-contained: no @vite, no fonts, no images. It is
    served from the cache, and anything it referenced would have to be cached
    too - which is how an offline page ends up rendering as unstyled text on
    the one occasion it matters.

    It says nothing about which school or which portal, because it is one file
    shared by all of them and guessing would be worse than not saying.
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
        </style>
    </head>
    <body>
        <div class="card">
            <div class="mark" aria-hidden="true">&#128246;</div>
            <h1>You&rsquo;re offline</h1>
            <p class="hint">
                This portal needs a connection to show your information. Nothing has been lost &mdash;
                reconnect and try again.
            </p>
            <button type="button" onclick="location.reload()">Try again</button>
        </div>

        <script>
            // Reload by itself the moment the device is back, so nobody is left
            // looking at this page after the connection returns.
            window.addEventListener('online', () => location.reload());
        </script>
    </body>
</html>
