<?php
// Quick debug page to check paths and CSS loading
require_once __DIR__ . '/config/paths.php';
?>
<!DOCTYPE html>
<html>

<head>
  <title>Path Debug</title>
</head>

<body>
  <h1>Path Configuration Debug</h1>
  <pre>
BASE_PATH: <?php echo BASE_PATH; ?>
SCRIPT_NAME: <?php echo $_SERVER['SCRIPT_NAME']; ?>
REQUEST_URI: <?php echo $_SERVER['REQUEST_URI']; ?>
DOCUMENT_ROOT: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>

CSS File Path: <?php echo assetUrl('assets/css/style.css'); ?>
    </pre>

  <h2>CSS Test</h2>
  <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
  <p style="font-family: Arial;">This text should have styling if CSS loaded</p>
  <button class="btn-primary">Test Button (should be styled)</button>

  <h2>Check if files exist:</h2>
  <ul>
    <li>/domain-portal/style.css: <?php echo file_exists(__DIR__ . '/style.css') ? '✅ EXISTS' : '❌ NOT FOUND'; ?></li>
    <li>/domain-portal/assets/css/style.css:
      <?php echo file_exists(__DIR__ . '/assets/css/style.css') ? '✅ EXISTS' : '❌ NOT FOUND'; ?></li>
    <li>/domain-portal/api/mock_domains.php:
      <?php echo file_exists(__DIR__ . '/api/mock_domains.php') ? '✅ EXISTS' : '❌ NOT FOUND'; ?></li>
  </ul>
</body>

</html>