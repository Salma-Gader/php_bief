/**
 * Sunset Brunch: page motion (landing page only)
 *
 * Inspired by the motion of the "Golden Portal" template:
 *   - smooth, eased scrolling (Lenis);
 *   - a wall of clouds that rises faster than the hero and covers it,
 *     while the sky drifts slowly behind (parallax) and gently breathes;
 *   - titles that rise line by line from behind a mask (SplitText);
 *   - photos revealed through their arch, then gently zooming;
 *   - Q&A answers that brighten as they reach the reading line;
 *   - dusk clouds that hang over and rise between sections.
 *
 * Rules:
 *   - Nothing here is needed to use the page: without JavaScript, or with
 *     "reduce motion" switched on, every element is simply shown.
 *   - The libraries load after the page has finished loading, so the first
 *     screen (hero) is painted as fast as before.
 *   - Only transform, opacity and clip-path are animated.
 *   - Endless (ambient) movements pause when their section is off screen.
 */

const VENDOR = new URL('../vendor/', import.meta.url);
const VENDOR_FILES = ['ScrollTrigger.min.js', 'SplitText.min.js', 'lenis.min.js'];

if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
    start().catch(() => {
        // A library failed to load: the page simply stays still.
        document.documentElement.classList.remove('has-motion');
    });
}

async function start() {
    if (document.readyState !== 'complete') {
        await new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));
    }
    await loadScript('gsap.min.js'); // the plugins need gsap first
    await Promise.all(VENDOR_FILES.map(loadScript));
    await document.fonts.ready;      // line breaks must be measured with the real fonts
    init();
}

function loadScript(file) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = new URL(file, VENDOR).href;
        script.onload = resolve;
        script.onerror = reject;
        document.head.append(script);
    });
}

function init() {
    const { gsap, ScrollTrigger, SplitText } = window;
    gsap.registerPlugin(ScrollTrigger, SplitText);

    // Everything is created inside matchMedia: if the visitor turns on
    // "reduce motion" later, GSAP undoes all of it and restores the page.
    gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', (context) => {
        document.documentElement.classList.add('has-motion');
        const scroller = smoothScroll(gsap, ScrollTrigger);
        const doveImages = loadDoveImages(); // downloads while the rest is set up
        let cancelled = false;

        // One section at a time, giving the browser a break in between, so that
        // setting up the motion never blocks a tap or a scroll for long.
        const steps = [
            () => hero(gsap, ScrollTrigger),
            () => reveals(gsap, SplitText),
            () => portal(gsap),
            () => dishes(gsap, ScrollTrigger),
            () => gallery(gsap),
            () => faq(gsap),
            () => clouds(gsap, ScrollTrigger),
            () => solidPill(ScrollTrigger),
        ];
        (async () => {
            for (const step of steps) {
                await nextTask();
                if (cancelled) return;
                context.add(step); // recorded in the context, so it is undone with the rest
            }
            const images = await doveImages;
            if (!cancelled && images) context.add(() => doves(gsap, ScrollTrigger, images));
        })();

        return () => {
            cancelled = true;
            scroller.destroy();
            document.documentElement.classList.remove('has-motion');
        };
    });
}

/** Let the browser handle input and paint before the next piece of work. */
function nextTask() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

/* ==========================================================================
   Smooth scrolling (Lenis), driven by GSAP's clock so ScrollTrigger stays in sync
   ========================================================================== */

