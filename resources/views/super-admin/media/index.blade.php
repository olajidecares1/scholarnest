@php
    $libraryItems = $imageLibrary->concat($videoLibrary)->map(fn ($item) => [
        'id' => (string) $item->id,
        'name' => $item->name,
        'type' => $item->type->value,
        'url' => $item->url(),
    ]);
@endphp

<x-super-admin-layout page-title="Media" page-subtitle="Upload and manage images and videos, including login/register backgrounds.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Images</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['images']) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Videos</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['videos']) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Storage Used</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['totalSize'] / (1024 * 1024), 1) }} MB</p>
            </div>
        </div>

        {{-- Backgrounds panel --}}
        <div
            x-data="{
                loginId: {{ $settings->login_background_media_id ? (int) $settings->login_background_media_id : 'null' }},
                registerId: {{ $settings->register_background_media_id ? (int) $settings->register_background_media_id : 'null' }},
                library: @js($libraryItems),
                get loginMedia() { return this.library.find(m => m.id === String(this.loginId)) ?? null },
                get registerMedia() { return this.library.find(m => m.id === String(this.registerId)) ?? null },
            }"
            class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
        >
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Login &amp; Registration Backgrounds</h2>
            <small class="field-hint mt-1">Choose an image or video from your library, or select None to use the default background.</small>

            <div class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Login Page</h3>
                    <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-5">
                        <button
                            type="button"
                            @click="loginId = null"
                            class="relative flex aspect-video items-center justify-center rounded-[8px] border-2 text-[10px] font-semibold text-gray-400 transition-all duration-150"
                            :class="loginId === null ? 'border-primary-500 text-primary-600' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'"
                        >
                            None
                        </button>
                        <template x-for="item in library" :key="'login-'+item.id">
                            <button
                                type="button"
                                @click="loginId = parseInt(item.id)"
                                class="group relative aspect-video overflow-hidden rounded-[8px] border-2 transition-all duration-150"
                                :class="loginId === parseInt(item.id) ? 'border-primary-500' : 'border-transparent hover:border-gray-300'"
                            >
                                <img x-show="item.type === 'image'" :src="item.url" class="h-full w-full object-cover" :alt="item.name">
                                <video x-show="item.type === 'video'" :src="item.url" muted class="h-full w-full object-cover"></video>
                                <span x-show="loginId === parseInt(item.id)" class="absolute inset-0 flex items-center justify-center bg-primary-500/30">
                                    <i class="fa-solid fa-check text-white drop-shadow text-[17px] leading-none" aria-hidden="true"></i>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Registration Page</h3>
                    <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-5">
                        <button
                            type="button"
                            @click="registerId = null"
                            class="relative flex aspect-video items-center justify-center rounded-[8px] border-2 text-[10px] font-semibold text-gray-400 transition-all duration-150"
                            :class="registerId === null ? 'border-primary-500 text-primary-600' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'"
                        >
                            None
                        </button>
                        <template x-for="item in library" :key="'register-'+item.id">
                            <button
                                type="button"
                                @click="registerId = parseInt(item.id)"
                                class="group relative aspect-video overflow-hidden rounded-[8px] border-2 transition-all duration-150"
                                :class="registerId === parseInt(item.id) ? 'border-primary-500' : 'border-transparent hover:border-gray-300'"
                            >
                                <img x-show="item.type === 'image'" :src="item.url" class="h-full w-full object-cover" :alt="item.name">
                                <video x-show="item.type === 'video'" :src="item.url" muted class="h-full w-full object-cover"></video>
                                <span x-show="registerId === parseInt(item.id)" class="absolute inset-0 flex items-center justify-center bg-primary-500/30">
                                    <i class="fa-solid fa-check text-white drop-shadow text-[17px] leading-none" aria-hidden="true"></i>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <h3 class="mt-6 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Live Preview</h3>
            <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="relative aspect-video overflow-hidden rounded-[5px] bg-gray-900 lg:rounded-[8px]">
                    <template x-if="loginMedia?.type === 'image'">
                        <img :src="loginMedia.url" class="absolute inset-0 h-full w-full object-cover">
                    </template>
                    <template x-if="loginMedia?.type === 'video'">
                        <video :src="loginMedia.url" autoplay muted loop playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                    </template>
                    <div class="absolute inset-0 bg-black/35"></div>
                    <div class="relative flex h-full items-center justify-center p-4">
                        <div class="rounded-[5px] bg-white/95 px-5 py-3 text-center shadow-xl lg:rounded-[8px]">
                            <p class="text-xs font-bold text-gray-900">Sign In</p>
                            <small class="block text-[10px] text-gray-500">to Your Account</small>
                        </div>
                    </div>
                    <span class="absolute left-2 top-2 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-semibold text-white">Login Preview</span>
                </div>

                <div class="relative aspect-video overflow-hidden rounded-[5px] bg-gray-900 lg:rounded-[8px]">
                    <template x-if="registerMedia?.type === 'image'">
                        <img :src="registerMedia.url" class="absolute inset-0 h-full w-full object-cover">
                    </template>
                    <template x-if="registerMedia?.type === 'video'">
                        <video :src="registerMedia.url" autoplay muted loop playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                    </template>
                    <div class="absolute inset-0 bg-black/35"></div>
                    <div class="relative flex h-full items-center justify-center p-4">
                        <div class="rounded-[5px] bg-white/95 px-5 py-3 text-center shadow-xl lg:rounded-[8px]">
                            <p class="text-xs font-bold text-gray-900">Create Your</p>
                            <small class="block text-[10px] text-gray-500">School Account</small>
                        </div>
                    </div>
                    <span class="absolute left-2 top-2 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-semibold text-white">Register Preview</span>
                </div>
            </div>

            <form method="POST" action="{{ route('super-admin.media.backgrounds.update') }}" class="mt-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="login_background_media_id" :value="loginId">
                <input type="hidden" name="register_background_media_id" :value="registerId">
                <button
                    type="submit"
                    class="btn flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
                >
                    Save Backgrounds
                </button>
            </form>
        </div>

        {{-- Upload + Library --}}
        <div x-data="{ uploadOpen: false, renaming: null, replacing: null }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-1">
                    <a href="{{ route('super-admin.media.index') }}" class="btn rounded-[8px] px-3 py-1.5 text-sm font-semibold {{ ! request('type') ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">All</a>
                    <a href="{{ route('super-admin.media.index', ['type' => 'image']) }}" class="btn rounded-[8px] px-3 py-1.5 text-sm font-semibold {{ request('type') === 'image' ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">Images</a>
                    <a href="{{ route('super-admin.media.index', ['type' => 'video']) }}" class="btn rounded-[8px] px-3 py-1.5 text-sm font-semibold {{ request('type') === 'video' ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">Videos</a>
                </div>

                <button type="button" @click="uploadOpen = true" class="btn flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <i class="fa-solid fa-upload text-[14px] leading-none" aria-hidden="true"></i>
                    Upload Media
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse ($media as $item)
                    <div class="overflow-hidden rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                        <div class="relative aspect-video bg-gray-100 dark:bg-gray-900">
                            @if ($item->type->value === 'image')
                                <img src="{{ $item->url() }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                            @else
                                <video src="{{ $item->url() }}" class="h-full w-full object-cover" muted></video>
                                <span class="absolute inset-0 flex items-center justify-center bg-black/20">
                                    <svg class="h-8 w-8 text-white drop-shadow" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" /><path d="M10 9l5 3-5 3V9z" fill="currentColor" /></svg>
                                </span>
                            @endif
                            <span class="absolute right-1.5 top-1.5 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-semibold uppercase text-white">{{ $item->type->label() }}</span>
                        </div>

                        <div class="p-3">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item->name }}</p>
                            <small class="field-hint mt-0.5">{{ $item->humanSize() }} @if($item->width) &middot; {{ $item->width }}&times;{{ $item->height }} @endif</small>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <button
                                    type="button"
                                    @click="renaming = { id: @js($item->uuid), name: @js($item->name) }"
                                    class="btn rounded-[8px] border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Rename
                                </button>
                                <button
                                    type="button"
                                    @click="replacing = { id: @js($item->uuid), name: @js($item->name) }"
                                    class="btn rounded-[8px] border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    Replace
                                </button>
                                <form method="POST" action="{{ route('super-admin.media.destroy', $item) }}" onsubmit="return confirm('Delete {{ $item->name }}? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn rounded-[8px] border border-red-300 px-2.5 py-1 text-xs font-semibold text-red-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-[5px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400 lg:rounded-[10px]">
                        No media uploaded yet. Click "Upload Media" to add your first image or video.
                    </div>
                @endforelse
            </div>

            @if ($media->hasPages())
                <div class="mt-4">{{ $media->links() }}</div>
            @endif

            {{-- Upload modal --}}
            <div x-show="uploadOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="uploadOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Upload Media</h3>
                    <form method="POST" action="{{ route('super-admin.media.store') }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                        @csrf
                        <div>
                            <x-input-label for="file" value="Image or Video File" />
                            <input
                                id="file"
                                name="file"
                                type="file"
                                required
                                accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.webm"
                                class="mt-1 w-full"
                            >
                            <small class="field-hint mt-1">JPG, PNG, WEBP, MP4, MOV, or WEBM. Max 50MB. Images are automatically optimized.</small>
                        </div>
                        <x-text-field name="name" label="Display Name (optional)" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Leave blank to use the original filename." />
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="uploadOpen = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="btn rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Upload</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Rename modal --}}
            <div x-show="renaming" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="renaming = null" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Rename Media</h3>
                    <form method="POST" :action="renaming ? '{{ route('super-admin.media.update', ['media' => '__ID__']) }}'.replace('__ID__', renaming.id) : '#'" class="mt-4 space-y-2">
                        @csrf
                        @method('PUT')
                        <x-text-field name="name" label="Display Name" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Shown throughout the Media Library." x-model="renaming ? renaming.name : ''" required />
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="renaming = null" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="btn rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Replace modal --}}
            <div x-show="replacing" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="replacing = null" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Replace File</h3>
                    <small class="block mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="replacing ? 'Uploading a new file for \"' + replacing.name + '\" keeps it selected anywhere it is already in use.' : ''"></small>
                    <form method="POST" :action="replacing ? '{{ route('super-admin.media.replace', ['media' => '__ID__']) }}'.replace('__ID__', replacing.id) : '#'" enctype="multipart/form-data" class="mt-4 space-y-2">
                        @csrf
                        <input
                            name="file"
                            type="file"
                            required
                            accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.webm"
                            class="w-full"
                        >
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="replacing = null" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="btn rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Replace</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-super-admin-layout>
