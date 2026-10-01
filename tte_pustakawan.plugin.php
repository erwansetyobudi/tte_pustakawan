<?php
/**
 * Plugin Name: Tanda Tangan Elektronik Pustakawan
 * Description: Pengesahan dokumen PDF internal dengan QR verifikasi, identitas akun SLiMS, hash SHA-256 dan validasi OPAC.
 * Version: 1.0.10
 * Author: Erwan Setyo Budi
 */
use SLiMS\Plugins;
$plugin = Plugins::getInstance();
$plugin->registerMenu('system', 'Tanda Tangan Elektronik', __DIR__ . '/admin/index.inc.php');
$plugin->registerMenu('opac', 'tte-validation', __DIR__ . '/opac/validation.inc.php');
