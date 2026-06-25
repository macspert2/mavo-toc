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

	/**
	 * A plain display:none snap (the previous approach) can't be animated and
	 * instantly reclaims the body's space, making whatever follows the TOC jump
	 * up the page. Animating max-height instead needs an explicit pixel value
	 * to transition to/from — "none"/"auto" (what it would otherwise be) isn't
	 * animatable — so this measures the real height and sets it just before
	 * each transition. Collapsing additionally waits a frame before applying
	 * the target (0px): setting both values in the same tick would let the
	 * browser collapse them into one change with nothing to actually animate
	 * between. The `hidden` attribute (not display:none) is applied only once
	 * a collapse has actually finished, so the links lose tab focusability
	 * exactly when they become invisible, not before.
	 */
	function setCollapsed( toc, collapsed, titleBtn ) {
		var body = toc.querySelector( '.mavo-toc__body' );

		toc.classList.toggle( 'mavo-toc--collapsed', collapsed );
		if ( titleBtn ) {
			titleBtn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
		}

		if ( ! body ) {
			return;
		}

		clearTimeout( body._mavoTocHideTimer );

		if ( collapsed ) {
			body.hidden = false;
			body.style.maxHeight = body.scrollHeight + 'px';
			// A single rAF can still fire before the browser has actually
			// painted the frozen height above (observed in testing) — waiting
			// for a second one reliably guarantees that paint has happened
			// before the target value is applied, or there's nothing to
			// animate from.
			requestAnimationFrame( function () {
				requestAnimationFrame( function () {
					body.style.maxHeight = '0px';
				} );
			} );
			body._mavoTocHideTimer = setTimeout( function () {
				body.hidden = true;
			}, 4000 );
		} else {
			body.hidden = false;
			body.style.maxHeight = body.scrollHeight + 'px';
			body._mavoTocHideTimer = setTimeout( function () {
				// Released once fully open so later content changes (Show more,
				// Show subheadings) aren't clipped at this now-stale height.
				body.style.maxHeight = '';
			}, 400 );
		}
	}

	function initCollapsible( toc ) {
		var titleBtn = toc.querySelector( '.mavo-toc__title' );
		if ( ! titleBtn || ! toc.classList.contains( 'mavo-toc--collapsible' ) ) {
			return;
		}

		var body = toc.querySelector( '.mavo-toc__body' );
		if ( body && toc.classList.contains( 'mavo-toc--collapsed' ) ) {
			// Matches a server-rendered "collapsed by default" instantly, with
			// no animation — only later, actual transitions should animate.
			body.hidden = true;
		}

		titleBtn.addEventListener( 'click', function () {
			setCollapsed( toc, ! toc.classList.contains( 'mavo-toc--collapsed' ), titleBtn );
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

		function computeScrollTarget( target ) {
			var barOffset = measureBar( barSelector );
			var tocOffset = 0;
			if ( sticky ) {
				// Once stuck, a collapsible TOC shrinks to just its title bar, so
				// that's the height that will actually obstruct the heading.
				tocOffset = ( collapsible && titleBtn ? titleBtn : toc ).getBoundingClientRect().height;
			}
			var targetTop = target.getBoundingClientRect().top + window.pageYOffset;
			return Math.max( 0, targetTop - barOffset - tocOffset - 12 );
		}

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
						setCollapsed( toc, true, titleBtn );
					}
				}

				// The stuck/collapse observer would otherwise re-evaluate at
				// intermediate scroll positions reached *during* the animation
				// (where the sentinel hasn't crossed its threshold yet, since the
				// click can happen well before that point), briefly undoing the
				// forced state above and shifting the layout mid-scroll. Suspended
				// here, restored once scrolling settles.
				var observer = toc._mavoTocObserver;
				if ( observer ) {
					observer.disconnect();
				}

				// A sticky element apparently renders slightly differently (margins
				// collapse with neighbours) before it has *actually* engaged
				// position: sticky versus once it truly has, even with the classes
				// above already forced — measured directly, this was off by ~35px
				// in testing. An instant jump first guarantees we're genuinely past
				// the engagement point, so a second, corrected measurement (next
				// frame) reflects how the TOC really renders once stuck, with a
				// short smooth scroll covering only that small remaining gap.
				window.scrollTo( { top: computeScrollTarget( target ), behavior: 'instant' } );

				requestAnimationFrame( function () {
					window.scrollTo( { top: computeScrollTarget( target ), behavior: 'smooth' } );

					if ( observer && toc._mavoTocSentinel ) {
						var resume = function () {
							clearTimeout( resumeTimer );
							window.removeEventListener( 'scrollend', resume );
							observer.observe( toc._mavoTocSentinel );
						};
						var resumeTimer = setTimeout( resume, 1000 );
						window.addEventListener( 'scrollend', resume, { once: true } );
					}
				} );

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
		toc._mavoTocSentinel = sentinel;

		var collapsible = toc.classList.contains( 'mavo-toc--collapsible' );
		var titleBtn = toc.querySelector( '.mavo-toc__title' );

		function syncStuck( stuck ) {
			toc.classList.toggle( 'mavo-toc--stuck', stuck );

			if ( collapsible ) {
				setCollapsed( toc, stuck, titleBtn );
			}
		}

		// Safety net for the IntersectionObserver below: an instant/large scroll
		// jump (a deep-linked #hash loaded directly, browser back/forward
		// restoring scroll position) doesn't always trigger an observer
		// callback even though CSS position: sticky has genuinely engaged
		// (confirmed in testing) — checking the TOC's own position directly
		// catches what the sentinel-crossing approach misses. Comparing against
		// the *current* class avoids redundant churn when nothing's actually
		// changed (it would otherwise re-toggle collapsed on every scroll tick,
		// fighting the "peek" feature below).
		function syncFromPosition( barOffset ) {
			var stuck = toc.getBoundingClientRect().top <= barOffset + 1;
			if ( stuck !== toc.classList.contains( 'mavo-toc--stuck' ) ) {
				syncStuck( stuck );
			}
		}

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
						syncStuck( stuck );
					} );
				},
				{ rootMargin: '-' + barOffset + 'px 0px 0px 0px' }
			);

			toc._mavoTocObserver.observe( sentinel );

			// Covers the case where the page already landed scrolled past the
			// engagement point (e.g. a deep-linked #hash) before this ever ran —
			// observe()'s own initial callback fires from the *current* state,
			// but only once IntersectionObserver decides to report it, which is
			// exactly what's unreliable here.
			syncFromPosition( barOffset );
		}

		window.addEventListener(
			'scroll',
			function () {
				var barOffset = parseInt( getComputedStyle( document.documentElement ).getPropertyValue( '--mavo-toc-bar-offset' ), 10 ) || 0;
				syncFromPosition( barOffset );
			},
			{ passive: true }
		);

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
						setCollapsed( toc, true, titleBtn );
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
