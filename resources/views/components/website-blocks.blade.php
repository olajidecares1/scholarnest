@props(['blocks', 'editable' => false, 'section' => null, 'height' => '300px'])

@php
    $shadowMap = [
        'none' => 'none',
        'sm' => '0 1px 2px rgba(0,0,0,0.15)',
        'md' => '0 4px 8px rgba(0,0,0,0.20)',
        'lg' => '0 10px 25px rgba(0,0,0,0.25)',
        'xl' => '0 20px 45px rgba(0,0,0,0.30)',
    ];
    $blurMap = ['none' => '0px', 'sm' => '4px', 'md' => '8px', 'lg' => '16px', 'xl' => '24px'];

    $hexToRgba = function (string $hex, int $opacityPercent): string {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return $hex === 'transparent' ? 'transparent' : $hex;
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, ".round(max(0, min(100, $opacityPercent)) / 100, 2).')';
    };

    $boxStyle = fn (array $block) => "left:{$block['x']}%;top:{$block['y']}%;width:{$block['w']}%;height:{$block['h']}%;";

    $textStyle = function (array $block) use ($boxStyle) {
        $s = $block['style'];

        return $boxStyle($block)
            ."font-family:'{$s['font_family']}',var(--font-sans);font-weight:{$s['font_weight']};"
            ."font-size:{$s['font_size']}px;line-height:{$s['line_height']};letter-spacing:{$s['letter_spacing']}px;"
            ."text-align:{$s['align']};text-transform:{$s['text_transform']};color:{$s['color']};";
    };

    $buttonStyle = function (array $block) use ($boxStyle, $shadowMap) {
        $s = $block['style'];
        $justify = match ($s['align']) {
            'left' => 'flex-start',
            'right' => 'flex-end',
            default => 'center',
        };
        $shadow = $shadowMap[$s['shadow']] ?? 'none';

        return $boxStyle($block)
            ."display:flex;align-items:center;justify-content:{$justify};"
            ."font-family:'{$s['font_family']}',var(--font-sans);font-weight:{$s['font_weight']};"
            ."font-size:{$s['font_size']}px;line-height:{$s['line_height']};letter-spacing:{$s['letter_spacing']}px;"
            ."text-align:{$s['align']};text-transform:{$s['text_transform']};"
            ."background-color:{$s['bg_color']};color:{$s['text_color']};"
            ."border-width:{$s['border_width']}px;border-style:solid;border-color:{$s['border_color']};"
            ."border-radius:{$s['radius']}px;box-shadow:{$shadow};"
            ."padding:0 {$s['padding_x']}px;text-decoration:none;";
    };

    $cardStyle = function (array $block) use ($boxStyle, $shadowMap, $blurMap, $hexToRgba) {
        $s = $block['style'];
        $shadow = $shadowMap[$s['shadow']] ?? 'none';
        $blur = $blurMap[$s['blur']] ?? '0px';

        return $boxStyle($block)
            .'background-color:'.$hexToRgba($s['bg_color'], $s['bg_opacity'] ?? 100).';'
            ."backdrop-filter:blur({$blur});-webkit-backdrop-filter:blur({$blur});"
            .'border-width:'.$s['border_width'].'px;border-style:solid;border-color:'.$hexToRgba($s['border_color'], $s['border_opacity'] ?? 100).';'
            ."border-radius:{$s['radius']}px;box-shadow:{$shadow};padding:16px;box-sizing:border-box;overflow:auto;";
    };

    $cardTextStyle = fn (array $s) => "font-family:'{$s['font_family']}',var(--font-sans);font-weight:{$s['font_weight']};"
        ."font-size:{$s['font_size']}px;line-height:{$s['line_height']};text-align:{$s['align']};color:{$s['color']};";
@endphp

