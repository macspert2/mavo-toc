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
	 * TOC carries the selector to measure in its `data-sticky-bar` attribute (set
	 * from the plugin's settings) regardless of whether that particular TOC is
	 * itself sticky, since the menu bar obstruction is a page-wide fact.
	 */
	function updateBarOffset() {
		var ref = document.querySelector( '.mavo-toc[data-sticky-bar]' );
		var height = ref ? measureBar( ref.getAttribute( 'data-sticky-bar' ) ) : 0;

		document.documentElement.style.setProperty( '--mavo-toc-bar-offset', height + 'px' );

		return height;
	}

	function measureBar( selector ) {
		if ( ! selector ) {
			return 0;
		}
		var bar = document.querySelector( selector );
		return bar ? bar.getBoundingClientRect().height : 0;
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

	/**
	 * A plain scrollIntoView({block:'start'}) lines the heading up with the very
	 * top of the viewport, which is exactly where the fixed menu bar (and, once
	 * scrolled that far, our own stuck TOC bar) sits — hiding the heading behind
	 * both. The landing point is pulled down by their combined height instead.
	 */
	function initSmoothScroll( toc ) {
		if ( toc.getAttribute( 'data-smooth-scroll' ) !== '1' ) {
			return;
		}

		var sticky = toc.classList.contains( 'mavo-toc--sticky' );
		var collapsible = toc.classList.contains( 'mavo-toc--collapsible' );
		var titleBtn = toc.querySelector( '.mavo-toc__title' );
		var barSelector = toc.getAttribute( 'data-sticky-bar' );

		toc.querySelectorAll( 'a[href^="#"]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				var target = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
				if ( ! target ) {
					return;
				}
				e.preventDefault();

				// Force both "stuck" and collapsed now instead of trusting them to
				// happen once scrolling reaches that point: a sticky TOC will be
				// pinned at the destination either way, and CSS gives a stuck,
				// collapsed TOC a different (smaller) padding/font than a TOC
				// that's merely collapsed — so its height has to already match
				// what it'll actually look like there before tocOffset is read.
				if ( sticky ) {
					toc.classList.add( 'mavo-toc--stuck' );
					if ( collapsible && titleBtn && ! toc.classList.contains( 'mavo-toc--collapsed' ) ) {
						toc.classList.add( 'mavo-toc--collapsed' );
						titleBtn.setAttribute( 'aria-expanded', 'false' );
					}
				}

				var barOffset = measureBar( barSelector );
				var tocOffset = 0;
				if ( sticky ) {
					// Once stuck, a collapsible TOC shrinks to just its title bar, so
					// that's the height that will actually obstruct the heading.
					tocOffset = ( collapsible && titleBtn ? titleBtn : toc ).getBoundingClientRect().height;
				}

				var targetTop = target.getBoundingClientRect().top + window.pageYOffset;
				var scrollTo = Math.max( 0, targetTop - barOffset - tocOffset - 12 );

				window.scrollTo( { top: scrollTo, behavior: 'smooth' } );

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
			var barOffset = parseInt( getComputedStyle( document.documentElement ).getPropertyValue( '--mavo-toc-bar-offset' ), 10 ) || 0;

			if ( toc._mavoTocObserver ) {
				toc._mavoTocObserver.disconnect();
			}

			toc._mavoTocObserver = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						// !isIntersecting alone is also true before the page has ever
						// been scrolled anywhere near the TOC (the sentinel is simply
						// far below the viewport, not yet stuck), which would mark it
						// stuck from the moment the page loads. What actually means
						// "stuck" is the sentinel having scrolled past the offset line.
						var stuck = entry.rootBounds
							? entry.boundingClientRect.top < entry.rootBounds.top
							: ! entry.isIntersecting;
						toc.classList.toggle( 'mavo-toc--stuck', stuck );

						if ( collapsible ) {
							toc.classList.toggle( 'mavo-toc--collapsed', stuck );
							if ( titleBtn ) {
								titleBtn.setAttribute( 'aria-expanded', stuck ? 'false' : 'true' );
							}
						}
					} );
				},
				{ rootMargin: '-' + barOffset + 'px 0px 0px 0px' }
			);

			toc._mavoTocObserver.observe( sentinel );
		}

		if ( collapsible ) {
			// Re-opening the title while stuck is a one-off peek, not a new
			// preference: collapse it again once scrolling actually resumes.
			// Toggling the collapsed class itself (the peek) shifts the layout of
			// everything below it, which fires a "scroll" event with no real
			// position change (observed in testing) — filtered out by requiring
			// an actual scrollY change, or this would immediately undo the peek.
			var lastScrollY = window.scrollY;
			window.addEventListener(
				'scroll',
				function () {
					var currentScrollY = window.scrollY;
					var moved = Math.abs( currentScrollY - lastScrollY ) > 5;
					lastScrollY = currentScrollY;

					if ( moved && toc.classList.contains( 'mavo-toc--stuck' ) && ! toc.classList.contains( 'mavo-toc--collapsed' ) ) {
						toc.classList.add( 'mavo-toc--collapsed' );
						if ( titleBtn ) {
							titleBtn.setAttribute( 'aria-expanded', 'false' );
						}
					}
				},
				{ passive: true }
			);
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
