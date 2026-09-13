/**
 * A passport photograph, taken with the camera or chosen from the device.
 *
 * Both routes end in the SAME PLACE: a File written into the form's own file
 * input. Nothing is posted as base64 and there is no second endpoint, so a
 * captured photograph goes through exactly the validation, the optimisation
 * and the private-disk storage an uploaded one does. One path, one set of
 * rules, nothing to drift apart.
 *
 * Nothing is attached to the record until the person confirms it. A capture
 * sits in a preview with Retake beside it, and the file input stays empty
 * until Use This Photo is pressed - so closing the camera, or changing their
 * mind, leaves the record exactly as it was.
 */
import { isHeic, markPrepared, prepareImageFile, setInputFiles } from './image-upload-prep';

/** Longest edge a portrait is sent at. The server keeps 1600; see ImageProfile. */
const PORTRAIT_MAX_EDGE = 1600;

export default function photoField({ inputId, existing = null, maxKb = 10240 } = {}) {
    return {
        /** 'idle' | 'camera' | 'review' */
        mode: 'idle',

        /** True while a chosen photograph is being prepared for upload. */
        preparing: false,

        /**
         * Offered when the in-page camera cannot run - an in-app browser, a
         * refused permission, plain http. The phone's own camera app, opened
         * through a capture input, needs none of what getUserMedia needs.
         */
        offerNativeCamera: false,

        /** The photograph currently attached to the form, as an object URL. */
        preview: existing,

        /** A capture awaiting confirmation. Never written to the input. */
        pending: null,

        stream: null,
        error: null,
        busy: false,

        get input() {
            return document.getElementById(inputId)
        },

        /**
         * Whether a camera is worth offering at all.
         *
         * getUserMedia exists only in a secure context, so a site served over
         * plain http on a LAN has no camera however good the device is. Saying
         * so is better than a button that fails when pressed.
         */
        get cameraSupported() {
            return Boolean(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
        },

        async openCamera() {
            this.error = null

            this.offerNativeCamera = false

            if (!this.cameraSupported) {
                // No in-page camera here (an in-app browser, plain http). On a
                // phone the device's own camera still works through a capture
                // input, and this click is still the person's own gesture.
                this.openNativeCamera()

                return
            }

            this.busy = true

            try {
                // The FRONT camera, and a square-ish frame: this is a passport
                // photograph of the person holding the device, not a snapshot
                // of what they are pointing at.
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1080 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                })

                this.mode = 'camera'

                await this.$nextTick()

                const video = this.$refs.video
                video.srcObject = this.stream
                await video.play()
            } catch (e) {
                // Refusing permission is a choice, not a fault, and it reads
                // differently from a device that has no camera at all.
                this.error = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError')
                    ? 'Permission to use the camera was refused. Allow it in your browser settings, use your phone\'s camera app below, or upload a photograph instead.'
                    : 'The camera could not be started. Use your phone\'s camera app below, or upload a photograph instead.'

                // A second tap opens the device camera app instead - a new
                // gesture, which a file dialog needs.
                this.offerNativeCamera = true
            } finally {
                this.busy = false
            }
        },

        /**
         * Freeze the current frame, square-cropped from the centre.
         *
         * Square because every place this photograph is printed - the ID card,
         * the report card, the profile - shows it in a square or a circle. A
         * wide frame would be cropped by CSS at each of them, differently.
         */
        capture() {
            const video = this.$refs.video
            const side = Math.min(video.videoWidth, video.videoHeight)

            if (!side) {
                this.error = 'The camera is not ready yet. Give it a moment and try again.'

                return
            }

            const canvas = document.createElement('canvas')
            canvas.width = side
            canvas.height = side

            const context = canvas.getContext('2d')

            // Mirrored, so the preview matches what the person saw while they
            // were framing themselves. An unmirrored capture of a mirrored
            // preview looks wrong to everybody who has ever used a mirror.
            context.translate(side, 0)
            context.scale(-1, 1)

            context.drawImage(
                video,
                (video.videoWidth - side) / 2,
                (video.videoHeight - side) / 2,
                side,
                side,
                0,
                0,
                side,
                side,
            )

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        this.error = 'That photograph could not be saved. Please try again.'

                        return
                    }

                    this.pending = {
                        url: URL.createObjectURL(blob),
                        // Already upright and camera-sized, so the form's
                        // image preparation leaves it alone.
                        file: markPrepared(new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' })),
                    }

                    this.mode = 'review'
                    this.stopCamera()
                },
                'image/jpeg',
                // Enough for a passport photograph at the sizes it is printed,
                // and small enough to upload on a phone connection. The server
                // optimises again after it arrives.
                0.85,
            )
        },

        /** Throw the capture away and reopen the camera. */
        retake() {
            this.discardPending()
            this.openCamera()
        },

        /**
         * Accept the capture. ONLY HERE does anything reach the form.
         */
        confirm() {
            if (!this.pending) {
                return
            }

            this.writeToInput(this.pending.file)
            this.setPreview(this.pending.url)

            this.pending = null
            this.mode = 'idle'
        },

        /**
         * A file chosen from the device, or taken with the phone's camera app.
         *
         * Prepared before anything is checked: a 9MB photograph straight off a
         * phone is shrunk to a few hundred kilobytes and made upright here, so
         * it is judged by what will actually be sent. It used to be refused
         * outright for being over 5MB.
         */
        async chooseFile(event) {
            const file = event.target.files && event.target.files[0]

            this.error = null
            this.offerNativeCamera = false

            if (!file) {
                return
            }

            const type = (file.type || '').toLowerCase()

            // An EMPTY type is let through: several Android camera and file
            // apps report none. The server identifies every upload from its
            // content, whatever the browser said.
            if (type && !['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(type) && !isHeic(file)) {
                this.error = 'Choose a JPG, PNG or WebP photograph.'
                this.clear()

                return
            }

            this.preparing = true

            let chosen = file

            try {
                chosen = await prepareImageFile(file, { maxEdge: PORTRAIT_MAX_EDGE })
            } finally {
                this.preparing = false
            }

            // Still HEIC means this browser could not read it, so it cannot be
            // previewed and this server cannot promise to either.
            if (isHeic(chosen)) {
                this.error = 'This photo is in Apple\'s HEIC format, which this browser cannot open. On an iPhone, choose it in Safari, or set Settings > Camera > Formats to "Most Compatible".'
                this.clear()

                return
            }

            if (chosen.size > maxKb * 1024) {
                this.error = `That photograph is larger than ${Math.round(maxKb / 1024)}MB, even after resizing. Choose a smaller one.`
                this.clear()

                return
            }

            if (chosen !== file) {
                this.writeToInput(chosen)
            }

            this.setPreview(URL.createObjectURL(chosen))
        },

        /** The phone's own camera app, through a capture input. */
        openNativeCamera() {
            this.error = null
            this.offerNativeCamera = false
            this.$refs.capture?.click()
        },

        /** A photo returned by the camera app, handled like a chosen file. */
        async captureFromNativeCamera(event) {
            const file = event.target.files && event.target.files[0]

            if (!file) {
                return
            }

            this.writeToInput(file)
            event.target.value = ''

            await this.chooseFile({ target: this.input })
        },

        /** Take the photograph off the form again. */
        clear() {
            this.writeToInput(null)
            this.setPreview(null)
            this.discardPending()
            this.mode = 'idle'
        },

        cancelCamera() {
            this.stopCamera()
            this.discardPending()
            this.mode = 'idle'
        },

        // ---------------------------------------------------------------
        // Plumbing
        // ---------------------------------------------------------------

        /**
         * Put a File into the real <input type="file">, or empty it.
         *
         * DataTransfer is the only way to write to a file input, and it is what
         * lets a captured photograph travel as an ordinary upload.
         */
        writeToInput(file) {
            const input = this.input

            if (!input) {
                return
            }

            if (!file) {
                input.value = ''

                return
            }

            setInputFiles(input, [file])
        },

        setPreview(url) {
            // Object URLs hold their blob in memory until revoked. The one the
            // page was rendered with is a server address, not a blob, so it is
            // left alone.
            if (this.preview && this.preview.startsWith('blob:')) {
                URL.revokeObjectURL(this.preview)
            }

            this.preview = url
        },

        discardPending() {
            if (this.pending) {
                URL.revokeObjectURL(this.pending.url)
                this.pending = null
            }
        },

        /**
         * Release the camera.
         *
         * Every track, explicitly. A stream left running keeps the indicator
         * light on after the dialog has closed, which people reasonably read as
         * being watched.
         */
        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop())
                this.stream = null
            }

            if (this.$refs.video) {
                this.$refs.video.srcObject = null
            }
        },

        destroy() {
            this.stopCamera()
            this.discardPending()
            this.setPreview(null)
        },
    }
}
