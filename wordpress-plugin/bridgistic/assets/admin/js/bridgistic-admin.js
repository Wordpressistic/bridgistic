/**
 * Bridgistic admin UI. Vanilla JS, no dependencies.
 *
 * Talks to wp_ajax_* endpoints defined in class-bridgistic-admin-actions.php.
 * Every request carries the bridgistic_admin nonce. Secrets returned by the
 * create/rotate endpoints live only in DOM nodes the user explicitly reveals;
 * they are never persisted client-side.
 */
( function () {
	'use strict';

	if ( typeof window.bridgisticAdmin === 'undefined' ) {
		return;
	}

	var cfg = window.bridgisticAdmin;
	var i18n = cfg.i18n || {};

	// ---- tiny helpers ---------------------------------------------------------

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	function toast( message, kind ) {
		var host = $( '#bridgistic-toasts' );
		if ( ! host ) {
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'bridgistic-toast' + ( kind ? ' is-' + kind : '' );
		el.textContent = message;
		host.appendChild( el );
		window.setTimeout( function () {
			el.classList.add( 'is-leaving' );
			window.setTimeout( function () {
				el.remove();
			}, 220 );
		}, 3500 );
	}

	/** How long an admin-ajax call may take before we call it a timeout. */
	var REQUEST_TIMEOUT_MS = 20000;

	/**
	 * A classified request failure.
	 *
	 * `kind` is what callers branch on (a terminal auth failure should stop a
	 * poll loop; a transient network blip should not). `message` is what the
	 * user reads. Raw response bodies are never carried on this object — a WAF
	 * block page is HTML, and putting HTML anywhere near the DOM is how an
	 * error handler becomes an injection point.
	 */
	function RequestError( kind, message, status ) {
		var err = new Error( message );
		err.name = 'RequestError';
		err.kind = kind;
		err.status = status || 0;
		// Terminal failures will keep failing identically until a human fixes
		// something, so a caller that polls should give up rather than retry.
		err.terminal = kind === 'auth' || kind === 'forbidden' || kind === 'notfound';
		return err;
	}

	/**
	 * Turn an HTTP status into an explanation of the likely cause.
	 *
	 * Production WordPress sits behind Cloudflare, Wordfence, Sucuri, hosting
	 * WAFs, ModSecurity, and caching layers, any of which can intercept
	 * admin-ajax.php and answer with an HTML block page. The failure the user
	 * used to see for that was "Unexpected token '<'", which tells them
	 * nothing about what to change.
	 */
	function describeHttpFailure( status, sawJson ) {
		if ( status === 401 || status === 403 ) {
			return RequestError(
				status === 401 ? 'auth' : 'forbidden',
				i18n.httpForbidden ||
					'The request was rejected (HTTP ' +
						status +
						'). Likely causes: your login session expired, the security token is stale (reload this page), your account lost the required capability, or a security plugin or firewall blocked the request. Check Bridgistic → Health Check.',
				status
			);
		}
		if ( status === 404 ) {
			return RequestError(
				'notfound',
				i18n.httpNotFound ||
					'The endpoint was not found (HTTP 404). Likely causes: the plugin was deactivated mid-session, permalinks need re-saving, or the REST API is disabled. Check Bridgistic → Health Check.',
				404
			);
		}
		if ( status === 429 ) {
			return RequestError(
				'ratelimited',
				i18n.httpRateLimited ||
					'Too many requests (HTTP 429). Something is rate limiting this site — wait a minute and try again.',
				429
			);
		}
		if ( status >= 500 ) {
			return RequestError(
				'server',
				( i18n.httpServerError ||
					'The server returned an error (HTTP %d). Check your PHP error log, or open Bridgistic → Health Check.' ).replace( '%d', String( status ) ),
				status
			);
		}
		if ( ! sawJson ) {
			return RequestError(
				'blocked',
				( i18n.blockedResponse ||
					'A security plugin, firewall, or proxy may be blocking this request (got HTTP %d instead of JSON). Check Bridgistic → Health Check.' ).replace( '%d', String( status ) ),
				status
			);
		}
		return RequestError( 'error', i18n.error || 'Something went wrong. Check the Health page.', status );
	}

	function post( action, fields ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( fields || {} ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		// AbortController lets a stalled request be reported as a timeout
		// rather than as an indistinguishable network failure — the two have
		// completely different fixes (proxy/WAF holding the connection vs. the
		// site being unreachable).
		var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
		var timedOut = false;
		var timer = window.setTimeout( function () {
			timedOut = true;
			if ( controller ) {
				controller.abort();
			}
		}, REQUEST_TIMEOUT_MS );

		var options = { method: 'POST', credentials: 'same-origin', body: body };
		if ( controller ) {
			options.signal = controller.signal;
		}

		return window
			.fetch( cfg.ajaxUrl, options )
			.catch( function () {
				if ( timedOut ) {
					throw RequestError(
						'timeout',
						i18n.timeoutError ||
							'The request timed out. The site may be slow, or a proxy or firewall may be holding the connection. Try again, then check Bridgistic → Health Check.'
					);
				}
				throw RequestError(
					'network',
					i18n.networkError ||
						'Could not reach WordPress. Check the network connection, or whether the request was blocked before it reached WordPress.'
				);
			} )
			.then( function ( res ) {
				return res.text().then( function ( raw ) {
					var json = null;
					if ( raw ) {
						try {
							json = JSON.parse( raw );
						} catch ( err ) {
							json = null;
						}
					}

					// Detect a non-JSON body before parsing decides the message,
					// so an HTML block page is reported as a block page.
					if ( ! json ) {
						if ( res.ok ) {
							throw RequestError(
								'blocked',
								i18n.badResponse ||
									'WordPress returned something other than JSON (HTTP ' +
										res.status +
										'). A security plugin, proxy, or cache may have replaced the response with an HTML page. Check Bridgistic → Health Check.',
								res.status
							);
						}
						throw describeHttpFailure( res.status, false );
					}

					if ( ! res.ok ) {
						// A structured error body is more specific than the
						// status alone, so prefer it when WordPress sent one.
						var serverMessage = json.data && json.data.message ? json.data.message : null;
						var classified = describeHttpFailure( res.status, true );
						if ( serverMessage ) {
							classified.message = serverMessage;
						}
						throw classified;
					}

					if ( json.success !== true ) {
						throw RequestError(
							'error',
							( json.data && json.data.message ) || i18n.error || 'Something went wrong. Check the Health page.',
							res.status
						);
					}
					return json.data;
				} );
			} )
			.finally( function () {
				window.clearTimeout( timer );
			} );
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' );
			area.value = text;
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			try {
				document.execCommand( 'copy' );
				resolve();
			} catch ( err ) {
				reject( err );
			} finally {
				area.remove();
			}
		} );
	}

	function downloadJson( filename, text ) {
		var blob = new Blob( [ text ], { type: 'application/json' } );
		var url = URL.createObjectURL( blob );
		var link = document.createElement( 'a' );
		link.href = url;
		link.download = filename;
		document.body.appendChild( link );
		link.click();
		link.remove();
		URL.revokeObjectURL( url );
	}

	function busy( button, isBusy ) {
		if ( ! button ) {
			return;
		}
		button.disabled = isBusy;
		if ( isBusy ) {
			button.dataset.label = button.textContent;
			button.textContent = i18n.working || 'Working…';
		} else if ( button.dataset.label ) {
			button.textContent = button.dataset.label;
		}
	}

	// ---- global: theme toggle ---------------------------------------------------

	var themeToggle = $( '#bridgistic-theme-toggle' );
	if ( themeToggle ) {
		themeToggle.addEventListener( 'click', function () {
			var root = document.documentElement;
			var current = root.getAttribute( 'data-bridgistic-theme' );
			var osDark = window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
			var effective = current || ( osDark ? 'dark' : 'light' );
			var next = effective === 'light' ? 'dark' : 'light';
			root.setAttribute( 'data-bridgistic-theme', next );
			try {
				window.localStorage.setItem( 'bridgistic-theme', next );
			} catch ( err ) {
				/* storage unavailable — theme just won't persist */
			}
		} );
	}

	// ---- global: copy buttons ----------------------------------------------------

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-copy-target]' );
		if ( ! button ) {
			return;
		}
		var node = document.getElementById( button.getAttribute( 'data-copy-target' ) );
		if ( ! node ) {
			return;
		}
		copyText( node.textContent.trim() ).then(
			function () {
				toast( i18n.copied || 'Copied', 'success' );
			},
			function () {
				toast( i18n.copyFailed || 'Copy failed', 'error' );
			}
		);
	} );

	// ---- global: confirm forms (revoke / delete) -----------------------------------

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( 'form[data-confirm]' );
		if ( ! form ) {
			return;
		}
		var kind = form.getAttribute( 'data-confirm' );
		var message = kind === 'delete' ? i18n.confirmDelete : i18n.confirmRevoke;
		if ( ! window.confirm( message || 'Are you sure?' ) ) {
			event.preventDefault();
		}
	} );

	// ---- global: log detail toggles ---------------------------------------------------

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-log-toggle]' );
		if ( ! button ) {
			return;
		}
		var row = document.getElementById( button.getAttribute( 'data-log-toggle' ) );
		if ( row ) {
			row.hidden = ! row.hidden;
		}
	} );

	// ---- Claude Setup wizard ------------------------------------------------------------

	var setup = $( '#bridgistic-setup' );
	if ( setup ) {
		var state = {
			connection: 'extension',
			preset: 'read_only',
			configs: null,
			keyId: null,
			connectSince: null,
		};

		var goToStep = function ( step ) {
			setup.dataset.step = String( step );
			$$( '[data-step-panel]', setup ).forEach( function ( panel ) {
				var num = parseInt( panel.getAttribute( 'data-step-panel' ), 10 );
				panel.classList.toggle( 'is-current', num === step );
				panel.classList.toggle( 'is-done', num < step );
			} );
			var active = $( '[data-step-panel="' + step + '"]', setup );
			if ( active && active.scrollIntoView ) {
				active.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
			if ( 5 === step ) {
				startClientPoll();
			} else {
				stopClientPoll();
			}
		};

		setup.addEventListener( 'click', function ( event ) {
			var next = event.target.closest( '[data-step-next]' );
			if ( next ) {
				var target = parseInt( next.getAttribute( 'data-step-next' ), 10 );
				// Guard: cannot pass step 3 without a key.
				if ( target > 3 && ! state.keyId ) {
					toast( i18n.error || 'Create a key first', 'error' );
					goToStep( 3 );
					return;
				}
				goToStep( target );
			}
			var back = event.target.closest( '[data-step-back]' );
			if ( back ) {
				goToStep( parseInt( back.getAttribute( 'data-step-back' ), 10 ) );
			}
		} );

		// Step 1: connection choice.
		$$( '[data-connection]', setup ).forEach( function ( choice ) {
			choice.addEventListener( 'click', function () {
				$$( '[data-connection]', setup ).forEach( function ( other ) {
					other.classList.remove( 'is-selected' );
				} );
				choice.classList.add( 'is-selected' );
				state.connection = choice.getAttribute( 'data-connection' );
				syncConfigTabs();
			} );
		} );

		// Step 2: preset choice + developer warning.
		var devWarning = $( '[data-dev-warning]', setup );
		$$( '[data-preset]', setup ).forEach( function ( choice ) {
			choice.addEventListener( 'click', function () {
				var risky = choice.getAttribute( 'data-risky' ) === '1';
				if ( risky && ! window.confirm( i18n.confirmDevMode || 'Enable developer mode?' ) ) {
					return;
				}
				$$( '[data-preset]', setup ).forEach( function ( other ) {
					other.classList.remove( 'is-selected' );
				} );
				choice.classList.add( 'is-selected' );
				state.preset = choice.getAttribute( 'data-preset' );
				if ( devWarning ) {
					devWarning.hidden = ! risky;
				}
			} );
		} );

		// Step 3: create key.
		var createButton = $( '#bridgistic-create-key' );
		if ( createButton ) {
			createButton.addEventListener( 'click', function () {
				busy( createButton, true );
				post( 'bridgistic_setup_create_key', {
					preset: state.preset,
					label: ( $( '#bridgistic-key-label' ) || {} ).value || '',
				} )
					.then( function ( data ) {
						state.keyId = data.keyId;
						state.configs = data.configs;
						state.connectSince = data.connectSince;
						$( '#bridgistic-new-key-id' ).textContent = data.keyId;
						$( '#bridgistic-new-key-secret' ).textContent = data.secret;
						$( '#bridgistic-key-result' ).hidden = false;
						fillConfigs();
						toast( i18n.secretOnce || 'Secret shown once — copy it now', 'success' );
					} )
					.catch( function ( err ) {
						toast( err.message, 'error' );
					} )
					.finally( function () {
						busy( createButton, false );
					} );
			} );
		}

		// Step 4: config tabs + copy/download.
		var fillConfigs = function () {
			if ( ! state.configs ) {
				return;
			}
			[ 'desktop', 'code', 'cli', 'codex', 'gemini' ].forEach( function ( key ) {
				var node = $( '#bridgistic-config-' + key );
				if ( node && state.configs[ key ] ) {
					node.textContent = state.configs[ key ];
				}
			} );
			var ext = state.configs.extensionFields;
			if ( ext ) {
				if ( $( '#bridgistic-ext-site-url' ) ) {
					$( '#bridgistic-ext-site-url' ).textContent = ext.siteUrl;
				}
				if ( $( '#bridgistic-ext-key-id' ) ) {
					$( '#bridgistic-ext-key-id' ).textContent = ext.keyId;
				}
				if ( $( '#bridgistic-ext-secret' ) ) {
					$( '#bridgistic-ext-secret' ).textContent = ext.secret;
				}
			}
		};

		var showConfigTab = function ( name ) {
			$$( '[data-config-tab]', setup ).forEach( function ( tab ) {
				tab.classList.toggle( 'is-active', tab.getAttribute( 'data-config-tab' ) === name );
			} );
			$$( '[data-config-panel]', setup ).forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-config-panel' ) !== name;
			} );
			// The extension panel has its own per-field copy buttons and its
			// own download-the-extension action; the generic JSON copy/download
			// footer buttons below don't apply to it (there's no single JSON
			// blob to copy for a three-field paste-in prompt).
			// The remote panel has no JSON blob either — it is an endpoint URL
			// plus a link out to the cloud page, so the copy/download footer
			// would have nothing to act on.
			var hasNoJsonBlob = 'extension' === name || 'remote' === name;
			[ '#bridgistic-copy-config', '#bridgistic-download-config' ].forEach( function ( sel ) {
				var btn = $( sel );
				if ( btn ) {
					btn.hidden = hasNoJsonBlob;
				}
			} );
		};

		// Which config tab each connection choice lands on. Anything unmapped
		// falls through to the Claude Desktop config, which is the shape most
		// MCP clients accept.
		var CONNECTION_TABS = {
			code: 'code',
			codex: 'codex',
			gemini: 'gemini',
			manual: 'cli',
			extension: 'extension',
			remote: 'remote',
		};

		var syncConfigTabs = function () {
			showConfigTab( CONNECTION_TABS[ state.connection ] || 'desktop' );
		};

		$$( '[data-config-tab]', setup ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				showConfigTab( tab.getAttribute( 'data-config-tab' ) );
			} );
		} );

		var activeConfigText = function () {
			var panel = $$( '[data-config-panel]', setup ).filter( function ( p ) {
				return ! p.hidden;
			} )[ 0 ];
			var pre = panel ? $( '.bridgistic-code[id]', panel ) : null;
			return pre ? pre.textContent : '';
		};

		var copyConfig = $( '#bridgistic-copy-config' );
		if ( copyConfig ) {
			copyConfig.addEventListener( 'click', function () {
				copyText( activeConfigText() ).then( function () {
					toast( i18n.copied || 'Copied', 'success' );
				} );
			} );
		}

		var downloadConfig = $( '#bridgistic-download-config' );
		if ( downloadConfig ) {
			downloadConfig.addEventListener( 'click', function () {
				if ( ! state.configs ) {
					return;
				}
				var downloadMap = {
					code: [ 'claude_code_config.json', state.configs.code ],
					codex: [ 'config.toml', state.configs.codex ],
					gemini: [ 'settings.json', state.configs.gemini ]
				};
				var picked = downloadMap[ state.connection ] || [ 'claude_desktop_config.json', state.configs.desktop ];
				downloadJson( picked[ 0 ], picked[ 1 ] );
			} );
		}

		// Step 5: test connection.
		var testButton = $( '#bridgistic-test-connection' );
		if ( testButton ) {
			testButton.addEventListener( 'click', function () {
				var out = $( '#bridgistic-test-result' );
				busy( testButton, true );
				out.hidden = false;
				out.innerHTML = '<div class="bridgistic-callout is-info"><p>' + ( i18n.testRunning || 'Testing…' ) + '</p></div>';
				post( 'bridgistic_test_connection', {} )
					.then( function ( data ) {
						var kind = data.ok ? 'is-success' : 'is-danger';
						var detail = data.hmac ? data.hmac.message : '';
						out.innerHTML =
							'<div class="bridgistic-callout ' + kind + '"><p></p></div>';
						out.querySelector( 'p' ).textContent = data.message + ' ' + detail;
					} )
					.catch( function ( err ) {
						out.innerHTML = '<div class="bridgistic-callout is-danger"><p></p></div>';
						out.querySelector( 'p' ).textContent = err.message;
					} )
					.finally( function () {
						busy( testButton, false );
					} );
			} );
		}

		// Step 5: client check — poll until the AI client makes its first real
		// request with this key, instead of making the user guess and go check
		// the Logs page themselves.
		var clientPollTimer = null;
		var clientPollCount = 0;
		var CLIENT_POLL_MAX = 40; // ~2.5 minutes at 4s intervals, then wait for manual "Check now".

		var renderClientStatus = function ( html ) {
			var out = $( '#bridgistic-client-status' );
			if ( out ) {
				out.innerHTML = html;
			}
		};

		var renderWaiting = function () {
			renderClientStatus(
				'<div class="bridgistic-callout is-info"><p>' +
					( i18n.clientWaiting || "Waiting for your AI client's first request…" ) +
					'</p></div>'
			);
		};

		var renderConnected = function () {
			renderClientStatus(
				'<div class="bridgistic-callout is-success"><p>' +
					( i18n.clientConnected || 'Connected — a real request just came in from your AI client.' ) +
					'</p></div>'
			);
		};

		var renderStillWaiting = function () {
			renderClientStatus(
				'<div class="bridgistic-callout is-info"><p>' +
					( i18n.clientStillWaiting || "Still waiting. That's normal if you haven't asked your AI assistant to do anything yet." ) +
					'</p><button type="button" class="bridgistic-button is-soft is-small" id="bridgistic-client-recheck">' +
					( i18n.checkNow || 'Check now' ) +
					'</button></div>'
			);
			var recheck = $( '#bridgistic-client-recheck' );
			if ( recheck ) {
				recheck.addEventListener( 'click', function () {
					clientPollCount = 0;
					renderWaiting();
					pollClientOnce();
				} );
			}
		};

		var clientPollInFlight = false;

		var pollClientOnce = function () {
			if ( ! state.keyId || ! state.connectSince || clientPollInFlight ) {
				return;
			}
			// The wizard's own tab being hidden means nobody is watching this
			// step; skip the round trip rather than polling into the void.
			if ( document.visibilityState === 'hidden' ) {
				return;
			}

			clientPollInFlight = true;
			post( 'bridgistic_poll_client_connected', { key_id: state.keyId, since: state.connectSince } )
				.then( function ( data ) {
					if ( data.connected ) {
						renderConnected();
						stopClientPoll();
						return;
					}
					clientPollCount++;
					if ( clientPollCount >= CLIENT_POLL_MAX ) {
						stopClientPoll();
						renderStillWaiting();
					}
				} )
				.catch( function ( err ) {
					// A revoked or deleted key will never report connected, so
					// stop and say so instead of spinning until the cap.
					if ( err && err.terminal ) {
						stopClientPoll();
						renderStillWaiting();
					}
					// Anything transient: try again on the next tick.
				} )
				.finally( function () {
					clientPollInFlight = false;
				} );
		};

		var startClientPoll = function () {
			// Guarding on the existing timer keeps a second click on "create
			// key" from leaving two intervals running against the same key.
			if ( clientPollTimer || ! state.keyId ) {
				return;
			}
			clientPollCount = 0;
			renderWaiting();
			pollClientOnce();
			clientPollTimer = window.setInterval( pollClientOnce, 4000 );
		};

		var stopClientPoll = function () {
			if ( clientPollTimer ) {
				window.clearInterval( clientPollTimer );
				clientPollTimer = null;
			}
		};

		// A poll loop that outlives its page keeps firing requests against a
		// document nobody can see.
		window.addEventListener( 'beforeunload', stopClientPoll );

		syncConfigTabs();
	}

	// ---- Dashboard: live connection status ----------------------------------------------

	var heroStatus = $( '#bridgistic-hero-status' );
	if ( heroStatus ) {
		var badgeEl = function ( kind, text, pulse ) {
			var span = document.createElement( 'span' );
			span.className = 'bridgistic-badge is-' + kind + ( pulse ? ' is-pulse' : '' );
			span.textContent = text;
			return span;
		};

		var wasConnected = !! $( '.bridgistic-badge.is-pass', heroStatus );
		var dashboardPollTimer = null;

		var renderDashboardStatus = function ( data ) {
			heroStatus.innerHTML = '';
			heroStatus.appendChild( badgeEl( data.statusKind, data.statusLabel, data.connected ) );
			var note = document.createElement( 'span' );
			note.className = 'bridgistic-hero-status-note';
			note.textContent = data.statusNote;
			heroStatus.appendChild( note );

			var connectionBadge = $( '#bridgistic-connection-badge' );
			if ( connectionBadge ) {
				connectionBadge.innerHTML = '';
				connectionBadge.appendChild( badgeEl( data.connected ? 'pass' : 'muted', data.connected ? ( i18n.connectedShort || 'Connected' ) : ( i18n.notConnectedShort || 'Not connected' ) ) );
			}

			var auditCount = $( '#bridgistic-audit-count' );
			if ( auditCount ) {
				auditCount.textContent = data.auditCount;
			}
			var latestLog = $( '#bridgistic-latest-log' );
			if ( latestLog ) {
				latestLog.textContent = data.latestLogText || i18n.noActivity || 'No MCP activity yet.';
			}

			if ( data.connected && ! wasConnected ) {
				toast( i18n.dashboardJustConnected || 'Bridgistic just connected — a request came in from your AI client.', 'success' );
			}
			wasConnected = data.connected;
		};

		// Self-rescheduling timeout rather than setInterval: the delay has to
		// change (backoff on failure, longer while hidden), and an interval
		// whose handler outlives its period stacks overlapping requests against
		// admin-ajax.php — the last thing a struggling site needs.
		var BASE_POLL_MS = 15000;
		var MAX_POLL_MS = 120000;
		var dashboardPollDelay = BASE_POLL_MS;
		var dashboardStopped = false;
		var dashboardInFlight = false;

		var scheduleDashboardPoll = function ( delay ) {
			if ( dashboardStopped ) {
				return;
			}
			if ( dashboardPollTimer ) {
				window.clearTimeout( dashboardPollTimer );
			}
			dashboardPollTimer = window.setTimeout( pollDashboardOnce, delay );
		};

		var pollDashboardOnce = function () {
			if ( dashboardStopped ) {
				return;
			}
			// Pause entirely while the tab is hidden. Re-check cheaply rather
			// than polling into the void.
			if ( document.visibilityState === 'hidden' ) {
				scheduleDashboardPoll( BASE_POLL_MS );
				return;
			}
			if ( dashboardInFlight ) {
				scheduleDashboardPoll( dashboardPollDelay );
				return;
			}

			dashboardInFlight = true;
			post( 'bridgistic_dashboard_status', {} )
				.then( function ( data ) {
					renderDashboardStatus( data );
					dashboardPollDelay = BASE_POLL_MS; // recovered
				} )
				.catch( function ( err ) {
					// A stale nonce or a missing route will fail identically
					// forever; retrying every 15s just adds load and a stream
					// of identical errors nobody can act on.
					if ( err && err.terminal ) {
						dashboardStopped = true;
						toast( err.message, 'error' );
						return;
					}
					// Otherwise back off exponentially, capped, so a site that
					// is briefly unwell is not hammered while it recovers.
					dashboardPollDelay = Math.min( dashboardPollDelay * 2, MAX_POLL_MS );
				} )
				.finally( function () {
					dashboardInFlight = false;
					scheduleDashboardPoll( dashboardPollDelay );
				} );
		};

		scheduleDashboardPoll( BASE_POLL_MS );

		document.addEventListener( 'visibilitychange', function () {
			// Coming back to the tab should feel instant, and resets any
			// backoff accumulated while nobody was looking.
			if ( document.visibilityState === 'visible' && ! dashboardStopped ) {
				dashboardPollDelay = BASE_POLL_MS;
				scheduleDashboardPoll( 0 );
			}
		} );
		window.addEventListener( 'beforeunload', function () {
			dashboardStopped = true;
			if ( dashboardPollTimer ) {
				window.clearTimeout( dashboardPollTimer );
			}
		} );
	}

	// ---- Multi-Site: connections.json builder --------------------------------------------

	var msOthers = $( '#bridgistic-ms-others' );
	if ( msOthers ) {
		var MS_STORAGE_KEY = 'bridgistic-ms-sites';
		var msTemplate = $( '#bridgistic-ms-row-template' );
		var msEmptyHint = $( '#bridgistic-ms-empty' );
		var msJsonOut = $( '#bridgistic-ms-json' );

		var msLoadSaved = function () {
			try {
				var raw = window.localStorage.getItem( MS_STORAGE_KEY );
				return raw ? JSON.parse( raw ) : [];
			} catch ( err ) {
				return [];
			}
		};

		// Structural fields only (alias/siteUrl/keyId) — secrets are never persisted here.
		var msSaveStructural = function () {
			var rows = $$( '[data-ms-row]', msOthers ).map( function ( row ) {
				return {
					alias: $( '[data-ms-field="alias"]', row ).value,
					siteUrl: $( '[data-ms-field="siteUrl"]', row ).value,
					keyId: $( '[data-ms-field="keyId"]', row ).value,
				};
			} );
			try {
				window.localStorage.setItem( MS_STORAGE_KEY, JSON.stringify( rows ) );
			} catch ( err ) {
				/* storage unavailable — rows just won't persist across reloads */
			}
		};

		var msUpdateEmptyHint = function () {
			if ( msEmptyHint ) {
				msEmptyHint.hidden = msOthers.children.length > 0;
			}
		};

		var msComputeJson = function () {
			var out = {};

			var thisAlias = ( $( '#bridgistic-ms-this-alias' ) || {} ).value || '';
			if ( thisAlias.trim() ) {
				out[ thisAlias.trim() ] = {
					siteUrl: ( $( '#bridgistic-ms-this-site-url' ) || {} ).value || '',
					keyId: ( $( '#bridgistic-ms-this-key' ) || {} ).value || '',
					secret: ( $( '#bridgistic-ms-this-secret' ) || {} ).value || 'PASTE_YOUR_SECRET_HERE',
				};
			}

			$$( '[data-ms-row]', msOthers ).forEach( function ( row ) {
				var alias = $( '[data-ms-field="alias"]', row ).value.trim();
				if ( ! alias ) {
					return;
				}
				out[ alias ] = {
					siteUrl: $( '[data-ms-field="siteUrl"]', row ).value,
					keyId: $( '[data-ms-field="keyId"]', row ).value,
					secret: $( '[data-ms-field="secret"]', row ).value || 'PASTE_YOUR_SECRET_HERE',
				};
			} );

			if ( msJsonOut ) {
				msJsonOut.textContent = JSON.stringify( out, null, 2 );
			}
		};

		var msAddRow = function ( prefill ) {
			if ( ! msTemplate || ! msTemplate.content ) {
				return;
			}
			var node = msTemplate.content.firstElementChild.cloneNode( true );
			if ( prefill ) {
				[ 'alias', 'siteUrl', 'keyId' ].forEach( function ( field ) {
					if ( prefill[ field ] ) {
						$( '[data-ms-field="' + field + '"]', node ).value = prefill[ field ];
					}
				} );
			}
			msOthers.appendChild( node );
			msUpdateEmptyHint();
			msComputeJson();
		};

		msOthers.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '[data-ms-remove]' );
			if ( remove ) {
				var row = remove.closest( '[data-ms-row]' );
				if ( row ) {
					row.remove();
					msUpdateEmptyHint();
					msComputeJson();
					msSaveStructural();
				}
			}
		} );

		msOthers.addEventListener( 'input', function () {
			msComputeJson();
			msSaveStructural();
		} );

		var addButton = $( '#bridgistic-ms-add' );
		if ( addButton ) {
			addButton.addEventListener( 'click', function () {
				msAddRow( null );
				msSaveStructural();
			} );
		}

		var thisKeySelect = $( '#bridgistic-ms-this-key' );
		var syncThisSecret = function () {
			var secretField = $( '#bridgistic-ms-this-secret' );
			if ( ! thisKeySelect || ! secretField ) {
				return;
			}
			var freshKeyId = thisKeySelect.getAttribute( 'data-fresh-key-id' );
			var freshSecret = thisKeySelect.getAttribute( 'data-fresh-secret' );
			secretField.value = freshKeyId && freshKeyId === thisKeySelect.value ? freshSecret : '';
			msComputeJson();
		};
		if ( thisKeySelect ) {
			thisKeySelect.addEventListener( 'change', syncThisSecret );
			syncThisSecret();
		}

		[ '#bridgistic-ms-this-alias', '#bridgistic-ms-this-secret' ].forEach( function ( sel ) {
			var el = $( sel );
			if ( el ) {
				el.addEventListener( 'input', msComputeJson );
			}
		} );

		var msCopy = $( '#bridgistic-ms-copy' );
		if ( msCopy ) {
			msCopy.addEventListener( 'click', function () {
				copyText( msJsonOut ? msJsonOut.textContent : '' ).then( function () {
					toast( i18n.copied || 'Copied', 'success' );
				} );
			} );
		}

		var msDownload = $( '#bridgistic-ms-download' );
		if ( msDownload ) {
			msDownload.addEventListener( 'click', function () {
				downloadJson( 'connections.json', msJsonOut ? msJsonOut.textContent : '{}' );
			} );
		}

		msLoadSaved().forEach( function ( saved ) {
			msAddRow( saved );
		} );
		msUpdateEmptyHint();
		msComputeJson();
	}

	// ---- Keys page: rotate / get config / ajax revoke -------------------------------------

	document.addEventListener( 'click', function ( event ) {
		var rotate = event.target.closest( '[data-key-rotate]' );
		if ( rotate ) {
			if ( ! window.confirm( i18n.confirmRotate || 'Rotate secret?' ) ) {
				return;
			}
			busy( rotate, true );
			post( 'bridgistic_rotate_key', { key_id: rotate.getAttribute( 'data-key-rotate' ) } )
				.then( function ( data ) {
					var section = $( '#bridgistic-rotated-result' );
					$( '#bridgistic-rotated-key-id' ).textContent = data.keyId;
					$( '#bridgistic-rotated-key-secret' ).textContent = data.secret;
					section.hidden = false;
					section.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					toast( i18n.secretOnce || 'Secret shown once', 'success' );
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.finally( function () {
					busy( rotate, false );
				} );
			return;
		}

		var getConfig = event.target.closest( '[data-key-config]' );
		if ( getConfig ) {
			busy( getConfig, true );
			var keyId = getConfig.getAttribute( 'data-key-config' );
			post( 'bridgistic_get_config', { key_id: keyId } )
				.then( function ( data ) {
					var section = $( '#bridgistic-key-config-result' );
					$( '#bridgistic-config-key-id' ).textContent = keyId;
					$( '#bridgistic-existing-config' ).textContent = data.configs.desktop;
					section.hidden = false;
					section.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.finally( function () {
					busy( getConfig, false );
				} );
		}
	} );

	// ---- Health page ----------------------------------------------------------------------

	var runHealth = $( '#bridgistic-run-health' );
	if ( runHealth ) {
		var report = null;

		var iconFor = function ( status ) {
			var map = { pass: 'M4 12.5 9.5 18 20 6.5', warn: 'M12 3 1.5 21h21L12 3zm0 7v5m0 3h.01', fail: 'M5 5l14 14M19 5 5 19', info: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zm0-14h.01M12 12v6' };
			return (
				'<svg class="bridgistic-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' +
				( map[ status ] || map.info ) +
				'"/></svg>'
			);
		};

		var badgeFor = function ( status ) {
			var label = { pass: 'Pass', warn: 'Warning', fail: 'Fail', info: 'Info' }[ status ] || status;
			var cls = { pass: 'is-pass', warn: 'is-warn', fail: 'is-fail', info: 'is-info' }[ status ] || 'is-muted';
			return '<span class="bridgistic-badge ' + cls + '">' + label + '</span>';
		};

		var renderChecks = function ( data ) {
			var grid = $( '#bridgistic-health-grid' );
			grid.innerHTML = '';
			data.checks.forEach( function ( check ) {
				var card = document.createElement( 'article' );
				card.className = 'bridgistic-card bridgistic-health-card is-' + check.status;
				var head = document.createElement( 'header' );
				head.innerHTML = iconFor( check.status ) + '<h3></h3>' + badgeFor( check.status );
				head.querySelector( 'h3' ).textContent = check.label;
				card.appendChild( head );
				var msg = document.createElement( 'p' );
				msg.textContent = check.message;
				card.appendChild( msg );
				if ( check.fix ) {
					var fix = document.createElement( 'span' );
					fix.className = 'bridgistic-fix';
					fix.textContent = '→ ' + check.fix;
					card.appendChild( fix );
				}
				grid.appendChild( card );
			} );

			// Score arc.
			var arc = $( '#bridgistic-score-arc' );
			var number = $( '#bridgistic-score-number' );
			var circumference = 326.7;
			if ( arc ) {
				arc.style.strokeDashoffset = String( circumference * ( 1 - data.score / 100 ) );
				arc.style.stroke = data.score >= 80 ? 'var(--bz-accent)' : data.score >= 50 ? 'var(--bz-warn)' : 'var(--bz-danger)';
			}
			if ( number ) {
				number.textContent = String( data.score );
			}
			var headline = $( '#bridgistic-health-headline' );
			var subline = $( '#bridgistic-health-subline' );
			if ( headline ) {
				headline.textContent =
					data.score >= 90 ? 'Everything looks healthy' : data.score >= 60 ? 'Mostly healthy — review the warnings' : 'Attention needed';
			}
			if ( subline ) {
				subline.textContent = 'Checked ' + data.checked_at;
			}
		};

		var run = function () {
			busy( runHealth, true );
			post( 'bridgistic_run_health', {} )
				.then( function ( data ) {
					report = data.report;
					renderChecks( data );
					$( '#bridgistic-copy-report' ).disabled = false;
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.finally( function () {
					busy( runHealth, false );
				} );
		};

		runHealth.addEventListener( 'click', run );

		var copyReport = $( '#bridgistic-copy-report' );
		if ( copyReport ) {
			copyReport.addEventListener( 'click', function () {
				if ( ! report ) {
					return;
				}
				copyText( JSON.stringify( report, null, 2 ) ).then( function () {
					toast( i18n.copied || 'Copied', 'success' );
				} );
			} );
		}

		// Auto-run on page open — skeletons are already painted.
		run();
	}

	// ---- Snapshots page -----------------------------------------------------------------------

	var createSnapshot = $( '#bridgistic-create-snapshot' );
	if ( createSnapshot ) {
		createSnapshot.addEventListener( 'click', function () {
			busy( createSnapshot, true );
			post( 'bridgistic_create_snapshot', {} )
				.then( function ( data ) {
					toast( data.message, 'success' );
					window.location.reload();
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
					busy( createSnapshot, false );
				} );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var restore = event.target.closest( '[data-snapshot-restore]' );
		if ( restore ) {
			if ( ! window.confirm( i18n.confirmRestore || 'Restore snapshot?' ) ) {
				return;
			}
			busy( restore, true );
			post( 'bridgistic_restore_snapshot', { snapshot_id: restore.getAttribute( 'data-snapshot-restore' ) } )
				.then( function ( data ) {
					toast( data.message, 'success' );
					window.location.reload();
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
					busy( restore, false );
				} );
			return;
		}

		var remove = event.target.closest( '[data-snapshot-delete]' );
		if ( remove ) {
			if ( ! window.confirm( i18n.confirmDelete || 'Delete permanently?' ) ) {
				return;
			}
			busy( remove, true );
			post( 'bridgistic_delete_snapshot', { snapshot_id: remove.getAttribute( 'data-snapshot-delete' ) } )
				.then( function () {
					window.location.reload();
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
					busy( remove, false );
				} );
		}
	} );

	// ---- Playbooks page ---------------------------------------------------------------------------

	document.addEventListener( 'click', function ( event ) {
		var builtin = event.target.closest( '[data-playbook-run]' );
		if ( builtin ) {
			var slug = builtin.getAttribute( 'data-playbook-run' );
			var resultBox = $( '[data-playbook-result="' + slug + '"]' );
			busy( builtin, true );
			post( 'bridgistic_run_builtin_playbook', { playbook: slug } )
				.then( function ( data ) {
					if ( resultBox ) {
						resultBox.hidden = false;
						resultBox.textContent = data.message;
					}
					toast( i18n.done || 'Done', 'success' );
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.finally( function () {
					busy( builtin, false );
				} );
			return;
		}

		var saved = event.target.closest( '[data-saved-playbook-run]' );
		if ( saved ) {
			var savedSlug = saved.getAttribute( 'data-saved-playbook-run' );
			var dry = saved.getAttribute( 'data-dry' ) === '1';
			if ( ! dry && ! window.confirm( 'Run this playbook live? Writes go through the Guard (dry-run, approvals, snapshots).' ) ) {
				return;
			}
			var row = $( '[data-saved-playbook-result="' + savedSlug + '"]' );
			busy( saved, true );
			post( 'bridgistic_run_saved_playbook', { playbook: savedSlug, dry_run: dry ? '1' : '' } )
				.then( function ( data ) {
					if ( row ) {
						row.hidden = false;
						row.querySelector( 'pre' ).textContent = JSON.stringify( data, null, 2 );
					}
					toast( i18n.done || 'Done', data.status === 'ok' ? 'success' : undefined );
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.finally( function () {
					busy( saved, false );
				} );
		}
	} );

	// ---- Export page: secret checkbox guard ----------------------------------------------------------

	var exportForm = $( '#bridgistic-export-form' );
	if ( exportForm ) {
		exportForm.addEventListener( 'submit', function ( event ) {
			var secretBox = $( 'input[name="include_secret"]', exportForm );
			if ( ! secretBox || ! secretBox.checked ) {
				return;
			}
			var keySelect = $( '#bridgistic-export-key', exportForm );
			var freshKey = secretBox.getAttribute( 'data-fresh-key' );
			if ( keySelect && freshKey && keySelect.value !== freshKey ) {
				event.preventDefault();
				toast( 'The secret is only available for key ' + freshKey + ' — select it or untick "embed secret".', 'error' );
				return;
			}
			if ( ! window.confirm( 'The package will contain your key secret. Never share it publicly. Continue?' ) ) {
				event.preventDefault();
			}
		} );
	}
} )();
