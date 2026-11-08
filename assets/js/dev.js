/**
 * Pencilino Dev mode: hover an element to see its classes, click it to see how it is styled.
 * Reads the page in your browser. Nothing is sent or stored.
 */
( function () {
	'use strict';

	var cfg = window.pencilDev || {};
	var toolbar = document.querySelector( '[data-pencil-toolbar]' );
	if ( ! toolbar ) {
		return;
	}

	var L = cfg.labels || {};
	var prefix = cfg.prefix || '';
	var html = document.documentElement;
	var PROPS = [ [ 'font-family', 'font', 'Font' ], [ 'font-size', 'size', 'Size' ], [ 'color', 'color', 'Colour' ] ];
	var SIDES = [ 'top', 'right', 'bottom', 'left' ];
	var SPACING = [ 'gap' ];
	[ 'padding', 'margin' ].forEach( function ( base ) {
		SIDES.forEach( function ( side ) {
			SPACING.push( base + '-' + side );
		} );
	} );
	var GROUPS = [
		{ id: 'background', key: 'bg', one: 'Background', label: 'Backgrounds', keys: [ 'background' ] },
		{ id: 'spacing', key: 'space', one: 'Spacing', label: 'Spacing', keys: SPACING },
		{ id: 'radius', key: 'corners', one: 'Corners', label: 'Corners', keys: [ 'radius' ] },
		{ id: 'shadow', key: 'shadow', one: 'Shadow', label: 'Shadows', keys: [ 'shadow' ] },
		{ id: 'border', key: 'borderColor', one: 'Border colour', label: 'Border colours', keys: [ 'border-color' ] }
	];
	var classKeys = {};
	var TRANSIENT = [ ':hover', ':focus', ':active', ':visited', ':target' ];
	var QUOTE1 = String.fromCharCode( 34 );
	var QUOTE2 = String.fromCharCode( 39 );
	var on = false;
	var hovered = null;
	var pinned = null;
	var frame = 0;
	var cache = null;
	var rulesList = [];
	var blocked = 0;

	function t( key, fallback ) {
		return L[ key ] || fallback;
	}

	function h( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	/* The Dev button sits next to Edit. */
	var button = h( 'button', 'pencil-toolbar__dev' );
	button.type = 'button';
	button.setAttribute( 'aria-pressed', 'false' );
	var icon = h( 'span', 'dashicons dashicons-editor-code' );
	icon.setAttribute( 'aria-hidden', 'true' );
	var buttonLabel = h( 'span', '', t( 'dev', 'Dev' ) );
	button.appendChild( buttonLabel );
	button.appendChild( icon );
	toolbar.appendChild( button );

	var hoverBox = h( 'div', 'pencil-ui pencil-dev-box' );
	var pinBox = h( 'div', 'pencil-ui pencil-dev-box pencil-dev-box--pinned' );
	var tag = h( 'div', 'pencil-ui pencil-dev-tag' );
	var panel = h( 'aside', 'pencil-ui pencil-dev-panel' );
	[ hoverBox, pinBox, tag, panel ].forEach( function ( node ) {
		node.hidden = true;
		document.body.appendChild( node );
	} );

	/* Drag the panel by its header. The spot is remembered in this browser. */
	( function () {
		var KEY = 'pencilDevPanelPos';
		var drag = null;
		var place = function ( x, y ) {
			var w = panel.offsetWidth || 340;
			panel.style.left = Math.min( Math.max( 8, x ), Math.max( 8, window.innerWidth - w - 8 ) ) + 'px';
			panel.style.top = Math.min( Math.max( 8, y ), Math.max( 8, window.innerHeight - 80 ) ) + 'px';
			panel.style.right = 'auto';
		};
		var save = function () {
			var r = panel.getBoundingClientRect();
			try {
				window.localStorage.setItem( KEY, JSON.stringify( { x: Math.round( r.left ), y: Math.round( r.top ) } ) );
			} catch ( e ) {}
		};
		try {
			var saved = JSON.parse( window.localStorage.getItem( KEY ) || 'null' );
			if ( saved && typeof saved.x === 'number' && typeof saved.y === 'number' ) {
				place( saved.x, saved.y );
			}
		} catch ( e ) {}
		panel.addEventListener( 'pointerdown', function ( event ) {
			var target = event.target;
			if ( event.button !== 0 || ! target.closest || ! target.closest( '.pencil-dev-head' ) || target.closest( 'button, a, input' ) ) {
				return;
			}
			var r = panel.getBoundingClientRect();
			drag = { dx: event.clientX - r.left, dy: event.clientY - r.top };
			panel.classList.add( 'is-dragging' );
			panel.setPointerCapture( event.pointerId );
			event.preventDefault();
		} );
		panel.addEventListener( 'pointermove', function ( event ) {
			if ( drag ) {
				place( event.clientX - drag.dx, event.clientY - drag.dy );
			}
		} );
		var stop = function () {
			if ( drag ) {
				drag = null;
				panel.classList.remove( 'is-dragging' );
				save();
			}
		};
		panel.addEventListener( 'pointerup', stop );
		panel.addEventListener( 'pointercancel', stop );
		window.addEventListener( 'resize', function () {
			if ( panel.style.left && ! panel.hidden ) {
				var r = panel.getBoundingClientRect();
				place( r.left, r.top );
			}
		} );
	}() );

	/* Two small buttons on the selection: one level up, and back down to where you came from. */
	var trail = [];
	var nav = h( 'div', 'pencil-dev-nav' );

	function navButton( icon, label, onClick ) {
		var b = h( 'button', 'pencil-dev-navbtn' );
		b.type = 'button';
		b.title = label;
		b.setAttribute( 'aria-label', label );
		var mark = h( 'span', 'dashicons ' + icon );
		mark.setAttribute( 'aria-hidden', 'true' );
		b.appendChild( mark );
		b.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			onClick();
		} );
		return b;
	}

	var upButton = navButton( 'dashicons-arrow-up-alt2', t( 'selectParent', 'Select the parent' ), function () {
		var p = pinned ? parentOf( pinned ) : null;
		if ( p ) {
			trail.push( pinned );
			pin( p, true );
		}
	} );
	var downButton = navButton( 'dashicons-arrow-down-alt2', t( 'selectChild', 'Back to the child' ), function () {
		var c = trail.pop();
		if ( c && document.body.contains( c ) ) {
			pin( c, true );
		}
	} );
	nav.appendChild( upButton );
	nav.appendChild( downButton );
	pinBox.appendChild( nav );

	function updateNav() {
		upButton.hidden = ! ( pinned && parentOf( pinned ) );
		downButton.hidden = trail.length === 0;
	}
	function own( node ) {
		return ! node || ! node.closest || !! node.closest( '.pencil-ui, #wpadminbar' );
	}

	function rawClasses( el ) {
		return el.classList ? Array.prototype.slice.call( el.classList ) : [];
	}

	/* The classes of the theme. Pencilino's own classes (pencil-field and friends) are noise here. */
	function classes( el ) {
		return rawClasses( el ).filter( function ( c ) {
			return c.indexOf( 'pencil-' ) !== 0 || prefix.indexOf( 'pencil' ) === 0;
		} );
	}

	/* The span Pencilino puts around editable text. It has no classes of the theme, so the element around it is the real one. */
	function isWrapper( el ) {
		return el.tagName === 'SPAN' && el.hasAttribute( 'data-pencil-field' ) && classes( el ).length === 0;
	}

	/* The closest parent that is a real element of the page: not the body, and not Pencilino's own parts. */
	function parentOf( el ) {
		var p = el.parentElement;
		while ( p && p !== document.body && p !== document.documentElement && ( own( p ) || isWrapper( p ) ) ) {
			p = p.parentElement;
		}
		return p && p !== document.body && p !== document.documentElement ? p : null;
	}

	function inspectable( el ) {
		while ( el && el.parentElement && el !== document.body && isWrapper( el ) ) {
			el = el.parentElement;
		}
		return el;
	}

	/* Common framework class names are recognised without a prefix. */
	var FW = /^(title-[1-6]|body-[lms]|sub-title|text-link|use-(heading|body)-font|(heading|body|detail|subtitle|link)-on-(light|dark)|bg-(light|dark)-[1-3]|bg-brand-[12]|btn-(solid|outline)(-on-dark)?|boxed-(s|m|l|full)|rounded-(zero|s|m|l|full)|shadow-[sml]|pad-((tb|lr)-)?[0-9]+|flex-(row|column|wrap|align-(start|center|end|stretch)|justify-(start|center|end|between))|gap-[0-9]+|text-(start|center|end)|card|badge-[0-9]+)$/;

	function isFramework( name ) {
		return ( prefix !== '' && name.indexOf( prefix ) === 0 ) || FW.test( name );
	}


	function selector( el ) {
		var s = el.tagName.toLowerCase();
		if ( el.id ) {
			s += '#' + el.id;
		}
		classes( el ).forEach( function ( c ) {
			s += '.' + c;
		} );
		return s;
	}

	function label( el ) {
		var c = classes( el );
		var s = el.tagName.toLowerCase();
		if ( c.length ) {
			s += '.' + c[ 0 ] + ( c.length > 1 ? ' +' + ( c.length - 1 ) : '' );
		}
		return s;
	}

	function copy( text, done ) {
		function legacy() {
			var area = h( 'textarea' );
			area.value = text;
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}
			document.body.removeChild( area );
			if ( ok ) {
				done();
			}
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done ).catch( legacy );
		} else {
			legacy();
		}
	}

	function hex( rgb ) {
		var n = String( rgb ).match( /[0-9.]+/g );
		if ( ! n || n.length < 3 ) {
			return rgb;
		}
		var out = '#' + n.slice( 0, 3 ).map( function ( x ) {
			var s = Math.round( Number( x ) ).toString( 16 );
			return s.length < 2 ? '0' + s : s;
		} ).join( '' );
		return n.length > 3 && Number( n[ 3 ] ) < 1 ? out + ' (' + Math.round( Number( n[ 3 ] ) * 100 ) + '%)' : out;
	}

	function computed( el, name ) {
		var style = window.getComputedStyle( el );
		if ( name === 'color' ) {
			return hex( style.color );
		}
		if ( name === 'font-size' ) {
			return style.fontSize;
		}
		return style.fontFamily.split( ',' )[ 0 ].split( QUOTE1 ).join( '' ).split( QUOTE2 ).join( '' ).trim();
	}

	/* ---- Reading the CSS of the page ---- */

	function splitSelector( text ) {
		var parts = [];
		var depth = 0;
		var cur = '';
		for ( var i = 0; i < text.length; i++ ) {
			var c = text.charAt( i );
			if ( c === '(' || c === '[' ) {
				depth++;
			} else if ( c === ')' || c === ']' ) {
				depth--;
			}
			if ( c === ',' && depth === 0 ) {
				parts.push( cur );
				cur = '';
			} else {
				cur += c;
			}
		}
		parts.push( cur );
		return parts;
	}

	/* A rough specificity: ids, then classes, attributes and pseudo-classes, then a point for the tag. */
	function specificity( part ) {
		var ids = part.split( '#' ).length - 1;
		var pseudo = part.split( ':' ).length - 1 - 2 * ( part.split( '::' ).length - 1 );
		var cls = part.split( '.' ).length - 1 + part.split( '[' ).length - 1 + Math.max( 0, pseudo );
		return ids * 10000 + cls * 100 + 1;
	}

	function usableSheet( sheet ) {
		var node = sheet.ownerNode;
		var id = node ? ( node.id || '' ) + ' ' + ( node.href || '' ) : '';
		if ( sheet.disabled || id.indexOf( 'pencil-editor' ) > -1 || id.indexOf( 'pencil-dev' ) > -1 || id.indexOf( '/plugins/pencilino/' ) > -1 || id.indexOf( 'admin-bar' ) > -1 || id.indexOf( 'dashicons' ) > -1 ) {
			return false;
		}
		return ! ( sheet.media && sheet.media.mediaText && ! window.matchMedia( sheet.media.mediaText ).matches );
	}

	function collect() {
		var out = [];
		blocked = 0;

		function walk( list ) {
			for ( var i = 0; i < list.length; i++ ) {
				var rule = list[ i ];
				if ( rule.media && rule.cssRules && ! window.matchMedia( rule.media.mediaText ).matches ) {
					continue;
				}
				if ( rule.selectorText && rule.style ) {
					var vals = readProps( rule.style );
					if ( Object.keys( vals ).length ) {
						var parts = splitSelector( rule.selectorText ).filter( function ( part ) {
							return ! TRANSIENT.some( function ( s ) {
								return part.indexOf( s ) > -1;
							} );
						} ).map( function ( part ) {
							return { sel: part.trim(), spec: specificity( part ) };
						} );
						if ( parts.length ) {
							out.push( { parts: parts, vals: vals, order: out.length } );
						}
					}
				} else if ( rule.cssRules ) {
					walk( rule.cssRules );
				}
			}
		}

		Array.prototype.forEach.call( document.styleSheets, function ( sheet ) {
			if ( ! usableSheet( sheet ) ) {
				return;
			}
			try {
				walk( sheet.cssRules );
			} catch ( e ) {
				blocked++;
			}
		} );

		return out;
	}

	/* What the CSS says about one element, per property: the winning declaration. */
	function declared( node ) {
		if ( cache.has( node ) ) {
			return cache.get( node );
		}
		var best = {};

		function take( name, raw, rank ) {
			if ( ! best[ name ] || rank >= best[ name ].rank ) {
				best[ name ] = { raw: raw, rank: rank };
			}
		}

		rulesList.forEach( function ( rule ) {
			var top = -1;
			rule.parts.forEach( function ( part ) {
				try {
					if ( part.spec > top && node.matches( part.sel ) ) {
						top = part.spec;
					}
				} catch ( e ) {
					/* A selector this browser cannot test. */
				}
			} );
			if ( top < 0 ) {
				return;
			}
			Object.keys( rule.vals ).forEach( function ( name ) {
				var v = rule.vals[ name ];
				take( name, v.raw, ( v.important ? 1000000000 : 0 ) + top * 10000 + rule.order );
			} );
		} );

		if ( node.style ) {
			var inline = readProps( node.style );
			Object.keys( inline ).forEach( function ( k ) {
				take( k, inline[ k ].raw, 2000000000 );
			} );
		}

		cache.set( node, best );
		return best;
	}

	/* Where a property really comes from: this element, or the nearest parent that sets it. */
	function source( el, name ) {
		var node = el;
		while ( node && node.nodeType === 1 ) {
			var d = declared( node )[ name ];
			if ( d && [ 'inherit', 'initial', 'unset', 'revert' ].indexOf( d.raw ) < 0 ) {
				return {
					raw: d.raw,
					via: d.raw.indexOf( 'var(' ) > -1 ? 'var' : 'hard',
					vars: d.raw.match( /--[A-Za-z0-9_-]+/g ) || [],
					from: node === el ? null : node
				};
			}
			node = node.parentElement;
		}
		return { raw: '', via: 'none', vars: [], from: null };
	}

	function fresh() {
		rulesList = collect();
		cache = new Map();
		classKeys = buildClassKeys();
	}

	/* ---- What the framework expects ---- */

	var FWVAR = /^(primary-(brand-[12]|light-[1-3]|dark-[1-3]|transparent-1)|size-[0-9]+|title-size-[1-6]|body-size-[lms]|title-subtitle|heading-font|body-font|button-radius|radius-[sml])$/;
	var FWFAMS = [ [ 'colours', /^primary-/ ], [ 'size scale', /^size-[0-9]+$/ ], [ 'type scale', /^(title|body)-size-/ ], [ 'fonts', /^(heading|body)-font$/ ], [ 'radius', /^(button-radius|radius-[sml])$/ ] ];
	var CLASSFAMS = [ [ 'title', /^title-[1-6]$/ ], [ 'body size', /^body-[lms]$/ ], [ 'background', /^bg-/ ], [ 'corner', /^rounded-/ ], [ 'shadow', /^shadow-[sml]$/ ], [ 'width', /^boxed-/ ], [ 'gap', /^gap-[0-9]+$/ ], [ 'button', /^btn-(solid|outline)/ ], [ 'padding', /^pad-[0-9]+$/ ], [ 'top and bottom padding', /^pad-tb-[0-9]+$/ ], [ 'side padding', /^pad-lr-[0-9]+$/ ] ];
	var IGNORED = /^--(wp-|e-|elementor|woocommerce|wc-|swiper|bs-)/;
	var VENDOR = /^(elementor|e-|wp-|has-|is-|woocommerce|wc-|swiper|admin-bar|screen-reader)/;
	var ZERO = /^0(px|rem|em|%)?$/;
	var NOVALUE = [ '', 'inherit', 'initial', 'unset', 'revert', 'none', 'transparent', 'currentcolor', 'auto', 'normal' ];

	/* Split a CSS value on spaces, but not inside brackets. */
	function topSplit( text ) {
		var parts = [];
		var depth = 0;
		var cur = '';
		for ( var i = 0; i < text.length; i++ ) {
			var c = text.charAt( i );
			if ( c === '(' ) {
				depth++;
			} else if ( c === ')' ) {
				depth--;
			}
			if ( c === ' ' && depth === 0 ) {
				if ( cur ) {
					parts.push( cur );
				}
				cur = '';
			} else {
				cur += c;
			}
		}
		if ( cur ) {
			parts.push( cur );
		}
		return parts;
	}

	/* The properties we look at, in one flat list: text, background, padding and margin per side, gap, corners, shadow, border colour. */
	function readProps( style ) {
		var out = {};

		function get( name ) {
			return style.getPropertyValue( name );
		}

		function put( key, raw, name ) {
			if ( raw ) {
				out[ key ] = { raw: raw.trim(), important: style.getPropertyPriority( name ) === 'important' };
			}
		}

		PROPS.forEach( function ( p ) {
			var v = get( p[ 0 ] );
			if ( ! v && ( p[ 0 ] === 'font-family' || p[ 0 ] === 'font-size' ) ) {
				var shorthand = get( 'font' );
				if ( shorthand && shorthand.indexOf( 'var(' ) > -1 ) {
					v = shorthand;
				}
			}
			put( p[ 0 ], v, p[ 0 ] );
		} );

		put( 'background', get( 'background-color' ) || get( 'background' ), 'background-color' );

		[ 'padding', 'margin' ].forEach( function ( base ) {
			var whole = get( base );
			if ( whole ) {
				var parts = topSplit( whole );
				var map = parts.length === 1 ? [ 0, 0, 0, 0 ] : ( parts.length === 2 ? [ 0, 1, 0, 1 ] : ( parts.length === 3 ? [ 0, 1, 2, 1 ] : [ 0, 1, 2, 3 ] ) );
				SIDES.forEach( function ( side, i ) {
					put( base + '-' + side, parts[ map[ i ] ], base );
				} );
			}
			[ [ 'inline', [ 'left', 'right' ] ], [ 'block', [ 'top', 'bottom' ] ] ].forEach( function ( pair ) {
				var v = get( base + '-' + pair[ 0 ] );
				if ( v ) {
					var ps = topSplit( v );
					pair[ 1 ].forEach( function ( side, i ) {
						put( base + '-' + side, ps[ ps.length > 1 ? i : 0 ], base + '-' + pair[ 0 ] );
					} );
				}
			} );
			SIDES.forEach( function ( side ) {
				put( base + '-' + side, get( base + '-' + side ), base + '-' + side );
			} );
		} );

		put( 'gap', get( 'gap' ) || get( 'row-gap' ) || get( 'column-gap' ), 'gap' );
		put( 'radius', get( 'border-radius' ) || get( 'border-top-left-radius' ) || get( 'border-top-right-radius' ) || get( 'border-bottom-left-radius' ) || get( 'border-bottom-right-radius' ), 'border-radius' );
		put( 'shadow', get( 'box-shadow' ), 'box-shadow' );
		put( 'border-color', get( 'border-color' ) || get( 'border' ), 'border-color' );
		return out;
	}

	/* Is this value a variable, a hard-coded value, or nothing worth counting (0, auto, none)? */
	function classify( key, raw ) {
		var v = String( raw ).trim().toLowerCase();
		if ( v.indexOf( 'var(' ) > -1 ) {
			return 'var';
		}
		if ( NOVALUE.indexOf( v ) > -1 ) {
			return 'none';
		}
		if ( key === 'background' || key === 'border-color' ) {
			return /#[0-9a-f]{3}|rgb|hsl|gradient|^[a-z]+$/.test( v ) ? 'hard' : 'none';
		}
		if ( key === 'shadow' ) {
			return 'hard';
		}
		if ( ZERO.test( v ) ) {
			return 'none';
		}
		return /[0-9]/.test( v ) ? 'hard' : 'none';
	}

	/* Backgrounds, spacing, corners, shadows and border colours belong to the element itself. They are not inherited. */
	function boxState( el, group ) {
		var d = declared( el );
		var found = { via: 'none', vars: [] };
		group.keys.forEach( function ( k ) {
			if ( ! d[ k ] ) {
				return;
			}
			var kind = classify( k, d[ k ].raw );
			if ( kind === 'var' ) {
				found.vars = found.vars.concat( d[ k ].raw.match( /--[A-Za-z0-9_-]+/g ) || [] );
				if ( found.via !== 'hard' ) {
					found.via = 'var';
				}
			} else if ( kind === 'hard' ) {
				found.via = 'hard';
			}
		} );
		return found;
	}

	function unique( list ) {
		return list.filter( function ( v, i ) {
			return list.indexOf( v ) === i;
		} );
	}

	function boxValue( el, g ) {
		var s = window.getComputedStyle( el );
		if ( g.id === 'background' ) {
			return hex( s.backgroundColor );
		}
		if ( g.id === 'radius' ) {
			return s.borderRadius;
		}
		if ( g.id === 'shadow' ) {
			return s.boxShadow;
		}
		if ( g.id === 'border' ) {
			return hex( s.borderTopColor );
		}
		var d = declared( el );
		var bits = [];
		[ 'padding', 'margin' ].forEach( function ( base ) {
			if ( SIDES.some( function ( side ) { return d[ base + '-' + side ]; } ) ) {
				bits.push( base + ' ' + SIDES.map( function ( side ) {
					return s[ base + side.charAt( 0 ).toUpperCase() + side.slice( 1 ) ];
				} ).join( ' ' ) );
			}
		} );
		if ( d.gap ) {
			bits.push( 'gap ' + s.gap );
		}
		return bits.join( ', ' );
	}

	/* Which single-class rules set which property. Used to find two classes that fight over one property. */
	function buildClassKeys() {
		var map = {};
		rulesList.forEach( function ( rule ) {
			rule.parts.forEach( function ( part ) {
				if ( part.sel.charAt( 0 ) === '.' && /^[A-Za-z0-9_-]+$/.test( part.sel.slice( 1 ) ) ) {
					var name = part.sel.slice( 1 );
					map[ name ] = map[ name ] || {};
					Object.keys( rule.vals ).forEach( function ( k ) {
						map[ name ][ k ] = true;
					} );
				}
			} );
		} );
		return map;
	}

	function bare( name ) {
		return prefix !== '' && name.indexOf( prefix ) === 0 ? name.slice( prefix.length ) : name;
	}

	function niceKey( key ) {
		var map = { 'font-family': 'font', 'font-size': 'size', color: 'colour', background: 'background', radius: 'corners', shadow: 'shadow', gap: 'gap', 'border-color': 'border colour' };
		if ( map[ key ] ) {
			return map[ key ];
		}
		return key.indexOf( 'padding' ) === 0 ? 'padding' : 'margin';
	}

	/* Framework rules by class name: one family once, boxed-* without side padding, components complete. */
	function nameClashes( el ) {
		var out = [];
		var names = classes( el ).filter( isFramework ).map( bare );

		function has( re ) {
			return names.filter( function ( n ) {
				return re.test( n );
			} );
		}

		CLASSFAMS.forEach( function ( f ) {
			var hit = has( f[ 1 ] );
			if ( hit.length > 1 ) {
				out.push( hit.join( ' and ' ) + ' are both ' + f[ 0 ] + ' classes. Keep one.' );
			}
		} );
		var boxed = has( /^boxed-/ );
		var padding = has( /^pad-(lr-)?[0-9]+$/ );
		if ( boxed.length && padding.length ) {
			out.push( boxed[ 0 ] + ' already sets the side padding. Remove ' + padding.join( ', ' ) + '.' );
		}
		var component = has( /^(card|btn-.*|badge-[0-9]+)$/ );
		var stacked = has( /^(bg-.*|rounded-.*|shadow-[sml])$/ );
		if ( component.length && stacked.length ) {
			out.push( component[ 0 ] + ' is a complete component. Do not stack ' + stacked.join( ', ' ) + ' on it.' );
		}
		return out;
	}

	/* A base class and its modifier (card and card--dark) are meant to be used together. */
	function isModifier( owners ) {
		return owners.some( function ( a ) {
			return owners.some( function ( b ) {
				return a !== b && ( b.indexOf( a + '-' ) === 0 || b.indexOf( a + '_' ) === 0 );
			} );
		} );
	}

	/* One property, one owner: two classes on one element should not both set the same thing. */
	function conflicts( el ) {
		var out = nameClashes( el );
		var list = classes( el ).filter( function ( c ) {
			return classKeys[ c ] && ! VENDOR.test( c );
		} );
		if ( list.length < 2 ) {
			return out;
		}
		var byKey = {};
		list.forEach( function ( c ) {
			Object.keys( classKeys[ c ] ).forEach( function ( k ) {
				( byKey[ k ] = byKey[ k ] || [] ).push( c );
			} );
		} );
		var groups = {};
		Object.keys( byKey ).forEach( function ( k ) {
			var owners = byKey[ k ];
			if ( owners.length < 2 || owners.every( isFramework ) || isModifier( owners ) ) {
				return;
			}
			var id = owners.join( ' and ' );
			( groups[ id ] = groups[ id ] || [] ).push( niceKey( k ) );
		} );
		Object.keys( groups ).forEach( function ( id ) {
			out.push( id + ( id.split( ' and ' ).length > 2 ? ' all set the ' : ' both set the ' ) + unique( groups[ id ] ).join( ', ' ) + '.' );
		} );
		return out;
	}

	/* The variables the page defines on :root. */
	function rootVars() {
		var names = {};

		function walk( list ) {
			for ( var i = 0; i < list.length; i++ ) {
				var rule = list[ i ];
				if ( rule.selectorText && rule.style ) {
					var onRoot = rule.selectorText.split( ',' ).some( function ( sel ) {
						sel = sel.trim();
						return sel === ':root' || sel === 'html' || sel === 'body';
					} );
					if ( onRoot ) {
						for ( var n = 0; n < rule.style.length; n++ ) {
							if ( rule.style[ n ].indexOf( '--' ) === 0 ) {
								names[ rule.style[ n ] ] = true;
							}
						}
					}
				} else if ( rule.cssRules ) {
					walk( rule.cssRules );
				}
			}
		}

		Array.prototype.forEach.call( document.styleSheets, function ( sheet ) {
			if ( ! usableSheet( sheet ) ) {
				return;
			}
			try {
				walk( sheet.cssRules );
			} catch ( e ) {
				/* Not readable. */
			}
		} );
		return Object.keys( names ).filter( function ( n ) {
			return ! IGNORED.test( n );
		} );
	}

	/* Do the variables of this site follow the framework: names, the 4 point size scale, and what is missing. */
	function frameworkCheck() {
		var names = rootVars();
		var lines = [];
		if ( ! names.length ) {
			return lines;
		}
		var root = window.getComputedStyle( document.documentElement );
		var fw = [];
		var mine = [];
		names.forEach( function ( n ) {
			( FWVAR.test( bare( n.slice( 2 ) ) ) ? fw : mine ).push( n );
		} );

		function more( list, max ) {
			return list.slice( 0, max ).join( ', ' ) + ( list.length > max ? ' (+' + ( list.length - max ) + ')' : '' );
		}

		if ( ! fw.length ) {
			lines.push( { kind: 'hard', text: t( 'fwNone', 'This site does not use the common framework variable names yet. Its {n} variables have their own names:' ).replace( '{n}', String( mine.length ) ) + ' ' + more( mine, 6 ) } );
			return lines;
		}
		lines.push( { kind: 'ok', text: t( 'fwSome', '{w} of {n} variables follow the common framework names.' ).replace( '{w}', String( fw.length ) ).replace( '{n}', String( names.length ) ) } );
		if ( mine.length ) {
			lines.push( { kind: 'hard', text: t( 'fwOwn', 'Own names, not from the framework:' ) + ' ' + more( mine, 6 ) } );
		}
		var off = [];
		var wrong = [];
		fw.forEach( function ( n ) {
			var m = /^size-([0-9]+)$/.exec( bare( n.slice( 2 ) ) );
			if ( ! m ) {
				return;
			}
			var num = Number( m[ 1 ] );
			if ( num % 4 !== 0 ) {
				off.push( n );
			}
			var raw = root.getPropertyValue( n ).trim();
			var px = /px$/.test( raw ) ? parseFloat( raw ) : ( /rem$/.test( raw ) ? parseFloat( raw ) * 16 : null );
			if ( px !== null && Math.abs( px - num ) > 0.1 ) {
				wrong.push( n + ' is ' + raw );
			}
		} );
		if ( off.length ) {
			lines.push( { kind: 'hard', text: more( off, 6 ) + ' ' + t( 'fwOff', 'is not on the 4 point scale.' ) } );
		}
		if ( wrong.length ) {
			lines.push( { kind: 'hard', text: more( wrong, 4 ) + '. ' + t( 'fwWrong', 'The name and the value do not match.' ) } );
		}
		var missing = FWFAMS.filter( function ( f ) {
			return ! fw.some( function ( n ) {
				return f[ 1 ].test( bare( n.slice( 2 ) ) );
			} );
		} ).map( function ( f ) {
			return f[ 0 ];
		} );
		if ( missing.length ) {
			lines.push( { kind: 'hard', text: t( 'fwMissing', 'No framework variables yet for:' ) + ' ' + missing.join( ', ' ) + '.' } );
		}
		return lines;
	}

	/* ---- The panel ---- */

	function section( title ) {
		var s = h( 'div', 'pencil-dev-section' );
		if ( title ) {
			s.appendChild( h( 'p', 'pencil-dev-label', title ) );
		}
		panel.appendChild( s );
		return s;
	}

	function flash( node, text ) {
		var old = node.textContent;
		node.textContent = text;
		window.setTimeout( function () {
			node.textContent = old;
		}, 1100 );
	}

	function chip( text, framework ) {
		var b = h( 'button', 'pencil-dev-chip' + ( framework ? ' pencil-dev-chip--framework' : '' ) );
		b.type = 'button';
		b.title = t( 'copy', 'Copy' );
		var name = h( 'span', '', bare( text ) );
		b.appendChild( name );
		b.addEventListener( 'click', function () {
			copy( text, function () {
				name.textContent = t( 'copied', 'Copied' );
				window.setTimeout( function () {
					name.textContent = bare( text );
				}, 1100 );
			} );
		} );
		return b;
	}

	function row( parent, key, val ) {
		var r = h( 'div', 'pencil-dev-row' );
		r.appendChild( h( 'span', 'pencil-dev-key', key ) );
		r.appendChild( h( 'code', 'pencil-dev-val', val ) );
		parent.appendChild( r );
	}

	/* A row of the Result: the value, with the variable behind it as a tooltip. Click copies the variable. */
	function tipRow( parent, key, val, tip, copyText, swatch ) {
		var r = h( 'div', 'pencil-dev-row pencil-dev-row--tip' );
		r.title = tip;
		var k = h( 'span', 'pencil-dev-key', key );
		r.appendChild( k );
		var v = h( 'code', 'pencil-dev-val' );
		if ( swatch ) {
			var dot = h( 'span', 'pencil-dev-swatch' );
			dot.style.background = swatch;
			v.appendChild( dot );
		}
		v.appendChild( document.createTextNode( val ) );
		r.appendChild( v );
		if ( copyText ) {
			r.classList.add( 'is-copyable' );
			r.addEventListener( 'click', function () {
				copy( copyText, function () {
					flash( k, t( 'copied', 'Copied' ) );
				} );
			} );
		}
		parent.appendChild( r );
	}

	/* A readable name for an element: its own class as words, else what kind of element it is. */
	function nice( el ) {
		var own = classes( el ).filter( function ( c ) {
			return ! isFramework( c );
		} )[ 0 ];
		if ( own ) {
			own = own.split( '__' ).join( ' ' ).split( '--' ).join( ' ' ).split( '-' ).join( ' ' ).split( '_' ).join( ' ' ).trim();
			return own.charAt( 0 ).toUpperCase() + own.slice( 1 );
		}
		var tagName = el.tagName.toLowerCase();
		var kinds = { a: 'Link', p: 'Paragraph', img: 'Image', button: 'Button', ul: 'List', ol: 'List', li: 'List item', section: 'Section', header: 'Header', footer: 'Footer', nav: 'Menu', span: 'Text', div: 'Block', main: 'Main content', article: 'Article', figure: 'Figure', form: 'Form' };
		return /^h[1-6]$/.test( tagName ) ? t( 'heading', 'Heading' ) : t( 'kind_' + tagName, kinds[ tagName ] || tagName );
	}

	function render( el ) {
		panel.textContent = '';

		var head = h( 'div', 'pencil-dev-head' );
		head.appendChild( h( 'strong', 'pencil-dev-title', t( 'panelTitle', 'Dev mode' ) ) );
		var tools = h( 'span', 'pencil-dev-tools' );
		var close = h( 'button', 'pencil-dev-close', String.fromCharCode( 215 ) );
		close.type = 'button';
		close.setAttribute( 'aria-label', t( 'close', 'Close' ) );
		close.addEventListener( 'click', unpin );
		tools.appendChild( close );
		head.appendChild( tools );
		panel.appendChild( head );

		if ( api.tabs && api.tabs( panel, el, render ) ) {
			return;
		}

		var list = classes( el );
		var clsSec = section( t( 'classes', 'Classes' ) );
		if ( list.length && api.classChips ) {
			clsSec.appendChild( api.classChips( el, list, function () {
				render( el );
			} ) );
		} else if ( list.length ) {
			var wrap = h( 'div', 'pencil-dev-chips' );
			list.forEach( function ( c ) {
				wrap.appendChild( chip( c, isFramework( c ) ) );
			} );
			clsSec.appendChild( wrap );
		} else {
			clsSec.appendChild( h( 'p', 'pencil-dev-muted', t( 'noClasses', 'No classes on this element.' ) ) );
		}

		/* The result: what the element looks like now. Hover a row for its variable, click to copy it. */
		var styling = section( t( 'result', 'Result' ) );
		PROPS.forEach( function ( p ) {
			var src = source( el, p[ 0 ] );
			var swapped = api.swapRaw ? api.swapRaw( el, p[ 0 ] ) : '';
			if ( swapped ) {
				src = { raw: swapped, via: swapped.indexOf( 'var(' ) > -1 ? 'var' : 'hard', vars: swapped.match( /--[A-Za-z0-9_-]+/g ) || [], from: null };
			}
			var tip = t( 'browser', 'Browser default' );
			if ( src.via === 'var' ) {
				tip = src.vars.length ? src.vars.join( ', ' ) : 'var()';
			} else if ( src.via === 'hard' ) {
				tip = t( 'hard', 'Hard-coded in the CSS' );
			}
			if ( src.from ) {
				tip += ', ' + t( 'from', 'from' ) + ' ' + label( src.from );
			}
			tipRow( styling, t( p[ 1 ], p[ 2 ] ), computed( el, p[ 0 ] ), tip, src.via === 'var' ? src.vars.join( ', ' ) : '', p[ 0 ] === 'color' ? window.getComputedStyle( el ).color : '' );
		} );

		GROUPS.forEach( function ( g ) {
			var state = boxState( el, g );
			if ( state.via === 'none' ) {
				return;
			}
			var names = unique( state.vars );
			tipRow( styling, t( g.key, g.one ), boxValue( el, g ), state.via === 'var' ? ( names.join( ', ' ) || 'var()' ) : t( 'hard', 'Hard-coded in the CSS' ), state.via === 'var' ? names.join( ', ' ) : '', '' );
		} );
		styling.appendChild( h( 'p', 'pencil-dev-note', t( 'tipHint', 'Hover a row for its variable, click to copy it.' ) ) );

		var fw = section( t( 'checkTitle', 'Consistency check' ) );
		var page = h( 'div', 'pencil-dev-result' );
		fw.appendChild( page );
		window.setTimeout( function () {
			if ( pinned === el ) {
				scan( page );
			}
		}, 30 );
	}

	/* The closest element with a class of its own, so the list names something you can find in the CSS. */
	function owner( node ) {
		while ( node && node !== document.body && classes( node ).length === 0 ) {
			node = node.parentElement;
		}
		return node && node !== document.body ? node : null;
	}

	/* Walk the visible elements in small steps and count where the CSS still has hard-coded values. */
	function scan( out ) {
		out.textContent = '';
		var token = pinned;
		out.appendChild( h( 'p', 'pencil-dev-note', t( 'checking', 'Checking this page...' ) ) );

		var skip = [ 'SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'LINK', 'META', 'BR' ];
		var nodes = Array.prototype.filter.call( document.body.querySelectorAll( '*' ), function ( node ) {
			return ! own( node ) && ! ( node instanceof SVGElement ) && skip.indexOf( node.tagName ) < 0 && node.getClientRects().length > 0;
		} );
		var cap = 2000;
		var r = { total: 0, clean: 0, dirty: 0, bad: [], boxTotal: 0, boxDirty: 0, boxKinds: {}, boxNames: [], clash: [], fw: [], hard: { 'font-family': 0, 'font-size': 0, color: 0 }, limited: nodes.length > cap };
		var seen = {};
		var at = 0;
		var end = Math.min( nodes.length, cap );

		function texty( node ) {
			return Array.prototype.some.call( node.childNodes, function ( n ) {
				return ( n.nodeType === 3 && n.nodeValue.trim() !== '' ) || ( n.nodeType === 1 && isWrapper( n ) && n.textContent.trim() !== '' );
			} );
		}

		function flag( node ) {
			var o = owner( node );
			var key = o ? selector( o ) : '';
			if ( o && ! seen[ key ] ) {
				seen[ key ] = true;
				r.bad.push( o );
			}
		}

		function visit( node ) {
			if ( isWrapper( node ) ) {
				return;
			}
			var flagged = false;
			if ( texty( node ) ) {
				r.total++;
				var ok = true;
				PROPS.forEach( function ( p ) {
					if ( source( node, p[ 0 ] ).via === 'hard' ) {
						r.hard[ p[ 0 ] ]++;
						ok = false;
					}
				} );
				if ( ok ) {
					r.clean++;
				} else {
					flagged = true;
				}
			}
			var styled = false;
			var dirtyBox = false;
			GROUPS.forEach( function ( g ) {
				var state = boxState( node, g );
				if ( state.via !== 'none' ) {
					styled = true;
				}
				if ( state.via === 'hard' ) {
					dirtyBox = true;
					r.boxKinds[ g.id ] = ( r.boxKinds[ g.id ] || 0 ) + 1;
				}
			} );
			if ( styled ) {
				r.boxTotal++;
			}
			if ( dirtyBox ) {
				r.boxDirty++;
				flagged = true;
			}
			var found = conflicts( node );
			if ( found.length ) {
				r.clash.push( { el: node, lines: found } );
				flagged = true;
			}
			if ( flagged ) {
				flag( node );
			}
		}

		function finish() {
			out.textContent = '';
			r.dirty = r.total - r.clean;
			r.fw = frameworkCheck();
			var count = r.hard[ 'font-family' ] + r.hard[ 'font-size' ] + r.hard.color;
			Object.keys( r.boxKinds ).forEach( function ( k ) {
				count += r.boxKinds[ k ];
			} );
			count += r.fw.filter( function ( line ) {
				return line.kind === 'hard';
			} ).length;

			if ( ! count ) {
				out.appendChild( h( 'p', 'pencil-dev-line pencil-dev-line--ok', t( 'pageClean', 'Nothing hard-coded on this page.' ) ) );
			} else {
				var msg = count === 1 ? t( 'hardOneShort', '1 hard-coded value on this page.' ) : t( 'hardManyShort', '{n} hard-coded values on this page.' ).replace( '{n}', String( count ) );
				var line = h( 'p', 'pencil-dev-line pencil-dev-line--hard', msg );
				if ( cfg.cleanupUrl ) {
					var link = h( 'a', 'pencil-dev-pill', t( 'cleanUp', 'Clean up your site' ) );
					link.href = cfg.cleanupUrl;
					link.target = '_blank';
					link.rel = 'noopener';
				}
				out.appendChild( line );
				if ( link ) {
					out.appendChild( link );
				}
			}

			if ( r.limited || blocked ) {
				out.appendChild( h( 'p', 'pencil-dev-note', r.limited ? t( 'limitedAll', 'Only the first 2000 elements were checked.' ) : t( 'blocked', 'Some stylesheets come from another domain and cannot be read.' ) ) );
			}
		}

		function step() {
			if ( pinned !== token ) {
				return;
			}
			var stop = Math.min( at + 40, end );
			for ( ; at < stop; at++ ) {
				visit( nodes[ at ] );
			}
			if ( at < end ) {
				window.setTimeout( step, 0 );
			} else {
				finish();
			}
		}

		step();
	}

	/* ---- Boxes on the page ---- */

	function place( box, el ) {
		if ( ! el || ! document.body.contains( el ) ) {
			box.hidden = true;
			return null;
		}
		var r = el.getBoundingClientRect();
		box.style.left = r.left + 'px';
		box.style.top = r.top + 'px';
		box.style.width = r.width + 'px';
		box.style.height = r.height + 'px';
		box.hidden = false;
		return r;
	}

	function draw() {
		frame = 0;
		if ( ! on ) {
			return;
		}
		var pinRect = place( pinBox, pinned );
		pinBox.classList.toggle( 'is-top', !! pinRect && pinRect.top < 36 );
		updateNav();
		if ( api.draw ) {
			api.draw( pinned );
		}
		if ( hovered && hovered !== pinned ) {
			var r = place( hoverBox, hovered );
			if ( r ) {
				tag.textContent = label( hovered );
				tag.hidden = false;
				tag.style.left = Math.max( 4, r.left ) + 'px';
				tag.style.top = ( r.top > 36 ? r.top - 28 : r.bottom + 4 ) + 'px';
			}
		} else {
			hoverBox.hidden = true;
			tag.hidden = true;
		}
	}

	function schedule() {
		if ( ! frame ) {
			frame = window.requestAnimationFrame( draw );
		}
	}

	function pin( el, keepTrail ) {
		if ( ! keepTrail ) {
			trail = [];
		}
		pinned = el;
		fresh();
		render( el );
		panel.hidden = false;
		schedule();
	}

	function unpin() {
		trail = [];
		pinned = null;
		if ( api.hide ) {
			api.hide();
		}
		panel.hidden = true;
		pinBox.hidden = true;
		schedule();
	}

	function setDev( value ) {
		if ( value ) {
			/* Editing and inspecting do not mix: close the editor first. */
			var toggle = document.querySelector( '[data-pencil-toggle]' );
			if ( toggle && toggle.getAttribute( 'aria-pressed' ) === 'true' ) {
				toggle.click();
			}
		}
		on = value;
		html.classList.toggle( 'pencil-dev-active', on );
		button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		if ( ! on ) {
			hovered = null;
			pinned = null;
			if ( api.hide ) {
				api.hide();
			}
			[ hoverBox, pinBox, tag, panel ].forEach( function ( node ) {
				node.hidden = true;
			} );
		}
	}

	/* What the Spacing tab (dev-spacing.js) can use. */
	var api = window.pencilDevApi = {
		h: h,
		t: t,
		classes: classes,
		rootVars: rootVars,
		schedule: schedule,
		bare: bare,
		isFramework: isFramework,
		known: function () {
			return Object.keys( classKeys );
		},
		usable: usableSheet,
		hex: hex,
		copy: copy,
		nice: nice,
		pin: function ( el ) {
			pin( el );
		}
	};

	button.addEventListener( 'click', function () {
		setDev( ! on );
	} );

	document.addEventListener( 'mousemove', function ( event ) {
		if ( ! on ) {
			return;
		}
		hovered = own( event.target ) ? null : inspectable( event.target );
		schedule();
	}, true );

	document.addEventListener( 'click', function ( event ) {
		if ( ! on ) {
			return;
		}
		if ( event.target.closest && event.target.closest( '[data-pencil-toggle]' ) ) {
			setDev( false );
			return;
		}
		if ( own( event.target ) ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		pin( inspectable( event.target ) );
	}, true );

	document.addEventListener( 'keydown', function ( event ) {
		if ( on && event.key === 'Escape' ) {
			if ( pinned ) {
				unpin();
			} else {
				setDev( false );
			}
		}
	} );

	window.addEventListener( 'scroll', schedule, true );
	window.addEventListener( 'resize', schedule );
}() );