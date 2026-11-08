/**
 * Pencilino Dev mode: the class switcher.
 * Pick a related class for the selected element, for example title-2 to title-3. The change shows at once, is saved,
 * and prints as one small style block in the head that copies the declarations of the new class onto the element.
 * Nothing is written to theme files. The clean-up prompt carries the exact class change into the theme.
 */
( function () {
	'use strict';

	var api = window.pencilDevApi;
	var cfg = ( window.pencilDev || {} ).classes;
	if ( ! api || ! cfg ) {
		return;
	}

	var h = api.h;
	var t = api.t;
	var PROPS = [ 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'color', 'background-color', 'background', 'border-radius', 'box-shadow', 'border-color' ];
	/* Families of classes that are safe to swap: type size, text colour, background, corners, shadow. */
	var FAMILIES = [
		/^(title-[1-6]|body-[lms])$/,
		/^(heading|body|detail|subtitle|link)-on-(light|dark)$/,
		/^bg-/,
		/^rounded-/,
		/^shadow-[sml]$/
	];
	var entries = {};
	var timers = {};
	var openClass = '';
	var lastEl = null;
	var redraw = null;
	var status = { text: '', error: false };

	( cfg.entries || [] ).forEach( function ( e ) {
		entries[ e.selector ] = ( e.swaps || [] ).slice();
	} );

	/* One live style tag takes over from the one printed by the server, so changes show at once. */
	var serverTag = document.getElementById( 'pencil-dev-classes-output' );
	if ( serverTag ) {
		serverTag.parentNode.removeChild( serverTag );
	}
	var liveTag = document.createElement( 'style' );
	liveTag.id = 'pencil-dev-classes-live';
	document.head.appendChild( liveTag );

	function css() {
		var out = '';
		Object.keys( entries ).forEach( function ( sel ) {
			entries[ sel ].forEach( function ( swap ) {
				swap.rules.forEach( function ( rule ) {
					var decls = '';
					Object.keys( rule.decls ).forEach( function ( name ) {
						decls += name + ':' + rule.decls[ name ] + ' !important;';
					} );
					var block = sel + '{' + decls + '}';
					out += ( rule.media ? '@media ' + rule.media + '{' + block + '}' : block ) + '\n';
				} );
			} );
		} );
		return out;
	}

	function apply() {
		liveTag.textContent = css();
	}
	apply();

	function swapsOf( sel ) {
		return sel && entries[ sel ] ? entries[ sel ] : [];
	}

	/* ---- What a class does ---- */

	/* The declarations of a class as the stylesheets hold them, with the media query they sit in. */
	function rulesFor( name ) {
		var found = [];

		function walk( list, media ) {
			for ( var i = 0; i < list.length; i++ ) {
				var rule = list[ i ];
				if ( rule.selectorText && rule.style ) {
					var hit = rule.selectorText.split( ',' ).some( function ( part ) {
						return part.trim() === '.' + name;
					} );
					if ( hit ) {
						var decls = {};
						var any = false;
						PROPS.forEach( function ( p ) {
							var v = rule.style.getPropertyValue( p );
							if ( v ) {
								decls[ p ] = v.trim();
								any = true;
							}
						} );
						if ( any ) {
							found.push( { media: media, decls: decls } );
						}
					}
				} else if ( rule.cssRules ) {
					walk( rule.cssRules, rule.media && ! media ? rule.media.mediaText : media );
				}
			}
		}

		Array.prototype.forEach.call( document.styleSheets, function ( sheet ) {
			if ( ! api.usable( sheet ) ) {
				return;
			}
			try {
				walk( sheet.cssRules, '' );
			} catch ( e ) {
				/* A stylesheet from another domain cannot be read. */
			}
		} );
		return found;
	}

	function familyOf( name ) {
		var b = api.bare( name );
		for ( var i = 0; i < FAMILIES.length; i++ ) {
			if ( FAMILIES[ i ].test( b ) ) {
				return i;
			}
		}
		return -1;
	}

	/* The classes this class can be swapped for: same family, same prefix. */
	function options( name ) {
		var fam = familyOf( name );
		if ( fam < 0 || ! api.isFramework( name ) ) {
			return [];
		}
		var prefixed = api.bare( name ) !== name;
		var list = api.known().filter( function ( n ) {
			return familyOf( n ) === fam && ( api.bare( n ) !== n ) === prefixed && api.isFramework( n );
		} );
		if ( list.indexOf( name ) < 0 ) {
			list.push( name );
		}
		return list;
	}

	/* What a class looks like, read from a hidden element. */
	function probe( name ) {
		var node = document.createElement( 'div' );
		node.className = name;
		node.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;';
		document.body.appendChild( node );
		var s = window.getComputedStyle( node );
		var out = { size: s.fontSize, color: api.hex( s.color ), bg: api.hex( s.backgroundColor ), radius: s.borderRadius, shadow: s.boxShadow };
		document.body.removeChild( node );
		return out;
	}

	function hint( p, fam ) {
		if ( fam === 0 ) {
			return p.size;
		}
		if ( fam === 1 ) {
			return p.color;
		}
		if ( fam === 2 ) {
			return p.bg;
		}
		return fam === 3 ? p.radius : ( p.shadow === 'none' ? 'none' : '' );
	}

	/* ---- Reading and saving ---- */

	function post( action, data, done ) {
		var body = new window.URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		window.fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				done( json && json.success ? json.data : null, json && json.data && json.data.message );
			} )
			.catch( function () {
				done( null, '' );
			} );
	}

	function save( sel ) {
		status = { text: t( 'saving', 'Saving' ), error: false };
		window.clearTimeout( timers[ sel ] );
		timers[ sel ] = window.setTimeout( function () {
			var sent = swapsOf( sel ).length;
			post( 'pencil_classes_save', { selector: sel, swaps: JSON.stringify( swapsOf( sel ) ) }, function ( data, message ) {
				status = data && data.kept === sent ? { text: t( 'applied', 'Applied' ), error: false } : { text: message || t( 'saveFailed', 'Not saved' ), error: true };
				if ( redraw ) {
					redraw();
				}
			} );
		}, 250 );
		if ( redraw ) {
			redraw();
		}
	}

	function choose( sel, from, to ) {
		var swaps = swapsOf( sel ).filter( function ( s ) {
			return s.from !== from;
		} );
		if ( to !== from ) {
			var rules = rulesFor( to );
			if ( ! rules.length ) {
				status = { text: t( 'noStyles', 'No styles found for this class' ), error: true };
				return false;
			}
			swaps.push( { from: from, to: to, rules: rules } );
		}
		if ( swaps.length ) {
			entries[ sel ] = swaps;
		} else {
			delete entries[ sel ];
		}
		apply();
		save( sel );
		api.schedule();
		return true;
	}

	/* ---- The class chips ---- */

	function tool( icon, label, onClick, expanded ) {
		var b = h( 'button', 'pencil-dev-chip-tool' );
		b.type = 'button';
		b.title = label;
		b.setAttribute( 'aria-label', label );
		if ( expanded !== undefined ) {
			b.setAttribute( 'aria-haspopup', 'listbox' );
			b.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		}
		var mark = h( 'span', 'dashicons ' + icon );
		mark.setAttribute( 'aria-hidden', 'true' );
		b.appendChild( mark );
		b.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
			onClick( mark );
		} );
		return b;
	}

	function menu( c, shown, sel, rerender, build ) {
		var fam = familyOf( c );
		var names = options( c );
		var info = {};
		names.forEach( function ( n ) {
			info[ n ] = probe( n );
		} );
		names.sort( function ( a, b ) {
			return fam === 0 ? parseFloat( info[ b ].size ) - parseFloat( info[ a ].size ) : a.localeCompare( b, undefined, { numeric: true } );
		} );

		var box = h( 'div', 'pencil-dev-menu' );
		box.setAttribute( 'role', 'listbox' );
		var search = h( 'input', 'pencil-dev-menu-search' );
		search.type = 'search';
		search.id = 'pencil-dev-class-search';
		search.placeholder = t( 'searchClasses', 'Search classes' );
		search.setAttribute( 'aria-label', t( 'searchClasses', 'Search classes' ) );
		box.appendChild( search );
		var list = h( 'div', 'pencil-dev-menu-list' );
		box.appendChild( list );

		function fill() {
			var q = search.value.trim().toLowerCase();
			list.textContent = '';
			names.forEach( function ( n ) {
				if ( q && n.toLowerCase().indexOf( q ) < 0 ) {
					return;
				}
				var o = h( 'button', 'pencil-dev-opt' );
				o.type = 'button';
				o.setAttribute( 'role', 'option' );
				o.setAttribute( 'aria-selected', n === shown ? 'true' : 'false' );
				o.appendChild( h( 'span', '', n ) );
				o.appendChild( h( 'small', '', n === c ? t( 'original', '(original)' ) : hint( info[ n ], fam ) ) );
				o.addEventListener( 'click', function ( event ) {
					event.stopPropagation();
					if ( choose( sel, c, n ) ) {
						openClass = '';
						rerender();
					} else {
						build();
					}
				} );
				list.appendChild( o );
			} );
		}
		search.addEventListener( 'input', fill );
		fill();
		return box;
	}

	function note( swaps ) {
		return h( 'p', 'pencil-dev-note pencil-dev-swapnote', t( 'swapNote', 'Applied on the site. Run the clean-up to make it permanent in your theme.' ) );
	}

	/* The Classes section: only the classes that can be changed, with a copy on click and an edit button. */
	api.classChips = function ( el, list, rerender ) {
		if ( el !== lastEl ) {
			lastEl = el;
			openClass = '';
			status = { text: '', error: false };
		}
		var sel = api.anchorSelector ? api.anchorSelector( el ) : '';
		var wrap = h( 'div', 'pencil-dev-classbox' );

		function build() {
			wrap.textContent = '';
			var swaps = swapsOf( sel );
			var chips = h( 'div', 'pencil-dev-classlist pencil-dev-chips--switch' );
			var editable = list.filter( function ( c ) {
				return sel && options( c ).length > 1;
			} );
			editable.forEach( function ( c ) {
				var swap = swaps.filter( function ( s ) {
					return s.from === c;
				} )[ 0 ];
				var shown = swap ? swap.to : c;
				var item = h( 'div', 'pencil-dev-classitem' );
				var row = h( 'div', 'pencil-dev-chiprow' );
				var chip = h( 'span', 'pencil-dev-chip pencil-dev-chip--split' + ( api.isFramework( shown ) ? ' pencil-dev-chip--framework' : '' ) + ( swap ? ' is-changed' : '' ) );
				var nameBtn = h( 'button', 'pencil-dev-chip-name' );
				nameBtn.type = 'button';
				nameBtn.title = t( 'copy', 'Copy' ) + ' ' + shown;
				nameBtn.textContent = api.bare( shown );
				nameBtn.addEventListener( 'click', function ( event ) {
					event.stopPropagation();
					api.copy( shown, function () {
						nameBtn.textContent = t( 'copied', 'Copied' );
						nameBtn.classList.add( 'is-copied' );
						window.setTimeout( function () {
							nameBtn.textContent = api.bare( shown );
							nameBtn.classList.remove( 'is-copied' );
						}, 1100 );
					} );
				} );
				chip.appendChild( nameBtn );
				{
					chip.appendChild( tool( 'dashicons-edit', t( 'editClass', 'Edit class' ) + ' ' + shown, function () {
						openClass = openClass === c ? '' : c;
						build();
					}, openClass === c ) );
				}
				row.appendChild( chip );
				if ( openClass === c ) {
					row.appendChild( menu( c, shown, sel, rerender, build ) );
				}
				item.appendChild( row );
				chips.appendChild( item );
			} );
			wrap.appendChild( chips );
			if ( ! editable.length ) {
				wrap.appendChild( h( 'p', 'pencil-dev-muted', t( 'noEditable', 'No framework class on this element to change.' ) ) );
			}
			if ( openClass ) {
				var field = wrap.querySelector( '.pencil-dev-menu-search' );
				if ( field && document.activeElement !== field && ! wrap.contains( document.activeElement ) ) {
					field.focus();
				}
			}

			if ( ! swaps.length ) {
				if ( status.error ) {
					wrap.appendChild( h( 'p', 'pencil-dev-note', status.text ) );
				}
				return;
			}
			var bar = h( 'div', 'pencil-dev-swapbar' );
			bar.appendChild( h( 'span', 'pencil-dev-swapcount', swaps.length === 1 ? t( 'oneClassChanged', '1 class changed' ) : t( 'manyClassesChanged', '{n} classes changed' ).replace( '{n}', String( swaps.length ) ) ) );
			var reset = h( 'button', 'pencil-dev-pill', t( 'reset', 'Reset' ) );
			reset.type = 'button';
			reset.addEventListener( 'click', function () {
				delete entries[ sel ];
				openClass = '';
				status = { text: '', error: false };
				apply();
				save( sel );
				api.schedule();
				rerender();
			} );
			bar.appendChild( reset );
			if ( status.text && status.error ) {
				bar.appendChild( h( 'span', 'pencil-dev-sp-chip is-error', status.text ) );
			}
			wrap.appendChild( bar );
			wrap.appendChild( note( swaps ) );
		}

		redraw = function () {
			if ( document.body.contains( wrap ) ) {
				build();
			}
		};
		build();
		return wrap;
	};

	/* A click outside the chips, or Escape, closes the list. */
	document.addEventListener( 'click', function ( event ) {
		if ( openClass && redraw && ! ( event.target.closest && event.target.closest( '.pencil-dev-chips--switch' ) ) ) {
			openClass = '';
			redraw();
		}
	}, true );
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && openClass && redraw ) {
			event.stopPropagation();
			openClass = '';
			redraw();
		}
	}, true );

	/* ---- What the rest of the panel and the fix prompt need ---- */

	api.swaps = function ( el ) {
		return swapsOf( api.anchorSelector ? api.anchorSelector( el ) : '' ).map( function ( s ) {
			return { from: s.from, to: s.to };
		} );
	};

	/* Every class change on the site, except the ones on this element. */
	api.allSwaps = function ( except ) {
		var skip = except && api.anchorSelector ? api.anchorSelector( except ) : '';
		var out = [];
		Object.keys( entries ).forEach( function ( sel ) {
			if ( sel !== skip ) {
				entries[ sel ].forEach( function ( s ) {
					out.push( { selector: sel, from: s.from, to: s.to } );
				} );
			}
		} );
		return out;
	};

	/* The value a swap gives one property of this element, so the Result rows follow the swap. */
	api.swapRaw = function ( el, prop ) {
		var out = '';
		swapsOf( api.anchorSelector ? api.anchorSelector( el ) : '' ).forEach( function ( s ) {
			s.rules.forEach( function ( r ) {
				if ( r.decls[ prop ] && ( ! r.media || window.matchMedia( r.media ).matches ) ) {
					out = r.decls[ prop ];
				}
			} );
		} );
		return out;
	};
}() );