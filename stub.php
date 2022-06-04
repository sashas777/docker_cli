#!/usr/bin/env php
<?php
if (class_exists('Phar')) {
    Phar::mapPhar('default.phar');
    require 'phar://' . __FILE__ . '/bin/dcm';
}

__HALT_COMPILER(); ?>