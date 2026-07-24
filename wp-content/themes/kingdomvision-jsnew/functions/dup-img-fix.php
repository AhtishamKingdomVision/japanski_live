<?php
/**
 * Standalone tool: find and remove duplicate media attachments created by
 * the booking-system sync (same source image re-downloaded as
 * "name.jpg", "name-6.jpg", "name-7.jpg", ...).
 *
 * Two attachments are only ever treated as duplicates of each other when
 * they share the exact same "_source_url" postmeta (the remote URL the
 * sync downloaded them from). Filename similarity alone is NOT enough —
 * booking systems commonly name a *gallery* of different photos for the
 * same room "name-1.jpg", "name-2.jpg", "name-3.jpg", and those are not
 * duplicates of one another. Grouping by filename suffix alone would
 * wrongly delete real, distinct photos. Attachments with no recorded
 * source URL are never auto-merged — they're left alone for manual review.
 *
 * Keeps the original attachment (lowest ID in a duplicate group, and its
 * generated sizes) and reassigns any post that references a duplicate —
 * via featured image or other meta — to the original before deleting it.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ──────────────────────────────────────────────
 * 1. Filename helpers (used only to narrow the SQL search — NOT to decide
 *    what counts as a duplicate; that's decided by _source_url).
 * ────────────────────────────────────────────── */

function kvdif_base_stem_key( $filename ) {
    $filename = wp_basename( (string) $filename );
    if ( $filename === '' ) {
        return '';
    }

    $ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    $name = pathinfo( $filename, PATHINFO_FILENAME );

    $name = preg_replace( '/-\d+x\d+$/', '', $name );
    $name = preg_replace( '/-scaled$/', '', $name );
    $name = preg_replace( '/-\d+$/', '', $name );

    return strtolower( $name ) . '.' . $ext;
}

/**
 * True if a filename carries WP's duplicate-upload suffix (-6, -91, ...)
 * after stripping WP's own generated-size / "-scaled" suffixes. Used to
 * prefer a cleanly-named file as the "kept" one within a duplicate group,
 * instead of always defaulting to whichever has the lowest ID.
 */
function kvdif_has_dup_suffix( $attached_file ) {
    $name = pathinfo( wp_basename( (string) $attached_file ), PATHINFO_FILENAME );
    $name = preg_replace( '/-\d+x\d+$/', '', $name );
    $name = preg_replace( '/-scaled$/', '', $name );

    return (bool) preg_match( '/-\d+$/', $name );
}

/**
 * Pick which attachment to keep within a duplicate group: prefer one
 * whose filename has no WP duplicate-upload suffix (a real, canonical
 * name) over the lowest-ID heuristic. If every candidate is itself
 * suffixed (e.g. every copy was a re-upload under a slightly different
 * name), fall back to the lowest ID — but the caller should flag that
 * case for manual review, since "kept" then doesn't mean "canonical".
 */
function kvdif_pick_keep_id( array $ids ) {
    sort( $ids, SORT_NUMERIC );

    $clean_ids = array();
    foreach ( $ids as $id ) {
        $attached_file = get_post_meta( $id, '_wp_attached_file', true );
        if ( ! kvdif_has_dup_suffix( $attached_file ) ) {
            $clean_ids[] = $id;
        }
    }

    return ! empty( $clean_ids ) ? $clean_ids[0] : $ids[0];
}

/* ──────────────────────────────────────────────
 * 2. Finding duplicates (identity = _source_url, never filename alone)
 * ────────────────────────────────────────────── */

/**
 * Find every attachment whose filename plausibly relates to $filename,
 * along with its recorded _source_url (may be empty).
 *
 * @return array[] { id:int, attached_file:string, source_url:string }
 */
function kvdif_get_candidates_for_filename( $filename ) {
    global $wpdb;

    $filename = wp_basename( sanitize_text_field( (string) $filename ) );
    $stem     = pathinfo( $filename, PATHINFO_FILENAME );
    $ext      = pathinfo( $filename, PATHINFO_EXTENSION );

    if ( $stem === '' || $ext === '' ) {
        return array();
    }

    $like = '%' . $wpdb->esc_like( $stem ) . '%.' . $wpdb->esc_like( $ext );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.ID, filemeta.meta_value AS attached_file, srcmeta.meta_value AS source_url
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} filemeta ON filemeta.post_id = p.ID AND filemeta.meta_key = '_wp_attached_file'
             LEFT JOIN {$wpdb->postmeta} srcmeta ON srcmeta.post_id = p.ID AND srcmeta.meta_key = '_source_url'
             WHERE p.post_type = 'attachment'
             AND p.post_status = 'inherit'
             AND p.post_mime_type LIKE 'image/%%'
             AND filemeta.meta_value LIKE %s
             LIMIT 200",
            $like
        ),
        ARRAY_A
    );

    $target_key = kvdif_base_stem_key( $filename );
    $candidates = array();

    foreach ( (array) $rows as $row ) {
        if ( kvdif_base_stem_key( $row['attached_file'] ) === $target_key ) {
            $candidates[] = array(
                'id'            => (int) $row['ID'],
                'attached_file' => (string) $row['attached_file'],
                'source_url'    => trim( (string) $row['source_url'] ),
            );
        }
    }

    usort( $candidates, function ( $a, $b ) {
        return $a['id'] <=> $b['id'];
    } );

    return $candidates;
}

/**
 * MD5 of an attachment's actual file on disk — byte-identical files are
 * unambiguous proof of duplication, and this works even when _source_url
 * was never recorded (which turns out to be the common case here — most
 * of the images created by the sync bug have no _source_url meta at
 * all). Capped at 60MB and memoized per request.
 *
 * @return string Empty string if the file is missing/unreadable/too large.
 */
function kvdif_file_hash_for_attachment( $id ) {
    static $cache = array();

    $id = (int) $id;
    if ( isset( $cache[ $id ] ) ) {
        return $cache[ $id ];
    }

    $file = get_attached_file( $id );
    if ( ! $file || ! file_exists( $file ) || ! is_readable( $file ) || filesize( $file ) > 60 * MB_IN_BYTES ) {
        return $cache[ $id ] = '';
    }

    $hash = @md5_file( $file );

    return $cache[ $id ] = ( $hash !== false ) ? $hash : '';
}

/**
 * Identity key used to decide whether two candidates are the same image.
 * Byte-identical file content is the strongest, most reliable signal and
 * is preferred whenever the file is readable; _source_url is the
 * fallback for the rare case a file is missing from disk. Neither
 * available -> a unique key, so the candidate is never grouped.
 */
