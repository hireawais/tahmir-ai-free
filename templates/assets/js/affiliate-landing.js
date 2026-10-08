/**
 * Affiliate Landing Page — Enhanced JS
 * SVG draw animations, scroll reveals, parallax, particles
 */
document.addEventListener('DOMContentLoaded', function () {

    /* ========== NAV SCROLL ========== */
    var nav = document.getElementById('nav');
    window.addEventListener('scroll', function () {
        nav.classList.toggle('scrolled', window.scrollY > 60);
    }, { passive: true });

    /* ========== INTERSECTION OBSERVER REVEALS ========== */
    var revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
    var revealQueue = 0;
    var revealObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                var delay = Math.min(revealQueue * 60, 400);
                revealQueue++;
                setTimeout(function () {
                    e.target.style.transition = 'opacity .6s cubic-bezier(.22,1,.36,1), transform .6s cubic-bezier(.22,1,.36,1)';
                    e.target.style.opacity = '1';
                    e.target.style.transform = 'translate(0) scale(1)';
                    revealQueue = Math.max(0, revealQueue - 1);
                }, delay);
                revealObs.unobserve(e.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });
    revealEls.forEach(function (el) { revealObs.observe(el); });

    /* ========== SVG DRAW-IN ANIMATION ========== */
    var svgIcons = document.querySelectorAll('.svg-icon');
    if (svgIcons.length) {
        var svgObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('drawn');
                    svgObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.3 });
        svgIcons.forEach(function (el) { svgObs.observe(el); });
    }

    /* ========== HERO PARALLAX MOUSE ========== */
    var heroBrowser = document.getElementById('heroBrowser');
    var heroSection = document.querySelector('.hero');
    if (heroBrowser && heroSection) {
        heroSection.addEventListener('mousemove', function (e) {
            var rect = heroSection.getBoundingClientRect();
            var x = ((e.clientX - rect.left) / rect.width - 0.5) * 8;
            var y = ((e.clientY - rect.top) / rect.height - 0.5) * 6;
            heroBrowser.style.transform = 'rotateY(' + x + 'deg) rotateX(' + (-y) + 'deg)';
        });
        heroSection.addEventListener('mouseleave', function () {
            heroBrowser.style.transform = 'rotateY(0) rotateX(0)';
        });
    }

    /* ========== PARALLAX BACKGROUND BLOBS ========== */
    var blobs = document.querySelectorAll('.floating-blob');
    if (blobs.length) {
        window.addEventListener('scroll', function () {
            var scrollY = window.scrollY;
            blobs.forEach(function (blob, i) {
                var speed = 0.03 + (i * 0.015);
                blob.style.transform = 'translateY(' + (scrollY * speed) + 'px)';
            });
        }, { passive: true });
    }

    /* ========== SPOTLIGHT HOVER ON OFFER CARDS ========== */
    var spotlightCards = document.querySelectorAll('.offer-projection');
    spotlightCards.forEach(function (card) {
        var spotlight = card.querySelector('.spotlight');
        if (!spotlight) return;
        card.addEventListener('mousemove', function (e) {
            var rect = card.getBoundingClientRect();
            spotlight.style.setProperty('--mx', (e.clientX - rect.left) + 'px');
            spotlight.style.setProperty('--my', (e.clientY - rect.top) + 'px');
        });
    });

    /* ========== VIDEO MODAL ========== */
    var videoModal = document.getElementById('videoModal');
    var videoFrame = document.getElementById('videoFrame');
    var videoSrc = 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1';

    function openVideoModal() {
        videoFrame.src = videoSrc;
        videoModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeVideoModal() {
        videoModal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(function () { videoFrame.src = ''; }, 400);
    }

    var playIcon = document.querySelector('.play-icon');
    if (playIcon) playIcon.addEventListener('click', openVideoModal);
    var watchDemoBtn = document.querySelector('.btn-secondary');
    if (watchDemoBtn) watchDemoBtn.addEventListener('click', function (e) { e.preventDefault(); openVideoModal(); });
    var modalClose = document.getElementById('videoModalClose');
    if (modalClose) modalClose.addEventListener('click', closeVideoModal);
    if (videoModal) {
        videoModal.addEventListener('click', function (e) { if (e.target === videoModal) closeVideoModal(); });
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeVideoModal(); });

    /* ========== HORIZONTAL SCROLL DEMO (JS-pinned) ========== */
    var demoWrapper = document.querySelector('.demo-track-wrapper');
    var demoSticky = document.querySelector('.demo-track-sticky');
    var demoTrack = document.getElementById('demoTrack');
    var demoFrames = document.querySelectorAll('.demo-frame');
    var demoDots = document.querySelectorAll('.demo-progress-dot');
    if (demoWrapper && demoSticky && demoTrack && demoFrames.length) {
        window.addEventListener('scroll', function () {
            var wrapRect = demoWrapper.getBoundingClientRect();
            var wrapH = wrapRect.height - window.innerHeight;
            if (wrapH <= 0) return;
            if (wrapRect.top > 0) {
                demoSticky.classList.remove('pinned', 'unpinned');
            } else if (wrapRect.top <= 0 && wrapRect.bottom >= window.innerHeight) {
                demoSticky.classList.add('pinned');
                demoSticky.classList.remove('unpinned');
            } else {
                demoSticky.classList.remove('pinned');
                demoSticky.classList.add('unpinned');
            }
            var progress = Math.min(Math.max(Math.abs(wrapRect.top) / wrapH, 0), 1);
            var frameW = demoFrames[0].offsetWidth + 40;
            var maxScroll = frameW * (demoFrames.length - 1);
            demoTrack.style.transform = 'translateX(' + (-progress * maxScroll) + 'px)';
            var idx = Math.min(Math.floor(progress * demoFrames.length), demoFrames.length - 1);
            demoFrames.forEach(function (f, i) { f.classList.toggle('active', i === idx); });
            demoDots.forEach(function (d, i) { d.classList.toggle('active', i === idx); });
            var tw = demoFrames[idx].querySelector('.demo-typewriter');
            if (tw && !tw.dataset.typed) {
                tw.dataset.typed = '1';
                typeWriter(tw, tw.dataset.text);
            }
        }, { passive: true });
    }

    /* ========== TYPEWRITER ========== */
    function typeWriter(el, text, speed) {
        speed = speed || 35;
        el.innerHTML = '';
        var i = 0;
        var cursor = document.createElement('span');
        cursor.className = 'cursor';
        function type() {
            if (i < text.length) {
                el.textContent = text.slice(0, i + 1);
                el.appendChild(cursor);
                i++;
                setTimeout(type, speed);
            }
        }
        type();
    }

    /* ========== COUNTER ANIMATION ========== */
    function animateCounter(el, target, prefix, dur) {
        prefix = prefix || '$';
        dur = dur || 1500;
        var start = performance.now();
        function step(now) {
            var elapsed = now - start;
            var p = Math.min(elapsed / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = prefix + Math.round(eased * target).toLocaleString();
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    var offerAmounts = document.querySelectorAll('.offer-projection .amount');
    var counterObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                animateCounter(e.target, parseInt(e.target.dataset.count));
                counterObs.unobserve(e.target);
            }
        });
    }, { threshold: 0.5 });
    offerAmounts.forEach(function (el) { counterObs.observe(el); });

    /* ========== EARNINGS CALCULATOR ========== */
    var calcSlider = document.getElementById('calcSlider');
    var calcViews = document.getElementById('calcViews');
    var calcResult = document.getElementById('calcResult');
    var calcBreakdown = document.getElementById('calcBreakdown');
    var projCards = document.querySelectorAll('.offer-projection');
    var projBase = [
        { views: 5000, label: '1 demo video · {v} views' },
        { views: 25000, label: '10 videos · blog · social' },
        { views: 100000, label: '100 agency clients' }
    ];
    function calcEarnings(views) {
        return Math.round(views * 0.02 * 0.10 * 149 * 0.5 * 12);
    }
    if (calcSlider) {
        calcSlider.addEventListener('input', function () {
            var views = parseInt(calcSlider.value);
            var annual = calcEarnings(views);
            calcViews.textContent = views.toLocaleString();
            calcResult.textContent = '$' + annual.toLocaleString();
            calcBreakdown.textContent = views.toLocaleString() + ' views x 2% CTR x 10% conv x $149 x 50% x 12mo = $' + annual.toLocaleString() + '/year';
            var ratio = views / 5000;
            projCards.forEach(function (card, i) {
                var baseViews = projBase[i].views;
                var scaledViews = Math.round(baseViews * ratio);
                var earnings = calcEarnings(scaledViews);
                var amountEl = card.querySelector('.amount');
                if (amountEl) { amountEl.textContent = '$' + earnings.toLocaleString(); amountEl.dataset.count = earnings; }
                var descEl = card.querySelector('.desc');
                if (descEl && i === 0) descEl.textContent = '1 demo video · ' + (scaledViews / 1000).toFixed(0) + 'K views';
            });
        });
    }

    /* ========== PERSONA TABS ========== */
    document.querySelectorAll('.persona-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.tab;
            document.querySelectorAll('.persona-tab').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            document.querySelectorAll('.persona-panel').forEach(function (p) {
                p.classList.remove('active');
                if (p.dataset.panel === target) p.classList.add('active');
            });
        });
    });

    /* ========== PILLARS PROGRESS ========== */
    var pillarsSection = document.querySelector('.pillars-section');
    var pillarsProgress = document.getElementById('pillarsProgress');
    var pillarsProgressFill = document.getElementById('pillarsProgressFill');
    if (pillarsSection && pillarsProgress) {
        window.addEventListener('scroll', function () {
            var rect = pillarsSection.getBoundingClientRect();
            var inView = rect.top < window.innerHeight && rect.bottom > 0;
            pillarsProgress.classList.toggle('visible', inView);
            if (inView) {
                var p = Math.min(Math.max((-rect.top) / (rect.height - window.innerHeight), 0), 1);
                pillarsProgressFill.style.height = (p * 100) + '%';
            }
        }, { passive: true });
    }

    /* ========== OBJECTION SLASH REVEAL ========== */
    var objPairs = document.querySelectorAll('.objection-pair');
    var objObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                setTimeout(function () { e.target.classList.add('revealed'); }, 300);
                objObs.unobserve(e.target);
            }
        });
    }, { threshold: 0.4 });
    objPairs.forEach(function (el) { objObs.observe(el); });

    /* ========== TERMINAL SEQUENCE ========== */
    var terminalBody = document.getElementById('terminalBody');
    if (terminalBody) {
        var termLines = terminalBody.querySelectorAll('.terminal-line');
        var termObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    var delay = 0;
                    termLines.forEach(function (line) {
                        setTimeout(function () { line.classList.add('visible'); }, delay);
                        delay += 180;
                    });
                    termObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.3 });
        termObs.observe(terminalBody);
    }

    /* ========== SMOOTH SCROLL ========== */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.querySelector(a.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    /* ========== PARTICLE CANVAS (close section) ========== */
    var canvas = document.getElementById('particleCanvas');
    if (canvas) {
        var ctx = canvas.getContext('2d');
        var particles = [];
        var particleCount = 60;

        function resizeCanvas() {
            var parent = canvas.parentElement;
            canvas.width = parent.offsetWidth;
            canvas.height = parent.offsetHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        function Particle() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.vx = (Math.random() - 0.5) * 0.3;
            this.vy = (Math.random() - 0.5) * 0.3;
            this.r = Math.random() * 2 + 0.5;
            this.alpha = Math.random() * 0.3 + 0.1;
            var colors = ['124,58,237', '236,72,153', '6,182,212'];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }
        Particle.prototype.update = function () {
            this.x += this.vx;
            this.y += this.vy;
            if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
            if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
        };
        Particle.prototype.draw = function () {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(' + this.color + ',' + this.alpha + ')';
            ctx.fill();
        };

        for (var i = 0; i < particleCount; i++) particles.push(new Particle());

        var particleVisible = false;
        var particleObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { particleVisible = e.isIntersecting; });
        }, { threshold: 0.1 });
        particleObs.observe(canvas.parentElement);

        function animateParticles() {
            requestAnimationFrame(animateParticles);
            if (!particleVisible) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(function (p) { p.update(); p.draw(); });
            // Draw connections
            for (var i = 0; i < particles.length; i++) {
                for (var j = i + 1; j < particles.length; j++) {
                    var dx = particles[i].x - particles[j].x;
                    var dy = particles[i].y - particles[j].y;
                    var dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 120) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = 'rgba(234,88,12,' + (0.08 * (1 - dist / 120)) + ')';
                        ctx.stroke();
                    }
                }
            }
        }
        animateParticles();
    }

});
