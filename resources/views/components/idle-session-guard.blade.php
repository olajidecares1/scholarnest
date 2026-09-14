@props(['logoutUrl'])

{{--
    Frontend half of the 3-minute inactivity timeout (the server-side half
    is App\Http\Middleware\LogsOutIdleUsers, which enforces the same cutoff
    independently of this script). This component only exists to give the
    user a predictable, immediate experience, a warning at 2:30 and a real
    logout form submission (not just a client-side redirect) at 3:00, so the
    session is actually terminated server-side rather than merely hidden.
--}}
<div
    x-data="{
        warningAt: 150,
        timeoutAt: 180,
        idleSeconds: 0,
        showWarning: false,
        tickHandle: null,
        activityEvents: ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'],
        init() {
            this.activityEvents.forEach((evt) => window.addEventListener(evt, () => this.reset(), { passive: true }));
            this.tickHandle = setInterval(() => this.tick(), 1000);
        },
        reset() {
            this.idleSeconds = 0;
            this.showWarning = false;
        },
        tick() {
            this.idleSeconds++;
            if (this.idleSeconds >= this.timeoutAt) {
                this.expire();
            } else if (this.idleSeconds >= this.warningAt) {
                this.showWarning = true;
            }
        },
        expire() {
            clearInterval(this.tickHandle);
            this.$refs.idleLogoutForm.submit();
        },
    }"
>
    <div
        x-show="showWarning"
        x-transition
        style="display: none;"
        class="fixed inset-x-0 bottom-4 z-[100] mx-auto w-[calc(100%-2rem)] max-w-sm rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-lg dark:border-amber-800 dark:bg-amber-900/90"
    >
        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Your session will expire soon due to inactivity.</p>
        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
            You'll be signed out automatically in <span x-text="timeoutAt - idleSeconds"></span>s.
        </p>
        <button
            type="button"
            @click="reset()"
            class="mt-3 rounded-[8px] bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors duration-200 hover:bg-amber-700"
        >
            Stay signed in
        </button>
    </div>

    <form x-ref="idleLogoutForm" method="POST" action="{{ $logoutUrl }}" class="hidden">
        @csrf
    </form>
</div>
