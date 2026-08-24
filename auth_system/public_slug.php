<?php
/*
 * public_slug.php
 * FR-9: Public Profile URL
 *
 * Every user gets a unique, unguessable public_slug used to build their
 * publicly shareable profile URL (public_profile.php?slug=...). Existing
 * accounts created before this feature don't have one yet, so this helper
 * generates one lazily the first time it's needed and saves it.
 */

function sp_ensure_public_slug($pdo, $user_id, $existing_slug) {
    if (!empty($existing_slug)) {
        return $existing_slug;
    }

    // Retry a few times in the astronomically unlikely event of a
    // collision (UNIQUE constraint on public_slug).
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $slug = bin2hex(random_bytes(8)); // 16 hex characters

        try {
            $stmt = $pdo->prepare("UPDATE users SET public_slug = ? WHERE user_id = ?");
            $stmt->execute([$slug, $user_id]);
            return $slug;
        } catch (PDOException $e) {
            // Duplicate slug (extremely rare) - loop and try a new one.
            continue;
        }
    }

    return null;
}