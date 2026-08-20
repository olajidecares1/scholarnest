<div class="rounded-[10px] border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
    <template x-if="! selectedBlock">
        <p class="text-xs text-gray-500 dark:text-gray-400">Click any text, button, or card in the preview to edit it. Double-click text to type directly.</p>
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
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Content</label>
                <input type="text" x-model="selectedBlock.content" class="mt-1 h-10 w-full rounded-[8px] border border-gray-300 px-3 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            </div>

            <template x-if="selectedBlock.type === 'card'">
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Description (optional)</label>
                    <input type="text" x-model="selectedBlock.secondary_content" class="mt-1 h-10 w-full rounded-[8px] border border-gray-300 px-3 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
            </template>

            <template x-if="selectedBlock.type === 'button'">
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Link URL</label>
                    <input type="text" x-model="selectedBlock.url" placeholder="#contact" class="mt-1 h-10 w-full rounded-[8px] border border-gray-300 px-3 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
            </template>

            {{-- Typography --}}
            <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Typography</p>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Font Family</label>
                    <select x-model="selectedBlock.style.font_family" @change="ensureFontLoaded(selectedBlock.style.font_family)" class="mt-1 h-9 w-full rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <template x-for="family in Object.keys(fontOptions)" :key="family">
                            <option :value="family" x-text="family"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Font Weight</label>
                    <select x-model="selectedBlock.style.font_weight" class="mt-1 h-9 w-full rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <template x-for="weight in weightsFor(selectedBlock.style.font_family)" :key="weight">
                            <option :value="String(weight)" x-text="weight"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Font Size: <span x-text="selectedBlock.style.font_size"></span>px</label>
                    <input type="range" min="10" max="96" x-model.number="selectedBlock.style.font_size" class="mt-1 w-full">
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Text Color</label>
                    <input type="color" x-model="selectedBlock.style.color" class="mt-1 h-9 w-full rounded border border-gray-300 dark:border-gray-600">
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Text Align</label>
                    <div class="mt-1 flex gap-1">
                        <template x-for="option in ['left', 'center', 'right']" :key="option">
                            <button type="button" @click="selectedBlock.style.align = option" :class="selectedBlock.style.align === option ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 dark:bg-gray-700 dark:text-gray-300'" class="flex-1 rounded-[6px] border border-gray-300 py-1.5 text-xs font-semibold capitalize dark:border-gray-600" x-text="option"></button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Letter Spacing: <span x-text="selectedBlock.style.letter_spacing"></span>px</label>
                    <input type="range" min="-2" max="10" step="0.5" x-model.number="selectedBlock.style.letter_spacing" class="mt-1 w-full">
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Line Height: <span x-text="selectedBlock.style.line_height"></span></label>
                    <input type="range" min="0.8" max="2.2" step="0.05" x-model.number="selectedBlock.style.line_height" class="mt-1 w-full">
                </div>

                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300">Text Transform</label>
                    <select x-model="selectedBlock.style.text_transform" class="mt-1 h-9 w-full rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="none">None</option>
                        <option value="uppercase">UPPERCASE</option>
                        <option value="lowercase">lowercase</option>
                        <option value="capitalize">Capitalize</option>
                    </select>
                </div>
            </div>

            {{-- Button style --}}
            <template x-if="selectedBlock.type === 'button'">
                <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Button Style</p>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Background</label>
                            <input type="color" x-model="selectedBlock.style.bg_color" class="mt-1 h-9 w-full rounded border border-gray-300 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Hover Background</label>
                            <input type="color" x-model="selectedBlock.style.hover_bg_color" class="mt-1 h-9 w-full rounded border border-gray-300 dark:border-gray-600">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Border Width: <span x-text="selectedBlock.style.border_width"></span>px</label>
                        <input type="range" min="0" max="6" x-model.number="selectedBlock.style.border_width" class="mt-1 w-full">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Border Color</label>
                        <input type="color" x-model="selectedBlock.style.border_color" class="mt-1 h-9 w-full rounded border border-gray-300 dark:border-gray-600">
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Corner Radius: <span x-text="selectedBlock.style.radius"></span>px</label>
                        <input type="range" min="0" max="999" x-model.number="selectedBlock.style.radius" class="mt-1 w-full">
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Shadow</label>
                        <select x-model="selectedBlock.style.shadow" class="mt-1 h-9 w-full rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            <option value="none">None</option>
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                            <option value="xl">Extra Large</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Padding X: <span x-text="selectedBlock.style.padding_x"></span>px</label>
                            <input type="range" min="0" max="60" x-model.number="selectedBlock.style.padding_x" class="mt-1 w-full">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Padding Y: <span x-text="selectedBlock.style.padding_y"></span>px</label>
                            <input type="range" min="0" max="40" x-model.number="selectedBlock.style.padding_y" class="mt-1 w-full">
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Drag this button's edges/corners on the preview to resize its box.</p>
                </div>
            </template>

            {{-- Card style --}}
            <template x-if="selectedBlock.type === 'card'">
                <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Card Style</p>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Background Color</label>
                        <input type="color" x-model="selectedBlock.style.bg_color" class="mt-1 h-9 w-full rounded border border-gray-300 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Background Opacity: <span x-text="selectedBlock.style.bg_opacity"></span>%</label>
                        <input type="range" min="0" max="100" x-model.number="selectedBlock.style.bg_opacity" class="mt-1 w-full">
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Blur</label>
                        <select x-model="selectedBlock.style.blur" class="mt-1 h-9 w-full rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            <option value="none">None</option>
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                            <option value="xl">Extra Large</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Border Color</label>
                        <input type="color" x-model="selectedBlock.style.border_color" class="mt-1 h-9 w-full rounded border border-gray-300 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Border Width: <span x-text="selectedBlock.style.border_width"></span>px</label>
                        <input type="range" min="0" max="6" x-model.number="selectedBlock.style.border_width" class="mt-1 w-full">
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Corner Radius: <span x-text="selectedBlock.style.radius"></span>px</label>
                        <input type="range" min="0" max="48" x-model.number="selectedBlock.style.radius" class="mt-1 w-full">
                    </div>

                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-300">Shadow</label>
                        <select x-model="selectedBlock.style.shadow" class="mt-1 h-9 w-full rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            <option value="none">None</option>
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                            <option value="xl">Extra Large</option>
                        </select>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
