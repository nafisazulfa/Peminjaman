<?php
require_once __DIR__ . '/config/fungsi.php';
wajib_login();
pindah('/' . user()['role'] . '/dashboard.php');