function smoothScroll(gsap, ScrollTrigger) {
    // Wheel and trackpad get the eased glide; touch screens keep native scrolling.
    const lenis = new window.Lenis({ lerp: 0.085, anchors: false });
    const tick = (time) => lenis.raf(time * 1000);

    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add(tick);
    gsap.ticker.lagSmoothing(0);

    // In-page links ("Book a table", the menu pill) glide to their section.
    // The skip link keeps the browser's own instant jump.
    const onClick = (event) => {
        const link = event.target.closest('a[href^="#"]');
        if (!link || link.classList.contains('skip-link') || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const target = document.getElementById(link.hash.slice(1));
        if (!target) return;

        event.preventDefault();
        history.pushState(null, '', link.hash);
        lenis.scrollTo(target, {
            duration: 1.6,
            easing: (t) => 1 - Math.pow(1 - t, 4),
            // Like a normal anchor jump, the next Tab starts from the section.
            onComplete: () => moveFocusTo(target),
        });
    };
    document.addEventListener('click', onClick);

    return {
        destroy() {
            document.removeEventListener('click', onClick);
            gsap.ticker.remove(tick);
            lenis.destroy();
        },
    };
}

function moveFocusTo(element) {
    if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '-1');
    element.focus({ preventScroll: true });
}

/* ==========================================================================
   Hero: clouds rise over the title, the sky drifts behind
   ========================================================================== */

function hero(gsap, ScrollTrigger) {
    const section = document.querySelector('.hero');
    if (!section) return;
    const sky = section.querySelector('.hero__sky');
    const bank = document.querySelector('.hero__bank'); // at the top of the next section
    const content = section.querySelector('.hero__content');
    const doveLayer = section.querySelector('.hero__doves');

    // Scroll-linked: the clouds rise faster than the page and pass over the title,
    // the text lags behind and fades, the doves fly up and away, the sky moves
    // slowest. Together they give the depth of the template.
    gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: { trigger: section, start: 'top top', end: 'bottom top', scrub: true },
    })
        // The clouds start below the first screen: they are only drawn once the
        // visitor scrolls, so they never replace the title as the largest paint (LCP).
        // By the end they have risen past the top of the screen, whole.
        .fromTo(bank, { yPercent: 0 }, { yPercent: -110 }, 0)
        .to(content, { yPercent: 45, opacity: 0, duration: 0.6 }, 0)
        .to(doveLayer, { yPercent: -30 }, 0)
        .to(sky, { yPercent: 22 }, 0);

    // Ambient: the sky (and its clouds) slowly breathes while the hero is on screen.
    const ambient = [
        gsap.to(sky, { scale: 1.05, duration: 14, ease: 'sine.inOut', yoyo: true, repeat: -1 }),
    ];
    pauseWhenHidden(ScrollTrigger, section, ambient);
}

/* ==========================================================================
   Doves: flying across the hero sky, and one more in most sections
   ========================================================================== */

// The painted doves (AI-generated images supplied for the project), both facing left.
const DOVE_FILES = { glide: 'dove-glide.webp', wings: 'dove-wings.webp' };

// One list per layer (<div class="doves" data-flock="...">).
//
// Hero doves cross the sky right to left, forever, in a band near the top or the
// bottom of the hero, so they never cross the title:
//   y:     height of the flight line (share of the hero's height)
//   size:  width (share of the window's width, or of its height on tall phone screens)
//   time:  seconds to cross the screen (far doves are smaller and slower)
//   start: where the dove already is when the page opens (0 = right edge, 1 = left edge)
//
// In the other sections a dove's flight follows the scroll: it goes from `from` to
// `to` while the top of its section comes up the screen. Positions are [x, y]:
// shares of the window's width and height, measured from the section's top-left
// corner. The paths keep to the sides and the top, away from the headings.
// `flip` mirrors the dove when it flies to the right. `anchor: 'bottom'` measures
// from the section's bottom instead, and flies while that bottom crosses the screen.
const FLOCKS = {
    hero: [
        { pose: 'wings', y: 0.05, size: 0.10, time: 24, start: 0.30 },
        { pose: 'glide', y: 0.78, size: 0.11, time: 29, start: 0.72 },
        { pose: 'glide', y: 0.17, size: 0.055, time: 44, start: 0.85, far: true },
        { pose: 'wings', y: 0.70, size: 0.05, time: 50, start: 0.15, far: true },
    ],
    intro: [{ pose: 'glide', size: 0.15, from: [1.05, 0.75], to: [0.6, 0] }],
    menu: [
        { pose: 'wings', size: 0.12, from: [-0.18, 0.45], to: [0.16, 0.01], flip: true },
        // after the last cards, over the clouds at the bottom right
        { pose: 'glide', size: 0.14, from: [1.08, -0.1], to: [0.66, -0.62], anchor: 'bottom' },
    ],
    faq: [{ pose: 'wings', size: 0.16, from: [1.05, 0.4], to: [0.72, 0] }],
    booking: [{ pose: 'glide', size: 0.13, from: [-0.18, 0.4], to: [0.18, -0.02], flip: true }],
};

