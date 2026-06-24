( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function debounce( fn, wait ) {
		var timer;
		return function () {
			clearTimeout( timer );
			timer = setTimeout( fn, wait );
		};
	}

	/**
	 * The sticky bar's height isn't known ahead of time (it can change with the
	 * theme, viewport width, or content), so it's measured at runtime and exposed
	 * as a CSS custom property that mavo-toc.css uses for the `top` offset. Every
	 * sticky TOC carries the selector to measure in its `data-sticky-bar`
	 * attribute (set from the plugin's settings).
	 */
	function updateBarOffset() {
		var sticky = document.querySelector( '.mavo-toc--sticky[data-sticky-bar]' );
		var selector = sticky ? sticky.getAttribute( 'data-sticky-bar' ) : null;
		var height = 0;

		if ( selector ) {
			var bar = document.querySelector( selector );
			if ( bar ) {
				height = bar.getBoundingClientRect().height;
			}
		}

		document.documentElement.style.setProperty( '--mavo-toc-bar-offset', height + 'px' );

		return height;
	}

	function initCollapsible( toc ) {
		var titleBtn = toc.querySelector( '.mavo-toc__title' );
		if ( ! titleBtn || ! toc.classList.contains( 'mavo-toc--collapsible' ) ) {
			return;
		}

		titleBtn.addEventListener( 'click', function () {
			var collapsed = toc.classList.toggle( 'mavo-toc--collapsed' );
			titleBtn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		} );
	}

	function initMoreToggle( toc ) {
		var btn = toc.querySelector( '.mavo-toc__btn--more' );
		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var expanded = toc.classList.toggle( 'mavo-toc--expanded' );
			btn.textContent = expanded
				? btn.getAttribute( 'data-label-less' )
				: btn.getAttribute( 'data-label-more' );
		} );
	}

	/**
	 * Reveals one more nesting depth per click (depth is relative, 0 = the
	 * shallowest headings shown, not a literal h1-h6 number) until everything is
	 * shown, then resets back to depth 0 on the next click.
	 */
	function initLevelsToggle( toc ) {
		var btn = toc.querySelector( '.mavo-toc__btn--levels' );
		var lists = toc.querySelectorAll( '.mavo-toc__list[data-depth]' );
		if ( ! btn || ! lists.length ) {
			return;
		}

		var maxDepth = 0;
		lists.forEach( function ( list ) {
			maxDepth = Math.max( maxDepth, parseInt( list.getAttribute( 'data-depth' ), 10 ) || 0 );
		} );

		var current = 0;

		function apply() {
			lists.forEach( function ( list ) {
				var depth = parseInt( list.getAttribute( 'data-depth' ), 10 ) || 0;
				list.style.display = depth <= current ? '' : 'none';
			} );
			btn.setAttribute( 'aria-expanded', current > 0 ? 'true' : 'false' );
			btn.textContent = current >= maxDepth
				? btn.getAttribute( 'data-label-collapse' )
				: btn.getAttribute( 'data-label-expand' );
		}

		btn.addEventListener( 'click', function () {
			current = current < maxDepth ? current + 1 : 0;
			apply();
		} );
	}

	function initSmoothScroll( toc ) {
		if ( toc.getAttribute( 'data-smooth-scroll' ) !== '1' ) {
			return;
		}

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

	/**
	 * `position: sticky` has no "is it currently stuck" event, so this uses the
	 * standard sentinel trick: a 1px marker is placed right before the TOC and
	 * observed with a rootMargin matching the TOC's own sticky offset. Once that
	 * marker scrolls past the offset line, the TOC must be stuck. On stuck, the
	 * TOC collapses to its title only to stay out of the way of the menu bar; it
	 * reopens automatically once scrolled back into its normal flow position.
	 */
	function initStuckObserver( toc, onReobserve ) {
		if ( ! toc.classList.contains( 'mavo-toc--sticky' ) || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var sentinel = document.createElement( 'div' );
		sentinel.setAttribute( 'aria-hidden', 'true' );
		sentinel.style.height = '1px';
		toc.parentNode.insertBefore( sentinel, toc );

		var collapsible = toc.classList.contains( 'mavo-toc--collapsible' );
		var titleBtn = toc.querySelector( '.mavo-toc__title' );

		function observe() {
			var gap = parseInt( getComputedStyle( toc ).getPropertyValue( '--mavo-toc-gap' ), 10 ) || 12;
			var barOffset = parseInt( getComputedStyle( document.documentElement ).getPropertyValue( '--mavo-toc-bar-offset' ), 10 ) || 0;

			if ( toc._mavoTocObserver ) {
				toc._mavoTocObserver.disconnect();
			}

			toc._mavoTocObserver = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						var stuck = ! entry.isIntersecting;
						toc.classList.toggle( 'mavo-toc--stuck', stuck );

						if ( collapsible ) {
							toc.classList.toggle( 'mavo-toc--collapsed', stuck );
							if ( titleBtn ) {
								titleBtn.setAttribute( 'aria-expanded', stuck ? 'false' : 'true' );
							}
						}
					} );
				},
				{ rootMargin: '-' + ( barOffset + gap ) + 'px 0px 0px 0px' }
			);

			toc._mavoTocObserver.observe( sentinel );
		}

		onReobserve( observe );
	}

	ready( function () {
		var reobserveStuck = [];

		function refreshOffsets() {
			updateBarOffset();
			reobserveStuck.forEach( function ( fn ) {
				fn();
			} );
		}

		document.querySelectorAll( '.mavo-toc' ).forEach( function ( toc ) {
			initCollapsible( toc );
			initLevelsToggle( toc );
			initMoreToggle( toc );
			initSmoothScroll( toc );
			initStuckObserver( toc, function ( observe ) {
				reobserveStuck.push( observe );
			} );
		} );

		refreshOffsets();
		window.addEventListener( 'resize', debounce( refreshOffsets, 200 ) );
		window.addEventListener( 'load', refreshOffsets );
	} );
} )();
