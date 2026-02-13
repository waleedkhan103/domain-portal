<?php
require_once __DIR__ . '/config/paths.php';
?>
<!DOCTYPE html>
<html>

<head>
  <title>Path Debug</title>
  <style>
    body {
      font-family: monospace;
      padding: 20px;
      background: #f5f5f5;
    }

    .info {
      background: white;
      padding: 20px;
      border-radius: 5px;
      margin: 10px 0;
    }

    .key {
      color: blue;
      font-weight: bold;
    }

    .value {
      color: green;
    }
  </style>
</head>

<body>
  <h1>Path Configuration Debug</h1>

  <div class="info">
    <p><span class="key">BASE_PATH:</span> <span class="value"><?php echo BASE_PATH; ?></span></p>
    <p><span class="key">assetUrl('assets/css/style.css'):</span> <span
        class="value"><?php echo assetUrl('assets/css/style.css'); ?></span></p>
    <p><span class="key">pageUrl('domain_search.php'):</span> <span
        class="value"><?php echo pageUrl('domain_search.php'); ?></span></p>
  </div>

  <div class="info">
    <p><span class="key">__FILE__:</span> <span class="value"><?php echo __FILE__; ?></span></p>
    <p><span class="key">DOCUMENT_ROOT:</span> <span class="value"><?php echo $_SERVER['DOCUMENT_ROOT']; ?></span></p>
    <p><span class="key">SCRIPT_NAME:</span> <span class="value"><?php echo $_SERVER['SCRIPT_NAME']; ?></span></p>
    <p><span class="key">REQUEST_URI:</span> <span class="value"><?php echo $_SERVER['REQUEST_URI']; ?></span></p>
  </div>

  <div class="info">
    <h3>File Existence Check:</h3>
    <p><span class="key">/misc/waleed/domain-portal/assets/css/style.css exists:</span>
      <span class="value"><?php echo file_exists(__DIR__ . '/assets/css/style.css') ? '✓ YES' : '✗ NO'; ?></span>
    </p>
    <p><span class="key">assetUrl() location:</span>
      <span
        class="value"><?php echo file_exists(str_replace('config/paths.php', assetUrl('assets/css/style.css'), __FILE__)) ? '✓ EXISTS' : '✗ NOT FOUND'; ?></span>
    </p>
  </div>

  <div class="info">
    <h2>Test CSS Loading:</h2>
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <button class="btn-primary">Test Button (should be blue)</button>
    <div class="hero">Hero section (should have purple gradient)</div>
  </div>
</body>

</html>