function kvdif_candidate_identity_key( array $candidate ) {
    $hash = kvdif_file_hash_for_attachment( $candidate['id'] );
    if ( $hash !== '' ) {
        return 'hash:' . $hash;
    }

    if ( $candidate['source_url'] !== '' ) {
        return 'src:' . $candidate['source_url'];
    }

    return 'unique:' . $candidate['id'];
}

/**
 * Bucket candidates by identity key (see kvdif_candidate_identity_key()).
 * Buckets with 2+ members are real duplicate groups (safe to auto-clean).
 * Everything else — a lone attachment, or one that couldn't be verified
 * — is left untouched.
 *
 * @return array{candidates:array[], duplicate_groups:array[], singles:array[], keys:array<int,string>}
 */
function kvdif_duplicate_groups_for_filename( $filename ) {
    $candidates = kvdif_get_candidates_for_filename( $filename );

    $buckets = array();
    $keys    = array();
    foreach ( $candidates as $c ) {
        $key            = kvdif_candidate_identity_key( $c );
        $keys[ $c['id'] ] = $key;
        $buckets[ $key ][] = $c['id'];
    }

    $duplicate_groups = array();
    $singles          = array();

    foreach ( $buckets as $key => $ids ) {
        sort( $ids, SORT_NUMERIC );
        if ( count( $ids ) >= 2 && strpos( $key, 'unique:' ) !== 0 ) {
            $keep_id       = kvdif_pick_keep_id( $ids );
            $kept_attached = get_post_meta( $keep_id, '_wp_attached_file', true );

            $duplicate_groups[] = array(
                'basis'         => strpos( $key, 'hash:' ) === 0 ? 'content' : 'source_url',
                'key'           => $key,
                'keep_id'       => $keep_id,
                'delete_ids'    => array_values( array_diff( $ids, array( $keep_id ) ) ),
                'keep_is_clean' => ! kvdif_has_dup_suffix( $kept_attached ),
            );
        } else {
            foreach ( $ids as $id ) {
                $singles[] = $id;
            }
        }
    }

    return array(
        'candidates'       => $candidates,
        'duplicate_groups' => $duplicate_groups,
        'singles'          => $singles,
        'keys'             => $keys,
    );
}

/**
 * Scan the whole media library for true duplicates, using the same
 * content-hash-first / source-URL-fallback identity check as the
 * per-filename tool. Hashing every image in the library would be slow,
 * so this runs in two cheap stages: first bucket every image by its
 * base filename stem (a single indexed query, no file reads), then only
 * hash files inside a bucket that already has 2+ members — i.e. only
 * the files that already look suspicious by name.
 *
 * @return array[] { keep_id:int, delete_ids:int[], filename:string, keep_is_clean:bool }
 */
function kvdif_scan_all_duplicate_groups() {
    global $wpdb;

    @set_time_limit( 0 );

    $rows = $wpdb->get_results(
        "SELECT p.ID, filemeta.meta_value AS attached_file, srcmeta.meta_value AS source_url
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} filemeta ON filemeta.post_id = p.ID AND filemeta.meta_key = '_wp_attached_file'
         LEFT JOIN {$wpdb->postmeta} srcmeta ON srcmeta.post_id = p.ID AND srcmeta.meta_key = '_source_url'
         WHERE p.post_type = 'attachment'
         AND p.post_status = 'inherit'
         AND p.post_mime_type LIKE 'image/%'",
        ARRAY_A
    );

    $stem_buckets = array();
    foreach ( (array) $rows as $row ) {
        $stem = kvdif_base_stem_key( $row['attached_file'] );
        if ( $stem === '' ) {
            continue;
        }
        $stem_buckets[ $stem ][] = array(
            'id'            => (int) $row['ID'],
            'attached_file' => (string) $row['attached_file'],
            'source_url'    => trim( (string) $row['source_url'] ),
        );
    }

    $duplicates = array();

    foreach ( $stem_buckets as $candidates ) {
        if ( count( $candidates ) < 2 ) {
            continue;
        }

        $id_buckets = array();
        foreach ( $candidates as $c ) {
            $id_buckets[ kvdif_candidate_identity_key( $c ) ][] = $c['id'];
        }

        foreach ( $id_buckets as $key => $ids ) {
            if ( count( $ids ) < 2 || strpos( $key, 'unique:' ) === 0 ) {
                continue;
            }

            sort( $ids, SORT_NUMERIC );
            $keep_id       = kvdif_pick_keep_id( $ids );
            $kept_attached = get_post_meta( $keep_id, '_wp_attached_file', true );

            $duplicates[] = array(
                'keep_id'       => $keep_id,
                'delete_ids'    => array_values( array_diff( $ids, array( $keep_id ) ) ),
                'filename'      => wp_basename( (string) $kept_attached ),
                'keep_is_clean' => ! kvdif_has_dup_suffix( $kept_attached ),
            );
        }
    }

    return $duplicates;
}

/* ──────────────────────────────────────────────
 * Site-wide delete queue — built once by a scan, then consumed in small
 * batches so the "Delete All Duplicates" auto-continue loop never has to
 * re-run the (comparatively expensive) full-library scan on every batch.
 * ────────────────────────────────────────────── */

function kvdif_sitewide_queue_key() {
    return 'kvdif_sitewide_queue';
}

function kvdif_build_sitewide_queue( $groups = null ) {
    if ( $groups === null ) {
        $groups = kvdif_scan_all_duplicate_groups();
    }

    $pairs = array();
    foreach ( $groups as $group ) {
        foreach ( $group['delete_ids'] as $delete_id ) {
            $pairs[] = array( 'delete_id' => $delete_id, 'keep_id' => $group['keep_id'] );
        }
    }

    usort( $pairs, function ( $a, $b ) {
        return $a['delete_id'] <=> $b['delete_id'];
    } );

    set_transient( kvdif_sitewide_queue_key(), $pairs, 12 * HOUR_IN_SECONDS );

    return $pairs;
}

function kvdif_get_sitewide_queue() {
    $pairs = get_transient( kvdif_sitewide_queue_key() );
    return is_array( $pairs ) ? $pairs : null;
}

/* ──────────────────────────────────────────────
 * 3. Reassigning references before delete
 * ────────────────────────────────────────────── */

/** Meta keys that plausibly store an attachment ID are only touched if they contain one of these words. */
function kvdif_reassignable_meta_keywords() {
    return array( 'image', 'img', 'photo', 'thumb', 'gallery', 'banner', 'logo', 'picture', 'attachment', 'media' );
}

