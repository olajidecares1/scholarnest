{{-- What a school sees when it reaches a feature its plan does not include.

     The thing this replaces was `403 CBT requires the Standard or Exclusive
     plan.` on a white page with no links on it. That is not a refusal, it is a
     dead end: it tells a paying customer they have done something wrong and
     then abandons them there.

     The rules this page keeps to:
       - name the feature they actually clicked, not a route or a plan tier
       - say what it would have done for them, so the refusal carries an offer
       - name the plan that includes it
       - ALWAYS show a way back to the dashboard, in every state
       - never look like something broke --}}
@php
    use App\Enums\UserRole;

    $user = auth()->user();
    $isSchoolAdmin = $user?->role === UserRole::SchoolAdmin;

    // The way home differs by who hit the wall. A teacher has no subscription
    // page to be sent to and no authority to buy anything, so they are pointed
    // back to their own dashboard and told who can do something about it.
    $staff = auth('staff')->user();
    $dashboardRoute = $staff
        ? route('staff.dashboard', $staff->school)
        : route('dashboard');
@endphp

<x-plan-restricted-layout :title="$feature->label().' is not included in your plan'">
    <div class="w-full max-w-xl">
        <div class="restricted-card overflow-hidden rounded-[14px] border border-[#E1E8F2] bg-white shadow-[0_18px_50px_-24px_rgba(15,42,92,0.35)]">

            {{-- The band carries the feature's own icon - the same one on the
                 card they clicked - so the page reads as an answer to what
                 they just did rather than a generic wall. --}}
            <div class="relative overflow-hidden bg-white px-6 pb-6 pt-8 text-center sm:px-10">
                <div class="restricted-glow pointer-events-none absolute inset-x-0 -top-24 mx-auto h-48 w-48 rounded-full bg-primary-500/10 blur-3xl"></div>

                <span class="restricted-badge relative mx-auto flex h-16 w-16 items-center justify-center rounded-[18px] bg-white text-primary-500 shadow-[0_8px_24px_-8px_rgba(24,119,242,0.5)]">
                    <i class="fa-solid {{ $feature->icon() }} text-[26px]"></i>
                    <span class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full bg-amber-400 text-[11px] text-white shadow-sm">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                </span>

                <p class="restricted-line relative mt-5 text-[11px] font-bold uppercase tracking-[0.14em] text-primary-600" style="--delay: 60ms">
                    {{ $feature->requiredPlanLabel() }} feature
                </p>

                <h1 class="restricted-line relative mt-2 text-[22px] font-extrabold leading-tight tracking-tight text-[#0F2A5C] sm:text-[26px]" style="--delay: 120ms">
                    {{ $feature->label() }} is not part of your plan
                </h1>

                <p class="restricted-line relative mx-auto mt-2.5 max-w-md text-[13px] leading-[1.65] text-[#5B7099]" style="--delay: 180ms">
                    {{ $feature->blurb() }}
                </p>
            </div>

            <div class="px-6 pb-7 pt-6 sm:px-10">
                <div class="restricted-line rounded-[10px] border border-[#E1E8F2] bg-[#F7FAFF] p-4" style="--delay: 240ms">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[12px] text-primary-600">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[13px] font-bold text-[#0F2A5C]">
                                Your school is on the {{ $currentPlanName }}
                            </p>
                            <p class="mt-1 text-[12px] leading-[1.6] text-[#5B7099]">
                                @if ($isSchoolAdmin)
                                    Everything included in your current plan keeps working exactly as it does today.
                                    Upgrading adds {{ $feature->label() }} without disturbing your students, staff or results.
                                @else
                                    Everything your plan includes is still available to you. Only your School Administrator
                                    can change the school&rsquo;s plan &mdash; ask them if you need {{ $feature->label() }}.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Two ways out, and the way back to the dashboard is one of
                     them on every path through this page. A school admin also
                     gets the upgrade route, because for them the refusal has an
                     answer; a teacher does not, because for them it would be a
                     button that leads somewhere they cannot act. --}}
                <div class="restricted-line mt-5 flex flex-col gap-2.5 sm:flex-row" style="--delay: 300ms">
                    @if ($isSchoolAdmin)
                        <a
                            href="{{ route('subscriptions.choose-plan') }}"
                            class="restricted-cta flex h-[42px] flex-1 items-center justify-center gap-2 rounded-[10px] bg-primary-500 text-[13px] font-bold text-white shadow-[0_10px_24px_-12px_rgba(24,119,242,0.9)] transition hover:bg-primary-600"
                        >
                            <i class="fa-solid fa-arrow-up-right-dots text-[12px]"></i>
                            View Plans &amp; Upgrade
                        </a>
                    @endif

                    <a
                        href="{{ $dashboardRoute }}"
                        @class([
                            'flex h-[42px] items-center justify-center gap-2 rounded-[10px] border border-[#D9E3F2] bg-white text-[13px] font-bold text-[#0F2A5C] transition hover:border-[#B9CCE8] hover:bg-[#F7FAFF]',
                            'flex-1' => $isSchoolAdmin,
                            'w-full' => ! $isSchoolAdmin,
                        ])
                    >
                        <i class="fa-solid fa-arrow-left text-[12px]"></i>
                        Return to Dashboard
                    </a>
                </div>

                @if ($isSchoolAdmin)
                    <p class="restricted-line mt-4 text-center text-[11.5px] text-[#8194B3]" style="--delay: 360ms">
                        Not sure which plan you need?
                        <a href="{{ route('support-tickets.create') }}" class="font-semibold text-primary-500 hover:underline">Talk to support</a>
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-plan-restricted-layout>
