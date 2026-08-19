/* global kvSync, jQuery */
( function ( $ ) {
    'use strict';

    var stopRequested = false;
    var currentXhr    = null;
    var retryCount    = 0;
    var retryTimer    = null;
    var chunkInFlight = false;
    var currentPhase  = 'main';
    var syncRunId     = '';
    var TIMEOUT_MS    = parseInt( kvSync.timeoutMs, 10 ) || 300000;
    var MAX_RETRIES   = parseInt( kvSync.maxRetries, 10 ) || 2;
    var TRANSIENT_RETRY_DELAY_MS = 5 * 60 * 1000; // 5 minutes — timeout / network / 504

    var $runBtn        = $( '#kv-run-sync' );
    var $resumeBtn     = $( '#kv-resume-sync' );
    var $freshBtn      = $( '#kv-start-fresh' );
    var $stopBtn       = $( '#kv-stop-sync' );
    var $status        = $( '#kv-sync-status' );
    var $progressWrap  = $( '#kv-progress-wrap' );
    var $progressFill  = $( '#kv-progress-fill' );
    var $progressLabel = $( '#kv-progress-label' );

    var $statLastSync = $( '#kv-stat-last-sync' );
    var $statTotal    = $( '#kv-stat-total' );
    var $statAdded    = $( '#kv-stat-added' );
    var $statUpdated  = $( '#kv-stat-updated' );

    var $modal      = $( '#kv-sync-modal' );
    var $modalTitle = $( '#kv-sync-modal-title' );
    var $modalMeta  = $( '#kv-sync-modal-meta' );
    var $modalBody  = $( '#kv-sync-modal-body' );

    var $logsTbody = $( '#kv-sync-logs-tbody' );

    var logPollTimer = null;
    var LOG_POLL_MS  = 3000;

    /* ── Live log polling ── */

    function startLogPolling() {
        stopLogPolling();
        pollLogs();
        logPollTimer = setInterval( pollLogs, LOG_POLL_MS );
    }

    function stopLogPolling() {
        if ( logPollTimer !== null ) {
            clearInterval( logPollTimer );
            logPollTimer = null;
        }
    }

    function pollLogs() {
        $.ajax( {
            url     : kvSync.ajaxUrl,
            method  : 'POST',
            data    : { action : 'kv_sync_log_poll', nonce : kvSync.nonce },
            timeout : 30000,
        } )
        .done( function ( response ) {
            if ( ! response || ! response.success ) {
                return;
            }
            var data = response.data || {};
            if ( data.entries ) {
                renderLogs( data.entries );
            }
        } )
        .fail( function () {
            /* Polling is best-effort — ignore transient failures */
        } );
    }

    function renderLogs( entries ) {
        if ( ! $logsTbody.length || ! Array.isArray( entries ) ) {
            return;
        }
        if ( entries.length === 0 ) {
            $logsTbody.empty().append(
                $( '<tr>' ).append(
                    $( '<td>' ).attr( 'colspan', 5 )
                        .addClass( 'kv-sync-logs-table__empty' )
                        .text( 'No sync logs yet. Run a sync to generate activity here.' )
                )
            );
            return;
        }
        var rows = [];
        $.each( entries, function ( i, log ) {
            rows.push( buildLogRow( log ) );
        } );
        $logsTbody.empty().append( rows );
    }

    function buildLogRow( log ) {
        var status = String( log.status || 'unknown' ).toLowerCase();
        var badge  = 'kv-sync-badge--muted';
        if ( status === 'success' ) {
            badge = 'kv-sync-badge--success';
        } else if ( status === 'failed' ) {
            badge = 'kv-sync-badge--failed';
        } else if ( status === 'processing' ) {
            badge = 'kv-sync-badge--processing';
        }

        var error     = String( log.error || '' );
        var errorBits = error.split( /\r\n|\r|\n/ );
        var firstLine = errorBits[ 0 ] || '';
        var more      = errorBits.length - 1;

        var $notes = $( '<td>' ).addClass( 'kv-sync-logs-table__notes' );
        if ( firstLine ) {
            $notes.addClass( 'kv-sync-logs-table__notes--link' );
            $notes.attr( 'data-error', error );
            $notes.append( document.createTextNode( firstLine ) );
            if ( more > 0 ) {
                $notes.append(
                    $( '<span>' ).addClass( 'kv-sync-logs-table__more' ).text( '+' + more + ' more' )
                );
            }
        } else {
            $notes.text( '–' );
        }

        return $( '<tr>' ).append(
            $( '<td>' ).addClass( 'kv-sync-logs-table__mono' ).text( log.timestamp || '–' ),
            $( '<td>' ).addClass( 'kv-sync-logs-table__mono' ).text( log.property_id || '–' ),
            $( '<td>' ).text( log.property_name || '–' ),
            $( '<td>' ).append(
                $( '<span>' ).addClass( 'kv-sync-badge ' + badge )
                    .text( status.charAt( 0 ).toUpperCase() + status.slice( 1 ) )
            ),
            $notes
        );
    }

    /* ── Full-log popup ── */

    function openLogModal( $cell ) {
        var error = $cell.attr( 'data-error' );
        if ( ! error ) {
            return;
        }
        var $row = $cell.closest( 'tr' );
        var pid  = $row.find( 'td' ).eq( 1 ).text();
        var name = $row.find( 'td' ).eq( 2 ).text();
        var time = $row.find( 'td' ).eq( 0 ).text();
        var st   = $row.find( '.kv-sync-badge' ).text();

        $modalTitle.text( 'Property ' + pid + ( name && name !== '–' ? ' — ' + name : '' ) );
        $modalMeta.text( time + '  ·  ' + st );
        $modalBody.text( error );
        $modal.prop( 'hidden', false );
        $( 'body' ).addClass( 'kv-sync-modal-open' );
    }

    function closeLogModal() {
        $modal.prop( 'hidden', true );
        $( 'body' ).removeClass( 'kv-sync-modal-open' );
    }

    $( document ).on( 'click', '.kv-sync-logs-table__notes--link', function () {
        openLogModal( $( this ) );
    } );

    $( document ).on( 'click', '[data-kv-modal-close]', closeLogModal );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            closeLogModal();
        }
    } );

    function showResumeUi() {
        $runBtn.hide().prop( 'disabled', false );
        $resumeBtn.show().prop( 'disabled', false );
        $freshBtn.show().prop( 'disabled', false );
        $stopBtn.hide().prop( 'disabled', false );
    }

    function showIdleUi() {
        $runBtn.show().prop( 'disabled', false );
        $resumeBtn.hide().prop( 'disabled', false );
        $freshBtn.hide().prop( 'disabled', false );
        $stopBtn.hide().prop( 'disabled', false );
    }

    function showRunningUi() {
        $runBtn.hide().prop( 'disabled', true );
        $resumeBtn.hide().prop( 'disabled', true );
        $freshBtn.hide().prop( 'disabled', true );
        $stopBtn.show().prop( 'disabled', false );
    }

    if ( kvSync.isResumable ) {
        showResumeUi();
    }

    /* ── Chunk size validation (1–5 only) ── */
    var $chunkInput = $( '#kv_sync_chunk_size' );
    var $chunkError = $( '#kv-sync-chunk-error' );
    var $chunkForm  = $( '#kv-sync-settings-form' );

    function clampChunkSize( value ) {
        var n = parseInt( value, 10 );
        if ( isNaN( n ) ) {
            return 3;
        }
        return Math.max( 1, Math.min( 5, n ) );
    }

    $chunkInput.on( 'input change blur', function () {
        var n = parseInt( $chunkInput.val(), 10 );
        if ( $chunkInput.val() === '' || isNaN( n ) ) {
            return;
        }
        if ( n > 5 ) {
            $chunkInput.val( 5 );
            $chunkError.show();
        } else if ( n < 1 ) {
            $chunkInput.val( 1 );
            $chunkError.show();
        } else {
            $chunkError.hide();
        }
    });

    $chunkForm.on( 'submit', function () {
        var raw = $chunkInput.val();
        var n   = parseInt( raw, 10 );

        if ( raw === '' || isNaN( n ) || n < 1 || n > 5 ) {
            $chunkInput.val( clampChunkSize( raw ) );
            $chunkError.show();
            /* Still allow save — value is clamped to 1–5 before submit */
        } else {
            $chunkError.hide();
            $chunkInput.val( n );
        }
        return true;
    });

    function clearRetryTimer() {
        if ( retryTimer ) {
            clearTimeout( retryTimer );
            retryTimer = null;
        }
    }

    /* Page numbers live only in #kv-progress-label — status stays short */
    function setSyncingStatus() {
        $status.text( kvSync.i18n.syncing || 'Syncing…' );
    }

    function retryDelayMs( attempt ) {
        return Math.min( 15000, 2000 * Math.pow( 2, Math.max( 0, attempt - 1 ) ) );
    }

    function formatEta( seconds ) {
        seconds = parseFloat( seconds );
        if ( ! seconds || seconds <= 0 || isNaN( seconds ) ) {
            return '';
        }
        var mins = Math.floor( seconds / 60 );
        var secs = Math.round( seconds % 60 );
        if ( mins >= 60 ) {
            var hrs = Math.floor( mins / 60 );
            mins = mins % 60;
            return '  |  ETA: ~' + hrs + 'h ' + mins + 'm';
        }
        if ( mins > 0 ) {
            return '  |  ETA: ~' + mins + 'm ' + secs + 's';
        }
        return '  |  ETA: ~' + secs + 's';
    }

    function updateProgressFromData( data ) {
        var known      = data.pagination_known === true || data.pagination_known === 1 || data.pagination_known === '1';
        var page       = data.page || 1;
        var totalPages = data.total_pages || 1;
        var failed     = parseInt( data.failed_count, 10 ) || 0;
        var phase      = data.phase || currentPhase;
        currentPhase   = phase;
        var eta        = formatEta( data.eta_seconds );

        var pct;
        if ( phase === 'catchup' || phase === 'done' ) {
            pct = 100;
        } else if ( ! known ) {
            pct = 0;
        } else {
            pct = totalPages > 0 ? Math.min( 99, Math.round( ( page / totalPages ) * 100 ) ) : 0;
        }

        $progressFill.css( 'width', pct + '%' );

        if ( phase === 'catchup' ) {
            var catchupPage = data.catchup_page || page;
            $progressLabel.text(
                'Finishing skipped pages' +
                ( catchupPage ? ( ' — page ' + catchupPage ) : '' ) +
                ( failed ? ( '  |  ' + failed + ' left' ) : '' ) +
                '  |  Added: ' + ( data.added || 0 ) +
                '  |  Updated: ' + ( data.updated || 0 ) +
                eta
            );
            $status.text( kvSync.i18n.catchupShort || 'Retrying skipped pages…' );
        } else if ( ! known ) {
            $progressLabel.text(
                'Page ' + page + ' — waiting for API page count…' +
                ( failed ? ( '  |  Skipped: ' + failed ) : '' ) +
                '  |  Added: ' + ( data.added || 0 ) +
                '  |  Updated: ' + ( data.updated || 0 ) +
                eta
            );
            setSyncingStatus();
        } else {
            $progressLabel.text(
                'Page ' + page + ' of ' + totalPages +
                ( failed ? ( '  |  Skipped: ' + failed ) : '' ) +
                '  |  Added: ' + ( data.added || 0 ) +
                '  |  Updated: ' + ( data.updated || 0 ) +
                eta
            );
            setSyncingStatus();
        }

        if ( data.added   !== undefined ) { $statAdded.text( data.added ); }
        if ( data.updated !== undefined ) { $statUpdated.text( data.updated ); }
        if ( data.total   !== undefined ) { $statTotal.text( data.total ); }
        if ( data.last_sync )             { $statLastSync.text( data.last_sync ); }

        if ( ! data.done ) {
            kvSync.resumePage  = phase === 'catchup' ? totalPages : ( page + 1 );
            kvSync.resumeTotal = totalPages;
        }
    }

    function scheduleNext( delay ) {
        clearRetryTimer();
        retryTimer = setTimeout( processNextChunk, delay || 800 );
    }

    /* ──────────────────────────────────────────
     * Start / Start Fresh (from page 1)
     * ────────────────────────────────────────── */
    function abortCurrentXhr() {
        var pending = currentXhr;
        currentXhr  = null;
        chunkInFlight = false;
        if ( pending && pending.readyState !== 4 ) {
            pending.abort();
        }
    }

    function startFreshSync() {
        stopRequested = false;
        retryCount    = 0;
        currentPhase  = 'main';
        syncRunId     = '';
        clearRetryTimer();
        abortCurrentXhr();

        showRunningUi();
        $progressWrap.show();
        $progressFill.css( 'width', '0%' );
        $progressLabel.text( 'Starting from page 1…' );
        kvSync.resumePage  = 1;
        kvSync.resumeTotal = 1;
        setSyncingStatus();
        startLogPolling();

        postAjax( {
            action : 'kv_sync_start',
            nonce  : kvSync.nonce,
        } )
        .done( function ( response ) {
            if ( ! response || ! response.success ) {
                handleError( response && response.data && response.data.message );
                return;
            }
            if ( response.data && response.data.run_id ) {
                syncRunId = response.data.run_id;
            }
            kvSync.resumePage  = 1;
            kvSync.resumeTotal = response.data && response.data.total_pages
                ? response.data.total_pages
                : 1;
            $progressLabel.text( 'Page 1 of …' );
            $statAdded.text( '0' );
            $statUpdated.text( '0' );
            processNextChunk();
        } )
        .fail( function ( xhr ) {
            handleError( extractAjaxError( xhr ) );
        } );
    }

    $runBtn.on( 'click', function () {
        startFreshSync();
    } );

    /* Start from page 1 even when Resume is available */
    $freshBtn.on( 'click', function () {
        var msg = kvSync.i18n.freshConfirm ||
            'This will discard the interrupted sync progress and start again from page 1. Continue?';
        if ( ! window.confirm( msg ) ) {
            return;
        }
        startFreshSync();
    } );

    /* ──────────────────────────────────────────
     * Resume button
     * ────────────────────────────────────────── */
    $resumeBtn.on( 'click', function () {
        stopRequested = false;
        retryCount    = 0;
        clearRetryTimer();
        abortCurrentXhr();

        showRunningUi();
        $progressWrap.show();
        $status.text(
            ( kvSync.i18n.resuming || '' )
                .replace( '%d', kvSync.resumePage )
                .replace( '%d', kvSync.resumeTotal )
        );
        startLogPolling();

        postAjax( {
            action : 'kv_sync_resume',
            nonce  : kvSync.nonce,
        } )
        .done( function ( response ) {
            if ( ! response || ! response.success ) {
                handleError( response && response.data && response.data.message );
                return;
            }
            if ( response.data && response.data.done ) {
                finish( true, response.data );
                return;
            }
            if ( response.data ) {
                if ( response.data.run_id ) {
                    syncRunId = response.data.run_id;
                }
                kvSync.resumePage  = response.data.page || kvSync.resumePage;
                kvSync.resumeTotal = response.data.total_pages || kvSync.resumeTotal;
                currentPhase       = response.data.phase || currentPhase;
                $progressLabel.text(
                    'Page ' + kvSync.resumePage + ' of ' + kvSync.resumeTotal
                );
            }
            setSyncingStatus();
            processNextChunk();
        } )
        .fail( function ( xhr ) {
            handleError( extractAjaxError( xhr ) );
        } );
    } );

    /* ──────────────────────────────────────────
     * Stop button — invalidate server run so old chunks cannot move the page
     * ────────────────────────────────────────── */
    $stopBtn.on( 'click', function () {
        stopRequested = true;
        clearRetryTimer();
        $status.text( kvSync.i18n.stopping );
        $stopBtn.prop( 'disabled', true );

        var pending = currentXhr;
        currentXhr  = null;
        chunkInFlight = false;

        $.ajax( {
            url     : kvSync.ajaxUrl,
            method  : 'POST',
            data    : {
                action : 'kv_sync_stop',
                nonce  : kvSync.nonce,
                run_id : syncRunId,
            },
            timeout : 30000,
        } )
        .always( function () {
            syncRunId = '';
            if ( pending && pending.readyState !== 4 ) {
                pending.abort();
            }
            finish( false );
        } );
    } );

    function postAjax( data ) {
        currentXhr = $.ajax( {
            url     : kvSync.ajaxUrl,
            method  : 'POST',
            data    : data,
            timeout : TIMEOUT_MS,
        } );
        return currentXhr;
    }

    /* ──────────────────────────────────────────
     * Process one chunk / catchup item
     * ────────────────────────────────────────── */
    function processNextChunk() {
        if ( stopRequested ) {
            finish( false );
            return;
        }

        if ( chunkInFlight ) {
            return;
        }

        chunkInFlight = true;

        if ( currentPhase === 'catchup' ) {
            $status.text( kvSync.i18n.catchupShort || 'Retrying skipped pages…' );
        } else {
            setSyncingStatus();
        }

        postAjax( {
            action : 'kv_sync_chunk',
            nonce  : kvSync.nonce,
            run_id : syncRunId,
        } )
        .done( function ( response ) {
            chunkInFlight = false;

            if ( stopRequested ) {
                return;
            }

            if ( ! response || ! response.success ) {
                var errData    = response && response.data;
                var errMsg     = ( errData && errData.message ) || kvSync.i18n.error;
                var transient  = classifyTransientError( null, null, errData && errData.error_type );

                if ( errData && errData.phase ) {
                    currentPhase = errData.phase;
                }

                if ( transient ) {
                    scheduleTransientRetry( transient, errMsg );
                    return;
                }

                maybeRetryOrSkip( errMsg, false, errData );
                return;
            }

            var data = response.data || {};

            /* Old request after Stop / Start Fresh — ignore completely */
            if ( data.stale ) {
                return;
            }

            if ( data.busy ) {
                $status.text( kvSync.i18n.busy || 'Server still processing previous chunk, waiting…' );
                scheduleNext( parseInt( data.retry_after_ms, 10 ) || 5000 );
                return;
            }

            retryCount = 0;
            handleChunkSuccess( data );
        } )
        .fail( function ( xhr, textStatus ) {
            chunkInFlight = false;

            if ( stopRequested || textStatus === 'abort' ) {
                /* Stop handler already finishes the UI */
                return;
            }

            var isTimeout = textStatus === 'timeout' ||
                ( xhr && ( xhr.statusText === 'timeout' || ( xhr.status === 0 && ! xhr.responseText ) ) );

            var transientType = classifyTransientError( xhr, textStatus, null );
            if ( transientType ) {
                scheduleTransientRetry( transientType, extractAjaxError( xhr, textStatus ) );
                return;
            }

            maybeRetryOrSkip( extractAjaxError( xhr, textStatus ), isTimeout, null );
        } );
    }

    function handleChunkSuccess( data ) {
        updateProgressFromData( data );

        if ( data.done ) {
            $progressFill.css( 'width', '100%' );
            finish( true, data );
            return;
        }

        /* Entering catchup — keep bar full, do not look like a fresh sync */
            if ( data.phase === 'catchup' ) {
                currentPhase = 'catchup';
                $progressFill.css( 'width', '100%' );
                $status.text( kvSync.i18n.catchupShort || 'Retrying skipped pages…' );
            }

            scheduleNext( currentPhase === 'catchup' ? 1000 : 800 );
    }

    /**
     * Classify a failed chunk request as 'timeout' | 'network' | '504' | null.
     * These three get an indefinite 5-minute retry loop instead of the
     * short retry-then-skip behavior used for other errors.
     */
    function classifyTransientError( xhr, textStatus, serverErrorType ) {
        if ( serverErrorType === '504' || ( xhr && xhr.status === 504 ) ) {
            return '504';
        }
        if ( serverErrorType === 'timeout' ) {
            return 'timeout';
        }
        if ( serverErrorType === 'network' ) {
            return 'network';
        }
        if ( textStatus === 'timeout' || ( xhr && xhr.statusText === 'timeout' ) ) {
            return 'timeout';
        }
        if ( xhr && xhr.status === 0 ) {
            return 'network';
        }
        return null;
    }

    function transientErrorLabel( type ) {
        if ( type === '504' ) { return 'Server timeout (504)'; }
        if ( type === 'timeout' ) { return 'Request timed out'; }
        if ( type === 'network' ) { return 'Network issue — could not reach the server'; }
        return 'Temporary error';
    }

    /**
     * Timeout / network / 504 — wait 5 minutes and retry the same page
     * indefinitely (does not count against MAX_RETRIES, never skips).
     */
    /**
     * Best-effort: tell the server about a failure the server-side request
     * never lived long enough to log itself (e.g. Cloudflare returning its
     * own 504 page before kv_ajax_sync_chunk finishes). If the browser is
     * genuinely offline this will also fail to reach the server — that's
     * an inherent limit, not something to retry.
     */
    function reportClientErrorToLog( type, msg ) {
        $.ajax( {
            url     : kvSync.ajaxUrl,
            method  : 'POST',
            data    : {
                action     : 'kv_sync_log_client_error',
                nonce      : kvSync.nonce,
                error_type : type,
                message    : msg || '',
                phase      : currentPhase,
                page       : kvSync.resumePage || 0,
            },
            timeout : 15000,
        } );
        /* Fire-and-forget — no .done()/.fail() handling needed, the retry loop doesn't depend on this. */
    }

    function scheduleTransientRetry( type, msg ) {
        if ( stopRequested ) {
            finish( false );
            return;
        }

        chunkInFlight = false;

        var label = transientErrorLabel( type );
        /* Cloudflare/host error pages can return their whole HTML body as "message" —
         * only show it inline when it's actually short; full text still goes to console. */
        var shortMsg = ( msg && msg.length > 0 && msg.length <= 150 ) ? msg : '';
        var displayLabel = label + ( shortMsg ? ' (' + shortMsg + ')' : '' );

        $status.text( displayLabel + ' — retrying in 5 minutes…' );

        if ( window.console && window.console.warn ) {
            window.console.warn( '[kv-sync] ' + label + ' — will retry in 5 minutes.' + ( msg ? ' Detail: ' + msg : '' ) );
        }

        reportClientErrorToLog( type, msg );

        scheduleNext( TRANSIENT_RETRY_DELAY_MS );
    }

    /**
     * Retry a couple of times, then skip page and continue.
     * Failed pages are retried automatically at the end (catchup phase).
     */
    function maybeRetryOrSkip( msg, isTimeout, errData ) {
        if ( stopRequested ) {
            finish( false );
            return;
        }

        if ( errData && errData.phase ) {
            currentPhase = errData.phase;
        }

        if ( retryCount < MAX_RETRIES ) {
            retryCount += 1;
            var delay = retryDelayMs( retryCount );
            if ( isTimeout ) {
                delay = Math.max( delay, 8000 );
            }

            $status.text(
                ( kvSync.i18n.retrying || 'Chunk failed, retrying…' ) +
                ' (' + retryCount + '/' + MAX_RETRIES + ')' +
                ' — wait ' + Math.round( delay / 1000 ) + 's…'
            );

            scheduleNext( delay );
            return;
        }

        /* Retries exhausted → skip & continue (retry queued for end during main phase) */
        skipAndContinue( msg );
    }

    function skipAndContinue( msg ) {
        if ( stopRequested ) {
            finish( false );
            return;
        }

        chunkInFlight = true;

        var skipPage = kvSync.resumePage || 1;
        if ( currentPhase === 'main' ) {
            $status.text(
                ( kvSync.i18n.skipping || 'Page %d failed — continuing… (will retry at end)' )
                    .replace( '%d', skipPage )
            );
        } else {
            $status.text( kvSync.i18n.catchupShort || 'Retrying skipped pages…' );
        }

        postAjax( {
            action : 'kv_sync_skip',
            nonce  : kvSync.nonce,
            run_id : syncRunId,
        } )
        .done( function ( response ) {
            chunkInFlight = false;
            retryCount    = 0;

            if ( stopRequested ) {
                return;
            }

            if ( ! response || ! response.success ) {
                var skipErrData   = response && response.data;
                var skipErrMsg    = ( skipErrData && skipErrData.message ) || msg || kvSync.i18n.error;
                var skipTransient = classifyTransientError( null, null, skipErrData && skipErrData.error_type );

                if ( skipTransient ) {
                    scheduleTransientRetry( skipTransient, skipErrMsg );
                    return;
                }

                handleError( skipErrMsg );
                return;
            }

            var data = response.data || {};
            if ( data.stale ) {
                return;
            }
            updateProgressFromData( data );

            if ( data.done ) {
                $progressFill.css( 'width', '100%' );
                finish( true, data );
                return;
            }

            if ( data.phase === 'catchup' ) {
                currentPhase = 'catchup';
                $progressFill.css( 'width', '100%' );
                $status.text( kvSync.i18n.catchupShort || 'Retrying skipped pages…' );
            } else {
                setSyncingStatus();
            }

            scheduleNext( 1000 );
        } )
        .fail( function ( xhr, textStatus ) {
            chunkInFlight = false;

            if ( stopRequested || textStatus === 'abort' ) {
                return;
            }

            var skipTransientType = classifyTransientError( xhr, textStatus, null );
            if ( skipTransientType ) {
                scheduleTransientRetry( skipTransientType, extractAjaxError( xhr, textStatus ) );
                return;
            }

            handleError( extractAjaxError( xhr, textStatus ) || msg );
        } );
    }

    function extractAjaxError( xhr, textStatus ) {
        var msg = '';

        if ( xhr && xhr.responseJSON ) {
            if ( xhr.responseJSON.data && xhr.responseJSON.data.message ) {
                msg = xhr.responseJSON.data.message;
            } else if ( xhr.responseJSON.message ) {
                msg = xhr.responseJSON.message;
            }
        }

        if ( ! msg && xhr && xhr.responseText ) {
            var text = String( xhr.responseText ).replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
            if ( text ) {
                msg = text.substring( 0, 300 );
            }
        }

        if ( ! msg ) {
            if ( textStatus === 'timeout' || ( xhr && ( xhr.statusText === 'timeout' || ( xhr.status === 0 && ! xhr.responseText ) ) ) ) {
                msg = kvSync.i18n.timeout || 'Request timed out.';
            } else if ( xhr && xhr.status ) {
                msg = 'HTTP ' + xhr.status + ( xhr.statusText ? ' (' + xhr.statusText + ')' : '' );
            }
        }

        return msg;
    }

    function finish( completed, data ) {
        currentXhr    = null;
        chunkInFlight = false;
        retryCount    = 0;
        stopRequested = false;
        clearRetryTimer();
        stopLogPolling();
        pollLogs();

        $stopBtn.hide().prop( 'disabled', false );

        if ( completed ) {
            currentPhase       = 'main';
            syncRunId          = '';
            kvSync.isResumable = false;
            kvSync.resumePage  = 1;
            showIdleUi();

            if ( data && data.partial ) {
                $status.text( kvSync.i18n.donePartial || kvSync.i18n.done );
            } else {
                $status.text( kvSync.i18n.done );
            }

            if ( data ) {
                if ( data.added   !== undefined ) { $statAdded.text( data.added ); }
                if ( data.updated !== undefined ) { $statUpdated.text( data.updated ); }
                if ( data.total   !== undefined ) { $statTotal.text( data.total ); }
                if ( data.last_sync )             { $statLastSync.text( data.last_sync ); }
            }
        } else {
            kvSync.isResumable = true;
            showResumeUi();
            $status.text( kvSync.i18n.stopped );
        }
    }

    function handleError( msg ) {
        currentXhr    = null;
        chunkInFlight = false;
        clearRetryTimer();
        stopLogPolling();
        kvSync.isResumable = true;
        showResumeUi();
        $status.text( ( msg || kvSync.i18n.error ) );
    }

    /* ── Background image queue panel ── */

    var $queuePending    = $( '#kv-queue-pending' );
    var $queueProcessing = $( '#kv-queue-processing' );
    var $queueDone       = $( '#kv-queue-done' );
    var $queueFailed     = $( '#kv-queue-failed' );
    var $queueStatus     = $( '#kv-queue-status' );
    var $queueProcessBtn = $( '#kv-queue-process-now' );
    var QUEUE_POLL_MS    = 15000;
    var queuePollTimer   = null;

    function renderQueueCounts( counts ) {
        if ( ! counts ) {
            return;
        }
        $queuePending.text( counts.pending );
        $queueProcessing.text( counts.processing );
        $queueDone.text( counts.done );
        $queueFailed.text( counts.failed );
    }

    function pollQueueStatus() {
        $.ajax( {
            url     : kvSync.ajaxUrl,
            method  : 'POST',
            data    : { action : 'kv_image_queue_status', nonce : kvSync.nonce },
            timeout : 30000,
        } )
        .done( function ( response ) {
            if ( response && response.success ) {
                renderQueueCounts( response.data.counts );
            }
        } )
        .fail( function () {
            /* Polling is best-effort — ignore transient failures */
        } );
    }

    if ( $queuePending.length ) {
        pollQueueStatus();
        queuePollTimer = setInterval( pollQueueStatus, QUEUE_POLL_MS );
    }

    $queueProcessBtn.on( 'click', function () {
        $queueProcessBtn.prop( 'disabled', true );
        $queueStatus.text( kvSync.i18n.queueProcessing || 'Processing…' );

        $.ajax( {
            url     : kvSync.ajaxUrl,
            method  : 'POST',
            data    : { action : 'kv_image_queue_process_now', nonce : kvSync.nonce },
            timeout : 60000,
        } )
        .done( function ( response ) {
            if ( response && response.success ) {
                renderQueueCounts( response.data.counts );
                var r = response.data.result || {};
                $queueStatus.text(
                    'Processed ' + ( r.processed || 0 ) + ' (' + ( r.succeeded || 0 ) + ' ok, ' + ( r.failed || 0 ) + ' failed).'
                );
            } else {
                $queueStatus.text( extractAjaxError( null, '' ) || 'Error processing queue.' );
            }
        } )
        .fail( function ( xhr, textStatus ) {
            $queueStatus.text( extractAjaxError( xhr, textStatus ) || 'Error processing queue.' );
        } )
        .always( function () {
            $queueProcessBtn.prop( 'disabled', false );
        } );
    } );

} )( jQuery );