function loadDoveImages() {
    const load = async ([pose, file]) => {
        const image = new Image();
        image.src = new URL(`../img/${file}`, import.meta.url).href;
        await image.decode();
        return [pose, image];
    };
    // If a picture fails, the page simply has no doves.
    return Promise.all(Object.entries(DOVE_FILES).map(load)).then(Object.fromEntries, () => null);
}

/**
 * Each dove is drawn once on its own small <canvas>, which is then only moved:
 * the browser just slides a finished picture around (and a canvas never counts
 * as the page's "largest paint", so the doves do not slow down LCP).
 */
function drawDove(image, width, flip) {
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const height = Math.round((width * image.naturalHeight) / image.naturalWidth);
    const canvas = document.createElement('canvas');
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    const context = canvas.getContext('2d');
    if (flip) {
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
    }
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    return canvas;
}

function doves(gsap, ScrollTrigger, images) {
    const layers = Array.from(document.querySelectorAll('.doves[data-flock]'));
    if (layers.length === 0) return undefined;
    let made = []; // every tween and trigger below, so a rebuild can remove them

    // Gentle life on top of the flight: rise and fall, lean into the air.
    const floating = (canvas) => [
        gsap.to(canvas, {
            yPercent: gsap.utils.random(-45, -25),
            duration: gsap.utils.random(2.6, 3.8),
            ease: 'sine.inOut',
            yoyo: true,
            repeat: -1,
        }),
        gsap.fromTo(canvas, { rotation: -3 }, {
            rotation: 4,
            duration: gsap.utils.random(3.5, 5),
            ease: 'sine.inOut',
            yoyo: true,
            repeat: -1,
        }),
    ];

    const build = () => {
        made.forEach((item) => item.kill());
        made = [];
        const base = Math.max(window.innerWidth, window.innerHeight * 1.2); // doves not tiny on phones

        for (const layer of layers) {
            layer.replaceChildren();
            const section = layer.parentElement;
            const width = layer.clientWidth;
            const height = layer.clientHeight;
            const endless = []; // paused while the section is off screen

            for (const dove of FLOCKS[layer.dataset.flock] ?? []) {
                // largest size: 250 px in the hero, 300 px in the other sections
                const w = Math.round(gsap.utils.clamp(40, dove.time ? 250 : 300, base * dove.size));
                const canvas = drawDove(images[dove.pose], w, dove.flip);
                layer.append(canvas);

                if (dove.time) {
                    // Hero: cross the sky forever, never higher than 64 px (the menu pill)
                    gsap.set(canvas, { y: Math.max(height * dove.y, 64), opacity: 0 });
                    endless.push(
                        gsap.to(canvas, { opacity: dove.far ? 0.8 : 1, duration: 1.5, ease: 'power1.out' }),
                        gsap.fromTo(canvas, { x: width + 20 }, { x: -w - 20, duration: dove.time, ease: 'none', repeat: -1 })
                            .progress(dove.start),
                    );
                } else {
                    // Other sections: the flight follows the scroll (scrub 1.2 = a soft lag)
                    const screen = window.innerHeight;
                    const fromBottom = dove.anchor === 'bottom';
                    const origin = fromBottom ? height : 0;
                    made.push(gsap.fromTo(canvas,
                        { x: width * dove.from[0], y: origin + screen * dove.from[1] },
                        {
                            x: width * dove.to[0],
                            y: origin + screen * dove.to[1],
                            ease: 'none',
                            scrollTrigger: {
                                trigger: section,
                                start: fromBottom ? 'bottom bottom' : 'top bottom',
                                end: fromBottom ? 'bottom top' : 'top top',
                                scrub: 1.2,
                            },
                        }));
                }
                endless.push(...floating(canvas));
            }

            made.push(...endless, ScrollTrigger.create({
                trigger: section,
                start: 'top bottom',
                end: 'bottom top',
                onToggle: (self) => endless.forEach((a) => (self.isActive ? a.resume() : a.pause())),
            }));
            if (!ScrollTrigger.isInViewport(section)) endless.forEach((a) => a.pause());
        }
    };
    build();

    // A new window width means new flight lines and sizes.
    let lastWidth = window.innerWidth;
    let timer;
    const onResize = () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (Math.abs(window.innerWidth - lastWidth) < 40) return; // ignore phone toolbars
            lastWidth = window.innerWidth;
            build();
        }, 250);
    };
    window.addEventListener('resize', onResize);

    // Called by GSAP when the motion is undone (for example "reduce motion" turned on).
    return () => {
        window.removeEventListener('resize', onResize);
        clearTimeout(timer);
        made.forEach((item) => item.kill());
        layers.forEach((layer) => layer.replaceChildren());
    };
}

