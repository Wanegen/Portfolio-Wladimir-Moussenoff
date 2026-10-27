/* La Recette - outil front */
(function () {
	'use strict';

	var C = window.LR;
	if (!C || window.top !== window.self) return;

	var S = {
		pins: [],
		others: [],   // points des autres pages
		who: '',      // filtre « Assigné à » ('' = tous, '__me', '__none', id)
		openComments: {},
		adding: false,
		visible: true,
		panelOpen: false,
		filter: 'open',
		draft: null,   // { anchor, marker }
		active: null,  // id du pin dont la bulle est ouverte
		confirmDelete: false,
		cmtDraft: {},
		cmtEdit: {},   // commentaires en cours de modification { cid: texte }  // brouillons de commentaires { id: { text, files } }
		tbPos: null    // position de la barre d'outils déplacée { x, y }
	};
	var store = {
		get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
		set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
	};
	// Tutoriel vu : localStorage + cookie d'un an (repli si le stockage local est bloqué ou vidé).
	function tutoSeen() {
		if (store.get('lr_tuto')) return true;
		try { return /(?:^|;\s*)lr_tuto=1/.test(document.cookie); } catch (e) { return false; }
	}
	function markTutoSeen() {
		store.set('lr_tuto', '1');
		try { document.cookie = 'lr_tuto=1; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); } catch (e) {}
	}
	S.visible = store.get('lr_visible') !== '0';
	try { S.tbPos = JSON.parse(store.get('lr_tb') || 'null'); } catch (e) { S.tbPos = null; }

	/* ---------------------------------------------------------------- */
	/* Helpers                                                           */
	/* ---------------------------------------------------------------- */

	function h(tag, props) {
		var n = document.createElement(tag);
		props = props || {};
		Object.keys(props).forEach(function (k) {
			var v = props[k];
			if (v === null || v === undefined || v === false) return;
			if (k === 'class') n.className = v;
			else if (k === 'text') n.textContent = v;
			else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), v);
			else n.setAttribute(k, v === true ? '' : v);
		});
		for (var i = 2; i < arguments.length; i++) {
			var c = arguments[i];
			if (c === null || c === undefined || c === false) continue;
			if (Array.isArray(c)) c.forEach(function (x) { if (x) n.appendChild(typeof x === 'string' ? document.createTextNode(x) : x); });
			else n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		}
		return n;
	}

	function api(action, data) {
		var fd = new FormData();
		fd.append('action', 'lr_' + action);
		fd.append('nonce', C.nonce);
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (v instanceof Blob) fd.append(k, v, 'capture.' + ((v.type.split('/')[1] || 'bin').split(';')[0]));
			else fd.append(k, v);
		});
		return fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { throw new Error('Réponse invalide du serveur'); }); })
			.then(function (j) {
				if (!j || !j.success) throw new Error((j && j.data && j.data.message) || 'Erreur');
				return j.data;
			});
	}

	function pageKey() { return location.pathname + location.search; }

	function isOurs(node) {
		return !!(node && node.closest && node.closest('#lr-root, #lr-layer'));
	}

	function cssPath(node) {
		if (!(node instanceof Element)) return '';
		var parts = [];
		while (node && node.nodeType === 1 && node !== document.body && node !== document.documentElement) {
			var id = node.id;
			if (id && /^[A-Za-z][\w-]*$/.test(id) && !/\d{4,}/.test(id) && document.querySelectorAll('#' + id).length === 1) {
				parts.unshift('#' + id);
				return parts.join(' > ');
			}
			var idx = 1, sib = node;
			while ((sib = sib.previousElementSibling)) if (sib.tagName === node.tagName) idx++;
			parts.unshift(node.tagName.toLowerCase() + ':nth-of-type(' + idx + ')');
			node = node.parentElement;
		}
		parts.unshift('body');
		return parts.join(' > ');
	}

	function matchWho(p) {
		if (!S.who) return true;
		if (S.who === '__none') return !p.assignee;
		if (S.who === '__me') return !!C.user.me && p.assignee === C.user.me;
		return p.assignee === S.who;
	}
	function pinsFiltered() {
		return S.pins.filter(function (p) { return (S.filter === 'all' || p.status === S.filter) && matchWho(p); });
	}
	function pinNumber(p) { return S.pins.indexOf(p) + 1; }
	function findPin(id) { for (var i = 0; i < S.pins.length; i++) if (S.pins[i].id === id) return S.pins[i]; return null; }
	function findAny(id) { var p = findPin(id); if (p) return p; for (var i = 0; i < S.others.length; i++) if (S.others[i].id === id) return S.others[i]; return null; }

	var toastTimer;
	function toast(msg, err) {
		ui.toast.textContent = msg;
		ui.toast.className = 'lr-toast is-on' + (err ? ' is-err' : '');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { ui.toast.className = 'lr-toast'; }, 3000);
	}

	/* ---------------------------------------------------------------- */
	/* Icônes                                                            */
	/* ---------------------------------------------------------------- */

	var ICON = {
		pin: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><path d="M12 7v6M9 10h6"/></svg>',
		list: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
		eye: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
		eyeOff: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.1A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a10 10 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>',
		camera: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>',
		video: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/></svg>',
		chef: '<svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor"><circle cx="5.6" cy="7.6" r="3.3"/><circle cx="14.4" cy="7.6" r="3.3"/><circle cx="10" cy="5.6" r="3.9"/><rect x="5" y="8" width="10" height="6.2"/><rect x="5" y="15" width="10" height="2.6" rx=".6"/></svg>',
		help: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7M12 17h.01"/></svg>',
		chat: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>',
		user: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>',
		calendar: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
		desktop: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
		mobile: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg>',
		tablet: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="3"/><path d="M11 18h2"/></svg>',
		check: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
		grip: '<svg viewBox="0 0 24 12" width="22" height="11" fill="currentColor"><circle cx="6" cy="3" r="1.7"/><circle cx="12" cy="3" r="1.7"/><circle cx="18" cy="3" r="1.7"/><circle cx="6" cy="9" r="1.7"/><circle cx="12" cy="9" r="1.7"/><circle cx="18" cy="9" r="1.7"/></svg>',
		clip: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5l-8.6 8.6a5.5 5.5 0 0 1-7.8-7.8l8.9-8.9a3.7 3.7 0 0 1 5.2 5.2l-8.9 8.9a1.8 1.8 0 0 1-2.6-2.6l8.2-8.2"/></svg>',
		pencil: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg>',
		link: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>',
		close: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>'
	};
	function icon(name) { var s = h('span', { class: 'lr-ico', 'aria-hidden': 'true' }); s.innerHTML = ICON[name]; return s; }

	/* ---------------------------------------------------------------- */
	/* Captures (image webp / vidéo webm) via le partage d'onglet        */
	/* ---------------------------------------------------------------- */

	var CAP = {
		supported: !!(C.media && navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia),
		video: !!(C.media && navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia && window.MediaRecorder),
		busy: false
	};

	function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

	function requestTab() {
		// Appelé directement dans le clic (geste utilisateur obligatoire).
		return navigator.mediaDevices.getDisplayMedia({
			video: {
				displaySurface: 'browser',
				frameRate: { ideal: 30, max: 30 },
				// Taille réelle de la fenêtre (sinon Chrome capture en 1280 × 720 par défaut).
				width: { ideal: Math.round(window.innerWidth * (window.devicePixelRatio || 1)), max: 3840 },
				height: { ideal: Math.round(window.innerHeight * (window.devicePixelRatio || 1)), max: 2160 }
			},
			audio: false,
			preferCurrentTab: true,
			selfBrowserSurface: 'include',
			surfaceSwitching: 'exclude',
			monitorTypeSurfaces: 'exclude'
		});
	}

	function stopStream(s) { if (s) s.getTracks().forEach(function (t) { t.stop(); }); }

	function setCapturing(on, rec) {
		document.documentElement.classList.toggle('lr-capturing', on);
		document.documentElement.classList.toggle('lr-recording', !!(on && rec));
	}

	function captureError(err) {
		if (err && (err.name === 'NotAllowedError' || err.name === 'AbortError')) return 'Capture annulée.';
		return (err && err.message) || 'Capture impossible.';
	}

	/** Capture de l'onglet visible → Blob webp (1920 px max). */
	function captureImage() {
		var stream = null;
		CAP.busy = true;
		setCapturing(true);
		return requestTab().then(function (s) {
			stream = s;
			var v = document.createElement('video');
			v.muted = true;
			v.playsInline = true;
			v.srcObject = s;
			return v.play().then(function () { return wait(700); }).then(function () {
				// Nouvelle image après la fermeture de la fenêtre d'autorisation.
				return new Promise(function (r) {
					if (v.requestVideoFrameCallback) { v.requestVideoFrameCallback(function () { r(); }); setTimeout(r, 500); }
					else setTimeout(r, 150);
				});
			}).then(function () { return v; });
		}).then(function (v) {
			var w = v.videoWidth, hh = v.videoHeight;
			if (!w || !hh) throw new Error('Image vide, réessayez.');
			var k = Math.min(1, 1920 / w);
			var c = document.createElement('canvas');
			c.width = Math.round(w * k);
			c.height = Math.round(hh * k);
			c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
			return new Promise(function (res, rej) {
				c.toBlob(function (b) { b ? res(b) : rej(new Error('Encodage impossible.')); }, 'image/webp', 0.85);
			});
		}).then(function (blob) {
			return { kind: 'image', blob: blob };
		}).finally(function () {
			stopStream(stream);
			setCapturing(false);
			CAP.busy = false;
		});
	}

	/** Enregistrement de l'onglet → Blob webm, 5 s maximum (bouton Stop possible). */
	function captureVideo() {
		var stream = null, timer = null, tick = null;
		var max = (C.videoMax || 5) * 1000;
		CAP.busy = true;
		setCapturing(true, true);
		ui.recLabel.textContent = 'Autorisez le partage…';
		return requestTab().then(function (s) {
			stream = s;
			var types = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm', 'video/mp4'];
			var mime = '';
			for (var i = 0; i < types.length; i++) { if (MediaRecorder.isTypeSupported(types[i])) { mime = types[i]; break; } }
			var opts = { videoBitsPerSecond: 2500000 };
			if (mime) opts.mimeType = mime;
			var rec = new MediaRecorder(s, opts);
			var chunks = [];
			return new Promise(function (resolve, reject) {
				function stop() { if (rec.state !== 'inactive') rec.stop(); }
				rec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
				rec.onstop = function () { resolve(new Blob(chunks, { type: (rec.mimeType || mime || 'video/webm').split(';')[0] })); };
				rec.onerror = function (e) { reject((e && e.error) || new Error('Enregistrement impossible.')); };
				s.getVideoTracks()[0].addEventListener('ended', stop);
				ui.recStop.onclick = function (e) { e.preventDefault(); e.stopPropagation(); stop(); };
				wait(600).then(function () {
					var t0 = Date.now();
					rec.start(250);
					var upd = function () {
						var left = Math.max(0, Math.ceil((max - (Date.now() - t0)) / 1000));
						ui.recLabel.textContent = 'REC · ' + left + ' s';
					};
					upd();
					tick = setInterval(upd, 250);
					timer = setTimeout(stop, max);
				});
			});
		}).then(function (blob) {
			if (!blob.size) throw new Error('Vidéo vide, réessayez.');
			return { kind: 'video', blob: blob };
		}).finally(function () {
			clearTimeout(timer);
			clearInterval(tick);
			stopStream(stream);
			setCapturing(false);
			CAP.busy = false;
		});
	}

	function runCapture(kind) {
		if (CAP.busy) return Promise.reject(new Error('Capture déjà en cours.'));
		return kind === 'video' ? captureVideo() : captureImage();
	}

	function attachButtons(onPick) {
		if (!CAP.supported) return null;
		return h('div', { class: 'lr-attach' },
			h('button', { type: 'button', class: 'lr-btn lr-btn-sm', title: 'Capture de la fenêtre visible', onclick: function () { onPick('image'); } }, icon('camera'), 'Capture'),
			CAP.video ? h('button', { type: 'button', class: 'lr-btn lr-btn-sm', title: 'Enregistrer 5 secondes de la fenêtre', onclick: function () { onPick('video'); } }, icon('video'), 'Vidéo ' + (C.videoMax || 5) + ' s') : null
		);
	}

	/* Visionneuse ----------------------------------------------------- */

	function openLightbox(m, onDelete) {
		closeLightbox();
		var media = m.type === 'video'
			? h('video', { src: m.url, poster: m.poster || null, controls: true, autoplay: true, loop: true, muted: true, playsinline: true })
			: h('img', { src: m.url, alt: 'Capture' });
		var del = null;
		if (onDelete) {
			del = h('button', { type: 'button', class: 'lr-btn lr-btn-danger lr-btn-sm', text: 'Supprimer la capture', onclick: function () {
				if (del.getAttribute('data-confirm')) { closeLightbox(); onDelete(); return; }
				del.setAttribute('data-confirm', '1');
				del.textContent = 'Confirmer ?';
			} });
		}
		ui.lb = h('div', { class: 'lr-lb', onclick: function (e) { if (e.target === ui.lb) closeLightbox(); } },
			h('div', { class: 'lr-lb-box' },
				media,
				h('div', { class: 'lr-lb-bar' },
					h('a', { href: m.url, target: '_blank', rel: 'noopener', class: 'lr-btn lr-btn-sm', text: 'Ouvrir en grand' }),
					del,
					h('button', { type: 'button', class: 'lr-btn lr-btn-sm', text: 'Fermer', onclick: closeLightbox })
				)
			)
		);
		ui.root.appendChild(ui.lb);
	}

	function closeLightbox() {
		if (ui.lb) { ui.lb.remove(); ui.lb = null; return true; }
		return false;
	}

	function mediaThumb(m, onDelete) {
		var th = m.type === 'video'
			? h('img', { src: m.poster || '', alt: '', loading: 'lazy' })
			: h('img', { src: m.url, alt: '', loading: 'lazy' });
		return h('button', {
			type: 'button',
			class: 'lr-media-item' + (m.type === 'video' ? ' is-video' : ''),
			title: m.type === 'video' ? 'Voir la vidéo' : 'Voir l\'image',
			onclick: function (e) { e.stopPropagation(); openLightbox(m, onDelete || null); }
		}, th, m.type === 'video' ? h('span', { class: 'lr-play', 'aria-hidden': 'true' }, '▶') : null);
	}

	function mediaGallery(p) {
		if (!p.media || !p.media.length) return null;
		return h('div', { class: 'lr-media' }, p.media.map(function (m) {
			return mediaThumb(m, p.canDelete ? function () {
				api('media_delete', { id: p.id, index: m.i }).then(function (d) { replacePin(findAny(p.id) || p, d.pin); toast('Capture supprimée'); }).catch(function (err) { toast(err.message, true); });
			} : null);
		}));
	}

	function replacePin(p, np) {
		np.num = p.num;
		np.here = p.here;
		var i = S.pins.indexOf(p);
		if (i > -1) S.pins[i] = np;
		var j = S.others.indexOf(p);
		if (j > -1) S.others[j] = np;
		var reopen = S.active === p.id;
		renderAll();
		if (reopen) openPopover(np.id);
		return np;
	}

	/* ---------------------------------------------------------------- */
	/* Construction de l'interface                                       */
	/* ---------------------------------------------------------------- */

	var ui = {};

	function build() {
		var root = h('div', { id: 'lr-root' });

		ui.addBtn = h('button', { type: 'button', class: 'lr-tool', title: 'Ajouter un point (Échap pour annuler)', onclick: toggleAdding }, icon('pin'));
		ui.listBtn = h('button', { type: 'button', class: 'lr-tool', title: 'Points de cette page', onclick: togglePanel }, icon('list'));
		ui.badge = h('span', { class: 'lr-badge' });
		ui.listBtn.appendChild(ui.badge);
		ui.eyeBtn = h('button', { type: 'button', class: 'lr-tool', title: 'Afficher / masquer les points', onclick: toggleVisible });
		ui.helpBtn = h('button', { type: 'button', class: 'lr-tool', title: 'Revoir le tutoriel', onclick: function () { openTutorial(); } }, icon('help'));

		ui.toolbar = h('div', { class: 'lr-toolbar', role: 'toolbar', 'aria-label': 'La Recette' },
			h('div', { class: 'lr-brand', title: 'La Recette' }, icon('chef'), h('span', { text: 'La Recette' })),
			ui.addBtn, ui.listBtn, ui.eyeBtn, ui.helpBtn,
			ui.grip = h('button', { type: 'button', class: 'lr-grip', title: 'Déplacer la barre (double-clic : position d\'origine)', 'aria-label': 'Déplacer la barre d\'outils' }, icon('grip'))
		);

		ui.tabs = h('div', { class: 'lr-tabs' });
		ui.list = h('div', { class: 'lr-list' });
		ui.panel = h('aside', { class: 'lr-panel', 'aria-hidden': 'true' },
			h('div', { class: 'lr-panel-head' },
				h('strong', { text: 'La Recette' }),
				h('button', { type: 'button', class: 'lr-x', title: 'Fermer', onclick: togglePanel }, icon('close'))
			),
			ui.tabs,
			ui.whoFilter = h('div', { class: 'lr-filter' }),
			ui.list,
			ui.who = h('div', { class: 'lr-who' }),
			null
		);

		ui.hint = h('div', { class: 'lr-hint', text: 'Cliquez sur l\'élément à commenter · Échap pour annuler' });
		ui.toast = h('div', { class: 'lr-toast', role: 'status' });
		ui.recLabel = h('span', { class: 'lr-rec-label', text: 'REC' });
		ui.recStop = h('button', { type: 'button', class: 'lr-rec-stop', text: 'Stop' });
		ui.rec = h('div', { class: 'lr-rec' }, h('span', { class: 'lr-rec-dot' }), ui.recLabel, ui.recStop);

		root.appendChild(ui.toolbar);
		root.appendChild(ui.panel);
		root.appendChild(ui.hint);
		root.appendChild(ui.toast);
		root.appendChild(ui.rec);

		ui.layer = h('div', { id: 'lr-layer' });
		ui.hl = h('div', { class: 'lr-hl' });
		root.appendChild(ui.hl);

		document.body.appendChild(ui.layer);
		document.body.appendChild(root);
		ui.root = root;

		renderToolbar();
		initToolbarDrag();
	}

	/* Barre d'outils déplaçable -------------------------------------- */

	function applyToolbarPos() {
		var tb = ui.toolbar;
		if (!S.tbPos) {
			tb.classList.remove('is-moved');
			tb.style.left = tb.style.top = '';
			return;
		}
		var w = tb.offsetWidth, hh = tb.offsetHeight;
		var maxX = window.innerWidth - w - 8;
		if (S.panelOpen && window.innerWidth > 720) maxX = Math.min(maxX, window.innerWidth - ui.panel.offsetWidth - w - 12);
		var x = Math.max(8, Math.min(S.tbPos.x, maxX));
		var y = Math.max(8, Math.min(S.tbPos.y, window.innerHeight - hh - 8));
		tb.classList.add('is-moved');
		tb.style.left = x + 'px';
		tb.style.top = y + 'px';
	}

	function initToolbarDrag() {
		var g = ui.grip, tb = ui.toolbar, st = null;
		g.addEventListener('pointerdown', function (e) {
			if (e.button !== 0) return;
			e.preventDefault();
			var r = tb.getBoundingClientRect();
			st = { dx: e.clientX - r.left, dy: e.clientY - r.top };
			try { g.setPointerCapture(e.pointerId); } catch (err) {}
			tb.classList.add('is-dragging');
		});
		g.addEventListener('pointermove', function (e) {
			if (!st) return;
			S.tbPos = { x: e.clientX - st.dx, y: e.clientY - st.dy };
			applyToolbarPos();
		});
		function end() {
			if (!st) return;
			st = null;
			tb.classList.remove('is-dragging');
			if (S.tbPos) S.tbPos = { x: parseFloat(tb.style.left) || 8, y: parseFloat(tb.style.top) || 8 };
			store.set('lr_tb', S.tbPos ? JSON.stringify(S.tbPos) : '');
		}
		g.addEventListener('pointerup', end);
		g.addEventListener('pointercancel', end);
		g.addEventListener('dblclick', function () { S.tbPos = null; store.set('lr_tb', ''); applyToolbarPos(); });
		window.addEventListener('resize', applyToolbarPos);
		applyToolbarPos();
	}

	function renderToolbar() {
		var open = S.pins.filter(function (p) { return p.status === 'open'; }).length;
		ui.badge.textContent = open ? String(open) : '';
		ui.badge.style.display = open ? '' : 'none';
		ui.addBtn.classList.toggle('is-active', S.adding);
		ui.listBtn.classList.toggle('is-active', S.panelOpen);
		ui.eyeBtn.innerHTML = '';
		ui.eyeBtn.appendChild(icon(S.visible ? 'eye' : 'eyeOff'));
		ui.eyeBtn.classList.toggle('is-off', !S.visible);
	}

	/* ---------------------------------------------------------------- */
	/* Panneau latéral                                                   */
	/* ---------------------------------------------------------------- */

	function itemMeta(p) {
		return 'Ticket soumis par ' + p.author + (p.edited ? ' · modifié' : '');
	}

	function keep(p) { return (S.filter === 'all' || p.status === S.filter) && matchWho(p); }

	function renderWhoFilter() {
		ui.whoFilter.innerHTML = '';
		var sel = h('select', { class: 'lr-select', 'aria-label': 'Filtrer par personne assignée', onchange: function () { S.who = sel.value; renderAll(); } });
		var opts = [['', 'Toutes les personnes']];
		if (C.user.me) opts.push(['__me', 'Assignés à moi']);
		opts.push(['__none', 'Non assignés']);
		(C.assignees || []).forEach(function (a) { opts.push([a.id, a.label || a.name]); });
		opts.forEach(function (o) {
			var op = h('option', { value: o[0], text: o[1] });
			if (o[0] === S.who) op.selected = true;
			sel.appendChild(op);
		});
		ui.whoFilter.appendChild(h('label', { class: 'lr-filter-label' }, 'Assigné à', sel));
	}

	/* Pièces jointes des commentaires --------------------------------- */

	var FILE_MAX = { image: 20, video: 100 }; // Mo

	/** Réduit une image dans le navigateur (1920 px, webp) avant l'envoi. */
	function shrinkImage(file) {
		if (!window.createImageBitmap || file.type === 'image/gif') return Promise.resolve(file);
		return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
			var k = Math.min(1, 1920 / Math.max(bmp.width, bmp.height));
			var c = document.createElement('canvas');
			c.width = Math.round(bmp.width * k);
			c.height = Math.round(bmp.height * k);
			c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
			if (bmp.close) bmp.close();
			return new Promise(function (res) {
				c.toBlob(function (b) { res(b && b.size < file.size ? b : file); }, 'image/webp', 0.85);
			});
		}).catch(function () { return file; });
	}

	function cmtDraft(id) {
		if (!S.cmtDraft[id]) S.cmtDraft[id] = { text: '', files: [] };
		return S.cmtDraft[id];
	}

	function commentsBlock(p, elsewhere) {
		var box = h('div', { class: 'lr-comments' });
		if (elsewhere) {
			box.appendChild(h('a', { class: 'lr-goto', href: p.url + '#lr-pin-' + p.id, title: p.page }, icon('link'), h('span', { text: 'Voir le ticket sur sa page' })));
		}
		(p.comments || []).forEach(function (c) {
			var editing = Object.prototype.hasOwnProperty.call(S.cmtEdit, c.id);
			var body = null;
			if (editing) {
				var eta = h('textarea', { class: 'lr-input', rows: '3', maxlength: '3000', 'aria-label': 'Modifier le commentaire', oninput: function () { S.cmtEdit[c.id] = eta.value; } });
				eta.value = S.cmtEdit[c.id];
				var esave = h('button', { type: 'submit', class: 'lr-btn lr-btn-primary lr-btn-sm', text: 'Enregistrer' });
				body = h('form', {
					class: 'lr-comment-edit',
					onsubmit: function (e) {
						e.preventDefault();
						if (eta.value.trim() === c.text) { delete S.cmtEdit[c.id]; renderPanel(); return; }
						esave.disabled = true;
						api('comment_edit', { id: p.id, cid: c.id, text: eta.value.trim() }).then(function (d) {
							delete S.cmtEdit[c.id];
							replacePin(findAny(p.id) || p, d.pin);
							toast('Commentaire modifié');
						}).catch(function (err) { esave.disabled = false; toast(err.message, true); });
					},
					onkeydown: function (e) {
						if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); esave.click(); }
						if (e.key === 'Escape') { e.stopPropagation(); delete S.cmtEdit[c.id]; renderPanel(); }
					}
				}, eta, h('div', { class: 'lr-actions' },
					h('button', { type: 'button', class: 'lr-btn lr-btn-sm', text: 'Annuler', onclick: function () { delete S.cmtEdit[c.id]; renderPanel(); } }),
					esave
				));
				setTimeout(function () { if (eta.isConnected && document.activeElement !== eta) eta.focus({ preventScroll: true }); }, 20);
			} else if (c.text) {
				body = h('div', { class: 'lr-comment-text', text: c.text });
			}
			box.appendChild(h('div', { class: 'lr-comment' + (editing ? ' is-editing' : '') },
				h('div', { class: 'lr-comment-head' },
					h('strong', { text: c.name }),
					h('span', { text: c.date }),
					c.edited ? h('em', { class: 'lr-comment-edited', title: 'Modifié le ' + c.edited, text: 'modifié' }) : null,
					h('span', { class: 'lr-comment-tools' },
						c.canEdit && !editing ? h('button', { type: 'button', class: 'lr-comment-x lr-comment-pen', title: 'Modifier', onclick: function () { S.cmtEdit[c.id] = c.text; renderPanel(); } }, icon('pencil')) : null,
						c.canDelete ? h('button', { type: 'button', class: 'lr-comment-x', title: 'Supprimer', onclick: function () {
							api('comment_delete', { id: p.id, index: c.i }).then(function (d) { replacePin(findAny(p.id) || p, d.pin); }).catch(function (e) { toast(e.message, true); });
						} }, icon('close')) : null
					)
				),
				body,
				(c.media || []).length ? h('div', { class: 'lr-media lr-cmt-media' }, c.media.map(function (m) { return mediaThumb(m); })) : null
			));
		});
		if (!(p.comments || []).length) box.appendChild(h('p', { class: 'lr-empty-sm', text: 'Aucun commentaire pour l\'instant.' }));

		var draft = cmtDraft(p.id);
		var ta = h('textarea', { class: 'lr-input', rows: '2', placeholder: 'Ajouter un commentaire…', maxlength: '3000', oninput: function () { draft.text = ta.value; } });
		ta.value = draft.text;
		var send = h('button', { type: 'submit', class: 'lr-btn lr-btn-primary lr-btn-sm', text: 'Envoyer' });
		var prev = h('div', { class: 'lr-media lr-cmt-prev' });
		var urls = [];
		function renderPrev() {
			urls.forEach(function (u) { URL.revokeObjectURL(u); });
			urls = [];
			prev.innerHTML = '';
			draft.files.forEach(function (f, i) {
				var u = URL.createObjectURL(f.src);
				urls.push(u);
				var el = f.kind === 'video'
					? h('video', { src: u, muted: true, playsinline: true, preload: 'metadata' })
					: h('img', { src: u, alt: '' });
				prev.appendChild(h('div', { class: 'lr-media-item' + (f.kind === 'video' ? ' is-video' : ''), title: f.name },
					el,
					h('button', { type: 'button', class: 'lr-media-x', title: 'Retirer', onclick: function () { draft.files.splice(i, 1); renderPrev(); } }, icon('close'))
				));
			});
			prev.style.display = draft.files.length ? '' : 'none';
		}
		function addItem(item) {
			if (draft.files.length >= 4) { toast('4 pièces jointes maximum par commentaire.', true); return; }
			draft.files.push(item);
			renderPrev();
		}
		function addFiles(list) {
			Array.prototype.forEach.call(list, function (f) {
				var kind = /^video\//.test(f.type) ? 'video' : (/^image\//.test(f.type) ? 'image' : '');
				if (!kind) { toast(f.name + ' : format non pris en charge.', true); return; }
				if (f.size > FILE_MAX[kind] * 1048576) { toast(f.name + ' : trop lourd (' + FILE_MAX[kind] + ' Mo maximum).', true); return; }
				var item = { kind: kind, src: f, blob: f, name: f.name };
				item.ready = kind === 'image' ? shrinkImage(f).then(function (b) { item.blob = b; }) : Promise.resolve();
				addItem(item);
			});
		}
		var fileIn = h('input', { type: 'file', accept: 'image/*,video/*', multiple: true, style: 'display:none', onchange: function () { addFiles(fileIn.files); fileIn.value = ''; } });
		var tools = h('div', { class: 'lr-cmt-tools' },
			h('button', { type: 'button', class: 'lr-btn lr-btn-sm lr-btn-soft', title: 'Joindre une image ou une vidéo (optimisées à l\'envoi)', onclick: function () { fileIn.click(); } }, icon('clip'), 'Joindre'),
			attachButtons(function (kind) {
				runCapture(kind).then(function (m) {
					addItem({ kind: m.kind, src: m.blob, blob: m.blob, name: m.kind === 'video' ? 'Vidéo' : 'Capture', ready: Promise.resolve() });
				}).catch(function (err) { toast(captureError(err), true); });
			}),
			fileIn
		);
		renderPrev();

		box.appendChild(h('form', {
			class: 'lr-comment-form',
			onsubmit: function (e) {
				e.preventDefault();
				var text = ta.value.trim(), files = draft.files.slice();
				if (!text && !files.length) { ta.focus(); return; }
				send.disabled = true;
				send.textContent = 'Envoi…';
				var cid = '', pin = null, failed = 0;
				Promise.all(files.map(function (f) { return f.ready; })).then(function () {
					return api('comment', { id: p.id, text: text, with_media: files.length ? 1 : 0 });
				}).then(function (d) {
					cid = d.cid;
					pin = d.pin;
					return files.reduce(function (pr, f, k) {
						return pr.then(function () {
							send.textContent = 'Envoi ' + (f.kind === 'video' ? 'de la vidéo' : 'de l\'image') + (files.length > 1 ? ' ' + (k + 1) + '/' + files.length : '') + '…';
							return api('comment_media', { id: p.id, cid: cid, file: f.blob }).then(function (r) { pin = r.pin; }).catch(function (err) { failed++; toast(err.message, true); });
						});
					}, Promise.resolve());
				}).then(function () {
					delete S.cmtDraft[p.id];
					replacePin(findAny(p.id) || p, pin);
					if (!failed) toast('Commentaire ajouté');
				}).catch(function (err) { send.disabled = false; send.textContent = 'Envoyer'; toast(err.message, true); });
			},
			onkeydown: function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send.click(); } }
		}, ta, prev, h('div', { class: 'lr-cmt-bar' }, tools, send)));
		return box;
	}

	function card(p, elsewhere) {
		var n = (p.comments || []).length;
		var open = !!S.openComments[p.id];
		function toggle(e) {
			if (e) e.preventDefault();
			S.openComments[p.id] = !open;
			if (!open && !elsewhere) focusPin(p.id);
			else renderPanel();
		}
		var row = h('div', {
			class: 'lr-card-row',
			role: 'button',
			tabindex: '0',
			'aria-expanded': open ? 'true' : 'false',
			title: open ? 'Replier' : 'Voir les commentaires' + (elsewhere ? ' (ticket sur ' + p.page + ')' : ''),
			onclick: toggle,
			onkeydown: function (e) { if (e.key === 'Enter' || e.key === ' ') toggle(e); }
		},
			h('div', { class: 'lr-item' + (elsewhere ? ' is-elsewhere' : '') + (p.status === 'done' ? ' is-done' : '') + (!elsewhere && S.active === p.id ? ' is-active' : '') },
				h('span', { class: 'lr-num', text: String(elsewhere ? p.num : pinNumber(p)) }),
				h('span', { class: 'lr-item-body' },
					h('span', { class: 'lr-chips' },
						h('span', { class: 'lr-chip lr-item-title', text: p.title }),
						p.assigneeName
							? h('span', { class: 'lr-chip lr-chip-who', title: 'Assigné à ' + p.assigneeName }, icon('user'), h('span', { text: p.assigneeName }))
							: h('span', { class: 'lr-chip lr-chip-who is-none', text: 'Non assigné' })
					),
					h('span', { class: 'lr-item-meta', title: p.edited ? 'Modifié le ' + p.edited : null, text: itemMeta(p) })
				)
			),
			h('span', {
				class: 'lr-cmt-toggle' + (open ? ' is-open' : '') + (n ? ' has-comments' : ''),
				title: n + ' commentaire' + (n > 1 ? 's' : '')
			}, icon('chat'), h('span', { text: String(n) }))
		);
		return h('div', { class: 'lr-card' + (open ? ' is-open' : ''), 'data-id': p.id },
			row,
			open ? commentsBlock(p, elsewhere) : null
		);
	}

	function renderPanel() {
		var guest = needIdentity();
		ui.root.classList.toggle('is-guest', guest);
		if (guest) {
			ui.tabs.innerHTML = '';
			ui.whoFilter.innerHTML = '';
			if (!ui.list.querySelector('.lr-guest')) {
				ui.list.innerHTML = '';
				ui.list.appendChild(guestBlock());
			}
			return;
		}
		var all = S.pins.concat(S.others).filter(matchWho);
		var counts = { open: 0, done: 0, all: all.length };
		all.forEach(function (p) { counts[p.status]++; });

		ui.tabs.innerHTML = '';
		[['open', 'À traiter'], ['done', 'Résolus'], ['all', 'Tous']].forEach(function (t) {
			ui.tabs.appendChild(h('button', {
				type: 'button',
				class: 'lr-tab' + (S.filter === t[0] ? ' is-active' : ''),
				onclick: function () { S.filter = t[0]; renderAll(); }
			}, t[1] + ' (' + counts[t[0]] + ')'));
		});
		renderWhoFilter();

		// Conserve la position de défilement et le brouillon de commentaire en cours
		var scroll = ui.list.scrollTop;
		ui.list.innerHTML = '';

		var here = pinsFiltered();
		ui.list.appendChild(h('div', { class: 'lr-sec' }, h('span', { text: 'Sur cette page' }), h('span', { text: String(here.length) })));
		if (!here.length) {
			ui.list.appendChild(h('p', { class: 'lr-empty-sm', text: S.pins.length ? 'Aucun ticket dans ce filtre.' : 'Aucun ticket. Cliquez sur l\'icône pin pour en ajouter un.' }));
		}
		here.forEach(function (p) { ui.list.appendChild(card(p, false)); });

		var others = S.others.filter(keep);
		ui.list.appendChild(h('div', { class: 'lr-sec' }, h('span', { text: 'Sur les autres pages' }), h('span', { text: String(others.length) })));
		if (!others.length) {
			ui.list.appendChild(h('p', { class: 'lr-empty-sm', text: 'Aucun ticket sur les autres pages' + (S.others.length ? ' dans ce filtre.' : '.') }));
		} else {
			var groups = {}, order = [];
			others.forEach(function (p) {
				if (!groups[p.page]) { groups[p.page] = []; order.push(p.page); }
				groups[p.page].push(p);
			});
			order.forEach(function (pg) {
				groups[pg].forEach(function (p) { ui.list.appendChild(card(p, true)); });
			});
		}
		ui.list.scrollTop = scroll;
	}

	function togglePanel() {
		S.panelOpen = !S.panelOpen;
		ui.panel.classList.toggle('is-open', S.panelOpen);
		ui.root.classList.toggle('is-panel-open', S.panelOpen);
		ui.panel.setAttribute('aria-hidden', S.panelOpen ? 'false' : 'true');
		renderToolbar();
		applyToolbarPos();
	}

	function toggleVisible() {
		S.visible = !S.visible;
		store.set('lr_visible', S.visible ? '1' : '0');
		if (!S.visible) closePopover();
		renderAll();
	}

	/* ---------------------------------------------------------------- */
	/* Pins sur la page                                                  */
	/* ---------------------------------------------------------------- */

	function computePos(pin) {
		var lr = ui.layer.getBoundingClientRect();
		var t = null;
		if (pin.selector) { try { t = document.querySelector(pin.selector); } catch (e) { t = null; } }
		if (t) {
			var r = t.getBoundingClientRect();
			if (r.width || r.height) {
				return { x: r.left - lr.left + pin.rx * r.width, y: r.top - lr.top + pin.ry * r.height, anchored: true };
			}
		}
		return { x: pin.px - (lr.left + window.scrollX), y: pin.py - (lr.top + window.scrollY), anchored: false };
	}

	function renderMarkers() {
		Array.prototype.slice.call(ui.layer.querySelectorAll('.lr-marker:not(.is-draft)')).forEach(function (m) { m.remove(); });
		if (!S.visible) return;
		pinsFiltered().forEach(function (p) {
			var m = h('button', {
				type: 'button',
				class: 'lr-marker is-movable' + (p.status === 'done' ? ' is-done' : '') + (S.active === p.id ? ' is-active' : ''),
				'data-id': p.id,
				title: p.title + ' (glisser pour déplacer)',
				onclick: function (e) {
					e.preventDefault();
					e.stopPropagation();
					if (Date.now() - dragEndAt < 400) return;
					S.active === p.id ? closePopover() : openPopover(p.id);
				}
			}, String(pinNumber(p)));
			bindMarkerDrag(m, p.id);
			ui.layer.appendChild(m);
		});
		layout();
	}

	/* Déplacement d'un marqueur ------------------------------------- */

	var dragEndAt = 0;

	/** Ancre (élément + position relative) sous un point de la fenêtre. */
	function anchorAt(x, y) {
		x = Math.max(0, Math.min(window.innerWidth - 1, x));
		y = Math.max(0, Math.min(window.innerHeight - 1, y));
		var list = document.elementsFromPoint ? document.elementsFromPoint(x, y) : [document.elementFromPoint(x, y)];
		var t = null;
		for (var i = 0; i < list.length; i++) {
			var el = list[i];
			if (!el || isOurs(el) || el === document.documentElement) continue;
			if (el.closest && el.closest('svg')) el = el.closest('svg');
			t = el;
			break;
		}
		if (!t) t = document.body;
		var r = t.getBoundingClientRect();
		return {
			selector: cssPath(t),
			rx: r.width ? Math.min(1, Math.max(0, (x - r.left) / r.width)) : 0,
			ry: r.height ? Math.min(1, Math.max(0, (y - r.top) / r.height)) : 0,
			px: x + window.scrollX,
			py: y + window.scrollY
		};
	}

	function bindMarkerDrag(m, id) {
		var st = null;
		m.addEventListener('pointerdown', function (e) {
			if (e.button !== 0 || S.adding) return;
			var p = findPin(id);
			if (!p) return;
			var lr = ui.layer.getBoundingClientRect(), pos = computePos(p);
			st = { pid: e.pointerId, x0: e.clientX, y0: e.clientY, tx: lr.left + pos.x, ty: lr.top + pos.y, moving: false };
			// Suivi au niveau de la fenêtre : le pointeur sort vite du marqueur.
			window.addEventListener('pointermove', move, true);
			window.addEventListener('pointerup', end, true);
			window.addEventListener('pointercancel', end, true);
		});
		function move(e) {
			if (!st || e.pointerId !== st.pid) return;
			var dx = e.clientX - st.x0, dy = e.clientY - st.y0;
			if (!st.moving) {
				if (Math.abs(dx) + Math.abs(dy) < 6) return;
				st.moving = true;
				try { m.setPointerCapture(e.pointerId); } catch (err) {}
				if (S.active === id) closePopover();
				m.classList.add('is-dragging');
				document.documentElement.classList.add('lr-dragging');
			}
			e.preventDefault();
			var lr = ui.layer.getBoundingClientRect();
			m.style.left = (st.tx + dx - lr.left) + 'px';
			m.style.top = (st.ty + dy - lr.top) + 'px';
		}
		function end(e) {
			if (!st || e.pointerId !== st.pid) return;
			var s0 = st;
			st = null;
			window.removeEventListener('pointermove', move, true);
			window.removeEventListener('pointerup', end, true);
			window.removeEventListener('pointercancel', end, true);
			if (!s0.moving) return;
			dragEndAt = Date.now();
			m.classList.remove('is-dragging');
			document.documentElement.classList.remove('lr-dragging');
			if (e.type === 'pointercancel') { layout(); return; }
			var a = anchorAt(s0.tx + (e.clientX - s0.x0), s0.ty + (e.clientY - s0.y0));
			var p = findPin(id);
			if (!p) return;
			var old = { selector: p.selector, rx: p.rx, ry: p.ry, px: p.px, py: p.py };
			Object.keys(a).forEach(function (k) { p[k] = a[k]; });
			layout();
			api('update', { id: id, move: 1, selector: a.selector, rx: a.rx, ry: a.ry, px: a.px, py: a.py }).then(function (d) {
				replacePin(findPin(id) || p, d.pin);
				toast('Ticket déplacé');
			}).catch(function (err) {
				Object.keys(old).forEach(function (k) { p[k] = old[k]; });
				layout();
				toast(err.message, true);
			});
		}
	}

	var rafPending = false;
	function layout() {
		if (rafPending) return;
		rafPending = true;
		requestAnimationFrame(function () {
			rafPending = false;
			Array.prototype.forEach.call(ui.layer.querySelectorAll('.lr-marker'), function (m) {
				var pin = m.classList.contains('is-draft') ? (S.draft && S.draft.anchor) : findPin(+m.getAttribute('data-id'));
				if (!pin || m.classList.contains('is-dragging')) return;
				var pos = computePos(pin);
				m.style.left = pos.x + 'px';
				m.style.top = pos.y + 'px';
				m.classList.toggle('is-detached', !pos.anchored);
			});
			placePopover();
		});
	}

	/* ---------------------------------------------------------------- */
	/* Bulles (création / détail)                                        */
	/* ---------------------------------------------------------------- */

	function placePopover() {
		if (!ui.pop) return;
		var anchor = S.draft ? S.draft.anchor : findPin(S.active);
		if (!anchor) return;
		var pos = computePos(anchor);
		var lr = ui.layer.getBoundingClientRect();
		var w = ui.pop.offsetWidth || 320;
		var vx = lr.left + pos.x;
		var left = pos.x + 22;
		if (vx + 22 + w > window.innerWidth - 70) left = pos.x - 22 - w;
		if (lr.left + left < 8) left = 8 - lr.left;
		ui.pop.style.left = left + 'px';
		ui.pop.style.top = Math.max(pos.y - 18, 8 - lr.top) + 'px';
	}

	function closePopover() {
		if (ui.pop) { ui.pop.remove(); ui.pop = null; }
		if (S.draft) {
			S.draft.marker.remove();
			(S.draft.urls || []).forEach(function (u) { URL.revokeObjectURL(u); });
			S.draft = null;
		}
		S.active = null;
		S.confirmDelete = false;
		Array.prototype.forEach.call(ui.layer.querySelectorAll('.lr-marker.is-active'), function (m) { m.classList.remove('is-active'); });
		renderPanel();
	}

	function assigneeSelect(value) {
		var sel = h('select', { class: 'lr-input' }, h('option', { value: '', text: 'Non assigné' }));
		(C.assignees || []).forEach(function (a) {
			var o = h('option', { value: a.id, text: a.label || a.name });
			if (a.id === value) o.selected = true;
			sel.appendChild(o);
		});
		return sel;
	}

	function openDraft(anchor) {
		closePopover();
		var marker = h('div', { class: 'lr-marker is-draft is-active' }, '+');
		ui.layer.appendChild(marker);
		S.draft = { anchor: anchor, marker: marker, media: [], urls: [] };
		var draft = S.draft;

		var title = h('input', { type: 'text', class: 'lr-input', placeholder: 'Titre *', maxlength: '200' });
		var comment = h('textarea', { class: 'lr-input', rows: '4', placeholder: 'Commentaire' });
		var assignee = assigneeSelect(store.get('lr_last_assignee') || '');
		var save = h('button', { type: 'submit', class: 'lr-btn lr-btn-primary', text: 'Enregistrer' });

		var prev = h('div', { class: 'lr-media' });
		function renderPrev() {
			prev.innerHTML = '';
			draft.media.forEach(function (m, i) {
				var u = URL.createObjectURL(m.blob);
				draft.urls.push(u);
				var el = m.kind === 'video'
					? h('video', { src: u, muted: true, autoplay: true, loop: true, playsinline: true })
					: h('img', { src: u, alt: '' });
				prev.appendChild(h('div', { class: 'lr-media-item' + (m.kind === 'video' ? ' is-video' : '') },
					el,
					h('button', { type: 'button', class: 'lr-media-x', title: 'Retirer', onclick: function () { draft.media.splice(i, 1); renderPrev(); } }, icon('close'))
				));
			});
			prev.style.display = draft.media.length ? '' : 'none';
		}
		renderPrev();
		var attach = attachButtons(function (kind) {
			if (draft.media.length >= 4) { toast('4 captures maximum par point.', true); return; }
			runCapture(kind).then(function (m) {
				if (S.draft !== draft) return;
				draft.media.push(m);
				renderPrev();
				layout();
			}).catch(function (err) { toast(captureError(err), true); });
		});

		var form = h('form', {
			class: 'lr-pop',
			onsubmit: function (e) {
				e.preventDefault();
				if (!title.value.trim()) { title.focus(); title.classList.add('is-error'); return; }
				save.disabled = true;
				save.textContent = 'Enregistrement…';
				store.set('lr_last_assignee', assignee.value);
				api('create', {
					page: pageKey(),
					page_title: document.title,
					title: title.value.trim(),
					comment: comment.value,
					assignee: assignee.value,
					with_media: draft.media.length ? 1 : 0,
					selector: anchor.selector,
					rx: anchor.rx, ry: anchor.ry, px: anchor.px, py: anchor.py,
					vw: window.innerWidth, vh: window.innerHeight,
					ua: navigator.userAgent
				}).then(function (d) {
					var pin = d.pin, n = 0, failed = 0;
					return draft.media.reduce(function (pr, m) {
						return pr.then(function () {
							n++;
							save.textContent = 'Envoi ' + (m.kind === 'video' ? 'de la vidéo' : 'de la capture') + (draft.media.length > 1 ? ' ' + n + '/' + draft.media.length : '') + '…';
							return api('media', { id: pin.id, file: m.blob }).then(function (r) { pin = r.pin; }).catch(function (err) { failed++; toast(err.message, true); });
						});
					}, Promise.resolve()).then(function () {
						return { pin: pin, failed: failed };
					});
				}).then(function (d) {
					S.pins.push(d.pin);
					closePopover();
					if (S.filter === 'done') S.filter = 'open';
					renderAll();
					toast('Point #' + pinNumber(d.pin) + ' enregistré' + (d.pin.assigneeName ? ' et assigné à ' + d.pin.assigneeName : ''));
				}).catch(function (err) {
					save.disabled = false;
					save.textContent = 'Enregistrer';
					toast(err.message, true);
				});
			},
			onkeydown: function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : save.click(); } }
		},
			h('div', { class: 'lr-pop-head' },
				h('strong', { text: 'Nouveau ticket' }),
				h('button', { type: 'button', class: 'lr-x', title: 'Annuler', onclick: closePopover }, icon('close'))
			),
			title,
			comment,
			attach,
			prev,
			h('label', { class: 'lr-label' }, 'Assigner à', assignee),
			h('div', { class: 'lr-actions' },
				h('button', { type: 'button', class: 'lr-btn', text: 'Annuler', onclick: closePopover }),
				save
			)
		);
		ui.pop = form;
		ui.layer.appendChild(form);
		layout();
		setTimeout(function () { title.focus({ preventScroll: true }); }, 30);
	}

	function openPopover(id) {
		var p = findPin(id);
		if (!p) return;
		closePopover();
		S.active = id;

		var assignee = assigneeSelect(p.assignee);
		assignee.addEventListener('change', function () {
			update(p, { assignee: assignee.value }, assignee.value ? 'Assigné à ' + assignee.options[assignee.selectedIndex].text : 'Désassigné');
		});

		var statusBtn = h('button', {
			type: 'button',
			class: 'lr-btn ' + (p.status === 'done' ? '' : 'lr-btn-ok'),
			onclick: function () { update(p, { status: p.status === 'done' ? 'open' : 'done' }, p.status === 'done' ? 'Ticket rouvert' : 'Ticket résolu'); }
		}, p.status === 'done' ? 'Rouvrir' : icon('check'), p.status === 'done' ? null : 'Marquer comme résolu');

		var delBtn = null;
		if (p.canDelete) {
			delBtn = h('button', {
				type: 'button',
				class: 'lr-btn lr-btn-danger',
				text: 'Supprimer',
				onclick: function () {
					if (!S.confirmDelete) { S.confirmDelete = true; delBtn.textContent = 'Confirmer ?'; return; }
					api('delete', { id: p.id }).then(function () {
						S.pins = S.pins.filter(function (x) { return x.id !== p.id; });
						closePopover();
						renderAll();
						toast('Point supprimé');
					}).catch(function (err) { toast(err.message, true); });
				}
			});
		}

		var titleEl = h('div', { class: 'lr-pop-title', text: p.title });
		var commentEl = p.comment ? h('div', { class: 'lr-pop-comment', text: p.comment }) : null;
		var editBtn = null;
		function editMode() {
			var t = h('input', { type: 'text', class: 'lr-input', value: p.title, maxlength: '200', 'aria-label': 'Titre' });
			var c = h('textarea', { class: 'lr-input', rows: '4', placeholder: 'Commentaire', 'aria-label': 'Description' });
			c.value = p.comment || '';
			var save = h('button', { type: 'submit', class: 'lr-btn lr-btn-primary lr-btn-sm', text: 'Enregistrer' });
			var f = h('form', {
				class: 'lr-edit',
				onsubmit: function (e) {
					e.preventDefault();
					if (!t.value.trim()) { t.focus(); t.classList.add('is-error'); return; }
					if (t.value.trim() === p.title && c.value === (p.comment || '')) { openPopover(p.id); return; }
					save.disabled = true;
					update(p, { title: t.value.trim(), comment: c.value }, 'Ticket modifié');
				},
				onkeydown: function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); save.click(); } }
			},
				t, c,
				h('div', { class: 'lr-actions' },
					h('button', { type: 'button', class: 'lr-btn lr-btn-sm', text: 'Annuler', onclick: function () { openPopover(p.id); } }),
					save
				)
			);
			titleEl.replaceWith(f);
			if (commentEl) commentEl.remove();
			if (editBtn) editBtn.remove();
			layout();
			setTimeout(function () { t.focus({ preventScroll: true }); }, 20);
		}
		if (p.canEdit) editBtn = h('button', { type: 'button', class: 'lr-x lr-edit-btn', title: 'Modifier le ticket', onclick: editMode }, icon('pencil'));

		var pop = h('div', { class: 'lr-pop' },
			h('div', { class: 'lr-pop-head' },
				h('span', { class: 'lr-num', text: String(pinNumber(p)) }),
				h('span', { class: 'lr-status ' + (p.status === 'done' ? 'is-done' : ''), text: p.status === 'done' ? 'Résolu' : 'À traiter' }),
				p.edited ? h('span', { class: 'lr-edited', title: 'Modifié le ' + p.edited, text: 'modifié' }) : null,
				editBtn,
				h('button', { type: 'button', class: 'lr-x', title: 'Fermer', onclick: closePopover }, icon('close'))
			),
			titleEl,
			commentEl,
			mediaGallery(p),
			p.canAttach && (p.media || []).length < 6 ? attachButtons(function (kind) {
				runCapture(kind).then(function (m) {
					toast(m.kind === 'video' ? 'Envoi de la vidéo…' : 'Envoi de la capture…');
					return api('media', { id: p.id, file: m.blob });
				}).then(function (d) {
					replacePin(findPin(p.id) || p, d.pin);
					toast('Capture ajoutée');
				}).catch(function (err) { toast(captureError(err), true); });
			}) : null,
			h('ul', { class: 'lr-pop-meta' },
				h('li', null, icon('user'), h('span', { text: p.author })),
				h('li', null, icon('calendar'), h('span', { text: p.date + (p.edited ? ' · modifié le ' + p.edited : '') })),
				h('li', null, icon(p.device || 'desktop'), h('span', { text: ({ desktop: 'Ordinateur', mobile: 'Mobile', tablet: 'Tablette' })[p.device || 'desktop'] + (p.vw ? ' · ' + p.vw + ' px' : '') }))
			),
			h('label', { class: 'lr-label' }, 'Assigné à', assignee),
			h('div', { class: 'lr-actions' }, delBtn, statusBtn)
		);
		pop.addEventListener('click', function (e) { e.stopPropagation(); });
		ui.pop = pop;
		ui.layer.appendChild(pop);
		var m = ui.layer.querySelector('.lr-marker[data-id="' + id + '"]');
		if (m) m.classList.add('is-active');
		renderPanel();
		layout();
	}

	function update(p, data, msg) {
		data.id = p.id;
		api('update', data).then(function (d) {
			var i = S.pins.indexOf(p);
			if (i > -1) S.pins[i] = d.pin;
			var keep = S.active === p.id && (S.filter === 'all' || d.pin.status === S.filter);
			renderAll();
			if (keep) openPopover(d.pin.id); else closePopover();
			toast(msg);
		}).catch(function (err) {
			toast(err.message, true);
			var b = ui.pop && ui.pop.querySelector('.lr-edit button[type="submit"]');
			if (b) b.disabled = false;
		});
	}

	function focusPin(id) {
		var p = findPin(id);
		if (!p) return;
		if (S.filter !== 'all' && p.status !== S.filter) { S.filter = 'all'; }
		if (!S.visible) { S.visible = true; store.set('lr_visible', '1'); }
		renderAll();
		var pos = computePos(p);
		var lr = ui.layer.getBoundingClientRect();
		var pageY = lr.top + window.scrollY + pos.y;
		window.scrollTo({ top: Math.max(0, pageY - window.innerHeight / 3), behavior: 'smooth' });
		openPopover(id);
		var m = ui.layer.querySelector('.lr-marker[data-id="' + id + '"]');
		if (m) { m.classList.remove('is-flash'); void m.offsetWidth; m.classList.add('is-flash'); }
	}

	/* ---------------------------------------------------------------- */
	/* Mode ajout                                                        */
	/* ---------------------------------------------------------------- */

	var hovered = null;

	function toggleAdding() {
		if (!S.adding && needIdentity()) { toast('Connectez-vous d\'abord (nom et e-mail) pour ajouter un ticket.'); focusGuest(); return; }
		setAdding(!S.adding);
	}

	/* ---------------------------------------------------------------- */
	/* Identification des invités (nom + e-mail)                         */
	/* ---------------------------------------------------------------- */

	function needIdentity() { return C.user.isGuest && !C.user.email; }

	function renderWho() {
		if (!ui.who) return;
		ui.who.innerHTML = '';
		if (!C.user.name) return;
		ui.who.appendChild(document.createTextNode('Vous : ' + C.user.name + ' '));
		if (C.user.isGuest) ui.who.appendChild(h('button', { type: 'button', class: 'lr-link', text: 'modifier', onclick: function () { openIdentify(); } }));
	}

	/* ---------------------------------------------------------------- */
	/* Tutoriel (carrousel 3 slides)                                     */
	/* ---------------------------------------------------------------- */

	var TUTO = [
		{
			art: '<svg viewBox="0 0 120 90" width="150" height="112" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="10" width="104" height="70" rx="8" fill="#ffffff" stroke="#c9d3fd" stroke-width="2"/><rect x="18" y="22" width="50" height="7" rx="3" fill="#c9d3fd"/><rect x="18" y="35" width="70" height="5" rx="2.5" fill="#dfe4f7"/><rect x="18" y="45" width="60" height="5" rx="2.5" fill="#dfe4f7"/><circle cx="84" cy="40" r="11" fill="var(--lr-accent)"/><text x="84" y="44.5" text-anchor="middle" font-size="12" font-weight="700" fill="#fff" font-family="sans-serif">1</text><rect x="72" y="58" width="30" height="16" rx="4" fill="#1b1e2b"/><circle cx="87" cy="66" r="4" stroke="#fff" stroke-width="2"/></svg>',
			title: 'Bienvenue sur La Recette',
			text: 'Signalez ici les bugs visuels ou les erreurs de texte du site. Cliquez sur l\'icône pin, posez-le à l\'endroit exact du problème, décrivez-le, et ajoutez si besoin une capture photo ou une vidéo de 5 secondes pour le montrer.'
		},
		{
			art: '<svg viewBox="0 0 120 90" width="150" height="112" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="32" cy="36" r="12" fill="#dfe4f7"/><path d="M14 72c2-12 10-18 18-18s16 6 18 18" fill="#dfe4f7"/><path d="M58 45h22" stroke="var(--lr-accent)" stroke-width="4"/><path d="M74 37l8 8-8 8" stroke="var(--lr-accent)" stroke-width="4"/><circle cx="98" cy="36" r="12" fill="var(--lr-accent)"/><path d="M80 72c2-12 10-18 18-18s16 6 18 18" fill="var(--lr-accent)"/></svg>',
			title: 'Assignez chaque ticket',
			text: 'Dans « Assigner à », choisissez la personne concernée selon son périmètre, ou assignez-vous le ticket. Vous pouvez ensuite suivre et commenter chaque ticket depuis le panneau de droite.'
		},
		{
			art: '<svg viewBox="0 0 120 90" width="150" height="112" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M60 14c-13 0-22 10-22 23v14l-7 10h58l-7-10V37c0-13-9-23-22-23z" fill="#1b1e2b"/><path d="M52 66a8 8 0 0 0 16 0" stroke="#1b1e2b" stroke-width="4"/><circle cx="82" cy="22" r="10" fill="var(--lr-accent)"/><text x="82" y="26.5" text-anchor="middle" font-size="12" font-weight="700" fill="#fff" font-family="sans-serif">1</text></svg>',
			title: 'Suivez chaque ticket',
			text: 'Retrouvez tous les tickets et leurs commentaires dans le panneau de droite : ce qui reste à traiter, ce qui est résolu, et qui s\'en occupe.'
		}
	];

	function openTutorial(then) {
		if (ui.tuto) ui.tuto.remove();
		var i = 0;
		var art = h('div', { class: 'lr-tuto-art' });
		var title = h('h3', { class: 'lr-tuto-title' });
		var text = h('p', { class: 'lr-tuto-text' });
		var dots = h('div', { class: 'lr-tuto-dots' });
		var prev = h('button', { type: 'button', class: 'lr-btn', text: 'Précédent', onclick: function () { if (i > 0) { i--; show(); } } });
		var next = h('button', { type: 'button', class: 'lr-btn lr-btn-primary', onclick: function () { if (i < TUTO.length - 1) { i++; show(); } else { done(); } } });
		function done() {
			markTutoSeen();
			if (ui.tuto) { ui.tuto.remove(); ui.tuto = null; }
			document.removeEventListener('keydown', keys, true);
			if (then) then();
		}
		function show() {
			var t = TUTO[i];
			art.innerHTML = t.art;
			title.textContent = t.title;
			text.textContent = t.text;
			dots.innerHTML = '';
			TUTO.forEach(function (x, k) {
				dots.appendChild(h('button', { type: 'button', class: 'lr-tuto-dot' + (k === i ? ' is-on' : ''), 'aria-label': 'Étape ' + (k + 1), onclick: function () { i = k; show(); } }));
			});
			prev.style.visibility = i ? 'visible' : 'hidden';
			next.textContent = i < TUTO.length - 1 ? 'Suivant' : 'C\'est parti';
		}
		function keys(e) {
			if (!ui.tuto) return;
			if (e.key === 'ArrowRight') { e.stopPropagation(); next.click(); }
			else if (e.key === 'ArrowLeft') { e.stopPropagation(); prev.click(); }
			else if (e.key === 'Escape') { e.stopPropagation(); done(); }
		}
		document.addEventListener('keydown', keys, true);
		ui.tuto = h('div', { class: 'lr-id lr-tuto' },
			h('div', { class: 'lr-id-box lr-tuto-box', role: 'dialog', 'aria-label': 'Tutoriel La Recette' },
				h('button', { type: 'button', class: 'lr-tuto-skip', text: 'Passer le tutoriel', onclick: done }),
				art, title, text, dots,
				h('div', { class: 'lr-actions' }, prev, next)
			)
		);
		ui.layer.appendChild(ui.tuto);
		show();
		setTimeout(function () { next.focus({ preventScroll: true }); }, 30);
	}

	/** Inscription (nom + e-mail) : partagée par le panneau et la fenêtre « modifier ». */
	function submitIdentity(name, email, err, btn, done) {
		err.textContent = '';
		if (!name.value.trim()) { name.focus(); return; }
		if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { err.textContent = 'Adresse e-mail invalide.'; email.focus(); return; }
		btn.disabled = true;
		api('identify', { name: name.value.trim(), email: email.value.trim() }).then(function (d) {
			var first = !C.user.email;
			C.user.name = d.user.name;
			C.user.email = d.user.email;
			C.user.me = d.user.me;
			C.assignees = d.assignees;
			S.guest = null;
			renderWho();
			renderAll();
			if (!S.loaded) load().then(openFromHash);
			toast(first ? 'Bienvenue ' + d.user.name + ', vous pouvez maintenant ajouter des tickets.' : 'Profil mis à jour.');
			if (done) done();
		}).catch(function (e2) { btn.disabled = false; err.textContent = e2.message; });
	}

	/** Panneau d'un visiteur non inscrit : formulaire nom + e-mail à la place des tickets. */
	function guestBlock() {
		S.guest = S.guest || { name: '', email: '' };
		var name = h('input', { type: 'text', class: 'lr-input', placeholder: 'Prénom Nom *', maxlength: '80', autocomplete: 'name', 'aria-label': 'Prénom Nom', oninput: function () { S.guest.name = name.value; } });
		var email = h('input', { type: 'email', class: 'lr-input', placeholder: 'E-mail *', maxlength: '120', autocomplete: 'email', 'aria-label': 'E-mail', oninput: function () { S.guest.email = email.value; } });
		name.value = S.guest.name;
		email.value = S.guest.email;
		var err = h('div', { class: 'lr-id-err' });
		var btn = h('button', { type: 'submit', class: 'lr-btn lr-btn-primary', text: 'Se connecter' });
		ui.guestName = name;
		return h('form', {
			class: 'lr-guest',
			onsubmit: function (e) { e.preventDefault(); submitIdentity(name, email, err, btn); }
		},
			h('span', { class: 'lr-guest-ico' }, icon('chef')),
			h('strong', { class: 'lr-guest-title', text: 'Connectez-vous pour voir et ajouter des tickets' }),
			h('p', { class: 'lr-guest-text', text: 'Indiquez votre nom et votre e-mail : vous pourrez créer des tickets, les commenter et être assigné(e).' }),
			name, email, err, btn
		);
	}

	function focusGuest() {
		if (!S.panelOpen) togglePanel();
		setTimeout(function () { if (ui.guestName && ui.guestName.isConnected) ui.guestName.focus({ preventScroll: true }); }, 320);
	}

	function openIdentify(then) {
		if (ui.idBox) ui.idBox.remove();
		var name = h('input', { type: 'text', class: 'lr-input', placeholder: 'Prénom Nom *', maxlength: '80', autocomplete: 'name', value: C.user.name || '' });
		var email = h('input', { type: 'email', class: 'lr-input', placeholder: 'E-mail *', maxlength: '120', autocomplete: 'email', value: C.user.email || '' });
		var err = h('div', { class: 'lr-id-err' });
		var btn = h('button', { type: 'submit', class: 'lr-btn lr-btn-primary', text: 'Continuer' });
		function close() { if (ui.idBox) { ui.idBox.remove(); ui.idBox = null; } }
		var form = h('form', {
			class: 'lr-id-box',
			onsubmit: function (e) {
				e.preventDefault();
				submitIdentity(name, email, err, btn, function () { close(); if (then) then(); });
			}
		},
			h('div', { class: 'lr-id-head' }, icon('chef'), h('strong', { text: 'La Recette' })),
			h('p', { class: 'lr-id-intro', text: 'Pour commenter ce site, indiquez votre nom et votre e-mail.' }),
			name, email, err,
			h('div', { class: 'lr-actions' },
				h('button', { type: 'button', class: 'lr-btn', text: 'Plus tard', onclick: close }),
				btn
			)
		);
		ui.idBox = h('div', { class: 'lr-id' }, form);
		ui.layer.appendChild(ui.idBox);
		setTimeout(function () { (name.value ? email : name).focus({ preventScroll: true }); }, 30);
	}

	function setAdding(on) {
		S.adding = on;
		document.documentElement.classList.toggle('lr-adding', on);
		ui.hint.classList.toggle('is-on', on);
		if (!on) { ui.hl.style.display = 'none'; hovered = null; }
		else closePopover();
		renderToolbar();
	}

	function targetFrom(e) {
		var t = e.target;
		if (!(t instanceof Element)) return null;
		if (t.closest('svg')) t = t.closest('svg');
		return t;
	}

	function onMove(e) {
		if (!S.adding) return;
		var t = targetFrom(e);
		if (!t || isOurs(t) || t === document.documentElement || t === document.body) { ui.hl.style.display = 'none'; hovered = null; return; }
		if (t === hovered) return;
		hovered = t;
		var r = t.getBoundingClientRect();
		ui.hl.style.display = 'block';
		ui.hl.style.left = r.left + 'px';
		ui.hl.style.top = r.top + 'px';
		ui.hl.style.width = r.width + 'px';
		ui.hl.style.height = r.height + 'px';
	}

	function block(e) {
		if (!S.adding || isOurs(e.target)) return;
		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();
	}

	function onClick(e) {
		if (!S.adding || isOurs(e.target)) return;
		block(e);
		var t = targetFrom(e) || document.body;
		var r = t.getBoundingClientRect();
		var anchor = {
			selector: cssPath(t),
			rx: r.width ? Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)) : 0,
			ry: r.height ? Math.min(1, Math.max(0, (e.clientY - r.top) / r.height)) : 0,
			px: e.pageX,
			py: e.pageY
		};
		setAdding(false);
		openDraft(anchor);
	}

	/* ---------------------------------------------------------------- */
	/* Global                                                            */
	/* ---------------------------------------------------------------- */

	function renderAll() {
		renderToolbar();
		renderPanel();
		renderMarkers();
	}

	function load() {
		return api('list', { page: pageKey() }).then(function (d) {
			var here = [], others = [], idx = {};
			(d.pins || []).forEach(function (p) {
				idx[p.page] = (idx[p.page] || 0) + 1;
				p.num = idx[p.page];
				(p.here ? here : others).push(p);
			});
			S.pins = here;
			S.others = others;
			S.loaded = true;
			renderAll();
		}).catch(function (err) { toast(err.message, true); });
	}

	function openFromHash() {
		var m = /^#lr-pin-(\d+)$/.exec(location.hash);
		if (!m) return;
		var id = +m[1];
		if (findPin(id)) setTimeout(function () { focusPin(id); }, 300);
		else toast('Ce point n\'existe plus ou est sur une autre page.', true);
	}

	function start() {
		build();
		['pointerdown', 'mousedown', 'mouseup', 'pointerup'].forEach(function (ev) {
			document.addEventListener(ev, block, { capture: true, passive: false });
		});
		document.addEventListener('click', onClick, true);
		document.addEventListener('mousemove', onMove, true);
		document.addEventListener('scroll', function () { if (S.adding) { ui.hl.style.display = 'none'; hovered = null; } }, true);
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') return;
			if (CAP.busy) return;
			if (closeLightbox()) return;
			if (S.adding) setAdding(false);
			else if (ui.pop) closePopover();
			else if (S.panelOpen) togglePanel();
		});
		document.addEventListener('click', function (e) {
			// Un élément retiré du DOM pendant le clic (panneau re-rendu) appartient à l'outil.
			if (e.target && e.target.isConnected === false) return;
			if (ui.pop && !S.draft && !CAP.busy && !isOurs(e.target)) closePopover();
		});
		window.addEventListener('resize', layout);
		window.addEventListener('load', layout);
		window.addEventListener('hashchange', openFromHash);
		setInterval(layout, 1500);

		renderWho();
		// Inscription obligatoire (nom + e-mail) avant de voir et d'ajouter des points.
		// Tutoriel : une seule fois par site et par navigateur, même si on le passe sans s'inscrire (v0.10.5).
		if (needIdentity()) { renderPanel(); if (!tutoSeen()) openTutorial(focusGuest); }
		else {
			// Premier passage : le tutoriel s'affiche par-dessus, mais les tickets sont chargés tout de suite.
			if (!tutoSeen()) openTutorial();
			load().then(openFromHash).then(openFromLink);
		}
	}

	/** Lien d'accès (?recette=…) : panneau ouvert d'emblée. */
	function openFromLink() {
		try {
			if (new URLSearchParams(location.search).has('recette') && !location.hash && !S.panelOpen) togglePanel();
		} catch (e) {}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
