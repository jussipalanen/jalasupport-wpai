<?php

/**
 * Constants defined at runtime (plugin bootstrap and Bedrock config) that
 * PHPStan cannot discover statically.
 */
define('JALASUPPORT_VERSION', '0.0.0');
define('JALASUPPORT_FILE', __FILE__);
define('JALASUPPORT_PATH', __DIR__ . '/');
define('WP_ENV', 'development');
