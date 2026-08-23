<?php
/*
 * csrf.php
 * NFR-1: CSRF protection helper, shared across all POST-handling pages.
 *
 * Usage:
 *   require_once __DIR__ . "/csrf.php";   (after session_start())
 *   $csrf_token = sp_csrf_token();        (call before rendering the form)
 *   Add a hidden input field named "csrf_token" with that value to the form.
 *
 *   On POST, call sp_csrf_verify($_POST["csrf_token"] ?? "") before doing
 *   anything with the submitted data. If it returns false, show an error
 *   instead of processing the form (session expired / token mismatch).
 */

/**
 * Returns the current CSRF token for this session, generating one on
 * first use. Safe to call multiple times per request/page.
 */
function sp_csrf_token() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/**
 * Verifies a submitted token against the session token using a
 * timing-safe comparison. Returns false if no session token exists yet
 * (e.g. session expired) or the submitted token doesn't match.
 */
function sp_csrf_verify($submitted_token) {
    if (empty($_SESSION["csrf_token"]) || empty($submitted_token)) {
        return false;
    }

    return hash_equals($_SESSION["csrf_token"], $submitted_token);
}