@if (! $editable)
    <div class="relative" style="height: {{ $height }}">
        @foreach ($blocks as $block)
            @if ($block['type'] === 'text')
                <span class="absolute m-0" style="{{ $textStyle($block) }}">{{ $block['content'] }}</span>
            @elseif ($block['type'] === 'button')
                <a href="{{ $block['url'] ?: '#' }}" data-block-btn="{{ $block['uuid'] }}" class="absolute transition-all duration-300 ease-out hover:-translate-y-1" style="{{ $buttonStyle($block) }}">{{ $block['content'] }}</a>
            @elseif ($block['type'] === 'card')
                <div class="absolute" style="{{ $cardStyle($block) }}">
                    @if ($block['content'])
                        <p class="m-0" style="{{ $cardTextStyle($block['style']) }}">{{ $block['content'] }}</p>
                    @endif
                    @if ($block['secondary_content'])
                        <p class="m-0 mt-1" style="font-family:'{{ $block['style']['font_family'] }}',var(--font-sans);font-size:13px;font-weight:400;color:{{ $block['style']['color'] }};opacity:0.8;">{{ $block['secondary_content'] }}</p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
    @foreach ($blocks as $block)
        @if ($block['type'] === 'button')
            <style>[data-block-btn="{{ $block['uuid'] }}"]:hover { background-color: {{ $block['style']['hover_bg_color'] }}; color: {{ $block['style']['hover_text_color'] }}; }</style>
        @endif
    @endforeach
@else
    <div data-canvas class="relative rounded-[8px] border border-dashed border-gray-300 dark:border-gray-600" style="height: {{ $height }}" @click.self="deselect()">
        <template x-for="block in sections['{{ $section }}']" :key="block.uuid">
            <div>
                <template x-if="block.type === 'text'">
                    <div
                        class="absolute edn-hero-editable-el"
                        :class="{ 'is-selected': selected === block.uuid }"
                        x-draggable-resizable="block"
                        x-bind:style="textStyleFor(block)"
                        @pointerdown="select(block.uuid)"
                    >
                        <span
                            class="block h-full w-full"
                            x-text="block.content"
                            @dblclick.stop="startEditing(block, $event)"
                            @blur="stopEditing(block, $event)"
                            :contenteditable="editingId === block.uuid"
                        ></span>
                        <template x-for="handle in handles" :key="handle">
                            <span class="edn-resize-handle" :data-handle="handle" x-show="selected === block.uuid"></span>
                        </template>
                    </div>
                </template>

                <template x-if="block.type === 'button'">
                    <a
                        href="#"
                        @click.prevent
                        class="absolute edn-hero-editable-el"
                        :class="{ 'is-selected': selected === block.uuid }"
                        x-draggable-resizable="block"
                        x-bind:style="buttonStyleFor(block)"
                        @pointerdown="select(block.uuid)"
                    >
                        <span x-text="block.content" @dblclick.stop="startEditing(block, $event)" @blur="stopEditing(block, $event)" :contenteditable="editingId === block.uuid"></span>
                        <template x-for="handle in handles" :key="handle">
                            <span class="edn-resize-handle" :data-handle="handle" x-show="selected === block.uuid"></span>
                        </template>
                    </a>
                </template>

                <template x-if="block.type === 'card'">
                    <div
                        class="absolute edn-hero-editable-el"
                        :class="{ 'is-selected': selected === block.uuid }"
                        x-draggable-resizable="block"
                        x-bind:style="cardStyleFor(block)"
                        @pointerdown="select(block.uuid)"
                    >
                        <p class="m-0" x-text="block.content" @dblclick.stop="startEditing(block, $event)" @blur="stopEditing(block, $event)" :contenteditable="editingId === block.uuid"></p>
                        <p class="m-0 mt-1 text-xs opacity-80" x-text="block.secondary_content" @dblclick.stop="startEditing(block, $event, true)" @blur="stopEditing(block, $event, true)" :contenteditable="editingId === block.uuid + '-secondary'"></p>
                        <template x-for="handle in handles" :key="handle">
                            <span class="edn-resize-handle" :data-handle="handle" x-show="selected === block.uuid"></span>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
@endif