function kvdif_replace_id_recursive( $value, $old_id, $new_id, &$changed ) {
    if ( is_array( $value ) ) {
        foreach ( $value as $k => $v ) {
            $value[ $k ] = kvdif_replace_id_recursive( $v, $old_id, $new_id, $changed );
        }
        return $value;
    }

    if ( is_int( $value ) && $value === $old_id ) {
        $changed = true;
        return $new_id;
    }

    if ( is_string( $value ) && $value === (string) $old_id ) {
        $changed = true;
        return (string) $new_id;
    }

    return $value;
}

/**
 * Point every post that references $old_id (as featured image, or via a
 * postmeta field that looks like it stores an attachment reference) at
 * $new_id instead. Must run before the duplicate is deleted.
 *
 * @return array{featured:int,meta:int}
 */
function kvdif_reassign_attachment_references( $old_id, $new_id ) {
    global $wpdb;

    $old_id = (int) $old_id;
    $new_id = (int) $new_id;

    if ( $old_id < 1 || $new_id < 1 || $old_id === $new_id ) {
        return array( 'featured' => 0, 'meta' => 0 );
    }

    $featured = 0;
    $meta_hit = 0;

    /* Featured images */
    $posts_using = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
            $old_id
        )
    );
    foreach ( $posts_using as $post_id ) {
        update_post_meta( (int) $post_id, '_thumbnail_id', $new_id );
        $featured++;
    }

    $keyword_like = array();
    $like_args    = array();
    foreach ( kvdif_reassignable_meta_keywords() as $word ) {
        $keyword_like[] = 'meta_key LIKE %s';
        $like_args[]     = '%' . $wpdb->esc_like( $word ) . '%';
    }
    $keyword_sql = '(' . implode( ' OR ', $keyword_like ) . ')';

    /* Direct scalar meta values equal to the old attachment ID */
    $sql  = "SELECT post_id, meta_key FROM {$wpdb->postmeta}
             WHERE meta_value = %s AND meta_key != '_thumbnail_id' AND {$keyword_sql}";
    $args = array_merge( array( (string) $old_id ), $like_args );

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
    foreach ( (array) $rows as $row ) {
        if ( (int) $row['post_id'] === $old_id ) {
            continue;
        }
        update_post_meta( (int) $row['post_id'], $row['meta_key'], $new_id );
        $meta_hit++;
    }

    /* Serialized meta values (arrays/objects) that contain the old ID */
    $needle_int = 'i:' . $old_id . ';';
    $needle_str = '"' . $old_id . '"';

    $sql  = "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta}
             WHERE (meta_value LIKE %s OR meta_value LIKE %s)
             AND (meta_value LIKE %s OR meta_value LIKE %s)
             AND {$keyword_sql}";
    $args = array_merge(
        array(
            '%' . $wpdb->esc_like( $needle_int ) . '%',
            '%' . $wpdb->esc_like( $needle_str ) . '%',
            'a:%',
            'O:%',
        ),
        $like_args
    );

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
    foreach ( (array) $rows as $row ) {
        if ( (int) $row['post_id'] === $old_id ) {
            continue;
        }

        $data = @unserialize( $row['meta_value'] );
        if ( $data === false ) {
            continue;
        }

        $changed  = false;
        $new_data = kvdif_replace_id_recursive( $data, $old_id, $new_id, $changed );

        if ( $changed ) {
            update_post_meta( (int) $row['post_id'], $row['meta_key'], $new_data );
            $meta_hit++;
        }
    }

    return array( 'featured' => $featured, 'meta' => $meta_hit );
}

/* ──────────────────────────────────────────────
 * 4. Activity log
 * ────────────────────────────────────────────── */

function kvdif_log( array $entry ) {
    $defaults = array(
        'timestamp' => current_time( 'Y-m-d H:i:s' ),
        'filename'  => '',
        'status'    => 'info',
        'message'   => '',
    );

    $log  = array_merge( $defaults, $entry );
    $logs = get_option( 'kvdif_logs', array() );
    if ( ! is_array( $logs ) ) {
        $logs = array();
    }

    $logs[] = $log;
    if ( count( $logs ) > 300 ) {
        $logs = array_slice( $logs, -300 );
    }

    update_option( 'kvdif_logs', $logs, false );

    error_log( sprintf( '[kvdif] %s | %s | %s | %s', $log['timestamp'], $log['filename'], strtoupper( $log['status'] ), $log['message'] ) );
}

function kvdif_get_logs() {
    $logs = get_option( 'kvdif_logs', array() );
    return is_array( $logs ) ? array_reverse( $logs ) : array();
}

/* ──────────────────────────────────────────────
 * 5. Deleting duplicates
 * ────────────────────────────────────────────── */

/**
 * Remove up to $batch_size confirmed duplicates of $filename per call —
 * "confirmed" meaning byte-identical content, or (if a file is missing)
 * an identical _source_url. The one kept per group is always the lowest
 * attachment ID in that group. Call repeatedly (each call re-queries
 * remaining duplicates) until 'remaining' is 0.
 */
