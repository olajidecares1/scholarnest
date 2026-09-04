{{-- The trap field for RejectBotSubmissions.

     Hidden three ways over, because one is not enough: off-screen rather than
     display:none (which some scripts specifically skip), tabindex -1 so it
     cannot be reached by keyboard, aria-hidden so a screen reader never
     announces it, and autocomplete off so a browser never fills it in on a
     person's behalf.

     Anything that fills this in was not a person. --}}
<div aria-hidden="true" style="position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden;">
    <label for="{{ \App\Http\Middleware\RejectBotSubmissions::FIELD }}">Leave this field blank</label>
    <input
        type="text"
        id="{{ \App\Http\Middleware\RejectBotSubmissions::FIELD }}"
        name="{{ \App\Http\Middleware\RejectBotSubmissions::FIELD }}"
        value=""
        tabindex="-1"
        autocomplete="off"
    >
</div>
