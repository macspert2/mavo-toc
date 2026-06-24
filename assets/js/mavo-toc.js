( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		document.querySelectorAll( '.mavo-toc' ).forEach( function ( toc ) {
			var titleBtn = toc.querySelector( '.mavo-toc__title' );
			if ( titleBtn && toc.classList.contains( 'mavo-toc--collapsible' ) ) {
				titleBtn.addEventListener( 'click', function () {
					var collapsed = toc.classList.toggle( 'mavo-toc--collapsed' );
					titleBtn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				} );
			}

			var toggleBtn = toc.querySelector( '.mavo-toc__toggle' );
			if ( toggleBtn ) {
				toggleBtn.addEventListener( 'click', function () {
					var expanded = toc.classList.toggle( 'mavo-toc--expanded' );
					toggleBtn.textContent = expanded
						? toggleBtn.getAttribute( 'data-label-less' )
						: toggleBtn.getAttribute( 'data-label-more' );
				} );
			}

			if ( toc.getAttribute( 'data-smooth-scroll' ) === '1' ) {
				toc.querySelectorAll( 'a[href^="#"]' ).forEach( function ( link ) {
					link.addEventListener( 'click', function ( e ) {
						var target = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
						if ( ! target ) {
							return;
						}
						e.preventDefault();
						target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
						if ( history.pushState ) {
							history.pushState( null, '', link.getAttribute( 'href' ) );
						}
					} );
				} );
			}
		} );
	} );
} )();
