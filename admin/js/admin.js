/**
 * Pencilino - admin page tabs and copy buttons.
 */
( function () {
	'use strict';

	var links  = document.querySelectorAll( '[data-tab]' );
	var panels = document.querySelectorAll( '[data-tab-panel]' );

	if ( ! links.length || ! panels.length ) {
		return;
	}

	/**
	 * Show one panel and mark its link as active.
	 *
	 * @param {string} name Tab name.
	 */
	// Older links and bookmarks: Changes and Comments now live inside the Change log tab.
	var aliases = {
		changes: { tab: 'activity', sub: 'changes' },
		comments: { tab: 'activity', sub: 'comments' },
		'settings-overview': { tab: 'settings', sub: 'overview' },
		'settings-focus': { tab: 'settings', sub: 'focus' },
		'settings-dev': { tab: 'settings', sub: 'dev' },
		'settings-framework': { tab: 'ai-instructions', sub: 'cleanup' },
		'settings-hidden': { tab: 'settings', sub: 'hidden' },
		'ai-basics': { tab: 'ai-instructions', sub: 'front-end' },
		'ai-theme-rules': { tab: 'ai-instructions', sub: 'front-end' },
		'ai-structure': { tab: 'ai-instructions', sub: 'structure' },
		'ai-content': { tab: 'ai-instructions', sub: 'content' },
		'ai-design': { tab: 'ai-instructions', sub: 'design' },
		'ai-seo': { tab: 'ai-instructions', sub: 'seo' },
		'ai-checks': { tab: 'ai-instructions', sub: 'checks' },
		'ai-cleanup': { tab: 'ai-instructions', sub: 'cleanup' }
	};

	function activateSubtab( group, name ) {
		group.querySelectorAll( '[data-subtab]' ).forEach( function ( other ) {
			var active = other.getAttribute( 'data-subtab' ) === name;
			other.classList.toggle( 'pencil-subtabs__btn--active', active );
			other.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
		group.querySelectorAll( '[data-subtab-panel]' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-subtab-panel' ) !== name;
		} );
	}

	function showTab( name ) {
		var found = false;
		var sub   = '';

		if ( aliases[ name ] ) {
			sub  = aliases[ name ].sub;
			name = aliases[ name ].tab;
		}
		panels.forEach( function ( panel ) {
			var match = panel.getAttribute( 'data-tab-panel' ) === name;
			panel.hidden = ! match;
			found = found || match;
		} );

		if ( ! found ) {
			showTab( panels[ 0 ].getAttribute( 'data-tab-panel' ) );
			return;
		}

		links.forEach( function ( link ) {
			var active = link.getAttribute( 'data-tab' ) === name;
			link.classList.toggle( 'pencil-header__link--active', active );
			if ( active ) {
				link.setAttribute( 'aria-current', 'page' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );

		if ( sub ) {
			panels.forEach( function ( panel ) {
				var group = panel.getAttribute( 'data-tab-panel' ) === name ? panel.querySelector( '[data-subtabs]' ) : null;
				if ( group ) {
					activateSubtab( group, sub );
				}
			} );
		}
	}

	links.forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			var name = link.getAttribute( 'data-tab' );
			showTab( name );
			// Keep the tab in the URL so a reload lands on the same one.
			window.history.replaceState( null, '', '#' + name );
		} );
	} );

	var initial = window.location.hash.replace( '#', '' );
	if ( ! initial && /[?&]pencil-comment-/.test( window.location.search ) ) {
		initial = 'comments';
	}
	showTab( initial );

	// The saved toast fades out by itself. Drop the flag from the URL so a reload does not show it again.
	var toast = document.querySelector( '[data-pencil-toast]' );
	if ( toast ) {
		toast.addEventListener( 'animationend', function () {
			toast.remove();
		} );
		var current = new URL( window.location.href );
		current.searchParams.delete( 'pencil-settings-saved' );
		window.history.replaceState( null, '', current.toString() );
	}

	// Settings toggles save as soon as they change.
	document.querySelectorAll( 'form[data-pencil-autosave]' ).forEach( function ( form ) {
		form.addEventListener( 'change', function () {
			form.submit();
		} );
	} );

	// A link to admin.php?page=pencilino#get-started from elsewhere in the admin.
	window.addEventListener( 'hashchange', function () {
		showTab( window.location.hash.replace( '#', '' ) );
		// A link from a long tab to a shorter one would otherwise land at the bottom.
		window.scrollTo( 0, 0 );
	} );

	// Sub-tabs inside a panel (Get started: For you / For clients).
	document.querySelectorAll( '[data-subtabs]' ).forEach( function ( group ) {
		var buttons = group.querySelectorAll( '[data-subtab]' );

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				activateSubtab( group, button.getAttribute( 'data-subtab' ) );

				// Some sub-tabs keep their name in the URL so a reload lands on the same one.
				var hash = button.getAttribute( 'data-subtab-hash' );
				if ( hash ) {
					window.history.replaceState( null, '', '#' + hash );
				}
			} );
		} );
	} );

	// Copy buttons read the text of the element they point to.
	document.querySelectorAll( '[data-copy]' ).forEach( function ( button ) {
		var source  = document.getElementById( button.getAttribute( 'data-copy' ) );
		var idle    = button.textContent;
		var done    = button.getAttribute( 'data-copied-label' ) || idle;

		if ( ! source ) {
			return;
		}

		function confirmCopied() {
			button.textContent = done;
			window.setTimeout( function () {
				button.textContent = idle;
			}, 1800 );
		}

		// Older browsers, or a page without clipboard permission: select the
		// text and use the legacy copy command, which still works on a click.
		function legacyCopy() {
			source.focus();
			if ( source.select ) {
				source.select();
			}
			try {
				if ( document.execCommand( 'copy' ) ) {
					confirmCopied();
				}
			} catch ( e ) {
				// The text stays selected so the user can copy it by hand.
			}
		}

		button.addEventListener( 'click', function () {
			var text = source.value !== undefined ? source.value : source.textContent;

			if ( ! navigator.clipboard || ! navigator.clipboard.writeText ) {
				legacyCopy();
				return;
			}

			navigator.clipboard.writeText( text ).then( confirmCopied ).catch( legacyCopy );
		} );
	} );
}() );

