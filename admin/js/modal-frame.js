/* Runs inside the Pencilino edit popup. Reports saves to the page behind it, saves on request and keeps navigation inside the popup. */
( function () {
	'use strict';

	if ( window.parent === window ) {
		return;
	}

	const data = window.pencilModalFrame || {};
	const wpData = window.wp && window.wp.data ? window.wp.data : null;

	// Some plugins load the block editor data store on classic screens too, so only the body class says which editor this is.
	const blockEditor = Boolean( wpData ) && document.body.classList.contains( 'block-editor-page' );

	const send = ( type, value ) => {
		try {
			window.parent.postMessage( { source: 'pencilino', type, value }, window.location.origin );
		} catch ( e ) {
			/* Without the message the page behind the popup falls back to a reload. */
		}
	};

	// Tells the popup this screen loaded.
	send( 'context' );

	// The field a theme points at is found below and outlined. The rest of the screen stays visible.
	// The part of the screen a control belongs to. A control inside a repeater counts as the whole repeater.
	const controlsIn = ( node ) => node.querySelectorAll( 'input:not([type="hidden"]), select, textarea' ).length;
	// A repeater in plain code: rows named like list[0][key]. A theme or plugin can also mark the wrapper with data-pencil-repeater.
	const repeaterOf = ( control ) => {
		const marked = control.closest( '[data-pencil-repeater]' );
		if ( marked ) {
			return marked;
		}
		const match = /^(.+?)\[\d+\]\[/.exec( control.name || '' );
		if ( ! match ) {
			return null;
		}
		const stop = '.inside, .postbox, form, #post';
		const group = Array.from( document.querySelectorAll( '#post [name^="' + ( match[ 1 ] + '[' ).replace( /["\\]/g, '\\$&' ) + '"]' ) );
		const own = new Set( group );
		let block = control;
		// The smallest wrapper that holds every row.
		while ( block.parentElement && ! block.matches( stop ) && ! group.every( ( item ) => block.contains( item ) ) ) {
			block = block.parentElement;
		}
		// Then up to the wrapper of the whole repeater (heading, rows and add button), as long as it holds nothing else.
		while ( block.parentElement && ! block.parentElement.matches( stop ) && Array.from( block.parentElement.querySelectorAll( 'input:not([type="hidden"]), select, textarea' ) ).every( ( item ) => own.has( item ) ) ) {
			block = block.parentElement;
		}
		return block.matches( stop ) ? null : block;
	};
	const blockOf = ( control ) => {
		const acf = control.closest( '.acf-field' );
		if ( acf ) {
			let outer = acf;
			while ( outer.parentElement && outer.parentElement.closest( '.acf-field' ) ) {
				outer = outer.parentElement.closest( '.acf-field' );
			}
			return outer;
		}
		// A repeater written in plain code: a control in a row counts as the whole repeater, so every row opens together.
		const repeater = repeaterOf( control );
		if ( repeater ) {
			return repeater;
		}
		const known = control.closest( '.options_group, .form-field, tr' );
		if ( known ) {
			return known;
		}
		// A meta box written in plain code: the smallest wrapper that holds only this one control (label and input), never the whole box.
		let block = control;
		while ( block.parentElement && ! block.parentElement.matches( '.inside, .postbox, form, #post' ) && controlsIn( block.parentElement ) <= 1 ) {
			block = block.parentElement;
		}
		return block;
	};

	const fieldTarget = ( field ) => {
		const byId = ( id ) => document.getElementById( id );
		const name = field.replace( /^(?:acf|scf):/, '' );
		if ( 'title' === field ) { return byId( 'titlediv' ); }
		if ( 'content' === field ) { return byId( 'postdivrich' ); }
		if ( 'excerpt' === field ) { return byId( 'postexcerpt' ); }
		if ( 'featured_image' === field ) { return byId( 'postimagediv' ); }
		if ( 'woo:price' === field ) {
			const price = byId( '_regular_price' );
			return price ? price.closest( '.options_group' ) : null;
		}
		if ( name !== field && name ) { return document.querySelector( '.acf-field[data-name="' + name + '"]' ); }
		if ( 0 === field.indexOf( 'name:' ) ) {
			const control = document.querySelector( '#wpbody-content [name="' + field.slice( 5 ).replace( /["\\]/g, '\\$&' ) + '"]' );
			return control ? blockOf( control ) : null;
		}
		return null;
	};

	// Without a name from the theme, look for the field whose value is what the clicked region showed.
	const normalize = ( value ) => String( value || '' ).toLowerCase().replace( /[^\p{L}\p{N}]+/gu, ' ' ).trim();
	const numeric = ( value ) => {
		const digits = String( value || '' ).replace( /[^0-9.,]/g, '' ).replace( ',', '.' );
		return /^\d+(\.\d+)?$/.test( digits ) ? String( parseFloat( digits ) ) : '';
	};
	const plainText = ( html ) => { const div = document.createElement( 'div' ); div.innerHTML = html; return div.textContent || ''; };
	const imageName = ( src ) => {
		try { return decodeURIComponent( new URL( src, window.location.href ).pathname.split( '/' ).pop() ).replace( /-\d+x\d+(?=\.\w+$)/, '' ).replace( /-scaled(?=\.\w+$)/, '' ).toLowerCase(); } catch ( e ) { return ''; }
	};
	const keyFor = ( control ) => {
		if ( ! control ) { return ''; }
		if ( 'post_title' === control.name ) { return 'title'; }
		if ( 'content' === control.id ) { return 'content'; }
		if ( 'excerpt' === control.id ) { return 'excerpt'; }
		return control.name ? 'name:' + control.name : '';
	};
	const controlValues = ( control ) => {
		if ( 'SELECT' === control.tagName ) {
			const option = control.options[ control.selectedIndex ];
			return option ? [ option.textContent, option.value ] : [];
		}
		if ( 'content' === control.id ) { return [ plainText( control.value ) ]; }
		return [ control.value ];
	};
	// Past the most this many fields, a guess is no help and the whole screen stays visible.
	const MAX_GUESSED = 4;

	const detectFields = ( hint, image ) => {
		const form = document.getElementById( 'post' );
		if ( ! form ) { return []; }
		const skip = '#submitdiv, #slugdiv, #postcustom, .tagsdiv, #categorydiv, #commentstatusdiv, #trackbacksdiv, #authordiv, #pageparentdiv, .inline-edit-row, #screen-meta';

		// Fields that end up in the same block on the screen count once.
		const few = ( keys ) => {
			const seen = [];
			const list = [];
			keys.forEach( ( key ) => {
				const target = fieldTarget( key );
				if ( target && seen.indexOf( target ) < 0 ) {
					seen.push( target );
					list.push( key );
				}
			} );
			return list.length >= 1 && list.length <= MAX_GUESSED ? list : [];
		};

		if ( image ) {
			const wanted = imageName( image );
			const found = Array.from( form.querySelectorAll( 'img' ) ).filter( ( img ) => wanted && imageName( img.currentSrc || img.src ) === wanted ).map( ( img ) => {
				if ( img.closest( '#postimagediv' ) ) { return 'featured_image'; }
				const wrap = img.closest( '.acf-field, .options_group, .form-field, tr, .postbox' );
				return keyFor( wrap ? wrap.querySelector( '[name]' ) : null );
			} ).filter( Boolean );
			const list = few( Array.from( new Set( found ) ) );
			if ( list.length ) { return list; }
		}

		const target = normalize( hint );
		if ( ! target ) { return []; }
		const targetNumber = /[a-z]/i.test( hint ) ? '' : numeric( hint );
		const controls = Array.from( form.querySelectorAll( 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=file]), textarea, select' ) )
			.filter( ( control ) => control.name && ! control.closest( skip ) );
		const exact = [];
		const partial = [];
		controls.forEach( ( control ) => {
			controlValues( control ).forEach( ( raw ) => {
				const value = normalize( raw );
				if ( ! value ) { return; }
				if ( value === target || ( target.length >= 30 && value.indexOf( target ) === 0 ) || ( targetNumber && numeric( raw ) === targetNumber ) ) {
					exact.push( control );
				} else if ( value.length >= 3 && target.indexOf( value ) !== -1 ) {
					partial.push( control );
				}
			} );
		} );
		const keys = ( list ) => Array.from( new Set( list.map( keyFor ).filter( Boolean ) ) );
		// A value that is only found once is the field. A value that is found in a few fields shows just those fields.
		const fromExact = few( keys( exact ) );
		return fromExact.length ? fromExact : few( keys( partial ) );
	};

	// Show the whole edit screen and point at the field that was clicked: scroll to it and outline it.
	// Nothing is hidden. When the field cannot be found, the screen simply opens at the top.
	const visibleBox = ( node ) => {
		if ( ! node ) { return null; }
		// A field inside the custom fields panel is out of sight while that panel is closed.
		if ( node.closest( '.edit-post-meta-boxes-main' ) && document.querySelector( '.edit-post-meta-boxes-main__presenter button[aria-expanded="false"]' ) ) { return null; }
		const panel = node.closest( '.postbox' );
		if ( panel ) { panel.classList.remove( 'closed' ); }
		// A field that is still hidden (the screen is building itself) climbs up to the page frame. That is no field: skip it.
		const frame = '#wpwrap, #wpcontent, #wpbody, #wpbody-content, #editor, .block-editor';
		let box = node;
		while ( box && box !== document.body && ! box.matches( frame ) && 0 === box.getBoundingClientRect().height ) {
			box = box.parentElement;
		}
		return box && box !== document.body && ! box.matches( frame ) ? box : null;
	};
	let panelClicked = 0;
	const highlightFields = ( fieldKeys, scroll ) => {
		document.querySelectorAll( '.pencil-highlight' ).forEach( ( node ) => node.classList.remove( 'pencil-highlight' ) );
		// The block editor keeps custom fields in a small panel at the bottom. Open it when it is closed.
		const presenter = document.querySelector( '.edit-post-meta-boxes-main__presenter button[aria-expanded="false"]' );
		// One click at a time: if the panel is still closed a moment later, the click did not take, so try again.
		if ( presenter && Date.now() - panelClicked > 1500 ) { panelClicked = Date.now(); presenter.click(); }
		const targets = [];
		fieldKeys.forEach( ( key ) => {
			const box = visibleBox( fieldTarget( key ) );
			if ( box && targets.indexOf( box ) < 0 ) { targets.push( box ); }
		} );
		targets.forEach( ( box ) => box.classList.add( 'pencil-highlight' ) );
		// ...and give that panel room, so the field is not squeezed into a strip. The visitor can still drag it.
		const panel = targets.length ? targets[ 0 ].closest( '.edit-post-meta-boxes-main' ) : null;
		if ( panel && panel.getBoundingClientRect().height < window.innerHeight * 0.5 ) {
			panel.style.height = Math.round( window.innerHeight * 0.6 ) + 'px';
		}
		if ( scroll && targets.length ) {
			try { targets[ 0 ].scrollIntoView( { block: targets[ 0 ].getBoundingClientRect().height > window.innerHeight * 0.7 ? 'start' : 'center', behavior: 'auto' } ); } catch ( e ) { /* The screen stays at the top. */ }
		}
		return targets;
	};
	const fields = data.field ? String( data.field ).split( ',' ).filter( Boolean ) : detectFields( data.hint || '', data.image || '' );
	if ( fields.length ) {
		// Some screens build their fields after the page loads (the block editor takes a few seconds), so keep looking until the field is there.
		let attempts = 0;
		let settled = 0;
		const look = window.setInterval( () => {
			attempts += 1;
			const found = highlightFields( fields, true );
			settled = found.length ? settled + 1 : 0;
			// Once found, look a few more times while the layout settles, then stop.
			if ( attempts >= 60 || settled >= 4 ) { window.clearInterval( look ); }
		}, 400 );
		// Saving reloads the classic screen; the field travels along so the same one is pointed at.
		const postForm = document.getElementById( 'post' );
		if ( postForm && ! postForm.querySelector( 'input[name="pencil_field"]' ) ) {
			const remember = document.createElement( 'input' );
			remember.type = 'hidden';
			remember.name = 'pencil_field';
			remember.value = fields.join( ',' );
			postForm.append( remember );
		}
	}

	// Saves started inside the screen itself (its own Update button) count too.
	const postFormForSave = document.getElementById( 'post' );
	if ( postFormForSave ) {
		postFormForSave.addEventListener( 'submit', () => send( 'saving' ) );
	}
	if ( window.acf && window.acf.addAction ) {
		window.acf.addAction( 'validation_failure', () => send( 'invalid' ) );
	}

	// Classic screens: WordPress reloads the screen with a result message after saving.
	if ( data.saved ) {
		send( 'saved' );
	}

	// Keep every admin screen inside the popup layout. Links to the website open in a new tab.
	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest ? event.target.closest( 'a[href]' ) : null;
		const href = link ? link.getAttribute( 'href' ) : '';

		if ( ! href || '#' === href.charAt( 0 ) || /^(javascript|mailto|tel):/i.test( href ) ) {
			return;
		}

		try {
			const url = new URL( link.href, window.location.href );
			if ( url.origin === window.location.origin && url.pathname.includes( '/wp-admin/' ) ) {
				url.searchParams.set( 'pencil_modal', '1' );
				link.href = url.href;
			} else if ( ! link.target ) {
				link.target = '_blank';
				link.rel = 'noopener';
			}
		} catch ( e ) {
			/* Leave the link alone. */
		}
	}, true );

	document.querySelectorAll( 'form' ).forEach( ( form ) => {
		if ( 'get' !== ( form.getAttribute( 'method' ) || 'get' ).toLowerCase() || form.querySelector( 'input[name="pencil_modal"]' ) ) {
			return;
		}
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = 'pencil_modal';
		input.value = '1';
		form.append( input );
	} );

	let dirty = false;
	const setDirty = ( value ) => {
		if ( value !== dirty ) {
			dirty = value;
			send( 'dirty', value );
		}
	};

	// Classic editor: compare the form with how it looked once its fields had started up.
	// A comparison also catches changes that fields make with scripts, such as an ACF image.
	const form = document.getElementById( 'post' );
	if ( form ) {
		const serialize = () => new URLSearchParams( new FormData( form ) ).toString();
		let baseline = null;

		window.setTimeout( () => {
			baseline = serialize();
		}, 1500 );

		// The media library and similar scripts change fields without firing events.
		window.setInterval( () => {
			if ( null !== baseline ) {
				setDirty( serialize() !== baseline );
			}
		}, 800 );

		[ 'input', 'change' ].forEach( ( type ) => {
			document.addEventListener( type, () => {
				window.setTimeout( () => {
					if ( null !== baseline ) {
						setDirty( serialize() !== baseline );
					}
				}, 0 );
			}, true );
		} );

		// The visual editor keeps its content outside the form until saving.
		try {
			const watch = ( editor ) => editor.on( 'input change undo redo', () => {
				if ( editor.isDirty() ) {
					setDirty( true );
				}
			} );
			if ( window.tinymce ) {
				window.tinymce.editors.forEach( watch );
				window.tinymce.on( 'AddEditor', ( event ) => watch( event.editor ) );
			}
		} catch ( e ) {
			/* The form comparison still covers the other fields. */
		}
	}

	// Escape inside the frame closes the popup, like it does on the page behind it.
	// A visible visual editor keeps its own document, so Escape pressed inside it is passed on too.
	try {
		const hookEscape = ( editor ) => editor.on( 'keydown', ( event ) => { if ( 27 === event.keyCode ) { send( 'escape' ); } } );
		if ( window.tinymce ) {
			window.tinymce.editors.forEach( hookEscape );
			window.tinymce.on( 'AddEditor', ( event ) => hookEscape( event.editor ) );
		}
	} catch ( e ) { /* Escape still works outside the visual editor. */ }
	window.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && ! event.defaultPrevented && ! document.querySelector( '.select2-container--open, .ui-autocomplete:not([style*="display: none"]), .media-modal' ) ) {
			send( 'escape' );
		}
	}, true );

	// The Save button in the popup header asks this screen to save, like its own button would.
	window.addEventListener( 'message', ( event ) => {
		const message = event.data;

		if ( event.origin !== window.location.origin || event.source !== window.parent || ! message || 'pencilino' !== message.source || 'save' !== message.type ) {
			return;
		}

		if ( blockEditor && wpData.select( 'core/editor' ) ) {
			wpData.dispatch( 'core/editor' ).savePost();
			return;
		}

		send( 'saving' );
		// Drafts and pending posts keep their status. Published posts only have Update.
		const button = document.getElementById( 'save-post' ) || document.getElementById( 'publish' );
		if ( button ) {
			button.click();
		} else if ( form ) {
			form.requestSubmit();
		}
	} );

	// Block editor: saves go through the REST API, so watch the editor state instead.
	if ( ! blockEditor ) {
		return;
	}

	const { select, subscribe } = wpData;
	let wasSaving = false;

	subscribe( () => {
		const editor = select( 'core/editor' );

		if ( ! editor || ! editor.isSavingPost ) {
			return;
		}

		// Custom fields in meta boxes (such as ACF) save after the post itself.
		let saving = editor.isSavingPost() && ! editor.isAutosavingPost();
		try {
			const editPost = select( 'core/edit-post' );
			saving = saving || Boolean( editPost && editPost.isSavingMetaBoxes && editPost.isSavingMetaBoxes() );
		} catch ( e ) {
			/* The store is not available in every WordPress version. */
		}

		if ( wasSaving && ! saving && editor.didPostSaveRequestSucceed() ) {
			send( 'saved' );
		}
		wasSaving = saving;

		setDirty( editor.isEditedPostDirty() );
	} );
}() );