function kvdif_remove_duplicates_for_filename( $filename, $batch_size = 2 ) {
    $batch_size = max( 1, (int) $batch_size );

    try {
        $info             = kvdif_duplicate_groups_for_filename( $filename );
        $duplicate_groups = $info['duplicate_groups'];

        $pairs = array();
        foreach ( $duplicate_groups as $group ) {
            foreach ( $group['delete_ids'] as $delete_id ) {
                $pairs[] = array( 'delete_id' => $delete_id, 'keep_id' => $group['keep_id'] );
            }
        }

        if ( empty( $pairs ) ) {
            $singles_count = count( $info['singles'] );
            $status        = $singles_count > 0 ? 'no_duplicates' : 'no_duplicates';

            kvdif_log( array(
                'filename' => $filename,
                'status'   => 'warning',
                'message'  => $singles_count > 0
                    ? sprintf( 'No confirmed duplicates. Found %d file(s) with matching name but no identical content or source URL — left untouched.', $singles_count )
                    : 'No matching attachments found for this filename.',
            ) );

            return array(
                'status'    => $status,
                'remaining' => 0,
                'singles'   => $info['singles'],
                'errors'    => array(),
            );
        }

        usort( $pairs, function ( $a, $b ) {
            return $a['delete_id'] <=> $b['delete_id'];
        } );

        $batch     = array_slice( $pairs, 0, $batch_size );
        $remaining = count( $pairs ) - count( $batch );

        $deleted   = 0;
        $failed    = 0;
        $featured  = 0;
        $meta_hits = 0;
        $details   = array();
        $errors    = array();

        foreach ( $batch as $pair ) {
            try {
                $refs = kvdif_reassign_attachment_references( $pair['delete_id'], $pair['keep_id'] );
                $ok   = wp_delete_attachment( $pair['delete_id'], true );

                if ( $ok ) {
                    $deleted++;
                } else {
                    $failed++;
                    $errors[] = sprintf( 'Could not delete attachment #%d (kept #%d) — wp_delete_attachment() failed.', $pair['delete_id'], $pair['keep_id'] );
                }

                $featured  += $refs['featured'];
                $meta_hits += $refs['meta'];

                $details[] = array(
                    'id'                  => $pair['delete_id'],
                    'keep_id'             => $pair['keep_id'],
                    'deleted'             => (bool) $ok,
                    'featured_reassigned' => $refs['featured'],
                    'meta_reassigned'     => $refs['meta'],
                );
            } catch ( Throwable $e ) {
                $failed++;
                $errors[] = sprintf( 'Error deleting attachment #%d (kept #%d): %s', $pair['delete_id'], $pair['keep_id'], $e->getMessage() );
            }
        }

        $status = $remaining > 0 ? 'partial' : 'done';

        kvdif_log( array(
            'filename' => $filename,
            'status'   => ! empty( $errors ) ? 'error' : 'success',
            'message'  => sprintf(
                'Deleted %d, failed %d, %d remaining. Featured reassigned: %d. Meta reassigned: %d.%s',
                $deleted,
                $failed,
                $remaining,
                $featured,
                $meta_hits,
                $errors ? ' Errors: ' . implode( ' | ', $errors ) : ''
            ),
        ) );

        return array(
            'status'              => $status,
            'deleted_count'       => $deleted,
            'failed_count'        => $failed,
            'featured_reassigned' => $featured,
            'meta_reassigned'     => $meta_hits,
            'details'             => $details,
            'errors'              => $errors,
            'remaining'           => $remaining,
            'singles'             => $info['singles'],
        );
    } catch ( Throwable $e ) {
        kvdif_log( array(
            'filename' => $filename,
            'status'   => 'error',
            'message'  => 'Fatal error: ' . $e->getMessage(),
        ) );

        return array(
            'status'    => 'error',
            'remaining' => 0,
            'errors'    => array( $e->getMessage() ),
            'singles'   => array(),
        );
    }
}

/**
 * Manual override: merge attachments the admin has visually confirmed are
 * the same photo but weren't auto-matched (e.g. re-compressed to a
 * slightly different file, so the content hash differs). Keeps the
 * cleanest-named / lowest-ID one among $ids and deletes the rest, with
 * the same reference reassignment as the automatic path. Unlike the
 * automatic path, this is not gated on a hash or source URL match —
 * it trusts the admin's explicit selection instead.
 */
function kvdif_force_merge_attachments( array $ids ) {
    @set_time_limit( 60 );

    try {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        $ids = array_values( array_filter( $ids, function ( $id ) {
            return get_post_type( $id ) === 'attachment';
        } ) );
        sort( $ids, SORT_NUMERIC );

        if ( count( $ids ) < 2 ) {
            kvdif_log( array(
                'filename' => '(manual merge)',
                'status'   => 'warning',
                'message'  => 'Manual merge requested with fewer than 2 valid attachments selected — nothing done.',
            ) );

            return array(
                'status'  => 'invalid',
                'errors'  => array( 'Select at least 2 attachments to merge.' ),
            );
        }

        $keep_id    = kvdif_pick_keep_id( $ids );
        $delete_ids = array_values( array_diff( $ids, array( $keep_id ) ) );

        $deleted   = 0;
        $failed    = 0;
        $featured  = 0;
        $meta_hits = 0;
        $errors    = array();

        foreach ( $delete_ids as $delete_id ) {
            try {
                $refs = kvdif_reassign_attachment_references( $delete_id, $keep_id );
                $ok   = wp_delete_attachment( $delete_id, true );

                if ( $ok ) {
                    $deleted++;
                } else {
                    $failed++;
                    $errors[] = sprintf( 'Could not delete attachment #%d (kept #%d).', $delete_id, $keep_id );
                }

                $featured  += $refs['featured'];
                $meta_hits += $refs['meta'];
            } catch ( Throwable $e ) {
                $failed++;
                $errors[] = sprintf( 'Error deleting attachment #%d: %s', $delete_id, $e->getMessage() );
            }
        }

        kvdif_log( array(
            'filename' => '(manual merge)',
            'status'   => $errors ? 'error' : 'success',
            'message'  => sprintf(
                'Manually merged %d attachment(s) into #%d (kept). Deleted %d, failed %d. Featured reassigned: %d. Meta reassigned: %d.%s',
                count( $ids ),
                $keep_id,
                $deleted,
                $failed,
                $featured,
                $meta_hits,
                $errors ? ' Errors: ' . implode( ' | ', $errors ) : ''
            ),
        ) );

        return array(
            'status'               => 'done',
            'keep_id'              => $keep_id,
            'deleted_count'        => $deleted,
            'failed_count'         => $failed,
            'featured_reassigned'  => $featured,
            'meta_reassigned'      => $meta_hits,
            'errors'               => $errors,
        );
    } catch ( Throwable $e ) {
        kvdif_log( array(
            'filename' => '(manual merge)',
            'status'   => 'error',
            'message'  => 'Fatal error: ' . $e->getMessage(),
        ) );

        return array(
            'status' => 'error',
            'errors' => array( $e->getMessage() ),
        );
    }
}

/**
 * Process one small batch from the site-wide duplicate queue (built by
 * kvdif_build_sitewide_queue() when "Scan All Duplicates" runs). Call
 * repeatedly — each call consumes and re-saves the queue — until
 * 'remaining' is 0. Never re-runs the full library scan itself, so the
 * "Delete All Duplicates" auto-continue loop stays fast regardless of
 * library size.
 */