/* Templates: a link to another template scrolls to it and flashes it. */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( 'a[href^="#pencil-template-"]' ) : null;
		if ( ! link ) {
			return;
		}

		var target = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
		if ( ! target ) {
			return;
		}

		event.preventDefault();
		// A filter can hide the template the link points to: show all templates first.
		if ( target.hidden ) {
			var all = document.querySelector( '.pencil-filters [data-pencil-filter="all"]' );
			if ( all ) {
				all.click();
			}
		}
		var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		target.scrollIntoView( { behavior: still ? 'auto' : 'smooth', block: 'center' } );
		target.classList.add( 'pencil-admin__record--flash' );
		window.setTimeout( function () {
			target.classList.remove( 'pencil-admin__record--flash' );
		}, 1600 );
	} );
}() );

/* Templates: click a file name to copy it. */
( function () {
	'use strict';

	function legacyCopy( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.className = 'pencil-copy-source';
		document.body.appendChild( area );
		area.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( area );
		return ok;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-pencil-copy]' ) : null;
		if ( ! button ) {
			return;
		}

		var text = button.getAttribute( 'data-pencil-copy' );
		var idle = button.textContent;

		function done() {
			button.textContent = 'Copied';
			window.setTimeout( function () {
				button.textContent = idle;
			}, 1400 );
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done ).catch( function () {
				if ( legacyCopy( text ) ) {
					done();
				}
			} );
		} else if ( legacyCopy( text ) ) {
			done();
		}
	} );
}() );

/* Templates: filter bubbles. */
( function () {
	'use strict';

	var bar = document.querySelector( '.pencil-filters' );
	if ( ! bar ) {
		return;
	}

	var buttons = bar.querySelectorAll( '[data-pencil-filter]' );
	var items = document.querySelectorAll( '.pencil-templates [data-group]' );

	bar.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-pencil-filter]' ) : null;
		if ( ! button ) {
			return;
		}
		var group = button.getAttribute( 'data-pencil-filter' );
		Array.prototype.forEach.call( buttons, function ( b ) {
			b.setAttribute( 'aria-pressed', b === button ? 'true' : 'false' );
		} );
		Array.prototype.forEach.call( items, function ( li ) {
			li.hidden = group !== 'all' && li.getAttribute( 'data-group' ) !== group;
		} );
	} );
}() );

/* Templates: the small button at the end of a long list of templates. */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-pencil-more]' ) : null;
		if ( ! button ) {
			return;
		}
		var rest = button.parentNode.querySelector( '.pencil-admin__rest' );
		if ( ! rest ) {
			return;
		}
		var open = rest.hidden;
		rest.hidden = ! open;
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		button.textContent = button.getAttribute( open ? 'data-less' : 'data-more' );
	} );
}() );
