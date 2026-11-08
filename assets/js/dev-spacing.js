/**
 * Pencilino Dev mode: the Spacing tab.
 * Pick a step of the size scale for padding and margin of the selected element. The change shows at once,
 * is saved as a small override and prints as one style block in the head. Nothing is written to theme files.
 */
( function () {
	'use strict';

	var api = window.pencilDevApi;
	var cfg = ( window.pencilDev || {} ).spacing;
	if ( ! api || ! cfg ) {
		return;
	}

	var h = api.h;
	var t = api.t;
	var SIDES = [ 'top', 'right', 'bottom', 'left' ];
	var NS = 'http://www.w3.org/2000/svg';
	var tab = 'inspect';
	var lastEl = null;
	var scope = 'one';
	var split = { padding: false, margin: false };
	var entries = {};
	var timers = {};
	var status = { text: '', error: false };
	var clearAsk = false;

	( cfg.entries || [] ).forEach( function ( e ) {
		entries[ e.selector ] = Object.assign( {}, e.props );
	} );

	/* One live style tag takes over from the one printed by the server, so changes show at once. */
	var serverTag = document.getElementById( 'pencil-dev-spacing-output' );
	if ( serverTag ) {
		serverTag.parentNode.removeChild( serverTag );
	}
	var liveTag = document.createElement( 'style' );
	liveTag.id = 'pencil-dev-spacing-live';
	document.head.appendChild( liveTag );

	var varPrefix = cfg.prefix || '';

	function css() {
		var out = '';
		Object.keys( entries ).forEach( function ( sel ) {
			var rules = '';
			Object.keys( entries[ sel ] ).forEach( function ( name ) {
				rules += name + ':var(--' + varPrefix + 'size-' + entries[ sel ][ name ] + ',' + entries[ sel ][ name ] + 'px) !important;';
			} );
			if ( rules ) {
				out += sel + '{' + rules + '}\n';
			}
		} );
		return out;
	}

	function apply() {
		liveTag.textContent = css();
	}
	apply();

	/* ---- The scale ---- */

	function scale() {
		var found = [];
		var byPrefix = {};
		api.rootVars().forEach( function ( name ) {
			var m = /^--((?:[a-z0-9]+-)*)size-([0-9]+)$/.exec( name );
			if ( m ) {
				( byPrefix[ m[ 1 ] ] = byPrefix[ m[ 1 ] ] || [] ).push( parseInt( m[ 2 ], 10 ) );
			}
		} );
		Object.keys( byPrefix ).forEach( function ( key ) {
			if ( byPrefix[ key ].length > found.length ) {
				found = byPrefix[ key ];
				varPrefix = key;
			}
		} );
		found.sort( function ( a, b ) {
			return a - b;
		} );
		return found.length >= 3 ? found : cfg.scale;
	}

	/* ---- Finding the element again later ---- */

	var CLASS_OK = /^-?[A-Za-z_][A-Za-z0-9_-]*$/;

	function cls( el ) {
		return api.classes( el ).filter( function ( c ) {
			return CLASS_OK.test( c ) && ! /^(is|has)-/.test( c );
		} );
	}

	function piece( el ) {
		var s = el.tagName.toLowerCase();
		var list = cls( el );
		if ( list.length ) {
			s += '.' + list.join( '.' );
		}
		var parent = el.parentElement;
		if ( parent ) {
			var sameTag = Array.prototype.filter.call( parent.children, function ( n ) {
				return n.tagName === el.tagName;
			} );
			var matching = sameTag.filter( function ( n ) {
				try {
					return n.matches( s );
				} catch ( e ) {
					return false;
				}
			} );
			if ( matching.length > 1 ) {
				s += ':nth-of-type(' + ( sameTag.indexOf( el ) + 1 ) + ')';
			}
		}
		return s;
	}

	function anchor( el ) {
		var path = [];
		var node = el;
		while ( node && node !== document.body && node.nodeType === 1 ) {
			if ( node.id && /^[A-Za-z][A-Za-z0-9_-]*$/.test( node.id ) && document.querySelectorAll( '#' + node.id ).length === 1 ) {
				path.unshift( '#' + node.id );
				return path.join( ' > ' );
			}
			path.unshift( piece( node ) );
			node = node.parentElement;
		}
		path.unshift( 'body' );
		return path.join( ' > ' );
	}

	function check( sel, el ) {
		try {
			var found = document.querySelectorAll( sel );
			return found.length === 1 && found[ 0 ] === el ? sel : '';
		} catch ( e ) {
			return '';
		}
	}

	/* Several siblings with the same tag and classes: a list of cards, items, buttons. */
	function repeater( el ) {
		var list = cls( el );
		if ( ! list.length || ! el.parentElement ) {
			return null;
		}
		var group = el.tagName.toLowerCase() + '.' + list.join( '.' );
		var count = el.parentElement.querySelectorAll( ':scope > ' + group ).length;
		if ( count < 2 ) {
			return null;
		}
		var parent = anchor( el.parentElement );
		return { selector: parent + ' > ' + group, count: count };
	}

	function selectorFor( el ) {
		if ( scope === 'all' ) {
			var rep = repeater( el );
			if ( rep ) {
				return rep.selector;
			}
		}
		return check( anchor( el ), el );
	}

	/* ---- Reading and saving ---- */

	function themeValue( el, name ) {
		liveTag.disabled = true;
		var px = parseFloat( window.getComputedStyle( el )[ name.replace( /-([a-z])/g, function ( m, c ) {
			return c.toUpperCase();
		} ) ] ) || 0;
		liveTag.disabled = false;
		return Math.round( px * 10 ) / 10;
	}

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
			post( 'pencil_spacing_save', { selector: sel, props: JSON.stringify( entries[ sel ] || {} ), prefix: varPrefix }, function ( data, message ) {
				status = data ? { text: t( 'applied', 'Applied' ), error: false } : { text: message || t( 'saveFailed', 'Not saved' ), error: true };
				refreshFooter();
			} );
		}, 250 );
		refreshFooter();
	}

	function setProps( sel, names, value ) {
		var props = entries[ sel ] || {};
		names.forEach( function ( name ) {
			if ( value === null ) {
				delete props[ name ];
			} else {
				props[ name ] = value;
			}
		} );
		if ( Object.keys( props ).length ) {
			entries[ sel ] = props;
		} else {
			delete entries[ sel ];
		}
		apply();
		save( sel );
		api.schedule();
	}

	/* ---- The panel ---- */

	var footerBox = null;
	var footerState = null;

	function svg( parts ) {
		var s = document.createElementNS( NS, 'svg' );
		s.setAttribute( 'viewBox', '0 0 14 14' );
		s.setAttribute( 'aria-hidden', 'true' );
		s.setAttribute( 'class', 'pencil-dev-sp-icon' );
		parts.forEach( function ( p ) {
			var node = document.createElementNS( NS, p[ 0 ] );
			Object.keys( p[ 1 ] ).forEach( function ( k ) {
				node.setAttribute( k, p[ 1 ][ k ] );
			} );
			s.appendChild( node );
		} );
		return s;
	}

	var EDGE = {
		top: { x1: 2, y1: 2, x2: 12, y2: 2 },
		bottom: { x1: 2, y1: 12, x2: 12, y2: 12 },
		left: { x1: 2, y1: 2, x2: 2, y2: 12 },
		right: { x1: 12, y1: 2, x2: 12, y2: 12 }
	};

	function icon( sides ) {
		var parts = [ [ 'rect', { x: 2, y: 2, width: 10, height: 10, rx: 2, 'class': 'frame' } ] ];
		sides.forEach( function ( s ) {
			parts.push( [ 'line', Object.assign( { 'class': 'edge' }, EDGE[ s ] ) ] );
		} );
		return svg( parts );
	}

	function names( base, sides ) {
		return sides.map( function ( s ) {
			return base + '-' + s;
		} );
	}

	/* The first option is what the page had before any adjustment. Closed, it shows only the number. Open, it says (original). */
	function original( select, first, value ) {
		var reset = function () {
			first.textContent = String( value );
		};
		select.addEventListener( 'focus', function () {
			first.textContent = value + ' (' + t( 'original', 'original' ) + ')';
		} );
		select.addEventListener( 'blur', reset );
		select.addEventListener( 'change', reset );
	}

	function field( el, sel, base, title, sides, rerender ) {
		var props = entries[ sel ] || {};
		var list = names( base, sides );
		var cur = props[ list[ 0 ] ];
		var wrap = h( 'label', 'pencil-dev-sp-field' + ( cur !== undefined ? ' is-changed' : '' ) );
		wrap.title = title;
		wrap.appendChild( icon( sides ) );
		var select = document.createElement( 'select' );
		select.setAttribute( 'aria-label', base + ' ' + title.toLowerCase() );
		var theme = themeValue( el, list[ 0 ] );
		var first = document.createElement( 'option' );
		first.value = '';
		first.textContent = String( theme );
		original( select, first, theme );
		select.appendChild( first );
		var steps = scale();
		if ( cur !== undefined && steps.indexOf( cur ) < 0 ) {
			steps = steps.concat( [ cur ] ).sort( function ( a, b ) {
				return a - b;
			} );
		}
		steps.forEach( function ( n ) {
			var o = document.createElement( 'option' );
			o.value = String( n );
			o.textContent = String( n );
			select.appendChild( o );
		} );
		select.value = cur === undefined ? '' : String( cur );
		select.addEventListener( 'change', function () {
			setProps( sel, list, select.value === '' ? null : parseInt( select.value, 10 ) );
			rerender();
		} );
		wrap.appendChild( select );
		return wrap;
	}

	/* Pairs are only offered while both sides of a pair agree. */
	function canPair( el, sel, base ) {
		var props = entries[ sel ] || {};
		return [ [ 'top', 'bottom' ], [ 'left', 'right' ] ].every( function ( pair ) {
			var a = base + '-' + pair[ 0 ];
			var b = base + '-' + pair[ 1 ];
			return props[ a ] === props[ b ] && themeValue( el, a ) === themeValue( el, b );
		} );
	}

	function group( el, sel, base, title, rerender ) {
		var box = h( 'div', 'pencil-dev-section' );
		var head = h( 'p', 'pencil-dev-label pencil-dev-sp-head' );
		head.appendChild( h( 'span', '', title ) );
		var pairable = canPair( el, sel, base );
		var isSplit = split[ base ] || ! pairable;
		if ( pairable ) {
			var toggle = h( 'button', 'pencil-dev-link', isSplit ? t( 'link', 'Link sides' ) : t( 'split', 'Split sides' ) );
			toggle.type = 'button';
			toggle.addEventListener( 'click', function () {
				split[ base ] = ! split[ base ];
				rerender();
			} );
			head.appendChild( toggle );
		}
		box.appendChild( head );

		var grid = h( 'div', isSplit ? 'pencil-dev-sp-cross' : 'pencil-dev-sp-pair' );
		if ( isSplit ) {
			[ [ 'top', 'Top' ], [ 'left', 'Left' ], [ 'right', 'Right' ], [ 'bottom', 'Bottom' ] ].forEach( function ( s ) {
				var f = field( el, sel, base, t( s[ 0 ], s[ 1 ] ), [ s[ 0 ] ], rerender );
				f.classList.add( 'is-' + s[ 0 ] );
				grid.appendChild( f );
			} );
		} else {
			grid.appendChild( field( el, sel, base, t( 'vertical', 'Vertical' ), [ 'top', 'bottom' ], rerender ) );
			grid.appendChild( field( el, sel, base, t( 'horizontal', 'Horizontal' ), [ 'left', 'right' ], rerender ) );
		}
		box.appendChild( grid );

		var props = entries[ sel ] || {};
		var was = [];
		SIDES.forEach( function ( s ) {
			if ( props[ base + '-' + s ] !== undefined ) {
				was.push( s.charAt( 0 ).toUpperCase() + s.slice( 1 ) + ': ' + t( 'original', 'original' ) + ' ' + themeValue( el, base + '-' + s ) );
			}
		} );
		if ( was.length ) {
			box.appendChild( h( 'p', 'pencil-dev-sp-was', was.join( ', ' ) ) );
		}
		return box;
	}

	/* Which way the children run: side by side (horizontal) or stacked (vertical). Read from where the first two children sit. */
	function gapHorizontal( el ) {
		var kids = Array.prototype.filter.call( el.children, function ( c ) {
			return c.getClientRects().length > 0 && String( c.className ).indexOf( 'pencil-' ) !== 0;
		} );
		if ( kids.length >= 2 ) {
			var r1 = kids[ 0 ].getBoundingClientRect();
			var r2 = kids[ 1 ].getBoundingClientRect();
			if ( r2.left >= r1.right - 1 && r2.top < r1.bottom ) {
				return true;
			}
			return false;
		}
		var cs = window.getComputedStyle( el );
		return /flex/.test( cs.display ) && cs.flexDirection.indexOf( 'row' ) === 0;
	}

	function gapIcon( el ) {
		if ( gapHorizontal( el ) ) {
			return svg( [ [ 'rect', { x: 2, y: 2, width: 4, height: 10, rx: 1, 'class': 'frame' } ], [ 'rect', { x: 8, y: 2, width: 4, height: 10, rx: 1, 'class': 'frame' } ], [ 'line', { 'class': 'edge', x1: 7, y1: 2, x2: 7, y2: 12 } ] ] );
		}
		return svg( [ [ 'rect', { x: 2, y: 2, width: 10, height: 4, rx: 1, 'class': 'frame' } ], [ 'rect', { x: 2, y: 8, width: 10, height: 4, rx: 1, 'class': 'frame' } ], [ 'line', { 'class': 'edge', x1: 2, y1: 7, x2: 12, y2: 7 } ] ] );
	}

	/* Gap: only for elements that hold two or more children. It needs flex or grid to do anything. */
	function gapGroup( el, sel, rerender ) {
		if ( el.children.length < 2 ) {
			return null;
		}
		var display = window.getComputedStyle( el ).display;
		var works = /flex|grid/.test( display );
		var props = entries[ sel ] || {};
		var cur = props.gap;
		var box = h( 'div', 'pencil-dev-section' );
		var head = h( 'p', 'pencil-dev-label pencil-dev-sp-head' );
		head.appendChild( h( 'span', '', t( 'gap', 'Gap' ) ) );
		box.appendChild( head );
		var wrap = h( 'label', 'pencil-dev-sp-field' + ( cur !== undefined ? ' is-changed' : '' ) );
		wrap.title = t( 'gap', 'Gap' );
		wrap.appendChild( gapIcon( el ) );
		var select = document.createElement( 'select' );
		select.setAttribute( 'aria-label', 'gap' );
		var first = document.createElement( 'option' );
		first.value = '';
		var gapOriginal = works ? themeValue( el, 'row-gap' ) : 0;
		first.textContent = String( gapOriginal );
		original( select, first, gapOriginal );
		select.appendChild( first );
		var steps = scale();
		if ( cur !== undefined && steps.indexOf( cur ) < 0 ) {
			steps = steps.concat( [ cur ] ).sort( function ( a, b ) {
				return a - b;
			} );
		}
		steps.forEach( function ( n ) {
			var o = document.createElement( 'option' );
			o.value = String( n );
			o.textContent = String( n );
			select.appendChild( o );
		} );
		select.value = cur === undefined ? '' : String( cur );
		select.addEventListener( 'change', function () {
			setProps( sel, [ 'gap' ], select.value === '' ? null : parseInt( select.value, 10 ) );
			rerender();
		} );
		wrap.appendChild( select );
		box.appendChild( wrap );
		if ( ! works ) {
			box.appendChild( h( 'p', 'pencil-dev-sp-was', t( 'gapNeedsFlex', 'Gap only works on flex or grid. This element is display: ' ) + display + '.' ) );
		}
		return box;
	}
	function footer( sel ) {
		var props = entries[ sel ] || {};
		var n = Object.keys( props ).length;
		var total = Object.keys( entries ).length;
		footerBox.textContent = '';
		var row = h( 'div', 'pencil-dev-sp-foot' );
		var left = h( 'span', 'pencil-dev-sp-count' );
		left.appendChild( document.createTextNode( n === 1 ? t( 'oneChange', '1 adjustment' ) : t( 'manyChanges', '{n} adjustments' ).replace( '{n}', String( n ) ) ) );
		if ( n && status.text ) {
			left.appendChild( h( 'span', 'pencil-dev-sp-chip' + ( status.error ? ' is-error' : '' ), status.text ) );
		}
		row.appendChild( left );
		if ( n ) {
			var reset = h( 'button', 'pencil-dev-link', t( 'reset', 'Reset' ) );
			reset.type = 'button';
			reset.addEventListener( 'click', function () {
				delete entries[ sel ];
				apply();
				save( sel );
				footerState.rerender();
				api.schedule();
			} );
			row.appendChild( reset );
		}
		footerBox.appendChild( row );
		if ( total ) {
			var all = h( 'p', 'pencil-dev-sp-total' );
			all.appendChild( document.createTextNode( ( total === 1 ? t( 'oneOnSite', '1 adjustment on this site.' ) : t( 'manyOnSite', '{n} adjustments on this site.' ).replace( '{n}', String( total ) ) ) + ' ' ) );
			var clear = h( 'button', 'pencil-dev-link', clearAsk ? t( 'clearSure', 'Click again to clear all' ) : t( 'clearAll', 'Clear all' ) );
			clear.type = 'button';
			clear.addEventListener( 'click', function () {
				if ( ! clearAsk ) {
					clearAsk = true;
					footer( sel );
					return;
				}
				clearAsk = false;
				entries = {};
				apply();
				post( 'pencil_spacing_reset', { id: 'all' }, function () {} );
				status = { text: '', error: false };
				footerState.rerender();
				api.schedule();
			} );
			all.appendChild( clear );
			footerBox.appendChild( all );
		}
	}

	function refreshFooter() {
		if ( footerBox && footerState && document.body.contains( footerBox ) ) {
			footer( footerState.sel );
		}
	}

	/* Where the space next to an element really comes from, when it is not the element itself. */
	function skipNode( node ) {
		return ! node || node.nodeName === 'SCRIPT' || node.nodeName === 'STYLE' || String( node.className || '' ).indexOf( 'pencil-' ) !== -1;
	}

	function sibling( node, next ) {
		var s = next ? node.nextElementSibling : node.previousElementSibling;
		while ( s && skipNode( s ) ) {
			s = next ? s.nextElementSibling : s.previousElementSibling;
		}
		return s;
	}

	function spaceSource( el, below ) {
		var num = function ( node, name ) {
			return parseFloat( window.getComputedStyle( node )[ name ] ) || 0;
		};
		var own = below ? 'marginBottom' : 'marginTop';
		if ( num( el, own ) > 0 ) {
			return null;
		}
		var node = el;
		for ( var i = 0; i < 8; i++ ) {
			if ( node !== el && num( node, own ) > 0 ) {
				return { el: node, kind: 'parent', px: num( node, own ) };
			}
			var nb = sibling( node, below );
			if ( nb ) {
				var a = node.getBoundingClientRect();
				var b = nb.getBoundingClientRect();
				var gap = below ? b.top - a.bottom : a.top - b.bottom;
				if ( gap < 1 ) {
					return null;
				}
				if ( num( nb, below ? 'marginTop' : 'marginBottom' ) > 0 ) {
					return { el: nb, kind: below ? 'next' : 'previous', px: gap };
				}
				var par = node.parentElement;
				if ( par && /flex|grid/.test( window.getComputedStyle( par ).display ) && num( par, 'rowGap' ) > 0 ) {
					return { el: par, kind: 'parent', px: gap };
				}
				return null;
			}
			var p = node.parentElement;
			if ( ! p || p === document.body || p === document.documentElement ) {
				return null;
			}
			if ( num( p, below ? 'paddingBottom' : 'paddingTop' ) > 0 ) {
				return { el: p, kind: 'parent', px: num( p, below ? 'paddingBottom' : 'paddingTop' ) };
			}
			node = p;
		}
		return null;
	}

	function spaceHints( el ) {
		var found = [];
		[ false, true ].forEach( function ( below ) {
			var s = spaceSource( el, below );
			if ( s && s.px >= 1 ) {
				found.push( { below: below, s: s } );
			}
		} );
		if ( ! found.length ) {
			return null;
		}
		var section = h( 'div', 'pencil-dev-section' );
		var box = h( 'div', 'pencil-dev-sp-hint' );
		found.forEach( function ( o ) {
			var who = { parent: t( 'fromParent', 'the parent' ), previous: t( 'fromPrevious', 'the previous element' ), next: t( 'fromNext', 'the next element' ) }[ o.s.kind ];
			var dir = o.below ? t( 'spaceBelow', 'below' ) : t( 'spaceAbove', 'above' );
			var row = h( 'div', 'pencil-dev-sp-hintrow' );
			row.appendChild( h( 'span', '', t( 'spaceFrom', '{n} space {dir} comes from {who}.' ).replace( '{n}', String( Math.round( o.s.px ) ) ).replace( '{dir}', dir ).replace( '{who}', who ) ) );
			var go = h( 'button', 'pencil-dev-pill', t( 'selectIt', 'Select' ) );
			go.type = 'button';
			go.addEventListener( 'click', function () {
				api.pin( o.s.el );
			} );
			row.appendChild( go );
			box.appendChild( row );
		} );
		section.appendChild( box );
		return section;
	}

	function body( panel, el, rerender ) {
		if ( el !== lastEl ) {
			lastEl = el;
			scope = 'one';
			split = { padding: false, margin: false };
			status = { text: '', error: false };
			clearAsk = false;
		}
		var rep = repeater( el );
		if ( ! rep ) {
			scope = 'one';
		}
		var sel = selectorFor( el );

		if ( rep ) {
			var info = h( 'div', 'pencil-dev-section' );
			var seg = h( 'div', 'pencil-dev-sp-seg' );
			[ [ 'one', t( 'thisElement', 'This element' ) ], [ 'all', t( 'allIn', 'All {n} in section' ).replace( '{n}', String( rep.count ) ) ] ].forEach( function ( o ) {
				var b = h( 'button', scope === o[ 0 ] ? 'is-on' : '', o[ 1 ] );
				b.type = 'button';
				b.addEventListener( 'click', function () {
					scope = o[ 0 ];
					rerender();
				} );
				seg.appendChild( b );
			} );
			info.appendChild( seg );
			panel.appendChild( info );
		}
		if ( ! sel ) {
			var warn = h( 'div', 'pencil-dev-section' );
			warn.appendChild( h( 'p', 'pencil-dev-muted', t( 'noAnchor', 'This element cannot be found again reliably, so it cannot be adjusted.' ) ) );
			panel.appendChild( warn );
			return;
		}

		var hintBox = spaceHints( el );
		if ( hintBox ) {
			panel.appendChild( hintBox );
		}
		panel.appendChild( group( el, sel, 'padding', t( 'padding', 'Padding' ), rerender ) );
		panel.appendChild( group( el, sel, 'margin', t( 'margin', 'Margin' ), rerender ) );
		var gapBox = gapGroup( el, sel, rerender );
		if ( gapBox ) {
			panel.appendChild( gapBox );
		}

		footerBox = h( 'div', 'pencil-dev-section' );
		panel.appendChild( footerBox );
		footerState = { sel: sel, rerender: rerender };
		footer( sel );
	}

	/* The selector that finds this element again. The class switcher uses it too. */
	api.anchorSelector = function ( el ) {
		return check( anchor( el ), el );
	};

	/* ---- Tabs, called by the Dev panel ---- */

	api.tabs = function ( panel, el, render ) {
		var bar = h( 'div', 'pencil-dev-sp-tabs' );
		[ [ 'inspect', t( 'inspect', 'Inspect' ) ], [ 'spacing', t( 'spacing', 'Spacing' ) ] ].forEach( function ( o ) {
			var b = h( 'button', tab === o[ 0 ] ? 'is-on' : '', o[ 1 ] );
			b.type = 'button';
			b.addEventListener( 'click', function () {
				tab = o[ 0 ];
				if ( tab !== 'spacing' ) {
					hide();
				}
				render( el );
				api.schedule();
			} );
			bar.appendChild( b );
		} );
		panel.appendChild( bar );
		if ( tab !== 'spacing' ) {
			return false;
		}
		body( panel, el, function () {
			render( el );
			api.schedule();
		} );
		return true;
	};

	/* ---- The bands on the page: padding inside the element, margin outside it ---- */

	var overlay = h( 'div', 'pencil-ui pencil-dev-sp-overlay' );
	overlay.hidden = true;
	document.body.appendChild( overlay );
	var bands = {};
	[ 'padding', 'margin' ].forEach( function ( base ) {
		SIDES.forEach( function ( s ) {
			var b = h( 'div', 'pencil-dev-sp-band pencil-dev-sp-band--' + base );
			b.hidden = true;
			bands[ base + '-' + s ] = b;
			overlay.appendChild( b );
		} );
	} );

	function band( name, left, top, width, height, value ) {
		var b = bands[ name ];
		if ( value <= 0 || width <= 0 || height <= 0 ) {
			b.hidden = true;
			return;
		}
		b.hidden = false;
		b.style.left = left + 'px';
		b.style.top = top + 'px';
		b.style.width = width + 'px';
		b.style.height = height + 'px';
		b.textContent = Math.min( width, height ) >= 14 || value >= 14 ? String( Math.round( value ) ) : '';
	}

	function hide() {
		overlay.hidden = true;
	}

	api.hide = hide;

	api.draw = function ( el ) {
		if ( tab !== 'spacing' || ! el || ! document.body.contains( el ) ) {
			hide();
			return;
		}
		var r = el.getBoundingClientRect();
		var cs = window.getComputedStyle( el );
		var v = function ( n ) {
			return parseFloat( cs[ n ] ) || 0;
		};
		var p = { top: v( 'paddingTop' ), right: v( 'paddingRight' ), bottom: v( 'paddingBottom' ), left: v( 'paddingLeft' ) };
		var m = { top: v( 'marginTop' ), right: v( 'marginRight' ), bottom: v( 'marginBottom' ), left: v( 'marginLeft' ) };
		overlay.hidden = false;
		band( 'padding-top', r.left, r.top, r.width, p.top, p.top );
		band( 'padding-bottom', r.left, r.bottom - p.bottom, r.width, p.bottom, p.bottom );
		band( 'padding-left', r.left, r.top + p.top, p.left, r.height - p.top - p.bottom, p.left );
		band( 'padding-right', r.right - p.right, r.top + p.top, p.right, r.height - p.top - p.bottom, p.right );
		band( 'margin-top', r.left, r.top - m.top, r.width, m.top, m.top );
		band( 'margin-bottom', r.left, r.bottom, r.width, m.bottom, m.bottom );
		band( 'margin-left', r.left - m.left, r.top, m.left, r.height, m.left );
		band( 'margin-right', r.right, r.top, m.right, r.height, m.right );
	};
}() );