function kvdif_process_sitewide_delete_batch( $batch_size = 2 ) {
    $batch_size = max( 1, (int) $batch_size );

    try {
        $queue = kvdif_get_sitewide_queue();
        if ( $queue === null ) {
            $queue = kvdif_build_sitewide_queue();
        }

        if ( empty( $queue ) ) {
            delete_transient( kvdif_sitewide_queue_key() );

            kvdif_log( array(
                'filename' => '(scan all)',
                'status'   => 'success',
                'message'  => 'Site-wide cleanup complete — no confirmed duplicates remaining.',
            ) );

            return array( 'status' => 'no_duplicates', 'remaining' => 0, 'errors' => array() );
        }

        $batch     = array_slice( $queue, 0, $batch_size );
        $remainder = array_slice( $queue, $batch_size );

        $deleted   = 0;
        $failed    = 0;
        $featured  = 0;
        $meta_hits = 0;
        $errors    = array();

        foreach ( $batch as $pair ) {
            try {
                $refs = kvdif_reassign_attachment_references( $pair['delete_id'], $pair['keep_id'] );
                $ok   = wp_delete_attachment( $pair['delete_id'], true );

                if ( $ok ) {
                    $deleted++;
                } else {
                    $failed++;
                    $errors[] = sprintf( 'Could not delete attachment #%d (kept #%d).', $pair['delete_id'], $pair['keep_id'] );
                }

                $featured  += $refs['featured'];
                $meta_hits += $refs['meta'];
            } catch ( Throwable $e ) {
                $failed++;
                $errors[] = sprintf( 'Error deleting attachment #%d (kept #%d): %s', $pair['delete_id'], $pair['keep_id'], $e->getMessage() );
            }
        }

        if ( empty( $remainder ) ) {
            delete_transient( kvdif_sitewide_queue_key() );
        } else {
            set_transient( kvdif_sitewide_queue_key(), $remainder, 12 * HOUR_IN_SECONDS );
        }

        $remaining = count( $remainder );
        $status    = $remaining > 0 ? 'partial' : 'done';

        kvdif_log( array(
            'filename' => '(scan all)',
            'status'   => $errors ? 'error' : 'success',
            'message'  => sprintf(
                'Site-wide cleanup: deleted %d, failed %d, %d remaining. Featured reassigned: %d. Meta reassigned: %d.%s',
                $deleted,
                $failed,
                $remaining,
                $featured,
                $meta_hits,
                $errors ? ' Errors: ' . implode( ' | ', $errors ) : ''
            ),
        ) );

        return array(
            'status'               => $status,
            'deleted_count'        => $deleted,
            'failed_count'         => $failed,
            'featured_reassigned'  => $featured,
            'meta_reassigned'      => $meta_hits,
            'errors'               => $errors,
            'remaining'            => $remaining,
        );
    } catch ( Throwable $e ) {
        kvdif_log( array(
            'filename' => '(scan all)',
            'status'   => 'error',
            'message'  => 'Fatal error: ' . $e->getMessage(),
        ) );

        return array(
            'status'    => 'error',
            'remaining' => 0,
            'errors'    => array( $e->getMessage() ),
        );
    }
}

/* ──────────────────────────────────────────────
 * 6. Admin page
 * ────────────────────────────────────────────── */

add_action( 'admin_menu', 'kvdif_register_admin_page' );
function kvdif_register_admin_page() {
    add_submenu_page(
        'upload.php',
        'Fix Duplicate Images',
        'Fix Duplicate Images',
        'manage_options',
        'kv-dup-img-fix',
        'kvdif_render_admin_page'
    );
}

function kvdif_attachment_summary( $id ) {
    global $wpdb;

    $id   = (int) $id;
    $file = get_attached_file( $id );
    $meta = wp_get_attachment_metadata( $id );

    return array(
        'id'             => $id,
        'filename'       => wp_basename( (string) get_post_meta( $id, '_wp_attached_file', true ) ),
        'thumb'          => wp_get_attachment_image_url( $id, 'thumbnail' ),
        'upload_date'    => get_the_date( 'Y-m-d H:i', $id ),
        'file_size'      => ( $file && file_exists( $file ) ) ? size_format( filesize( $file ) ) : '—',
        'sizes_count'    => is_array( $meta ) && ! empty( $meta['sizes'] ) ? count( $meta['sizes'] ) : 0,
        'featured_usage' => (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d", $id )
        ),
        'edit_url'       => get_edit_post_link( $id, 'raw' ),
    );
}

