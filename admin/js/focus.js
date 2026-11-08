/**
 * Focus mode: pick the users who get it.
 * Search runs on the server, two letters or more, a handful of results. The whole user list is never loaded.
 */
( function () {
	'use strict';

	var counter = 0;

	function init( root ) {
		var text = window.pencilFocus || {};
		var ajax = root.getAttribute( 'data-ajax' );
		var actionSearch = root.getAttribute( 'data-action-search' ) || 'pencil_focus_search';
		var actionUsers = root.getAttribute( 'data-action-users' ) || 'pencil_focus_users';
		var one = root.getAttribute( 'data-one' ) || text.one;
		var many = root.getAttribute( 'data-many' ) || text.many;
		var uid = ++counter;
		var nonce = root.getAttribute( 'data-nonce' );
		var input = root.querySelector( '[data-focus-search]' );
		var list = root.querySelector( '[data-focus-results]' );
		var chosenEl = root.querySelector( '[data-focus-chosen]' );
		var noneEl = root.querySelector( '[data-focus-none]' );
		var countEl = root.querySelector( '[data-focus-count]' );
		var savedEl = root.querySelector( '[data-focus-saved]' );
		var chosen = [];
		var shown = [];
		var active = -1;
		var timer = null;
		var savedTimer = null;
		var request = 0;

		try {
			chosen = JSON.parse( root.getAttribute( 'data-users' ) || '[]' );
		} catch ( e ) {
			chosen = [];
		}

		function el( tag, cls, label ) {
			var node = document.createElement( tag );
			if ( cls ) {
				node.className = cls;
			}
			if ( label !== undefined ) {
				node.textContent = label;
			}
			return node;
		}

		function initials( name ) {
			var parts = String( name ).trim().split( /\s+/ );
			var first = parts[ 0 ] ? parts[ 0 ].charAt( 0 ) : '';
			var last = parts.length > 1 ? parts[ parts.length - 1 ].charAt( 0 ) : '';
			return ( first + last ).toUpperCase();
		}

		function person( user ) {
			var wrap = el( 'span', 'pencil-focus__who' );
			wrap.appendChild( el( 'strong', '', user.name ) );
			wrap.appendChild( el( 'small', '', user.email ) );
			if ( user.warn ) {
				wrap.appendChild( el( 'small', 'pencil-focus__warn', user.warn ) );
			}
			return wrap;
		}

		function flash( message, isError ) {
			savedEl.textContent = message;
			savedEl.classList.toggle( 'is-error', !! isError );
			savedEl.classList.add( 'show' );
			window.clearTimeout( savedTimer );
			savedTimer = window.setTimeout( function () {
				savedEl.classList.remove( 'show' );
			}, 1800 );
		}

		function send( data ) {
			var body = new FormData();
			body.append( 'nonce', nonce );
			Object.keys( data ).forEach( function ( key ) {
				body.append( key, data[ key ] );
			} );
			return fetch( ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
				return response.json();
			} );
		}

		function renderChosen() {
			chosenEl.textContent = '';
			chosen.forEach( function ( user ) {
				var li = el( 'li' );
				li.appendChild( el( 'span', 'pencil-focus__avatar', initials( user.name ) ) );
				li.appendChild( person( user ) );
				if ( user.role ) {
					li.appendChild( el( 'span', 'pencil-focus__pill', user.role ) );
				}
				var remove = el( 'button', 'pencil-focus__remove', text.remove || 'Remove' );
				remove.type = 'button';
				remove.setAttribute( 'aria-label', ( text.remove || 'Remove' ) + ' ' + user.name );
				remove.addEventListener( 'click', function () {
					change( 'remove', user );
				} );
				li.appendChild( remove );
				chosenEl.appendChild( li );
			} );
			var has = chosen.length > 0;
			chosenEl.hidden = ! has;
			countEl.hidden = ! has;
			noneEl.hidden = has;
			countEl.textContent = chosen.length + ' ' + ( chosen.length === 1 ? one : many );
		}

		function change( action, user ) {
			var before = chosen.slice();
			if ( action === 'add' ) {
				chosen.push( user );
				chosen.sort( function ( a, b ) {
					return a.name.localeCompare( b.name );
				} );
			} else {
				chosen = chosen.filter( function ( u ) {
					return u.id !== user.id;
				} );
			}
			renderChosen();
			send( { action: actionUsers, do: action, user: user.id } ).then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( 'failed' );
				}
				flash( 'Saved', false );
			} ).catch( function () {
				chosen = before;
				renderChosen();
				flash( text.error || 'Could not save.', true );
			} );
		}

		function close() {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			active = -1;
		}

		function setActive( index ) {
			var items = list.querySelectorAll( 'li[role="option"]' );
			items.forEach( function ( item, n ) {
				item.setAttribute( 'aria-selected', n === index ? 'true' : 'false' );
			} );
			active = index;
			if ( items[ index ] ) {
				input.setAttribute( 'aria-activedescendant', items[ index ].id );
				items[ index ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function pick( user ) {
			input.value = '';
			close();
			change( 'add', user );
			input.focus();
		}

		function showResults( users, more ) {
			list.textContent = '';
			shown = users.filter( function ( user ) {
				return ! chosen.some( function ( c ) {
					return c.id === user.id;
				} );
			} );
			active = -1;
			if ( ! shown.length ) {
				list.appendChild( el( 'li', 'pencil-focus__note-row', text.noMatch || 'No users found' ) );
			}
			shown.forEach( function ( user, n ) {
				var li = el( 'li' );
				li.id = 'pencil-focus-' + uid + '-result-' + user.id;
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );
				li.appendChild( el( 'span', 'pencil-focus__avatar', initials( user.name ) ) );
				li.appendChild( person( user ) );
				if ( user.role ) {
					li.appendChild( el( 'span', 'pencil-focus__pill', user.role ) );
				}
				li.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
					pick( user );
				} );
				li.addEventListener( 'mousemove', function () {
					setActive( n );
				} );
				list.appendChild( li );
			} );
			if ( more > 0 ) {
				list.appendChild( el( 'li', 'pencil-focus__note-row', more + ' ' + ( text.more || 'more.' ) ) );
			}
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function search() {
			var q = input.value.trim();
			if ( q.length < 2 ) {
				close();
				return;
			}
			var mine = ++request;
			send( { action: actionSearch, q: q } ).then( function ( result ) {
				if ( mine !== request || ! result || ! result.success ) {
					return;
				}
				showResults( result.data.users || [], result.data.more || 0 );
			} ).catch( function () {
				close();
			} );
		}

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( search, 250 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( list.hidden ) {
				return;
			}
			if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				setActive( Math.min( active + 1, shown.length - 1 ) );
			} else if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				setActive( Math.max( active - 1, 0 ) );
			} else if ( event.key === 'Enter' && shown[ active ] ) {
				event.preventDefault();
				pick( shown[ active ] );
			} else if ( event.key === 'Escape' ) {
				close();
			}
		} );

		input.addEventListener( 'blur', close );

		renderChosen();
	}

	document.querySelectorAll( '[data-pencil-focus]' ).forEach( init );
}() );