/* ==========================================================================
   Section titles: lines rise from behind a mask, then the rest follows
   ========================================================================== */

function reveals(gsap, SplitText) {
    gsap.utils.toArray('.reveal').forEach((group) => {
        const timeline = gsap.timeline({
            scrollTrigger: { trigger: group, start: 'top 85%', once: true },
        });
        const items = group.matches('[data-split]') ? [group] : Array.from(group.children);

        items.forEach((item, index) => {
            const at = index === 0 ? 0 : '<0.12';

            if (item.matches('[data-split]')) {
                // Split into lines only for the entrance, then give the original
                // text back (so it re-wraps normally if the window is resized).
                // aria: 'none': the text stays readable as is; no aria-label is added
                // (an aria-label is not allowed on a <p>).
                const split = SplitText.create(item, { type: 'lines', mask: 'lines', aria: 'none' });
                timeline.from(split.lines, {
                    yPercent: 110,
                    duration: 1.2,
                    ease: 'expo.out',
                    stagger: 0.1,
                    onComplete: () => split.revert(),
                }, at);
            } else {
                timeline.from(item, { y: 28, opacity: 0, duration: 1, ease: 'power3.out' }, at);
            }
        });
    });
}

/* ==========================================================================
   The portal: the arch opens from the bottom, the riad zooms out, then drifts
   ========================================================================== */

function portal(gsap) {
    const frame = document.querySelector('.portal');
    if (!frame) return;
    const image = frame.querySelector('img');

    gsap.timeline({ scrollTrigger: { trigger: frame, start: 'top 80%', once: true } })
        .fromTo(frame,
            { clipPath: 'inset(100% 0% 0% 0% round 999px 999px 18px 18px)' },
            { clipPath: 'inset(0% 0% 0% 0% round 999px 999px 18px 18px)', duration: 1.6, ease: 'expo.inOut' })
        .fromTo(image, { scale: 1.4 }, { scale: 1.12, duration: 2.2, ease: 'expo.out' }, 0.2);

    // Parallax: the photo moves a little slower than its window.
    gsap.fromTo(image, { yPercent: -6 }, {
        yPercent: 6,
        ease: 'none',
        scrollTrigger: { trigger: frame, start: 'top bottom', end: 'bottom top', scrub: true },
    });
}

/* ==========================================================================
   Menu cards: each arch window opens, its photo settles, then the text
   ========================================================================== */

