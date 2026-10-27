/* Maxou : écran de chargement et transitions entre les pages.
 * - Première page vue dans l'onglet (classe html.mx-ld posée dans le <head>) : écran avec le fond « objectif »
 *   de la section Photographie, le WM au centre et une barre qui se remplit pendant que tout le site charge
 *   (images, polices, scripts). À 100 %, l'écran s'efface.
 * - Pages suivantes (html.mx-in) : la page arrive par un fondu depuis le noir ; en cliquant un lien interne,
 *   la page part en fondu au noir avec le WM en jaune/vert, puis la suivante s'ouvre.
 */
(function () {
	var d = document.documentElement, rm = matchMedia('(prefers-reduced-motion:reduce)').matches;
	try { sessionStorage.setItem('mxSeen', '1'); } catch (e) {}

	/* ÉCRAN DE CHARGEMENT */
	var ld = document.querySelector('.ld');
	if (ld && d.classList.contains('mx-ld')) {
		var fill = ld.querySelector('.ld-bar i'), shown = 0, target = 0, done = false, t0 = performance.now(),
			imgs = Array.prototype.slice.call(document.images).filter(function (im) { return im.loading !== 'lazy'; }),
			total = imgs.length + 2, loaded = 0;

		var bump = function () { loaded++; target = Math.max(target, Math.min(0.9, loaded / total)); };
		imgs.forEach(function (im) { if (im.complete) bump(); else { im.addEventListener('load', bump, { once: true }); im.addEventListener('error', bump, { once: true }); } });
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(bump); else bump();
		bump(); // le document lui-même

		var finish = function () {
			if (done) return;
			done = true;
			var wait = Math.max(0, 900 - (performance.now() - t0));            // l'écran reste visible au moins ~1 s
			setTimeout(function () { target = 1; }, wait);
		};
		if (document.readyState === 'complete') finish(); else addEventListener('load', finish);
		setTimeout(finish, 8000);                                            // sécurité : jamais bloqué

		(function step() {
			shown += (target - shown) * 0.12;
			if (target === 1 && shown > 0.995) shown = 1;
			fill.style.transform = 'scaleX(' + shown.toFixed(4) + ')';
			if (shown < 1) return requestAnimationFrame(step);
			setTimeout(function () {
				d.classList.add('mx-ld-out');
				setTimeout(function () { d.classList.remove('mx-ld', 'mx-ld-out'); ld.remove(); }, 700);
			}, 250);
		})();
	}

	/* ARRIVÉE SUR UNE PAGE : fondu depuis le noir */
	var reveal = function () { requestAnimationFrame(function () { requestAnimationFrame(function () { d.classList.remove('mx-in'); }); }); };
	if (d.classList.contains('mx-in')) reveal();
	addEventListener('pageshow', function (e) { if (e.persisted) { d.classList.remove('mx-out'); reveal(); } });   // retour arrière

	/* DÉPART VERS UNE AUTRE PAGE : fondu au noir */
	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
		var u = new URL(a.href, location.href);
		if (u.origin !== location.origin || !/^https?:$/.test(u.protocol)) return;
		if (/\/wp-(admin|login)/.test(u.pathname)) return;
		if (u.pathname === location.pathname && u.search === location.search) return;   // ancre dans la même page
		if (rm) return;
		e.preventDefault();
		d.classList.add('mx-out');
		setTimeout(function () { location.href = a.href; }, 450);
	});
})();
