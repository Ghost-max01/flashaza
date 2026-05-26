<?php
// helper.php

/**
 * Generate a random transaction reference
 * Example: 8W9X1A2B3C4D
 *
 * @param int $length
 * @return string
 */
function generateReference($length = 12) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $ref = '';
    for ($i = 0; $i < $length; $i++) {
        $ref .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $ref;
}

/**
 * Calculate schedule expiry timestamp
 * (Used for auto-unsetting scheduled transactions)
 *
 * @param int $minutes
 * @return string DateTime in MySQL format
 */
function scheduleExpiry($minutes = 5) {
    $date = new DateTime("now", new DateTimeZone("Africa/Lagos"));
    $date->modify("+{$minutes} minutes");
    return $date->format("Y-m-d H:i:s");
}