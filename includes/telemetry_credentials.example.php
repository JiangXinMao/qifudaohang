<?php
declare(strict_types=1);

/*
 * Copy this file to telemetry_credentials.php and fill in the private values
 * issued by the query service. Never commit the generated App Secret.
 */
if (!defined('DATACABIN_ENDPOINT')) define('DATACABIN_ENDPOINT', 'https://stats.example.com/index.php?r=api/v1/events');
if (!defined('DATACABIN_APP_KEY')) define('DATACABIN_APP_KEY', 'YOUR_24_HEX_APP_KEY');
if (!defined('DATACABIN_APP_SECRET')) define('DATACABIN_APP_SECRET', 'YOUR_64_HEX_APP_SECRET');
if (!defined('DATACABIN_CREDENTIAL_ID')) define('DATACABIN_CREDENTIAL_ID', 'hmac-YOUR_CREDENTIAL_ID');
if (!defined('DATACABIN_REMOTE_PUBLIC_KEY')) define('DATACABIN_REMOTE_PUBLIC_KEY', 'YOUR_BASE64_ED25519_PUBLIC_KEY');
if (!defined('DATACABIN_REMOTE_PUBLIC_KEYS_JSON')) define('DATACABIN_REMOTE_PUBLIC_KEYS_JSON', '{}');
if (!defined('DATACABIN_REMOTE_ROOT_PUBLIC_KEYS_JSON')) define('DATACABIN_REMOTE_ROOT_PUBLIC_KEYS_JSON', '{}');
