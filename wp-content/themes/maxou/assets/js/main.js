/* Maxou : interactions du site (menu, accordéons, filtres, vidéos, animations). */
(function () {
	var $ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); },
		rm = matchMedia('(prefers-reduced-motion:reduce)').matches;

	/* menu : fond plein en descendant, verre en remontant */
	var pill = $('#pill')[0], bg = $('#burger')[0], ly = 0;
	if (pill && bg) bg.onclick = function () { var o = pill.classList.toggle('open'); bg.setAttribute('aria-expanded', o); };
	/* sous-menus : ouverts au survol (ordinateur) ; le bouton flèche (ou le bouton parent du menu de secours)
	   les ouvre au toucher. En quittant le menu à la souris, le sous-menu se referme. */
	$('.dd').forEach(function (dd) {
		var t = $('.dd-t', dd)[0] || $('button.dd-b', dd)[0];
		if (t) t.onclick = function () { var o = dd.classList.toggle('open'); t.setAttribute('aria-expanded', o); };
		dd.addEventListener('mouseleave', function () { dd.classList.remove('open'); if (t) t.setAttribute('aria-expanded', false); if (dd.contains(document.activeElement)) document.activeElement.blur(); });
	});
	if (pill) addEventListener('scroll', function () { var y = window.scrollY; if (y < 24) pill.classList.remove('solid'); else if (y > ly + 4) pill.classList.add('solid'); else if (y < ly - 4) pill.classList.remove('solid'); ly = y; }, { passive: true });


	/* menu de filtres (pages photos et vidéos) : flèches haut / bas et filtres, collé en haut de l'écran au défilement ;
	   sur mobile, regroupé sous « Filtrer » qui ouvre la liste en dessous */
	var fsel = $('.fsel')[0];
	if (fsel) {
		var mob = $('.fs-mob', fsel)[0], fbtn = $('.fs-f button', fsel),
			go = function (y) { if (window.maxouLenis) window.maxouLenis.scrollTo(y); else window.scrollTo({ top: y, behavior: rm ? 'auto' : 'smooth' }); };
		$('.fb-up', fsel)[0].onclick = function () { go(0); };
		$('.fb-down', fsel)[0].onclick = function () { go(document.documentElement.scrollHeight); };
		mob.onclick = function () { var o = fsel.classList.toggle('open'); mob.setAttribute('aria-expanded', o); };
		document.addEventListener('click', function (e) { if (!fsel.contains(e.target)) { fsel.classList.remove('open'); mob.setAttribute('aria-expanded', false); } });
		var stuck = function () { fsel.classList.toggle('stuck', fsel.getBoundingClientRect().top <= parseFloat(getComputedStyle(fsel).top) + 1); };
		addEventListener('scroll', stuck, { passive: true });
		stuck();
		fbtn.forEach(function (b) {
			b.onclick = function () {
				fbtn.forEach(function (k) { k.setAttribute('aria-pressed', k === b); });
				fsel.classList.remove('open');
				mob.setAttribute('aria-expanded', false);
				mob.textContent = b.dataset.f === 'all' ? 'Filtrer' : b.textContent;
				document.dispatchEvent(new CustomEvent(fsel.dataset.cible === 'videos' ? 'maxou:filtre-video' : 'maxou:filtre-photo', { detail: b.dataset.f }));
			};
		});
	}

	/* page de vidéos : les sections 2 colonnes hors catégorie disparaissent en fondu, les autres
	   réapparaissent, puis le fond en pointillés est redistribué (une section affichée sur deux). */
	var vsecs = $('.sec.mt[data-cat]'), vT;
	if (vsecs.length) document.addEventListener('maxou:filtre-video', function (e) {
		var f = e.detail, G = window.gsap && !rm ? gsap : null,
			ok = function (s) { return f === 'all' || s.dataset.cat.split(' ').indexOf(f) > -1; },
			out = vsecs.filter(function (s) { return !s.hidden && !ok(s); });
		clearTimeout(vT);
		if (G) { G.killTweensOf(vsecs); if (out.length) G.to(out, { opacity: 0, y: 24, duration: .35, ease: 'power2.in' }); }
		vT = setTimeout(function () {
			vsecs.forEach(function (s) {
				var was = s.hidden;
				s.hidden = !ok(s);
				if (G) { G.set(s, { opacity: 1, y: 0 }); if (was && !s.hidden) G.fromTo(s, { opacity: 0, y: 24 }, { opacity: 1, y: 0, duration: .5, ease: 'power2.out' }); }
			});
			var k = 0;
			$('.hero, .ph-hero, .sec').forEach(function (s) {
				if (s.hidden) return;
				k++;
				if (s.classList.contains('mt')) s.classList.toggle('dots', k >= 2 && k % 2 === 0);
			});
			if (window.ScrollTrigger) ScrollTrigger.refresh();
			var first = vsecs.filter(function (s) { return !s.hidden; })[0] || vsecs[0], y = first.getBoundingClientRect().top + window.scrollY - 200;
			if (window.scrollY > y) { if (window.maxouLenis) window.maxouLenis.scrollTo(y); else window.scrollTo({ top: y, behavior: rm ? 'auto' : 'smooth' }); }
		}, out.length && G ? 380 : 0);
	});

	/* vidéo de fond : lecture automatique en muet sur tous les navigateurs.
	   YouTube passe par l'API IFrame (mute puis play explicites, que Safari exige) ; si le navigateur
	   bloque malgré tout (mode économie d'énergie iOS, réglage « jamais de lecture auto »), la lecture
	   repart au premier geste du visiteur. La miniature reste visible tant que la vidéo ne joue pas. */
	var players = [], files = $('.vid video'), kick = function () {
		players.forEach(function (p) { try { p.mute(); p.playVideo(); } catch (e) {} });
		files.forEach(function (v) { v.muted = true; var r = v.play(); if (r && r.catch) r.catch(function () {}); });
	};
	var hv = $('.vid[data-yt]');
	if (hv.length) {
		window.onYouTubeIframeAPIReady = function () {
			hv.forEach(function (v) {
				var id = v.dataset.yt;
				players.push(new YT.Player(v.firstElementChild, {
					videoId: id,
					playerVars: { autoplay: 1, mute: 1, controls: 0, loop: 1, playlist: id, playsinline: 1, modestbranding: 1, rel: 0, iv_load_policy: 3, disablekb: 1, fs: 0, origin: location.origin },
					events: {
						onReady: function (e) { e.target.mute(); e.target.playVideo(); },
						onStateChange: function (e) {
							if (e.data === 1) v.classList.add('on');
							if (e.data === 0) { e.target.seekTo(0); e.target.playVideo(); }
						}
					}
				}));
			});
		};
		var tag = document.createElement('script'); tag.src = 'https://www.youtube.com/iframe_api'; tag.async = true; document.head.appendChild(tag);
	}
	if (hv.length || files.length) {
		setTimeout(kick, 1500);
		['pointerdown', 'touchstart', 'keydown'].forEach(function (ev) { addEventListener(ev, kick, { once: true, passive: true }); });
		document.addEventListener('visibilitychange', function () { if (!document.hidden) kick(); });
	}

	/* accordéons : un seul ouvert à la fois, par section */
	$('.sv').forEach(function (sv) {
		$('.sv-h', sv).forEach(function (b) {
			b.onclick = function () {
				var i = b.parentNode, o = !i.classList.contains('open');
				$('.sv-i', sv).forEach(function (k) { k.classList.remove('open'); $('.sv-h', k)[0].setAttribute('aria-expanded', false); });
				if (o) { i.classList.add('open'); b.setAttribute('aria-expanded', true); }
			};
		});
	});

	/* filtres : fondu des vidéos masquées et affichées, par section */
	$('.sec-liste').forEach(function (sec) {
		var cards = $('.card', sec);
		$('.chip', sec).forEach(function (c) {
			c.onclick = function () {
				$('.chip', sec).forEach(function (k) { k.setAttribute('aria-pressed', k === c); });
				var f = c.dataset.f, ok = function (k) { return f === 'all' || k.dataset.t.split(' ').indexOf(f) > -1; };
				var G = window.gsap && !rm ? gsap : null, out = cards.filter(function (k) { return !k.hidden && !ok(k); });
				var show = function () {
					cards.forEach(function (k) { var was = k.hidden; k.hidden = !ok(k); if (G && was && !k.hidden) G.fromTo(k, { opacity: 0, y: 16 }, { opacity: 1, y: 0, duration: .45, ease: 'power2.out' }); });
					if (window.ScrollTrigger) ScrollTrigger.refresh();
				};
				if (G) {
					G.killTweensOf(cards);
					cards.forEach(function (k) { if (!k.hidden && ok(k)) G.to(k, { opacity: 1, y: 0, duration: .3 }); });
					if (out.length) G.to(out, { opacity: 0, y: 16, duration: .3, ease: 'power2.in', onComplete: function () { G.set(out, { opacity: 1, y: 0 }); show(); } });
					else show();
				} else show();
			};
		});
	});

	/* vidéos : miniature, lecture au clic */
	var mk = function (id) { var f = document.createElement('iframe'); f.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&playsinline=1&modestbranding=1&iv_load_policy=3'; f.title = 'Vidéo'; f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen'; f.allowFullscreen = true; f.referrerPolicy = 'strict-origin-when-cross-origin'; return f; };
	$('.card').forEach(function (c) {
		var th = $('.th', c)[0], id = c.dataset.yt;
		if (!th || !id) return;
		var play = function () { if (th.classList.contains('playing')) return; th.innerHTML = ''; th.classList.add('playing'); th.removeAttribute('role'); th.appendChild(mk(id)); };
		th.addEventListener('click', play);
		th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); play(); } });
	});

	/* téléphone masqué : révélé au clic sur tous les boutons à la fois */
	var phs = $('.ph');
	if (phs.length) {
		var num = atob(phs[0].dataset.p), digits = num.replace(/[^\d+]/g, ''), href = 'tel:' + (digits.charAt(0) === '0' ? '+33' + digits.slice(1) : digits);
		phs.forEach(function (b) { b.onclick = function () { $('.ph').forEach(function (p) { var a = document.createElement('a'); a.href = href; a.textContent = num; a.className = p.className; p.replaceWith(a); }); }; });
	}

	/* animations */
	if (!window.gsap || rm) return;
	gsap.registerPlugin(ScrollTrigger);
	if (window.Lenis) {
		var L = new Lenis({ lerp: .09, smoothWheel: true });
		window.maxouLenis = L;
		L.on('scroll', ScrollTrigger.update);
		gsap.ticker.add(function (t) { L.raf(t * 1000); });
		gsap.ticker.lagSmoothing(0);
		$('a[href*="#"]').forEach(function (a) {
			a.addEventListener('click', function (e) {
				var u = new URL(a.href, location.href);
				if (u.pathname !== location.pathname || u.hash.length < 2) return;
				var t = document.getElementById(decodeURIComponent(u.hash.slice(1)));
				if (!t) return;
				e.preventDefault(); L.scrollTo(t, { offset: -70 }); if (pill) pill.classList.remove('open');
			});
		});
	}
	/* titres : apparition lettre par lettre */
	$('[data-wave]').forEach(function (el) {
		var t = el.textContent; el.setAttribute('aria-label', t);
		el.innerHTML = t.split(' ').map(function (w) { return '<span class="w" aria-hidden="true">' + w.split('').map(function (c) { return '<span class="ch">' + c.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span>'; }).join('') + '</span>'; }).join(' ');
		var ch = $('.ch', el), hero = !!el.closest('.hero, .ph-hero');
		gsap.from(ch, { yPercent: 55, opacity: 0, duration: .6, ease: 'power3.out', stagger: { amount: Math.min(.5, ch.length * .03) }, delay: hero ? .15 : 0, scrollTrigger: hero ? null : { trigger: el, start: 'top 88%', once: true } });
	});
	/* fondu des sections */
	$('[data-fade]').forEach(function (el) { gsap.from(el, { opacity: 0, y: 36, duration: .9, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 90%', once: true } }); });
	$('.card').forEach(function (el, i) { gsap.from(el, { opacity: 0, y: 40, duration: .8, delay: (i % 2) * .1, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 92%', once: true } }); });
	/* parallaxe des photos */
	if (matchMedia('(hover:hover)').matches) {
		$('.fo').forEach(function (fo) {
			fo.addEventListener('pointermove', function (e) {
				var r = fo.getBoundingClientRect(), px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
				$('.fp', fo).forEach(function (p) { var d = +p.dataset.depth; gsap.to(p, { x: px * d * 2, y: py * d * 2, duration: .9, ease: 'power2.out', overwrite: 'auto' }); });
			});
		});
	}
})();
