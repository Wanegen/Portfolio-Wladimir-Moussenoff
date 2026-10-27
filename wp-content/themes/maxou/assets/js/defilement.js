/* Maxou : section « Défilement images ».
 * Deux colonnes de photos de même largeur, droites et largement espacées, la seconde colonne décalée
 * vers le bas d'une demi-photo (effet en quinconce). Chaque photo garde son format d'origine (pas de
 * recadrage) ; une photo paysage prend toute la largeur des 2 colonnes, puis le quinconce reprend.
 * Les photos se suivent dans l'ordre du répéteur, chacune dans la colonne la moins haute, une seule fois.
 * Poids maîtrisé : la hauteur totale est calculée d'avance (aucun saut de défilement), mais seules les
 * photos proches de l'écran existent dans la page : elles sont créées une demi-hauteur d'écran à l'avance
 * et retirées une fois sorties. Chaque photo apparaît en fondu, avec une légère montée, en entrant à l'écran.
 * Filtres : la barre flottante envoie l'événement « maxou:filtre-photo » ; les photos affichées disparaissent
 * avec l'animation inverse, puis la série ne contenant que les photos de la catégorie se recompose.
 */
(function () {
	Array.prototype.forEach.call(document.querySelectorAll('.ig-feed'), function (feed) {
		var tpl = feed.querySelector('template.ig-src');
		if (!tpl) return;
		var src = Array.prototype.slice.call(tpl.content.querySelectorAll('img')).map(function (im) {
			var w = +im.getAttribute('width') || 4, h = +im.getAttribute('height') || 5;
			return { src: im.getAttribute('src'), srcset: im.getAttribute('srcset') || '', alt: im.getAttribute('alt') || '', ratio: h / w, wide: w > h * 1.05, cats: (im.getAttribute('data-cat') || '').split(' ').filter(Boolean) };
		});
		if (!src.length) return;

		var all = src, n = src.length, items = [], live = new Map(), ticking = false, filterT;

		/* apparition : chaque photo se révèle (fondu + légère montée) quand elle entre à l'écran */
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('vis'); io.unobserve(e.target); } });
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

		/* Positions (en px, relatives à la section) des photos. */
		function layout() {
			var W = feed.clientWidth, small = W < 600,
				gx = Math.round(W * (small ? 0.06 : 0.12)),         // écart entre les 2 colonnes
				cw = Math.floor((W - gx) / 2),                      // les 2 colonnes occupent toute la largeur du conteneur
				x0 = 0,
				portraitH = Math.round(cw * 1.5),
				gy = Math.round(portraitH * (small ? 0.22 : 0.4)),  // écart vertical entre deux photos
				half = Math.round((portraitH + gy) / 2),
				colY = [0, half];                                   // la 2e colonne commence une demi-photo plus bas
			items = [];
			var gw = Math.round(gy / 2),                            // écart réduit au-dessus et au-dessous d'un paysage
				prevWideEnd = -1;                                   // bas du dernier paysage, s'il précède directement
			for (var i = 0; i < n; i++) {
				var p = src[i];
				if (p.wide) {
					// paysage : toute la largeur des 2 colonnes, un demi-écart sous la plus basse photo (ou sous le
					// paysage précédent), puis le quinconce reprend avec un décalage réduit
					var fw = cw * 2 + gx, fh = Math.round(fw * p.ratio),
						fy = prevWideEnd >= 0 ? prevWideEnd + gw : (items.length ? Math.max(colY[0], colY[1]) - gy + gw : 0);
					items.push({ p: p, i: i, w: fw, h: fh, x: x0, y: fy });
					colY = [fy + fh + gw, fy + fh + gw + Math.round(half / 2)];
					prevWideEnd = fy + fh;
					continue;
				}
				prevWideEnd = -1;
				var c = colY[0] <= colY[1] ? 0 : 1, h = Math.round(cw * p.ratio);
				items.push({ p: p, i: i, w: cw, h: h, x: x0 + c * (cw + gx), y: colY[c] });
				colY[c] += h + gy;
			}
			feed.style.height = Math.max(colY[0], colY[1]) - gy + 'px';
			if (window.ScrollTrigger) setTimeout(function () { ScrollTrigger.refresh(); }, 300);
		}

		function make(it) {
			var el = document.createElement('div'), im = new Image();
			el.className = 'ig-it';
			el.style.width = it.w + 'px';
			el.style.height = it.h + 'px';
			el.style.transform = 'translate3d(' + it.x + 'px,' + it.y + 'px,0)';
			im.decoding = 'async';
			im.draggable = false;
			if (it.p.srcset) { im.srcset = it.p.srcset; im.sizes = it.w + 'px'; }   // le navigateur prend la version nette pour l'écran (Retina compris)
			im.onload = function () { im.classList.add('on'); };
			im.src = it.p.src;
			im.alt = it.p.alt;
			el.appendChild(im);
			feed.appendChild(el);
			io.observe(el);
			return el;
		}

		function update() {
			ticking = false;
			var rect = feed.getBoundingClientRect(), vh = window.innerHeight,
				top = -rect.top - vh * 0.5, bottom = -rect.top + vh * 1.5, keep = new Set();   // une demi-hauteur d'écran de marge au-dessus et au-dessous
			items.forEach(function (it) {
				if (it.y + it.h < top || it.y > bottom) return;
				if (!live.has(it.i)) live.set(it.i, make(it));
				keep.add(it.i);
			});
			live.forEach(function (el, key) { if (!keep.has(key)) { io.unobserve(el); el.remove(); live.delete(key); } });
		}

		function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }

		function rebuild() {
			live.forEach(function (el) { io.unobserve(el); el.remove(); });
			live.clear();
			if (!n) { items = []; feed.style.height = '0px'; return; }
			layout();
			update();
		}

		/* filtre : sortie animée des photos affichées, puis nouvelle série ; une galerie sans photo de la
		   catégorie est masquée en entier (titre compris) */
		var sec = feed.closest('.ig');
		document.addEventListener('maxou:filtre-photo', function (e) {
			var f = e.detail;
			clearTimeout(filterT);
			live.forEach(function (el) { el.classList.add('out'); });
			filterT = setTimeout(function () {
				src = f === 'all' ? all : all.filter(function (p) { return p.cats.indexOf(f) > -1; });
				n = src.length;
				if (sec) sec.hidden = !n;
				rebuild();
			}, 650);
		});

		addEventListener('scroll', onScroll, { passive: true });
		var rt, lastW = feed.clientWidth;
		addEventListener('resize', function () {
			clearTimeout(rt);
			rt = setTimeout(function () { if (feed.clientWidth !== lastW) { lastW = feed.clientWidth; rebuild(); } else update(); }, 150);
		});
		rebuild();
	});

	/* après un filtre (une fois toutes les galeries recomposées) : la page va au début de la première
	   galerie affichée, sous le menu de filtres collé en haut, et les positions sont recalculées */
	var goT;
	document.addEventListener('maxou:filtre-photo', function () {
		clearTimeout(goT);
		goT = setTimeout(function () {
			if (window.ScrollTrigger) ScrollTrigger.refresh();
			var first = Array.prototype.filter.call(document.querySelectorAll('.ig'), function (s) { return !s.hidden; })[0];
			if (first) {
				var y = first.getBoundingClientRect().top + window.scrollY - 140;
				if (window.maxouLenis) window.maxouLenis.scrollTo(y); else window.scrollTo({ top: y, behavior: 'smooth' });
			}
			window.dispatchEvent(new Event('scroll'));
		}, 700);
	});
})();