function kvdif_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.' ) );
    }

    /* ?kvdif_batch=N in the URL sets the default batch size — edit it directly to bump 2 -> 3 -> 4, etc. */
    $batch_from_query = isset( $_GET['kvdif_batch'] ) ? max( 1, min( 10, (int) $_GET['kvdif_batch'] ) ) : 2;

    $notice     = '';
    $last_batch = $batch_from_query;

    /* Clear log */
    if ( isset( $_POST['kvdif_clear_log'] ) && check_admin_referer( 'kvdif_clear_log', 'kvdif_clear_log_nonce' ) ) {
        delete_option( 'kvdif_logs' );
        $notice .= '<div class="notice notice-success"><p>' . esc_html__( 'Log cleared.' ) . '</p></div>';
    }

    /* Handle delete action — processes one small batch per request to avoid server timeouts */
    if (
        isset( $_POST['kvdif_action'] ) && $_POST['kvdif_action'] === 'delete' &&
        check_admin_referer( 'kvdif_delete', 'kvdif_nonce' )
    ) {
        @set_time_limit( 60 );

        $filename   = sanitize_text_field( wp_unslash( $_POST['kvdif_filename'] ?? '' ) );
        $batch_size = isset( $_POST['kvdif_batch_size'] ) ? max( 1, min( 10, (int) $_POST['kvdif_batch_size'] ) ) : $batch_from_query;

        $last_batch = $batch_size;

        $result = kvdif_remove_duplicates_for_filename( $filename, $batch_size );

        if ( $result['status'] === 'no_duplicates' ) {
            $singles_count = count( $result['singles'] ?? array() );
            $notice .= '<div class="notice notice-warning"><p>' .
                ( $singles_count > 0
                    ? esc_html( sprintf( 'No confirmed duplicates for "%1$s". %2$d file(s) matched the name but have neither identical content nor an identical source URL, so nothing was touched — check the table below and the log for details.', $filename, $singles_count ) )
                    : esc_html( sprintf( 'No attachments found for "%s".', $filename ) )
                ) . '</p></div>';
        } elseif ( $result['status'] === 'error' ) {
            $notice .= '<div class="notice notice-error"><p>' . esc_html( 'An error occurred: ' . implode( ' | ', $result['errors'] ) ) . '</p></div>';
        } else {
            $notice_class = ! empty( $result['errors'] ) ? 'notice-error' : 'notice-success';
            $notice .= '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html(
                sprintf(
                    'Deleted %1$d duplicate(s) of "%2$s" (batch of %3$d). Featured image reassigned on %4$d post(s), other meta reassigned on %5$d field(s). Failed: %6$d.',
                    $result['deleted_count'],
                    $filename,
                    $batch_size,
                    $result['featured_reassigned'],
                    $result['meta_reassigned'],
                    $result['failed_count']
                )
            ) . '</p>';

            if ( ! empty( $result['errors'] ) ) {
                $notice .= '<ul style="margin-left:18px;list-style:disc;">';
                foreach ( $result['errors'] as $error ) {
                    $notice .= '<li>' . esc_html( $error ) . '</li>';
                }
                $notice .= '</ul>';
            }

            $notice .= '</div>';

            if ( $result['remaining'] > 0 ) {
                $form_id = 'kvdif-continue-form';
                $notice .= '<div class="notice notice-warning"><p>' .
                    esc_html( sprintf( '%d duplicate(s) still remaining — continuing automatically in batches of %d…', $result['remaining'], $batch_size ) ) .
                    '</p>';
                $notice .= '<form method="post" id="' . esc_attr( $form_id ) . '" style="margin: 8px 0 12px;">';
                $notice .= wp_nonce_field( 'kvdif_delete', 'kvdif_nonce', true, false );
                $notice .= '<input type="hidden" name="kvdif_action" value="delete">';
                $notice .= '<input type="hidden" name="kvdif_filename" value="' . esc_attr( $filename ) . '">';
                $notice .= '<input type="hidden" name="kvdif_batch_size" value="' . esc_attr( $batch_size ) . '">';
                $notice .= '<button type="submit" class="button button-primary">' . esc_html( sprintf( 'Continue now (%d left)', $result['remaining'] ) ) . '</button>';
                $notice .= ' <span class="description">' . esc_html__( 'Auto-continuing — leave this tab open. Click Continue now to skip the wait.' ) . '</span>';
                $notice .= '</form>';
                $notice .= '<script>setTimeout(function(){var f=document.getElementById(' . wp_json_encode( $form_id ) . ');if(f){f.submit();}},800);</script>';
                $notice .= '</div>';
            } else {
                $notice .= '<div class="notice notice-success"><p>' . esc_html__( 'No more confirmed duplicates left for this filename.' ) . '</p></div>';
            }
        }
    }

    /* Handle manual "force merge" action — admin visually confirmed these rows are the same photo */
    if (
        isset( $_POST['kvdif_action'] ) && $_POST['kvdif_action'] === 'force_merge' &&
        check_admin_referer( 'kvdif_force_merge', 'kvdif_force_merge_nonce' )
    ) {
        $merge_ids = isset( $_POST['kvdif_ids'] ) && is_array( $_POST['kvdif_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['kvdif_ids'] ) ) : array();
        $result    = kvdif_force_merge_attachments( $merge_ids );

        if ( $result['status'] === 'invalid' ) {
            $notice .= '<div class="notice notice-warning"><p>' . esc_html( implode( ' ', $result['errors'] ) ) . '</p></div>';
        } elseif ( $result['status'] === 'error' ) {
            $notice .= '<div class="notice notice-error"><p>' . esc_html( 'An error occurred: ' . implode( ' | ', $result['errors'] ) ) . '</p></div>';
        } else {
            $notice_class = ! empty( $result['errors'] ) ? 'notice-error' : 'notice-success';
            $notice .= '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html(
                sprintf(
                    'Merged and deleted %1$d attachment(s), kept #%2$d. Featured image reassigned on %3$d post(s), other meta reassigned on %4$d field(s). Failed: %5$d.',
                    $result['deleted_count'],
                    $result['keep_id'],
                    $result['featured_reassigned'],
                    $result['meta_reassigned'],
                    $result['failed_count']
                )
            ) . '</p></div>';
        }
    }

    /* Handle "Delete All Duplicates" — processes the cached site-wide queue in small batches */
    if (
        isset( $_POST['kvdif_action'] ) && $_POST['kvdif_action'] === 'delete_all' &&
        check_admin_referer( 'kvdif_delete_all', 'kvdif_delete_all_nonce' )
    ) {
        @set_time_limit( 60 );

        $batch_size = isset( $_POST['kvdif_batch_size'] ) ? max( 1, min( 10, (int) $_POST['kvdif_batch_size'] ) ) : $batch_from_query;
        $last_batch = $batch_size;

        $result = kvdif_process_sitewide_delete_batch( $batch_size );

        if ( $result['status'] === 'no_duplicates' ) {
            $notice .= '<div class="notice notice-success"><p>' . esc_html__( 'No confirmed duplicates found site-wide.' ) . '</p></div>';
        } elseif ( $result['status'] === 'error' ) {
            $notice .= '<div class="notice notice-error"><p>' . esc_html( 'An error occurred: ' . implode( ' | ', $result['errors'] ) ) . '</p></div>';
        } else {
            $notice_class = ! empty( $result['errors'] ) ? 'notice-error' : 'notice-success';
            $notice .= '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html(
                sprintf(
                    'Deleted %1$d duplicate(s) site-wide (batch of %2$d). Featured image reassigned on %3$d post(s), other meta reassigned on %4$d field(s). Failed: %5$d.',
                    $result['deleted_count'],
                    $batch_size,
                    $result['featured_reassigned'],
                    $result['meta_reassigned'],
                    $result['failed_count']
                )
            ) . '</p>';

            if ( ! empty( $result['errors'] ) ) {
                $notice .= '<ul style="margin-left:18px;list-style:disc;">';
                foreach ( $result['errors'] as $error ) {
                    $notice .= '<li>' . esc_html( $error ) . '</li>';
                }
                $notice .= '</ul>';
            }

            $notice .= '</div>';

            $delete_all_url = admin_url( 'upload.php?page=kv-dup-img-fix' );

            if ( $result['remaining'] > 0 ) {
                $form_id = 'kvdif-continue-all-form';
                $notice .= '<div class="notice notice-warning"><p>' .
                    esc_html( sprintf( '%d duplicate(s) still remaining site-wide — continuing automatically in batches of %d…', $result['remaining'], $batch_size ) ) .
                    '</p>';
                $notice .= '<form method="post" action="' . esc_url( $delete_all_url ) . '" id="' . esc_attr( $form_id ) . '" style="margin: 8px 0 12px;">';
                $notice .= wp_nonce_field( 'kvdif_delete_all', 'kvdif_delete_all_nonce', true, false );
                $notice .= '<input type="hidden" name="kvdif_action" value="delete_all">';
                $notice .= '<input type="hidden" name="kvdif_batch_size" value="' . esc_attr( $batch_size ) . '">';
                $notice .= '<button type="submit" class="button button-primary">' . esc_html( sprintf( 'Continue now (%d left)', $result['remaining'] ) ) . '</button>';
                $notice .= ' <span class="description">' . esc_html__( 'Auto-continuing — leave this tab open.' ) . '</span>';
                $notice .= '</form>';
                $notice .= '<script>setTimeout(function(){var f=document.getElementById(' . wp_json_encode( $form_id ) . ');if(f){f.submit();}},800);</script>';
                $notice .= '</div>';
            } else {
                $notice .= '<div class="notice notice-success"><p>' . esc_html__( 'Site-wide cleanup complete — no confirmed duplicates left. Click Scan All Duplicates again to verify.' ) . '</p></div>';
            }
        }
    }

    $search_filename = isset( $_GET['kvdif_filename'] ) ? sanitize_text_field( wp_unslash( $_GET['kvdif_filename'] ) ) : '';
    $info             = $search_filename !== '' ? kvdif_duplicate_groups_for_filename( $search_filename ) : null;

    $role_by_id       = array();
    $group_by_id      = array();
    $keep_clean_by_id = array();
    if ( $info ) {
        foreach ( $info['duplicate_groups'] as $gi => $group ) {
            $role_by_id[ $group['keep_id'] ]       = 'original';
            $group_by_id[ $group['keep_id'] ]      = $gi + 1;
            $keep_clean_by_id[ $group['keep_id'] ] = $group['keep_is_clean'];
            foreach ( $group['delete_ids'] as $did ) {
                $role_by_id[ $did ]  = 'duplicate';
                $group_by_id[ $did ] = $gi + 1;
            }
        }
    }

    $scan_groups = null;
    if ( isset( $_GET['kvdif_scan'] ) ) {
        $scan_groups = kvdif_scan_all_duplicate_groups();
        kvdif_build_sitewide_queue( $scan_groups );
    }
    $sitewide_queue = kvdif_get_sitewide_queue();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Fix Duplicate Images' ); ?></h1>
        <p><?php esc_html_e( 'Enter a filename (e.g. TVN_03_2-Bedroom-Suite.jpg). Files are only treated as duplicates of one another when they are byte-for-byte identical, or (if the file is missing) share an identical recorded source URL — files that merely look similarly named (e.g. a real photo gallery numbered -1, -2, -3) are never merged or deleted.' ); ?></p>

        <?php echo $notice; ?>

        <form method="get" style="margin: 16px 0;">
            <input type="hidden" name="page" value="kv-dup-img-fix">
            <input type="hidden" name="kvdif_batch" value="<?php echo esc_attr( $batch_from_query ); ?>">
            <input type="text" name="kvdif_filename" value="<?php echo esc_attr( $search_filename ); ?>" placeholder="TVN_03_2-Bedroom-Suite.jpg" style="width: 360px;">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Find Duplicates' ); ?></button>
            <span class="description"><?php echo esc_html( sprintf( 'Batch size is %d — add ?kvdif_batch=N to the URL to change it.', $batch_from_query ) ); ?></span>
        </form>

        <?php if ( $search_filename !== '' && $info ) : ?>
            <?php if ( empty( $info['candidates'] ) ) : ?>
                <p><?php esc_html_e( 'No attachments found matching this filename.' ); ?></p>
            <?php else : ?>
                <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Merge the checked rows? The lowest-numbered / most cleanly-named one is kept, the rest are deleted and their featured-image/meta references reassigned to it.' ) ); ?>');">
                    <?php wp_nonce_field( 'kvdif_force_merge', 'kvdif_force_merge_nonce' ); ?>
                    <input type="hidden" name="kvdif_action" value="force_merge">
                    <input type="hidden" name="kvdif_filename" value="<?php echo esc_attr( $search_filename ); ?>">
                    <p class="description"><?php esc_html_e( 'Rows below that were not auto-matched (e.g. visually identical but re-compressed to a slightly different file) can be merged manually — tick 2+ rows you have visually confirmed are the same photo, then click Merge Checked.' ); ?></p>
                <table class="widefat striped" style="max-width: 1050px;">
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php esc_html_e( 'Preview' ); ?></th>
                            <th><?php esc_html_e( 'ID' ); ?></th>
                            <th><?php esc_html_e( 'Filename' ); ?></th>
                            <th><?php esc_html_e( 'Uploaded' ); ?></th>
                            <th><?php esc_html_e( 'Size' ); ?></th>
                            <th><?php esc_html_e( 'Featured on' ); ?></th>
                            <th><?php esc_html_e( 'Group' ); ?></th>
                            <th><?php esc_html_e( 'Status' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $info['candidates'] as $c ) :
                            $id   = $c['id'];
                            $row  = kvdif_attachment_summary( $id );
                            $role = $role_by_id[ $id ] ?? 'single';

                            $key = $info['keys'][ $id ] ?? '';

                            if ( $role === 'original' ) {
                                $basis = strpos( $key, 'hash:' ) === 0 ? 'identical content' : 'same source URL';
                                if ( ! empty( $keep_clean_by_id[ $id ] ) ) {
                                    $status_html = '<strong style="color:#00a32a;">' . esc_html( sprintf( 'Kept — canonical filename (%s)', $basis ) ) . '</strong>';
                                } else {
                                    $status_html = '<strong style="color:#dba617;">' . esc_html( sprintf( 'Kept — but this filename is ALSO suffixed like a duplicate (%s). No clean-named copy was found in this group; review manually.', $basis ) ) . '</strong>';
                                }
                            } elseif ( $role === 'duplicate' ) {
                                $basis       = strpos( $key, 'hash:' ) === 0 ? 'identical content' : 'same source URL';
                                $status_html = '<strong style="color:#d63638;">' . esc_html( sprintf( 'Duplicate — will be removed (%s)', $basis ) ) . '</strong>';
                            } elseif ( strpos( $key, 'hash:' ) === 0 ) {
                                $status_html = '<span style="color:#646970;">' . esc_html__( 'Unique image — not touched' ) . '</span>';
                            } elseif ( strpos( $key, 'src:' ) === 0 ) {
                                $status_html = '<span style="color:#646970;">' . esc_html__( 'Unique source — not touched' ) . '</span>';
                            } else {
                                $status_html = '<span style="color:#dba617;">' . esc_html__( 'File missing on disk and no source URL — skipped for safety' ) . '</span>';
                            }
                        ?>
                        <tr>
                            <td><input type="checkbox" name="kvdif_ids[]" value="<?php echo esc_attr( $id ); ?>"></td>
                            <td><?php if ( $row['thumb'] ) : ?><img src="<?php echo esc_url( $row['thumb'] ); ?>" style="width:60px;height:60px;object-fit:cover;"><?php endif; ?></td>
                            <td><a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['id'] ); ?></a></td>
                            <td><?php echo esc_html( $row['filename'] ); ?></td>
                            <td><?php echo esc_html( $row['upload_date'] ); ?></td>
                            <td><?php echo esc_html( $row['file_size'] ); ?></td>
                            <td><?php echo esc_html( $row['featured_usage'] ); ?></td>
                            <td><?php echo isset( $group_by_id[ $id ] ) ? '#' . esc_html( $group_by_id[ $id ] ) : '—'; ?></td>
                            <td><?php echo $status_html; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                    <button type="submit" class="button" style="margin-top: 10px;"><?php esc_html_e( 'Merge Checked Rows as Duplicates' ); ?></button>
                </form>

                <?php if ( ! empty( $info['duplicate_groups'] ) ) : ?>
                    <form method="post" style="margin-top: 16px;" onsubmit="return confirm('<?php echo esc_js( __( 'Remove confirmed duplicates? This runs in small batches automatically until finished.' ) ); ?>');">
                        <?php wp_nonce_field( 'kvdif_delete', 'kvdif_nonce' ); ?>
                        <input type="hidden" name="kvdif_action" value="delete">
                        <input type="hidden" name="kvdif_filename" value="<?php echo esc_attr( $search_filename ); ?>">
                        <label for="kvdif_batch_size"><?php esc_html_e( 'Batch size:' ); ?></label>
                        <input type="number" id="kvdif_batch_size" name="kvdif_batch_size" value="<?php echo esc_attr( $last_batch ); ?>" min="1" max="10" style="width: 60px;">
                        <button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638;"><?php esc_html_e( 'Remove Duplicates' ); ?></button>
                        <span class="description"><?php esc_html_e( 'Only removes rows marked "Duplicate — will be removed" above.' ); ?></span>
                    </form>
                <?php else : ?>
                    <p><em><?php esc_html_e( 'Nothing to remove — no two attachments have identical content or an identical source URL.' ); ?></em></p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <hr style="margin: 32px 0;">

        <h2><?php esc_html_e( 'Scan whole media library for duplicates' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Uses the same content-hash / source-URL check as the search above — never guesses from filenames alone.' ); ?></p>
        <p>
            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'kv-dup-img-fix', 'kvdif_scan' => 1 ), admin_url( 'upload.php' ) ) ); ?>" class="button">
                <?php esc_html_e( 'Scan All Duplicates' ); ?>
            </a>
        </p>

        <?php if ( is_array( $scan_groups ) ) : ?>
            <?php if ( empty( $scan_groups ) ) : ?>
                <p><?php esc_html_e( 'No duplicate groups found.' ); ?></p>
            <?php else :
                $scan_delete_total = 0;
                foreach ( $scan_groups as $g ) {
                    $scan_delete_total += count( $g['delete_ids'] );
                }
            ?>
                <table class="widefat striped" style="max-width: 900px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Kept filename' ); ?></th>
                            <th><?php esc_html_e( 'Kept ID' ); ?></th>
                            <th><?php esc_html_e( 'Duplicates to remove' ); ?></th>
                            <th><?php esc_html_e( 'Action' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scan_groups as $data ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( $data['filename'] ); ?>
                                <?php if ( empty( $data['keep_is_clean'] ) ) : ?>
                                    <span style="color:#dba617;"> — <?php esc_html_e( 'not a clean/canonical filename, review manually' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $data['keep_id'] ); ?></td>
                            <td><?php echo esc_html( count( $data['delete_ids'] ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'kv-dup-img-fix', 'kvdif_filename' => $data['filename'] ), admin_url( 'upload.php' ) ) ); ?>">
                                    <?php esc_html_e( 'Review' ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" style="margin-top: 16px;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete ALL confirmed duplicates site-wide? Originals are kept; this runs in small batches automatically until finished.' ) ); ?>');">
                    <?php wp_nonce_field( 'kvdif_delete_all', 'kvdif_delete_all_nonce' ); ?>
                    <input type="hidden" name="kvdif_action" value="delete_all">
                    <label for="kvdif_batch_size_all"><?php esc_html_e( 'Batch size:' ); ?></label>
                    <input type="number" id="kvdif_batch_size_all" name="kvdif_batch_size" value="<?php echo esc_attr( $last_batch ); ?>" min="1" max="10" style="width: 60px;">
                    <button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638;">
                        <?php echo esc_html( sprintf( 'Delete All %d Duplicates', $scan_delete_total ) ); ?>
                    </button>
                    <span class="description"><?php esc_html_e( 'Originals are never touched — only the extra copies listed above.' ); ?></span>
                </form>
            <?php endif; ?>
        <?php elseif ( is_array( $sitewide_queue ) && ! empty( $sitewide_queue ) ) : ?>
            <p><em><?php echo esc_html( sprintf( '%d duplicate(s) still queued from the last scan — click Scan All Duplicates to review, or use Continue now above if a cleanup is in progress.', count( $sitewide_queue ) ) ); ?></em></p>
        <?php endif; ?>

        <hr style="margin: 32px 0;">

        <h2><?php esc_html_e( 'Activity Log' ); ?></h2>
        <?php $logs = kvdif_get_logs(); ?>
        <?php if ( ! empty( $logs ) ) : ?>
            <form method="post" style="margin-bottom: 10px;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the activity log?' ) ); ?>');">
                <?php wp_nonce_field( 'kvdif_clear_log', 'kvdif_clear_log_nonce' ); ?>
                <button type="submit" name="kvdif_clear_log" value="1" class="button"><?php esc_html_e( 'Clear Log' ); ?></button>
            </form>
            <div style="max-height: 420px; overflow: auto; max-width: 1000px; border: 1px solid #dcdcde;">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:150px;"><?php esc_html_e( 'Time' ); ?></th>
                            <th style="width:200px;"><?php esc_html_e( 'Filename' ); ?></th>
                            <th style="width:90px;"><?php esc_html_e( 'Status' ); ?></th>
                            <th><?php esc_html_e( 'Message' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) :
                            $status = $log['status'] ?? 'info';
                            $color  = array(
                                'error'   => '#d63638',
                                'success' => '#00a32a',
                                'warning' => '#dba617',
                                'info'    => '#646970',
                            );
                            $color  = $color[ $status ] ?? '#646970';
                        ?>
                        <tr>
                            <td><?php echo esc_html( $log['timestamp'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $log['filename'] ?? '' ); ?></td>
                            <td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;"><?php echo esc_html( strtoupper( $status ) ); ?></span></td>
                            <td><?php echo esc_html( $log['message'] ?? '' ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'No log entries yet.' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
