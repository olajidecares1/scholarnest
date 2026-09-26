{{-- The panel beside the page canvas, for the block currently selected.

     EVERY CONTROL SAYS WHAT IT DOES. These are typographic and CSS terms,
     letter spacing, line height, text transform, opacity, blur, and a school
     administrator is not obliged to know them. Each one is described by what a
     visitor to the website will see, not by the property it sets. --}}
<div class="rounded-[10px] border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
    <template x-if="! selectedBlock">
        <small class="field-hint">Click any text, button, or card in the preview to edit it. Double-click text to type directly.</small>
    </template>

    <template x-if="selectedBlock">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200" x-text="selectedBlock.type + ' block'"></h4>
                <div class="flex items-center gap-2">
                    <button type="button" @click="removeBlock(selectedBlock.section, selectedBlock.uuid)" class="text-xs font-semibold text-red-500 hover:text-red-700">Delete</button>
                    <button type="button" @click="deselect()" class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Close</button>
                </div>
            </div>

            <div>
                <label class="field-label">Content</label>
                <input type="text" x-model="selectedBlock.content" class="mt-1 w-full">
                <small class="field-hint mt-1">The words visitors will read. Changes show in the preview as you type.</small>
            </div>

            <template x-if="selectedBlock.type === 'card'">
                <div>
                    <label class="field-label">Description (optional)</label>
                    <input type="text" x-model="selectedBlock.secondary_content" class="mt-1 w-full">
                    <small class="field-hint mt-1">A second, smaller line under the card's title. Leave blank for a title on its own.</small>
                </div>
            </template>

            <template x-if="selectedBlock.type === 'button'">
                <div>
                    <label class="field-label">Link URL</label>
                    <input type="text" x-model="selectedBlock.url" placeholder="#contact" class="mt-1 w-full">
                    <small class="field-hint mt-1">Where the button takes a visitor. Start with # to scroll to a section of the same page (#contact, #about), or paste a full web address.</small>
                </div>
            </template>

            {{-- Typography --}}
            <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Typography</p>
                <small class="field-hint">How this one block's text is set. It does not affect the rest of your website &mdash; the typeface for the whole site is on the Home tab.</small>

                <div>
                    <label class="field-label">Font Family</label>
                    <select x-model="selectedBlock.style.font_family" @change="ensureFontLoaded(selectedBlock.style.font_family)" class="mt-1 w-full">
                        <template x-for="family in Object.keys(fontOptions)" :key="family">
                            <option :value="family" x-text="family"></option>
                        </template>
                    </select>
                    <small class="field-hint mt-1">The typeface for this block. Each different typeface on a page adds a little to how long it takes to load, so two or three across a page is plenty.</small>
                </div>

                <div>
                    <label class="field-label">Font Weight</label>
                    <select x-model="selectedBlock.style.font_weight" class="mt-1 w-full">
                        <template x-for="weight in weightsFor(selectedBlock.style.font_family)" :key="weight">
                            <option :value="String(weight)" x-text="weight"></option>
                        </template>
                    </select>
                    <small class="field-hint mt-1">How thick the letters are &mdash; 400 is normal, 700 is bold. Only the weights this typeface actually has are listed.</small>
                </div>

                <div>
                    <label class="field-label">Font Size: <span x-text="selectedBlock.style.font_size"></span>px</label>
                    <input type="range" min="10" max="96" x-model.number="selectedBlock.style.font_size" class="mt-1 w-full">
                    <small class="field-hint mt-1">How large the text is. Anything under about 14 is hard to read on a phone.</small>
                </div>

                <div>
                    <label class="field-label">Text Color</label>
                    <input type="color" x-model="selectedBlock.style.color" class="mt-1 w-full">
                    <small class="field-hint mt-1">The colour of the text. Check it against what sits behind it &mdash; pale text on a pale background is unreadable for many visitors.</small>
                </div>

                <div>
                    <label class="field-label">Text Align</label>
                    <div class="mt-1 flex gap-1">
                        <template x-for="option in ['left', 'center', 'right']" :key="option">
                            <button type="button" @click="selectedBlock.style.align = option" :class="selectedBlock.style.align === option ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 dark:bg-gray-700 dark:text-gray-300'" class="flex-1 rounded-[6px] border border-gray-300 py-1.5 text-xs font-semibold capitalize dark:border-gray-600" x-text="option"></button>
                        </template>
                    </div>
                    <small class="field-hint mt-1">Which edge the lines line up against. Left is easiest to read for a paragraph; centre suits a short heading.</small>
                </div>

                <div>
                    <label class="field-label">Letter Spacing: <span x-text="selectedBlock.style.letter_spacing"></span>px</label>
                    <input type="range" min="-2" max="10" step="0.5" x-model.number="selectedBlock.style.letter_spacing" class="mt-1 w-full">
                    <small class="field-hint mt-1">The gap between individual letters. A little can make a short capitalised heading look deliberate; too much makes a sentence hard to read.</small>
                </div>

                <div>
                    <label class="field-label">Line Height: <span x-text="selectedBlock.style.line_height"></span></label>
                    <input type="range" min="0.8" max="2.2" step="0.05" x-model.number="selectedBlock.style.line_height" class="mt-1 w-full">
                    <small class="field-hint mt-1">The gap between lines of a paragraph. Around 1.5 is comfortable for body text; headings can sit tighter.</small>
                </div>

                <div>
                    <label class="field-label">Text Transform</label>
                    <select x-model="selectedBlock.style.text_transform" class="mt-1 w-full">
                        <option value="none">None</option>
                        <option value="uppercase">UPPERCASE</option>
                        <option value="lowercase">lowercase</option>
                        <option value="capitalize">Capitalize</option>
                    </select>
                    <small class="field-hint mt-1">Changes how the text is displayed without changing what you typed, so you can switch it back at any time.</small>
                </div>
            </div>

            {{-- Button style --}}
            <template x-if="selectedBlock.type === 'button'">
                <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Button Style</p>
                    <small class="field-hint">The shape and colour of the button itself, behind the text.</small>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="field-label">Background</label>
                            <input type="color" x-model="selectedBlock.style.bg_color" class="mt-1 w-full">
                            <small class="field-hint mt-1">The button's fill colour.</small>
                        </div>
                        <div>
                            <label class="field-label">Hover Background</label>
                            <input type="color" x-model="selectedBlock.style.hover_bg_color" class="mt-1 w-full">
                            <small class="field-hint mt-1">The colour it changes to when a visitor points at it &mdash; a sign that it can be clicked.</small>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Border Width: <span x-text="selectedBlock.style.border_width"></span>px</label>
                        <input type="range" min="0" max="6" x-model.number="selectedBlock.style.border_width" class="mt-1 w-full">
                        <small class="field-hint mt-1">How thick the outline around the button is. Set it to 0 for no outline.</small>
                    </div>
                    <div>
                        <label class="field-label">Border Color</label>
                        <input type="color" x-model="selectedBlock.style.border_color" class="mt-1 w-full">
                        <small class="field-hint mt-1">The colour of that outline. Only visible while the width above is more than 0.</small>
                    </div>

                    <div>
                        <label class="field-label">Corner Radius: <span x-text="selectedBlock.style.radius"></span>px</label>
                        <input type="range" min="0" max="999" x-model.number="selectedBlock.style.radius" class="mt-1 w-full">
                        <small class="field-hint mt-1">How rounded the corners are. 0 gives square corners; the far end of the slider gives a fully rounded pill.</small>
                    </div>

                    <div>
                        <label class="field-label">Shadow</label>
                        <select x-model="selectedBlock.style.shadow" class="mt-1 w-full">
                            <option value="none">None</option>
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                            <option value="xl">Extra Large</option>
                        </select>
                        <small class="field-hint mt-1">A soft shadow underneath, which makes the button look raised off the page.</small>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="field-label">Padding X: <span x-text="selectedBlock.style.padding_x"></span>px</label>
                            <input type="range" min="0" max="60" x-model.number="selectedBlock.style.padding_x" class="mt-1 w-full">
                            <small class="field-hint mt-1">Space to the left and right of the text, inside the button.</small>
                        </div>
                        <div>
                            <label class="field-label">Padding Y: <span x-text="selectedBlock.style.padding_y"></span>px</label>
                            <input type="range" min="0" max="40" x-model.number="selectedBlock.style.padding_y" class="mt-1 w-full">
                            <small class="field-hint mt-1">Space above and below the text. Keep the button tall enough to tap comfortably on a phone.</small>
                        </div>
                    </div>
                    <small class="block text-[11px] text-gray-500 dark:text-gray-400">Drag this button's edges/corners on the preview to resize its box.</small>
                </div>
            </template>

            {{-- Card style --}}
            <template x-if="selectedBlock.type === 'card'">
                <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Card Style</p>
                    <small class="field-hint">The panel the card's text sits on.</small>

                    <div>
                        <label class="field-label">Background Color</label>
                        <input type="color" x-model="selectedBlock.style.bg_color" class="mt-1 w-full">
                        <small class="field-hint mt-1">The card's fill colour.</small>
                    </div>
                    <div>
                        <label class="field-label">Background Opacity: <span x-text="selectedBlock.style.bg_opacity"></span>%</label>
                        <input type="range" min="0" max="100" x-model.number="selectedBlock.style.bg_opacity" class="mt-1 w-full">
                        <small class="field-hint mt-1">How solid that fill is. 100% hides whatever is behind the card; lower values let a background photograph show through.</small>
                    </div>

                    <div>
                        <label class="field-label">Blur</label>
                        <select x-model="selectedBlock.style.blur" class="mt-1 w-full">
                            <option value="none">None</option>
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                            <option value="xl">Extra Large</option>
                        </select>
                        <small class="field-hint mt-1">Softens whatever shows through the card, so the text stays readable over a busy photograph. Only has an effect while the opacity above is below 100%.</small>
                    </div>

                    <div>
                        <label class="field-label">Border Color</label>
                        <input type="color" x-model="selectedBlock.style.border_color" class="mt-1 w-full">
                        <small class="field-hint mt-1">The colour of the card's outline. Only visible while the width below is more than 0.</small>
                    </div>
                    <div>
                        <label class="field-label">Border Width: <span x-text="selectedBlock.style.border_width"></span>px</label>
                        <input type="range" min="0" max="6" x-model.number="selectedBlock.style.border_width" class="mt-1 w-full">
                        <small class="field-hint mt-1">How thick that outline is. Set it to 0 for no outline.</small>
                    </div>

                    <div>
                        <label class="field-label">Corner Radius: <span x-text="selectedBlock.style.radius"></span>px</label>
                        <input type="range" min="0" max="48" x-model.number="selectedBlock.style.radius" class="mt-1 w-full">
                        <small class="field-hint mt-1">How rounded the card's corners are. 0 gives square corners.</small>
                    </div>

                    <div>
                        <label class="field-label">Shadow</label>
                        <select x-model="selectedBlock.style.shadow" class="mt-1 w-full">
                            <option value="none">None</option>
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                            <option value="xl">Extra Large</option>
                        </select>
                        <small class="field-hint mt-1">A soft shadow underneath, which lifts the card off the page behind it.</small>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