function dishes(gsap, ScrollTrigger) {
    const cards = gsap.utils.toArray('.dish');
    if (cards.length === 0) return;

    gsap.set(cards.map((card) => card.querySelector('.dish__window')), { clipPath: 'inset(100% 0% 0% 0%)' });
    gsap.set(cards.map((card) => card.querySelector('.dish__body')), { opacity: 0, y: 24 });

    // Cards that enter the screen together are revealed one after another.
    ScrollTrigger.batch(cards, {
        start: 'top 88%',
        once: true,
        onEnter: (batch) => {
            const pick = (selector) => batch.map((card) => card.querySelector(selector));
            gsap.timeline()
                .to(pick('.dish__window'), { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.3, ease: 'expo.inOut', stagger: 0.14 })
                .from(pick('.dish__window img'), { scale: 1.3, duration: 1.8, ease: 'expo.out', stagger: 0.14 }, 0.15)
                .to(pick('.dish__body'), { opacity: 1, y: 0, duration: 0.9, ease: 'power3.out', stagger: 0.14 }, 0.5);
        },
    });
}

/* ==========================================================================
   Gallery: the photos open from the bottom, one after another
   ========================================================================== */

function gallery(gsap) {
    const strip = document.querySelector('.gallery__strip');
    if (!strip) return;

    gsap.fromTo(strip.querySelectorAll('.gallery__item'),
        { clipPath: 'inset(100% 0% 0% 0%)' },
        {
            clipPath: 'inset(0% 0% 0% 0%)',
            duration: 1.3,
            ease: 'expo.inOut',
            stagger: 0.08,
            scrollTrigger: { trigger: strip, start: 'top 85%', once: true },
        });
}

/* ==========================================================================
   Q&A: each answer brightens as it reaches the reading line
   (it starts at 62% opacity: dimmed, but still above the 4.5:1 contrast minimum)
   ========================================================================== */

function faq(gsap) {
    gsap.utils.toArray('.faq__item').forEach((item) => {
        gsap.fromTo(item, { opacity: 0.62, y: 24 }, {
            opacity: 1,
            y: 0,
            ease: 'none',
            scrollTrigger: { trigger: item, start: 'top 95%', end: 'top 62%', scrub: true },
        });
    });
}

/* ==========================================================================
   Clouds between sections
   ========================================================================== */

function clouds(gsap, ScrollTrigger) {
    // Clouds at the edges of sections drift up more slowly than the page (parallax).
    gsap.utils.toArray('.cloud').forEach((cloud) => {
        gsap.fromTo(cloud, { yPercent: 25 }, {
            yPercent: -25,
            ease: 'none',
            scrollTrigger: { trigger: cloud.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });

    // Dusk clouds hanging over the Q&A sink slowly as the page goes up.
    const hanging = document.querySelector('.cloudbank--hang');
    if (hanging) {
        gsap.fromTo(hanging, { yPercent: -30 }, {
            yPercent: 20,
            ease: 'none',
            scrollTrigger: { trigger: hanging.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
        const drift = gsap.to(hanging, { xPercent: 3, duration: 18, ease: 'sine.inOut', yoyo: true, repeat: -1 });
        pauseWhenHidden(ScrollTrigger, hanging.parentElement, [drift]);
    }

    // The warm clouds at the bottom of the page rise as the booking form arrives.
    const glow = document.querySelector('.booking__glow');
    if (glow) {
        gsap.fromTo(glow, { yPercent: 45 }, {
            yPercent: 0,
            ease: 'none',
            scrollTrigger: { trigger: glow.parentElement, start: 'top bottom', end: 'bottom bottom', scrub: true },
        });
    }
}

/* ==========================================================================
   The menu pill turns more solid once the hero has gone
   ========================================================================== */

function solidPill(ScrollTrigger) {
    const pill = document.querySelector('.pill');
    if (!pill) return;
    ScrollTrigger.create({
        trigger: '.hero',
        start: 'top -40%',
        onEnter: () => pill.classList.add('is-solid'),
        onLeaveBack: () => pill.classList.remove('is-solid'),
    });
}

/** Pause endless animations while their section is off screen (saves battery). */
function pauseWhenHidden(ScrollTrigger, section, animations) {
    ScrollTrigger.create({
        trigger: section,
        start: 'top bottom',
        end: 'bottom top',
        onToggle: (self) => animations.forEach((a) => (self.isActive ? a.resume() : a.pause())),
    });
}
